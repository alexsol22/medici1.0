<?php

namespace App\Livewire;

use App\Enums\Ruolo;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Operatori e utenti')]
class Operatori extends Component
{
    use WithPagination;

    public string $cerca = '';

    public string $filtroRuolo = '';

    public bool $modal = false;

    public ?int $userId = null;

    public array $f = [];

    public function updatingCerca(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'f.name' => 'required|string|max:255',
            'f.cognome' => 'required|string|max:255',
            'f.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->userId)],
            'f.cellulare' => 'nullable|string|max:20',
            'f.ruolo' => ['required', Rule::in(array_column(Ruolo::cases(), 'value'))],
            'f.cliente_id' => 'nullable|required_if:f.ruolo,cliente|exists:clienti,id',
            'f.codice' => 'nullable|required_if:f.ruolo,operatore|string|max:255',
            'f.costo_orario' => 'nullable|numeric|min:0',
            'f.password' => [$this->userId ? 'nullable' : 'required', 'string', 'min:8'],
        ];
    }

    public function nuovo(): void
    {
        $this->userId = null;
        $this->f = ['name' => '', 'cognome' => '', 'email' => '', 'cellulare' => '', 'ruolo' => 'operatore', 'cliente_id' => '', 'codice' => '', 'costo_orario' => '', 'password' => '', 'attivo' => true];
        $this->resetValidation();
        $this->modal = true;
    }

    public function modifica(int $id): void
    {
        $u = $this->query()->findOrFail($id);
        $this->userId = $u->id;
        $this->f = [
            'name' => $u->name, 'cognome' => $u->cognome ?? '', 'email' => $u->email, 'cellulare' => $u->cellulare ?? '',
            'ruolo' => $u->ruolo->value, 'cliente_id' => $u->cliente_id ?? '', 'codice' => $u->codice ?? '',
            'costo_orario' => $u->costo_orario ?? '', 'password' => '', 'attivo' => $u->attivo,
        ];
        $this->resetValidation();
        $this->modal = true;
    }

    public function salva(): void
    {
        $this->validate();
        $this->autorizzaRuolo($this->f['ruolo']);

        $dati = collect($this->f)->except('password')->map(fn ($v) => $v === '' ? null : $v)->all();
        $dati['attivo'] = (bool) ($this->f['attivo'] ?? true);
        if ($this->f['ruolo'] !== 'cliente') {
            $dati['cliente_id'] = null;
        }

        if ($this->userId) {
            $u = $this->query()->findOrFail($this->userId);
            $u->update($dati + ($this->f['password'] ? ['password' => $this->f['password']] : []));
        } else {
            User::create($dati + ['password' => $this->f['password'] ?: Hash::make(Str::random(16))]);
        }

        $this->modal = false;
    }

    public function attivaDisattiva(int $id): void
    {
        $u = $this->query()->findOrFail($id);
        abort_if($u->id === auth()->id(), 403);
        $u->update(['attivo' => ! $u->attivo]);
    }

    /** Solo il super admin può creare/modificare amministratori. */
    private function autorizzaRuolo(string $ruolo): void
    {
        abort_if(in_array($ruolo, ['admin', 'super_admin']) && auth()->user()->ruolo !== Ruolo::SuperAdmin, 403);
    }

    private function query()
    {
        return User::query()->when(auth()->user()->ruolo !== Ruolo::SuperAdmin, fn ($q) => $q->whereNotIn('ruolo', ['admin', 'super_admin']));
    }

    public function render()
    {
        $utenti = $this->query()->with('cliente')
            ->when($this->filtroRuolo, fn ($q) => $q->where('ruolo', $this->filtroRuolo))
            ->when($this->cerca, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->cerca}%")->orWhere('cognome', 'like', "%{$this->cerca}%")->orWhere('email', 'like', "%{$this->cerca}%")))
            ->orderBy('cognome')->orderBy('name')->paginate(10);

        return view('livewire.operatori', [
            'utenti' => $utenti,
            'clienti' => Cliente::orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'ruoli' => Ruolo::cases(),
        ]);
    }
}
