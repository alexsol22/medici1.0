<?php

namespace App\Livewire;

use App\Models\Legacy\Gda;
use App\Models\Legacy\Turno;
use App\Services\Timbratura;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
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
        if ($note !== null && ($this->risultato['ok'] ?? false)) {
            $this->risultato['nota'] = $note;
        }
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
        $titolo = match (true) {
            $turno === null => 'Timbra',
            $turno->stato === 'I' => 'Timbra uscita',
            default => 'Timbra entrata',
        };

        return view('livewire.scansione', [
            'turno' => $turno,
            'fotoSede' => $this->urlFotoSede($turno),
            'titolo' => $titolo,
            'progresso' => $this->progressoTurno($turno),
        ])->title($titolo);
    }

    /**
     * @return array{ingresso: ?string, fine: ?string, passaggi: Collection<int, array{titolo: string, ora: ?string}>}|null
     */
    private function progressoTurno(?Turno $turno): ?array
    {
        if ($turno === null) {
            return null;
        }

        $turno->load(['presenze' => fn ($query) => $query->with('postazione')->orderBy('data')->orderBy('orario')]);
        $presenze = $turno->presenze;

        return [
            'ingresso' => $presenze->firstWhere('tipo', Gda::INIZIO)?->rilevata_at?->format('H:i'),
            'fine' => $presenze->firstWhere('tipo', Gda::FINE)?->rilevata_at?->format('H:i'),
            'passaggi' => $presenze->where('tipo', Gda::PASSAGGIO)->map(fn (Gda $presenza): array => [
                'titolo' => $presenza->postazione?->titolo ?? 'Passaggio',
                'ora' => $presenza->rilevata_at?->format('H:i'),
            ])->values(),
        ];
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
