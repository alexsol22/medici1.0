<?php

namespace App\Livewire;

use App\Enums\Ruolo;
use App\Livewire\Concerns\ConAccesso;
use App\Models\Cliente;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Clienti')]
class Clienti extends Component
{
    use ConAccesso, WithPagination;

    public string $cerca = '';

    public bool $modal = false;

    public ?int $clienteId = null;

    public array $f = [];

    private const CAMPI = ['ragione_sociale', 'codice_fiscale', 'piva', 'indirizzo', 'cap', 'citta', 'provincia', 'referente_nome', 'referente_cognome', 'telefono', 'email', 'pec', 'lat', 'lng', 'inizio_contratto', 'fine_contratto'];

    protected function rules(): array
    {
        return [
            'f.ragione_sociale' => 'required|string|max:255',
            'f.codice_fiscale' => 'nullable|string|max:32',
            'f.piva' => 'nullable|digits:11',
            'f.cap' => 'nullable|string|max:10',
            'f.provincia' => 'nullable|string|size:2',
            'f.email' => 'nullable|email',
            'f.pec' => 'nullable|email',
            'f.lat' => 'nullable|numeric|between:-90,90',
            'f.lng' => 'nullable|numeric|between:-180,180',
            'f.fine_contratto' => 'nullable|date|after_or_equal:f.inizio_contratto',
        ];
    }

    public function updatingCerca(): void
    {
        $this->resetPage();
    }

    public function nuovo(): void
    {
        $this->reset('clienteId');
        $this->f = array_fill_keys(self::CAMPI, '');
        $this->resetValidation();
        $this->modal = true;
    }

    public function modifica(int $id): void
    {
        $c = $this->clientiVisibili()->findOrFail($id);
        $this->clienteId = $c->id;
        $this->f = collect(self::CAMPI)->mapWithKeys(fn ($k) => [$k => $c->$k instanceof \DateTimeInterface ? $c->$k->format('Y-m-d') : ($c->$k ?? '')])->all();
        $this->resetValidation();
        $this->modal = true;
    }

    public function salva(): void
    {
        $this->soloStaff();
        $this->validate();
        $dati = array_map(fn ($v) => $v === '' ? null : $v, $this->f);
        $dati['provincia'] = isset($dati['provincia']) ? strtoupper($dati['provincia']) : null;

        if ($this->clienteId) {
            $this->clientiVisibili()->findOrFail($this->clienteId)->update($dati);
        } else {
            Cliente::create($dati + ['is_test' => auth()->user()->ruolo === Ruolo::SuperAdmin]);
        }

        $this->modal = false;
    }

    public function elimina(int $id): void
    {
        $this->soloStaff();
        $this->clientiVisibili()->findOrFail($id)->delete();
    }

    public function render()
    {
        $clienti = $this->clientiVisibili()->withCount('sedi')
            ->when($this->cerca, fn ($q) => $q->where(fn ($w) => $w->where('ragione_sociale', 'like', "%{$this->cerca}%")->orWhere('citta', 'like', "%{$this->cerca}%")))
            ->orderBy('ragione_sociale')->paginate(10);

        return view('livewire.clienti', compact('clienti'));
    }
}
