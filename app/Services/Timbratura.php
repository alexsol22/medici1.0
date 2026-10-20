<?php

namespace App\Services;

use App\Enums\StatoTurno;
use App\Models\CodiceQr;
use App\Models\Postazione;
use App\Models\Presenza;
use App\Models\Sede;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Timbratura
{
    public const MARGINE_MINUTI = 15;

    /** Turno dell'operatore attivo adesso (finestra: 15 minuti prima dell'inizio, 15 minuti dopo la fine). */
    public function turnoAttivo(User $operatore, ?int $sedeId = null): ?Turno
    {
        $now = now();

        return Turno::with(['sede', 'cliente'])
            ->where('operatore_id', $operatore->id)
            ->where('stato', '!=', StatoTurno::Completato->value)
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->where('inizio', '<=', $now->copy()->addMinutes(self::MARGINE_MINUTI))
            ->where('fine', '>=', $now->copy()->subMinutes(self::MARGINE_MINUTI))
            ->orderBy('inizio')
            ->first();
    }

    /** @return array{ok: bool, message: string, tipo?: string, ritardo?: int} */
    public function scansiona(User $operatore, string $codice, ?float $lat, ?float $lng): array
    {
        $qr = CodiceQr::where('codice', trim($codice))->first();

        if (! $qr || $qr->stato !== CodiceQr::ATTIVO) {
            return $this->ko('Codice QR non valido o non attivo.');
        }

        $postazione = $qr->tipo === CodiceQr::POSTAZIONE ? Postazione::with('sede')->find($qr->postazione_id) : null;
        $sede = $postazione?->sede ?? Sede::find($qr->sede_id);

        if (! $sede) {
            return $this->ko('Il codice non è associato a nessuna sede.');
        }

        $turno = $this->turnoAttivo($operatore, $sede->id);
        if (! $turno) {
            return $this->ko('Nessun turno attivo per te in questa sede.');
        }

        $distanza = null;
        if ($lat !== null && $lng !== null && $sede->lat !== null && $sede->lng !== null) {
            $distanza = Calcoli::distanzaKm($lat, $lng, (float) $sede->lat, (float) $sede->lng);
        }

        if ($qr->tipo === CodiceQr::SEDE && $distanza !== null && $distanza * 1000 > $sede->distanza_max) {
            return $this->ko('Sei troppo lontano dalla sede ('.round($distanza * 1000).' m, massimo '.$sede->distanza_max.' m).');
        }

        return DB::transaction(function () use ($turno, $operatore, $qr, $postazione, $lat, $lng, $distanza) {
            $now = now();
            $base = [
                'turno_id' => $turno->id,
                'operatore_id' => $operatore->id,
                'cliente_id' => $turno->cliente_id,
                'sede_id' => $turno->sede_id,
                'codice_qr_id' => $qr->id,
                'rilevata_at' => $now,
                'lat' => $lat,
                'lng' => $lng,
                'distanza_km' => $distanza,
            ];

            if ($qr->tipo === CodiceQr::POSTAZIONE) {
                return $this->passaggio($turno, $postazione, $base, $now);
            }

            if ($turno->stato === StatoTurno::Programmato) {
                $ritardo = Calcoli::ritardoMinuti($turno->inizio, $now);
                Presenza::create($base + ['tipo' => Presenza::INIZIO, 'ritardo_minuti' => $ritardo]);
                $turno->update(['stato' => StatoTurno::Iniziato]);

                return ['ok' => true, 'tipo' => Presenza::INIZIO, 'ritardo' => $ritardo,
                    'message' => 'Turno iniziato. '.$this->descriviRitardo($ritardo, 'inizio')];
            }

            $inizio = $turno->presenze()->where('tipo', Presenza::INIZIO)->orderBy('rilevata_at')->first();
            $ritardo = Calcoli::ritardoMinuti($turno->fine, $now);
            Presenza::create($base + ['tipo' => Presenza::FINE, 'ritardo_minuti' => $ritardo]);
            $turno->update([
                'stato' => StatoTurno::Completato,
                'ore_lavorate' => Calcoli::ore($inizio?->rilevata_at ?? $turno->inizio, $now),
            ]);

            return ['ok' => true, 'tipo' => Presenza::FINE, 'ritardo' => $ritardo,
                'message' => 'Turno completato. '.$this->descriviRitardo($ritardo, 'fine')];
        });
    }

    private function passaggio(Turno $turno, ?Postazione $postazione, array $base, $now): array
    {
        if ($turno->stato !== StatoTurno::Iniziato) {
            return $this->ko('Devi prima timbrare l\'inizio turno scansionando il codice della sede.');
        }

        $ritardo = null;
        if ($postazione?->minuti_passaggio) {
            $inizio = $turno->presenze()->where('tipo', Presenza::INIZIO)->orderBy('rilevata_at')->first();
            $n = $turno->presenze()->where('tipo', Presenza::PASSAGGIO)->where('postazione_id', $postazione->id)->count() + 1;
            $previsto = ($inizio?->rilevata_at ?? $turno->inizio)->copy()->addMinutes($n * $postazione->minuti_passaggio);
            $ritardo = Calcoli::ritardoMinuti($previsto, $now);
        }

        Presenza::create($base + ['tipo' => Presenza::PASSAGGIO, 'postazione_id' => $postazione?->id, 'ritardo_minuti' => $ritardo]);

        return ['ok' => true, 'tipo' => Presenza::PASSAGGIO, 'ritardo' => $ritardo,
            'message' => 'Passaggio registrato'.($postazione ? ' — '.$postazione->titolo : '').'.'.($ritardo !== null ? ' '.$this->descriviRitardo($ritardo, 'passaggio') : '')];
    }

    /** L'operatore che scansiona il codice sede si assegna il turno programmato di oggi (sostituzione) e timbra. */
    public function sostituisci(User $operatore, string $codice, ?float $lat, ?float $lng): array
    {
        $qr = CodiceQr::where('codice', trim($codice))->where('stato', CodiceQr::ATTIVO)->where('tipo', CodiceQr::SEDE)->first();
        if (! $qr) {
            return $this->ko('Codice sede non valido.');
        }

        $turno = Turno::where('sede_id', $qr->sede_id)
            ->where('stato', StatoTurno::Programmato->value)
            ->whereDate('inizio', today())
            ->where('fine', '>=', now()->subMinutes(self::MARGINE_MINUTI))
            ->orderBy('inizio')
            ->first();

        if (! $turno) {
            return $this->ko('Nessun turno da sostituire oggi per questa sede.');
        }

        return DB::transaction(function () use ($turno, $operatore, $codice, $lat, $lng) {
            $precedente = $turno->operatore_id;
            $turno->update(['operatore_id' => $operatore->id]);
            $esito = $this->scansiona($operatore, $codice, $lat, $lng);

            if (! $esito['ok']) {
                $turno->update(['operatore_id' => $precedente]);
            }

            return $esito;
        });
    }

    private function descriviRitardo(int $minuti, string $evento): string
    {
        if ($minuti === 0) {
            return 'Puntuale.';
        }
        $abs = abs($minuti);
        $ritardoOk = $evento === 'fine' ? 'Uscita posticipata' : 'Sei in ritardo';
        $anticipoOk = $evento === 'fine' ? 'Uscita anticipata' : 'Sei in anticipo';

        return $minuti > 0 ? "$ritardoOk di $abs minuti." : "$anticipoOk di $abs minuti.";
    }

    private function ko(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
