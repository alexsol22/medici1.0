<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::guest')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function accedi()
    {
        $this->validate();
        $chiave = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($chiave, 5)) {
            $this->addError('email', 'Troppi tentativi. Riprova tra '.RateLimiter::availableIn($chiave).' secondi.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'attivo' => true], $this->remember)) {
            RateLimiter::hit($chiave);
            $this->addError('email', 'Credenziali non valide.');

            return;
        }

        RateLimiter::clear($chiave);
        session()->regenerate();

        return $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.login');
    }
}
