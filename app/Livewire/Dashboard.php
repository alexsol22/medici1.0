<?php

namespace App\Livewire;

use App\Enums\Ruolo;
use App\Livewire\Concerns\ConAccesso;
use App\Models\Sede;
use App\Models\Turno;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use ConAccesso;

    public function render()
    {
        $this->soloStaff();

        $clientiVisibili = $this->clientiVisibili();
        $idClientiVisibili = (clone $clientiVisibili)->select('clienti.id');
        $turniVisibili = Turno::query()->whereIn('cliente_id', clone $idClientiVisibili);
        $oggi = today();

        $metriche = [
            ['nome' => 'Clienti', 'valore' => $clientiVisibili->count(), 'destinazione' => 'clienti'],
            ['nome' => 'Sedi', 'valore' => Sede::query()->whereIn('cliente_id', clone $idClientiVisibili)->count(), 'destinazione' => 'sedi'],
            ['nome' => 'Operatori attivi', 'valore' => User::query()->where('ruolo', Ruolo::Operatore->value)->where('attivo', true)->count(), 'destinazione' => 'operatori'],
            ['nome' => 'Turni oggi', 'valore' => (clone $turniVisibili)->where('inizio', '<', $oggi->copy()->addDay())->where('fine', '>=', $oggi)->count(), 'destinazione' => 'turni'],
        ];

        $prossimiTurni = (clone $turniVisibili)
            ->with(['operatore', 'cliente', 'sede'])
            ->where('inizio', '>=', now())
            ->orderBy('inizio')
            ->limit(5)
            ->get();

        return view('livewire.dashboard', compact('metriche', 'prossimiTurni'));
    }
}
