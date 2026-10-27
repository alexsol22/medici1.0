<?php

namespace App\Livewire;

use App\Enums\StatoTurno;
use App\Livewire\Concerns\ConAccesso;
use App\Models\Presenza;
use App\Models\Turno;
use App\Models\User;
use App\Services\Calcoli;
use App\Services\PianificazioneTurni;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Turni')]
class Turni extends Component
{
    use ConAccesso, WithPagination;

    public string $da = '';

    public string $a = '';

    public ?int $filtroSede = null;

    public ?int $filtroOperatore = null;

    public string $vista = 'calendario';

    public string $periodoCalendario = 'mese';

    public string $mese = '';

    public string $selezioneMese = '';

    public string $selezioneData = '';

    public bool $modal = false;

    public ?int $turnoSelezionatoId = null;

    public array $f = [];

    public array $giorni = [];

    public ?string $esito = null;

    public function mount(): void
    {
        $this->mese = today()->toDateString();
        $this->allineaSelezioniCalendario();
        $this->da = now()->startOfWeek()->toDateString();
        $this->a = now()->endOfWeek()->toDateString();
    }

    public function updating($name): void
    {
        if (in_array($name, ['da', 'a', 'filtroSede', 'filtroOperatore', 'vista'])) {
            $this->resetPage();
        }
    }

    public function selezionaSuggerimento(string $tipo, int $id): void
    {
        abort_unless(in_array($tipo, ['sede', 'operatore'], true), 422);

        if ($tipo === 'sede') {
            $this->sediVisibili()->findOrFail($id);
            abort_unless($this->query()->where('sede_id', $id)->exists(), 404);
            $this->filtroSede = $id;

            return;
        }

        abort_unless($this->query()->where('operatore_id', $id)->exists(), 404);
        $this->filtroOperatore = $id;
    }

    public function rimuoviFiltro(string $tipo): void
    {
        abort_unless(in_array($tipo, ['sede', 'operatore'], true), 422);

        if ($tipo === 'sede') {
            $this->filtroSede = null;

            return;
        }

        $this->filtroOperatore = null;
    }

    public function oggi(): void
    {
        $this->mese = today()->toDateString();
        $this->allineaSelezioniCalendario();
    }

    public function cambiaPeriodoCalendario(string $periodo): void
    {
        abort_unless(in_array($periodo, ['mese', 'settimana', 'giorno'], true), 422);

        $this->periodoCalendario = $periodo;
        $this->allineaSelezioniCalendario();
    }

    public function updated(string $name): void
    {
        if ($name === 'selezioneMese') {
            $this->validateOnly('selezioneMese', ['selezioneMese' => 'required|date_format:Y-m']);
            $this->mese = Carbon::createFromFormat('!Y-m', $this->selezioneMese)->toDateString();
            $this->allineaSelezioniCalendario();

            return;
        }

        if ($name === 'selezioneData') {
            $this->validateOnly('selezioneData', ['selezioneData' => 'required|date_format:Y-m-d']);
            $this->mese = Carbon::parse($this->selezioneData)->toDateString();
            $this->allineaSelezioniCalendario();
        }
    }

    public function cambiaPeriodo(int $delta): void
    {
        $giorno = Carbon::parse($this->mese);

        $this->mese = match ($this->periodoCalendario) {
            'settimana' => $giorno->addWeeks($delta)->toDateString(),
            'giorno' => $giorno->addDays($delta)->toDateString(),
            default => $giorno->startOfMonth()->addMonths($delta)->toDateString(),
        };
        $this->allineaSelezioniCalendario();
    }

    public function vaiAlGiorno(string $data): void
    {
        $this->mese = Carbon::parse($data)->toDateString();
        $this->periodoCalendario = 'giorno';
        $this->allineaSelezioniCalendario();
    }

    public function mostraTurno(int $id): void
    {
        $this->turnoSelezionatoId = $this->query()->findOrFail($id)->id;
    }

    public function chiudiDettaglioTurno(): void
    {
        $this->turnoSelezionatoId = null;
    }

    public function nuovoGiorno(string $data): void
    {
        $this->nuovo();
        $this->f['data_inizio'] = $this->f['data_fine'] = Carbon::parse($data)->toDateString();
    }

    public function nuovo(): void
    {
        $this->soloStaff();
        $this->f = [
            'cliente_id' => '', 'sede_id' => '', 'operatore_id' => '',
            'data_inizio' => today()->toDateString(), 'ora_inizio' => '08:00',
            'data_fine' => today()->toDateString(), 'ora_fine' => '16:00',
            'ripeti_fino' => '', 'note' => '',
        ];
        $this->giorni = [];
        $this->esito = null;
        $this->resetValidation();
        $this->modal = true;
    }

