<?php

namespace App\Services;

use App\Enums\Ruolo;
use App\Models\Sede;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PianificazioneTurni
{
    /**
     * Genera le coppie inizio/fine del turno, ripetendolo nei giorni ISO indicati fino alla data $ripetiFino.
     *
     * @param  array<int,int>  $giorniIso  1 = lunedì ... 7 = domenica
     * @return Collection<int, array{inizio: Carbon, fine: Carbon}>
     */
    public function occorrenze(Carbon $inizio, Carbon $fine, ?Carbon $ripetiFino = null, array $giorniIso = []): Collection
    {
        $out = collect([['inizio' => $inizio, 'fine' => $fine]]);

        if (! $ripetiFino || ! $giorniIso) {
            return $out;
        }

        $durata = $inizio->diffInSeconds($fine);
        for ($d = $inizio->copy()->addDay()->startOfDay(); $d->lte($ripetiFino->copy()->endOfDay()); $d->addDay()) {
            if (in_array($d->isoWeekday(), $giorniIso, true)) {
                $i = $d->copy()->setTimeFrom($inizio);
                $out->push(['inizio' => $i, 'fine' => $i->copy()->addSeconds($durata)]);
            }
        }

        return $out;
    }

    /** Operatori attivi senza turni sovrapposti, esclusi quelli "esclusi" dal cliente; i preferiti per primi. */
    public function operatoriDisponibili(Carbon $inizio, Carbon $fine, ?int $clienteId = null, ?int $ignoraTurnoId = null): Collection
    {
        $occupati = Turno::query()
            ->where('inizio', '<', $fine)
            ->where('fine', '>', $inizio)
            ->when($ignoraTurnoId, fn ($q) => $q->whereKeyNot($ignoraTurnoId))
            ->pluck('operatore_id');

        $relazioni = $clienteId
            ? \DB::table('cliente_operatore')->where('cliente_id', $clienteId)->get()->groupBy('tipo')->map->pluck('user_id')
            : collect();
        $esclusi = $relazioni->get('escluso', collect());
        $preferiti = $relazioni->get('preferito', collect());

        return User::where('ruolo', Ruolo::Operatore->value)
            ->where('attivo', true)
            ->whereNotIn('id', $occupati)
            ->whereNotIn('id', $esclusi)
            ->orderBy('cognome')->orderBy('name')
            ->get()
            ->each(fn ($u) => $u->preferito = $preferiti->contains($u->id))
            ->sortByDesc('preferito')
            ->values();
    }

    /** Copertura settimanale: ore richieste vs programmate per ogni giorno della settimana di $lunedi. */
    public function copertura(Sede $sede, Carbon $lunedi): array
    {
        $turni = Turno::where('sede_id', $sede->id)
            ->whereBetween('inizio', [$lunedi->copy()->startOfDay(), $lunedi->copy()->addDays(6)->endOfDay()])
            ->get()
            ->groupBy(fn ($t) => $t->inizio->toDateString());

        return collect(range(0, 6))->map(function ($i) use ($sede, $lunedi, $turni) {
            $giorno = $lunedi->copy()->addDays($i);
            $richieste = $sede->oreRichieste($giorno->isoWeekday());
            $eseguite = (float) $turni->get($giorno->toDateString(), collect())->sum('ore');
            $stato = match (true) {
                $richieste == 0 || $richieste == $eseguite => 'ok',
                $eseguite > $richieste => 'extra',
                default => 'scoperto',
            };

            return ['giorno' => $giorno, 'richieste' => $richieste, 'eseguite' => $eseguite, 'stato' => $stato];
        })->all();
    }
}
