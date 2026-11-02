<?php

namespace App\Services;

use App\Models\Legacy\Admin;
use App\Models\Legacy\OperatoreEscludi;
use App\Models\Legacy\OperatorePreferito;
use App\Models\Legacy\Sede;
use App\Models\Legacy\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PianificazioneTurni
{
    private const GIORNI = [1 => 'lun', 2 => 'mar', 3 => 'mer', 4 => 'gio', 5 => 'ven', 6 => 'sab', 7 => 'dom'];

    /**
     * Come MyUtils::getDateTurni: la prima occorrenza è il turno stesso, poi i giorni ISO scelti fino a $ripetiFino.
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

        $giorniDurata = $inizio->copy()->startOfDay()->diffInDays($fine->copy()->startOfDay());
        $d = $inizio->copy()->startOfDay()->addDay();

        while ($d->lte($ripetiFino->copy()->startOfDay())) {
            if (in_array($d->isoWeekday(), $giorniIso, true)) {
                $i = $d->copy()->setTimeFrom($inizio);
                $out->push(['inizio' => $i, 'fine' => $d->copy()->addDays($giorniDurata)->setTimeFrom($fine)]);
            }
            $d->addDay();
        }

        return $out;
    }

    /**
     * Come Turni::getSelectOperatori: esclude chi ha turni sovrapposti e gli operatori esclusi dal cliente;
     * i preferiti vengono per primi; ordinati per costo orario e codice.
     */
    public function operatoriDisponibili(Carbon $inizio, Carbon $fine, ?int $clienteId = null, ?int $ignoraTurnoId = null): Collection
    {
        $i = $inizio->format('Y-m-d H:i');
        $f = $fine->format('Y-m-d H:i');
        $da = "CONCAT(data_inizio,' ',ora_inizio)";
        $a = "CONCAT(data_fine,' ',ora_fine)";

        $occupati = Turno::query()
            ->whereRaw("(($da < ? AND $a > ?) OR ($da < ? AND $a > ?) OR ($da > ? AND $a < ?) OR ($da < ? AND $a > ?))", [$i, $i, $f, $f, $i, $f, $i, $f])
            ->when($ignoraTurnoId, fn ($q) => $q->whereKeyNot($ignoraTurnoId))
            ->pluck('id_operatore');

        $preferiti = $clienteId ? OperatorePreferito::where('id_cliente', $clienteId)->pluck('id_operatore') : collect();
        $esclusi = $clienteId ? OperatoreEscludi::where('id_cliente', $clienteId)->pluck('id_operatore') : collect();

        return Admin::where('user_type', 4)
            ->where('attivo', 'Y')
            ->whereNotIn('id', $occupati)
            ->whereNotIn('id', $esclusi)
            ->orderBy('costo_orario')->orderBy('codice')
            ->get()
            ->each(fn ($u) => $u->preferito = $preferiti->contains($u->id))
            ->sortByDesc('preferito')
            ->values();
    }

    /** Come Turni::getCoperture: ore richieste (colonne lun..dom della sede) contro ore dei turni del giorno. */
    public function copertura(Sede $sede, Carbon $lunedi): array
    {
        $turni = Turno::where('id_sede', $sede->id)
            ->whereBetween('data_inizio', [$lunedi->toDateString(), $lunedi->copy()->addDays(6)->toDateString()])
            ->get()
            ->groupBy(fn ($t) => $t->data_inizio->toDateString());

        return collect(range(0, 6))->map(function ($i) use ($sede, $lunedi, $turni) {
            $giorno = $lunedi->copy()->addDays($i);
            $richieste = (float) $sede->{self::GIORNI[$giorno->isoWeekday()]};
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