    public function updatedFClienteId(): void
    {
        $this->f['sede_id'] = '';
        $this->f['operatore_id'] = '';
    }

    private function intervallo(): ?array
    {
        try {
            $i = Carbon::parse(($this->f['data_inizio'] ?? '').' '.($this->f['ora_inizio'] ?? ''));
            $f = Carbon::parse(($this->f['data_fine'] ?? '').' '.($this->f['ora_fine'] ?? ''));
        } catch (\Throwable) {
            return null;
        }

        return [$i, $f];
    }

    public function salva(PianificazioneTurni $piano): void
    {
        $this->soloStaff();
        $this->validate([
            'f.cliente_id' => 'required|exists:clienti,id',
            'f.sede_id' => 'required|exists:sedi,id',
            'f.operatore_id' => 'required|exists:users,id',
            'f.data_inizio' => 'required|date',
            'f.data_fine' => 'required|date',
            'f.ora_inizio' => 'required|date_format:H:i',
            'f.ora_fine' => 'required|date_format:H:i',
            'f.ripeti_fino' => 'nullable|date|after:f.data_inizio',
        ]);

        $sede = $this->sediVisibili()->where('cliente_id', $this->f['cliente_id'])->findOrFail($this->f['sede_id']);
        [$inizio, $fine] = $this->intervallo();
        if ($fine->lte($inizio)) {
            $this->addError('f.ora_fine', 'La fine deve essere successiva all\'inizio.');

            return;
        }

        $ripetiFino = $this->f['ripeti_fino'] ? Carbon::parse($this->f['ripeti_fino']) : null;
        $creati = 0;
        $saltati = [];

        foreach ($piano->occorrenze($inizio, $fine, $ripetiFino, array_map('intval', $this->giorni)) as $o) {
            $libero = $piano->operatoriDisponibili($o['inizio'], $o['fine'], $sede->cliente_id)->contains('id', (int) $this->f['operatore_id']);
            if (! $libero) {
                $saltati[] = $o['inizio']->format('d/m');

                continue;
            }
            Turno::create([
                'operatore_id' => $this->f['operatore_id'], 'cliente_id' => $sede->cliente_id, 'sede_id' => $sede->id,
                'inizio' => $o['inizio'], 'fine' => $o['fine'], 'ore' => Calcoli::ore($o['inizio'], $o['fine']),
                'note' => $this->f['note'] ?: null,
            ]);
            $creati++;
        }

        if ($saltati) {
            $this->esito = "Creati $creati turni. Operatore non disponibile il: ".implode(', ', $saltati).'.';
            if (! $creati) {
                return;
            }
        }
        $this->modal = $saltati ? true : false;
    }

    public function elimina(int $id): void
    {
        $this->soloStaff();
        $this->query()->findOrFail($id)->delete();
    }

    /** Apertura/chiusura manuale del turno da parte dell'amministrazione, con registro presenze. */
    public function apriChiudi(int $id): void
    {
        $this->soloStaff();
        $t = $this->query()->findOrFail($id);
        $now = now();
        $base = ['turno_id' => $t->id, 'operatore_id' => $t->operatore_id, 'cliente_id' => $t->cliente_id, 'sede_id' => $t->sede_id,
            'rilevata_at' => $now, 'note' => 'Registrazione manuale ('.auth()->user()->nome_completo.')'];

        if ($t->stato === StatoTurno::Programmato) {
            Presenza::create($base + ['tipo' => Presenza::INIZIO, 'ritardo_minuti' => Calcoli::ritardoMinuti($t->inizio, $now)]);
            $t->update(['stato' => StatoTurno::Iniziato]);
        } elseif ($t->stato === StatoTurno::Iniziato) {
            $inizio = $t->presenze()->where('tipo', Presenza::INIZIO)->first();
            Presenza::create($base + ['tipo' => Presenza::FINE, 'ritardo_minuti' => Calcoli::ritardoMinuti($t->fine, $now)]);
            $t->update(['stato' => StatoTurno::Completato, 'ore_lavorate' => Calcoli::ore($inizio?->rilevata_at ?? $t->inizio, $now)]);
        }
    }

    private function allineaSelezioniCalendario(): void
    {
        $riferimento = Carbon::parse($this->mese);
        $this->selezioneMese = $riferimento->format('Y-m');
        $this->selezioneData = $riferimento->toDateString();
    }

    private function query(): Builder
    {
        $u = auth()->user();

        return Turno::query()
            ->when($u->isOperatore(), fn ($q) => $q->where('operatore_id', $u->id))
            ->when(! $u->isOperatore(), fn ($q) => $q->whereIn('cliente_id', $this->clientiVisibili()->select('clienti.id')));
    }

