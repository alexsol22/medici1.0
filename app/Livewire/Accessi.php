<?php

namespace App\Livewire;

use App\Models\Accesso as AccessoUtente;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Log accessi')]
class Accessi extends Component
{
    use WithPagination;

    private function soloStaff(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->attivo && $user->isStaff(), 403);
    }

    public function render(): View
    {
        $this->soloStaff();

        $accessi = AccessoUtente::query()
            ->orderByDesc('accesso_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.accessi', compact('accessi'));
    }
}
