<?php

namespace App\Livewire;

use App\Models\Turno;
use App\Services\Timbratura;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Timbra')]
class Scansione extends Component
{
    public ?array $risultato = null;

    public function timbra(string $codice, ?float $lat = null, ?float $lng = null, bool $sostituzione = false): void
    {
        $t = app(Timbratura::class);
        $codice = mb_substr(trim($codice), 0, 64);
        $this->risultato = null;
        $user = auth()->user();

        $this->risultato = $sostituzione
            ? $t->sostituisci($user, $codice, $lat, $lng)
            : $t->scansiona($user, $codice, $lat, $lng);
    }

    public function render(Timbratura $t)
    {
        $user = auth()->user();

        $turno = $t->turnoAttivo($user);

        return view('livewire.scansione', [
            'turno' => $turno,
            'presenze' => $turno ? $turno->presenze()->with('postazione')->orderBy('rilevata_at')->get() : collect(),
            'prossimi' => Turno::with('sede')->where('operatore_id', $user->id)->where('inizio', '>', now())->orderBy('inizio')->limit(5)->get(),
        ]);
    }
}
