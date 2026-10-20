<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
#[Title('Il mio profilo')]
class Profilo extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $cognome = '';

    public string $email = '';

    public string $cellulare = '';

    public $foto = null;

    public string $password_attuale = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = $this->utente();
        $this->name = $user->name;
        $this->cognome = $user->cognome ?? '';
        $this->email = $user->email;
        $this->cellulare = $user->cellulare ?? '';
    }

    public function salvaProfilo(): void
    {
        $user = $this->utente();
        $dati = $this->validate([
            'name' => 'required|string|max:255',
            'cognome' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'cellulare' => 'nullable|string|max:20',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $aggiornamenti = [
            'name' => $dati['name'],
            'cognome' => $dati['cognome'],
            'email' => $dati['email'],
            'cellulare' => $dati['cellulare'] ?: null,
        ];

        if ($this->foto) {
            $this->eliminaFotoEsistente($user);
            $aggiornamenti['foto_path'] = $this->foto->store('avatar', 'local');
            $this->foto = null;
        }

        $user->update($aggiornamenti);
        session()->flash('profilo', 'Profilo aggiornato.');
    }

    public function rimuoviFoto(): void
    {
        $user = $this->utente();
        $this->eliminaFotoEsistente($user);
        $user->update(['foto_path' => null]);
        $this->foto = null;
        session()->flash('profilo', 'Foto rimossa.');
    }

    public function cambiaPassword(): void
    {
        $this->validate([
            'password_attuale' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed|different:password_attuale',
        ], [], ['password_attuale' => 'password attuale', 'password' => 'nuova password']);

        $this->utente()->update(['password' => $this->password]);
        $this->reset('password_attuale', 'password', 'password_confirmation');
        session()->flash('password', 'Password aggiornata.');
    }

    private function utente(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function eliminaFotoEsistente(User $user): void
    {
        if ($user->foto_path) {
            Storage::disk('local')->delete($user->foto_path);
        }
    }

    public function render()
    {
        return view('livewire.profilo', ['utente' => $this->utente()]);
    }
}
