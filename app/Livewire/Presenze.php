<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAccesso;
use App\Models\Presenza;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Presenze (GDA)')]
class Presenze extends Component
{
    use ConAccesso, WithPagination;

    public string $da = '';

    public string $a = '';

    public ?int $filtroSede = null;

    public ?int $filtroOperatore = null;

    public function mount(): void
    {
        $this->da = now()->startOfMonth()->toDateString();
        $this->a = now()->toDateString();
    }

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $clienti = $this->clientiVisibili()->select('clienti.id');
        $filtra = fn ($q, $campo) => $q
            ->whereIn('cliente_id', $clienti)
            ->when($this->da, fn ($q) => $q->where($campo, '>=', Carbon::parse($this->da)->startOfDay()))
            ->when($this->a, fn ($q) => $q->where($campo, '<=', Carbon::parse($this->a)->endOfDay()))
            ->when($this->filtroSede, fn ($q) => $q->where('sede_id', $this->filtroSede))
            ->when($this->filtroOperatore, fn ($q) => $q->where('operatore_id', $this->filtroOperatore));

        $turni = $filtra(Turno::query(), 'inizio');
        $previste = (float) (clone $turni)->sum('ore');
        $lavorate = (float) (clone $turni)->sum('ore_lavorate');

        $perOperatore = $filtra(Turno::query(), 'inizio')->with('operatore')->get()->groupBy('operatore_id')->map(fn ($g) => [
            'nome' => $g->first()->operatore->nome_completo, 'previste' => $g->sum('ore'), 'lavorate' => $g->sum('ore_lavorate'),
        ])->sortBy('nome');

        return view('livewire.presenze', [
            'previste' => $previste, 'lavorate' => $lavorate, 'perOperatore' => $perOperatore,
            'righe' => $filtra(Presenza::query(), 'rilevata_at')->with(['operatore', 'sede', 'postazione'])->latest('rilevata_at')->paginate(15),
            'sedi' => $this->sediVisibili()->orderBy('descrizione')->get(['id', 'descrizione']),
            'operatori' => User::where('ruolo', 'operatore')->orderBy('cognome')->get(),
        ]);
    }
}
