<?php

namespace App\Livewire;

use App\Models\Legacy\Config;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Impostazioni')]
class Impostazioni extends Component
{
    private const CAMPI = [
        'app', 'ragione_sociale', 'societa_iva', 'societa_indirizzo', 'societa_comune', 'societa_cap', 'societa_provincia',
        'societa_email', 'societa_telefono', 'email', 'phone', 'colore', 'colore_primario',
    ];

    /** @var array<string, string> */
    public array $f = [];

    public function mount(): void
    {
        $this->autorizza();
        $config = Config::firstOrNew();

        foreach (self::CAMPI as $campo) {
            $this->f[$campo] = (string) $config->{$campo};
        }
    }

    public function salva(): void
    {
        $this->autorizza();
        $this->validate([
            'f.app' => 'nullable|string|max:50',
            'f.ragione_sociale' => 'nullable|string|max:255',
            'f.societa_iva' => 'nullable|string|max:12',
            'f.societa_indirizzo' => 'nullable|string|max:255',
            'f.societa_comune' => 'nullable|string|max:255',
            'f.societa_cap' => 'nullable|string|max:255',
            'f.societa_provincia' => 'nullable|string|max:255',
            'f.societa_email' => 'nullable|email|max:255',
            'f.societa_telefono' => 'nullable|string|max:255',
            'f.email' => 'nullable|email|max:30',
            'f.phone' => 'nullable|string|max:30',
            'f.colore' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'f.colore_primario' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $config = Config::firstOrNew();
        $config->fill(array_map(fn ($v) => (string) $v, $this->f));
        $config->save();

        session()->flash('impostazioni', 'Impostazioni salvate.');
    }

    private function autorizza(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->isStaff(), 403);
    }

    public function render()
    {
        return view('livewire.impostazioni');
    }
}
