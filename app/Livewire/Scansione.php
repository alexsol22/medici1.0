<?php

namespace App\Livewire;

use App\Models\Legacy\Turno;
use App\Services\Timbratura;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Timbra')]
class Scansione extends Component
{
    public ?array $risultato = null;

    public int $esitoId = 0;

    public string $nota = '';

    public function timbra(string $codice, ?float $lat = null, ?float $lng = null, bool $sostituzione = false, ?string $nota = null): void
    {
        $this->nota = trim((string) $nota);
        $this->validate([
            'nota' => ['nullable', 'string', 'max:255'],
        ], [
            'nota.max' => 'La nota può contenere al massimo 255 caratteri.',
        ]);

        $t = app(Timbratura::class);
        $codice = mb_substr(trim($codice), 0, 64);
        $note = $this->nota !== '' ? $this->nota : null;
        $this->risultato = null;
        $user = auth()->user();

        $this->risultato = $sostituzione
            ? $t->sostituisci($user, $codice, $lat, $lng, $note)
            : $t->scansiona($user, $codice, $lat, $lng, $note);
        $this->esitoId++;

        $this->nota = '';
    }

    public function nascondiEsito(int $esitoId): void
    {
        if ($this->esitoId === $esitoId) {
            $this->risultato = null;
        }
    }

    public function render(Timbratura $t)
    {
        $user = auth()->user();

        $turno = $t->turnoAttivo($user);

        return view('livewire.scansione', [
            'turno' => $turno,
            'fotoSede' => $this->urlFotoSede($turno),
            'presenze' => $turno ? $turno->presenze()->with('postazione')->orderBy('data')->orderBy('orario')->get() : collect(),
            'prossimi' => Turno::with('sede')->where('id_operatore', $user->id)
                ->whereRaw("CONCAT(data_inizio,' ',ora_inizio) > ?", [now()->format('Y-m-d H:i')])
                ->orderBy('data_inizio')->orderBy('ora_inizio')->limit(5)->get(),
        ]);
    }

    /**
     * L'endpoint è unico, quindi senza un identificativo del file il browser
     * riusa la foto della sede precedente per tutta la sessione.
     */
    private function urlFotoSede(?Turno $turno): ?string
    {
        $percorso = $turno?->sede?->percorsoImmagineTimbratura();

        if ($percorso === null) {
            return null;
        }

        $disco = Storage::disk(config('filesystems.files_disk'));

        return route('scansione.foto', [
            'v' => $disco->lastModified($percorso).'-'.$disco->size($percorso).'-'.substr(sha1($percorso), 0, 12),
        ]);
    }
}
