<?php

namespace App\Livewire;

use App\Models\Avviso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Bacheca')]
class Bacheca extends Component
{
    public bool $modal = false;

    public array $f = [];

    public array $destinatari = [];

    public function mount(): void
    {
        abort_if(auth()->user()->isCliente(), 403);
    }

    public function nuovo(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
        $this->f = ['titolo' => '', 'testo' => '', 'per_tutti' => true];
        $this->destinatari = [];
        $this->resetValidation();
        $this->modal = true;
    }

    public function salva(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
        $this->validate([
            'f.titolo' => 'required|string|max:255',
            'f.testo' => 'required|string|max:5000',
            'destinatari' => 'array',
            'destinatari.*' => 'exists:users,id',
        ]);
        if (! $this->f['per_tutti'] && ! $this->destinatari) {
            $this->addError('destinatari', 'Seleziona almeno un destinatario.');

            return;
        }

        DB::transaction(function () {
            $avviso = Avviso::create([
                'titolo' => $this->f['titolo'], 'testo' => $this->f['testo'],
                'per_tutti' => (bool) $this->f['per_tutti'], 'autore_id' => auth()->id(),
            ]);
            $ids = $this->f['per_tutti'] ? User::where('ruolo', 'operatore')->where('attivo', true)->pluck('id') : collect($this->destinatari);
            $avviso->destinatari()->attach($ids->mapWithKeys(fn ($id) => [$id => ['letto_at' => null]])->all());
        });

        $this->modal = false;
    }

    public function segnaLetto(int $id): void
    {
        DB::table('avviso_user')->where('avviso_id', $id)->where('user_id', auth()->id())->whereNull('letto_at')->update(['letto_at' => now()]);
    }

    public function elimina(int $id): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
        Avviso::findOrFail($id)->delete();
    }

    public function render()
    {
        $u = auth()->user();
        $avvisi = $u->isOperatore()
            ? Avviso::join('avviso_user', 'avvisi.id', '=', 'avviso_user.avviso_id')->where('avviso_user.user_id', $u->id)
                ->select('avvisi.*', 'avviso_user.letto_at')->latest('avvisi.created_at')->get()
            : Avviso::withCount(['destinatari', 'destinatari as letti_count' => fn ($q) => $q->whereNotNull('avviso_user.letto_at')])->latest()->get();

        return view('livewire.bacheca', [
            'avvisi' => $avvisi,
            'operatori' => $u->isStaff() ? User::where('ruolo', 'operatore')->where('attivo', true)->orderBy('cognome')->get() : collect(),
        ]);
    }
}
