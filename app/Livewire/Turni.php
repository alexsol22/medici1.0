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

    public string $mese = '';

    public bool $modal = false;

    public array $f = [];

    public array $giorni = [];

    public ?string $esito = null;

    public function mount(): void
    {
        $this->mese = now()->startOfMonth()->toDateString();
        $this->da = now()->startOfWeek()->toDateString();
        $this->a = now()->endOfWeek()->toDateString();
    }

    public function updating($name): void
    {
        if (in_array($name, ['da', 'a', 'filtroSede', 'filtroOperatore', 'vista'])) {
            $this->resetPage();
        }
    }

    public function cambiaMese(int $delta): void
    {
        $this->mese = Carbon::parse($this->mese)->startOfMonth()->addMonths($delta)->toDateString();
    }

    public function oggi(): void
    {
        $this->mese = now()->startOfMonth()->toDateString();
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

    private function query()
    {
        $u = auth()->user();

        return Turno::query()
            ->when($u->isOperatore(), fn ($q) => $q->where('operatore_id', $u->id))
            ->when(! $u->isOperatore(), fn ($q) => $q->whereIn('cliente_id', $this->clientiVisibili()->select('clienti.id')));
    }

    public function render(PianificazioneTurni $piano)
    {
        $filtri = fn ($q) => $q
            ->when($this->filtroSede, fn ($q) => $q->where('sede_id', $this->filtroSede))
            ->when($this->filtroOperatore, fn ($q) => $q->where('operatore_id', $this->filtroOperatore));

        $turni = null;
        $calendario = null;
        if ($this->vista === 'lista') {
            $turni = $filtri($this->query()->with(['operatore', 'sede.cliente']))
                ->when($this->da, fn ($q) => $q->where('inizio', '>=', Carbon::parse($this->da)->startOfDay()))
                ->when($this->a, fn ($q) => $q->where('inizio', '<=', Carbon::parse($this->a)->endOfDay()))
                ->orderBy('inizio')->paginate(15);
        } else {
            $primo = Carbon::parse($this->mese)->startOfMonth();
            $da = $primo->copy()->startOfWeek();
            $a = $primo->copy()->endOfMonth()->endOfWeek();
            $perGiorno = $filtri($this->query()->with(['operatore', 'sede']))
                ->whereBetween('inizio', [$da, $a])->orderBy('inizio')->get()
                ->groupBy(fn ($t) => $t->inizio->toDateString());
            $calendario = ['primo' => $primo, 'da' => $da, 'a' => $a, 'turni' => $perGiorno];
        }

        $disponibili = collect();
        $sediForm = collect();
        if ($this->modal) {
            $sediForm = $this->sediVisibili()->where('cliente_id', $this->f['cliente_id'] ?: 0)->orderBy('descrizione')->get(['id', 'descrizione']);
            if ($iv = $this->intervallo()) {
                if ($iv[1]->gt($iv[0])) {
                    $disponibili = $piano->operatoriDisponibili($iv[0], $iv[1], $this->f['cliente_id'] ?: null);
                }
            }
        }

        return view('livewire.turni', [
            'turni' => $turni,
            'calendario' => $calendario,
            'sedi' => $this->sediVisibili()->orderBy('descrizione')->get(['id', 'descrizione']),
            'operatori' => User::where('ruolo', 'operatore')->orderBy('cognome')->get(),
            'clienti' => $this->clientiVisibili()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'sediForm' => $sediForm,
            'disponibili' => $disponibili,
            'giorniSettimana' => [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'],
        ]);
    }
}