    private function applicaRicerca(Builder $query): Builder
    {
        return $query
            ->when($this->filtroSede, fn (Builder $query) => $query->where('sede_id', $this->filtroSede))
            ->when($this->filtroOperatore, fn (Builder $query) => $query->where('operatore_id', $this->filtroOperatore));
    }

    private function calendario(): array
    {
        $riferimento = Carbon::parse($this->mese);
        $primo = $this->periodoCalendario === 'mese' ? $riferimento->copy()->startOfMonth() : $riferimento->copy();
        [$da, $a] = match ($this->periodoCalendario) {
            'settimana' => [$primo->copy()->startOfWeek(), $primo->copy()->endOfWeek()],
            'giorno' => [$primo->copy()->startOfDay(), $primo->copy()->endOfDay()],
            default => [$primo->copy()->startOfMonth()->startOfWeek(), $primo->copy()->endOfMonth()->endOfWeek()],
        };
        $perGiorno = $this->applicaRicerca($this->query()->with(['operatore', 'sede']))
            ->whereBetween('inizio', [$da->copy()->startOfDay(), $a->copy()->endOfDay()])
            ->orderBy('inizio')->get()
            ->groupBy(fn ($t) => $t->inizio->toDateString());

        return ['primo' => $primo, 'da' => $da, 'a' => $a, 'turni' => $perGiorno];
    }

    public function render(PianificazioneTurni $piano)
    {
        $turni = null;
        if ($this->vista === 'lista') {
            $turni = $this->applicaRicerca($this->query()->with(['operatore', 'sede.cliente']))
                ->when($this->da, fn ($q) => $q->where('inizio', '>=', Carbon::parse($this->da)->startOfDay()))
                ->when($this->a, fn ($q) => $q->where('inizio', '<=', Carbon::parse($this->a)->endOfDay()))
                ->orderBy('inizio')->paginate(15);
        }
        $calendario = $this->vista === 'lista' ? null : $this->calendario();

        $turnoDettaglio = $this->turnoSelezionatoId
            ? $this->query()->with(['operatore', 'sede.cliente'])->findOrFail($this->turnoSelezionatoId)
            : null;

        $disponibili = collect();
        $sediForm = collect();
        if ($this->modal) {
            $sediForm = $this->sediVisibili()->where('cliente_id', $this->f['cliente_id'] ?: 0)->orderBy('descrizione')->get(['id', 'descrizione']);
            $intervallo = $this->intervallo();
            if ($intervallo !== null && $intervallo[1]->gt($intervallo[0])) {
                $disponibili = $piano->operatoriDisponibili($intervallo[0], $intervallo[1], $this->f['cliente_id'] ?: null);
            }
        }

        $suggerimentiSedi = $this->sediVisibili()
            ->whereIn('id', $this->query()->select('sede_id')->distinct())
            ->orderBy('descrizione')
            ->get(['id', 'descrizione', 'citta'])
            ->map(fn ($sede) => [
                'id' => $sede->id,
                'label' => $sede->descrizione,
                'detail' => $sede->citta,
                'search' => $sede->descrizione.' '.$sede->citta,
            ])
            ->values();

        $suggerimentiOperatori = User::query()
            ->whereIn('id', $this->query()->select('operatore_id')->distinct())
            ->orderBy('cognome')
            ->get(['id', 'name', 'cognome', 'email'])
            ->map(fn ($operatore) => [
                'id' => $operatore->id,
                'label' => $operatore->nome_completo,
                'detail' => $operatore->email,
                'search' => $operatore->nome_completo.' '.$operatore->email,
            ])
            ->values();

        $sedeSelezionata = $this->filtroSede ? $this->sediVisibili()->find($this->filtroSede) : null;
        $operatoreSelezionato = $this->filtroOperatore
            ? $this->query()->where('operatore_id', $this->filtroOperatore)->with('operatore')->first()?->operatore
            : null;

        return view('livewire.turni', [
            'turni' => $turni,
            'calendario' => $calendario,
            'turnoDettaglio' => $turnoDettaglio,
            'suggerimentiSedi' => $suggerimentiSedi,
            'suggerimentiOperatori' => $suggerimentiOperatori,
            'sedeSelezionata' => $sedeSelezionata,
            'operatoreSelezionato' => $operatoreSelezionato,
            'clienti' => $this->clientiVisibili()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'sediForm' => $sediForm,
            'disponibili' => $disponibili,
            'giorniSettimana' => [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'],
        ]);
    }
}
