<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAccesso;
use App\Models\QrAziendale;
use App\Services\QrAziendale as Generatore;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
#[Title('Generatore QR')]
class GeneratoreQr extends Component
{
    use ConAccesso, WithFileUploads;

    public ?int $qrId = null;

    public string $contenuto = '';

    public string $titolo = 'Security Scanner';

    public string $colore = '#0369a1';

    public $logo;

    public ?string $logoEsistente = null;

    public ?int $clienteId = null;

    public ?int $sedeId = null;

    public ?string $messaggio = null;

    /** Quando è incorporato nella pagina Sedi il QR è legato a una sede e non si può cambiare. */
    public bool $bloccato = false;

    public function mount(?int $sede = null): void
    {
        abort_unless(auth()->user()->isStaff(), 403);

        if ($sede) {
            $s = $this->sediVisibili()->with('codiceQr')->findOrFail($sede);
            $this->bloccato = true;
            $this->clienteId = $s->cliente_id;
            $this->sedeId = $s->id;
            $this->contenuto = $s->codiceQr?->codice ?? '';
            $this->titolo = mb_substr($s->descrizione, 0, 40);

            if ($q = $this->salvati()->where('sede_id', $s->id)->latest()->first()) {
                $this->apri($q->id);
            }
        }
    }

    public function updatedClienteId(): void
    {
        abort_if($this->bloccato, 403);
        $this->sedeId = null;
        $this->contenuto = '';
    }

    public function updatedSedeId(): void
    {
        abort_if($this->bloccato, 403);
        $sede = $this->sedeSelezionata();
        $this->contenuto = $sede?->codiceQr?->codice ?? '';
        if ($sede) {
            $this->titolo = $sede->descrizione;
        }
    }

    public function rimuoviLogo(): void
    {
        $this->logo = null;
        $this->logoEsistente = null;
    }

    public function nuovo(): void
    {
        if ($this->bloccato) {
            return;
        }
        $this->reset(['qrId', 'logo', 'logoEsistente', 'clienteId', 'sedeId', 'messaggio']);
        $this->contenuto = '';
        $this->titolo = 'Security Scanner';
        $this->colore = '#0369a1';
    }

    public function apri(int $id): void
    {
        $q = $this->salvati()->findOrFail($id);
        $this->qrId = $q->id;
        $this->contenuto = $q->contenuto;
        $this->titolo = (string) $q->titolo;
        $this->colore = $q->colore;
        $this->logoEsistente = $q->logo_path;
        $this->logo = null;
        $this->clienteId = $q->cliente_id;
        $this->sedeId = $q->sede_id;
        $this->messaggio = null;
    }

    public function salva(): void
    {
        $this->soloStaff();
        $this->validaForm();
        $this->risolviContenuto();

        $cliente = $this->clienteId ? $this->clientiVisibili()->findOrFail($this->clienteId) : null;
        $sede = $this->sedeId ? $this->sediVisibili()->where('cliente_id', $cliente?->id)->findOrFail($this->sedeId) : null;

        $esistente = $this->qrId ? $this->salvati()->findOrFail($this->qrId) : null;
        $logoPath = $this->logoEsistente;
        if ($this->logo) {
            $logoPath = $this->logo->store('qr-loghi', 'local');
        }
        if ($esistente && $esistente->logo_path && $esistente->logo_path !== $logoPath) {
            Storage::disk('local')->delete($esistente->logo_path);
        }

        $dati = [
            'titolo' => $this->titolo ?: null, 'contenuto' => $this->contenuto, 'colore' => $this->colore,
            'logo_path' => $logoPath, 'cliente_id' => $cliente?->id, 'sede_id' => $sede?->id,
            'user_id' => auth()->id(),
        ];

        $qr = $esistente ? tap($esistente)->update($dati) : QrAziendale::create($dati);
        $this->qrId = $qr->id;
        $this->logo = null;
        $this->logoEsistente = $logoPath;
        $this->messaggio = 'QR salvato.';
    }

    public function elimina(int $id): void
    {
        $this->soloStaff();
        $q = $this->salvati()->findOrFail($id);
        if ($q->logo_path) {
            Storage::disk('local')->delete($q->logo_path);
        }
        $q->delete();
        if ($this->qrId === $id) {
            $this->nuovo();
        }
    }

    /** Senza sede il QR contiene il titolo come testo semplice. */
    private function risolviContenuto(): void
    {
        $this->contenuto = $this->sedeSelezionata()?->codiceQr?->codice ?? trim($this->titolo);
        $this->validate(['contenuto' => 'required|string|max:1000'], ['contenuto.required' => 'Scegli una sede oppure inserisci un titolo.']);
    }

    private function validaForm(): void
    {
        $this->validate([
            'contenuto' => 'nullable|string|max:1000',
            'titolo' => 'nullable|string|max:40',
            'colore' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'clienteId' => 'nullable|integer',
            'sedeId' => 'nullable|integer',
        ]);
    }

    private function salvati()
    {
        return QrAziendale::query()->where(fn ($q) => $q
            ->whereNull('cliente_id')
            ->orWhereIn('cliente_id', $this->clientiVisibili()->select('clienti.id')));
    }

    private function sedeSelezionata()
    {
        return $this->sedeId ? $this->sediVisibili()->find($this->sedeId) : null;
    }

    public function render(Generatore $gen)
    {
        $this->validaForm();

        $logoUri = null;
        if ($this->logo) {
            $logoUri = 'data:'.$this->logo->getMimeType().';base64,'.base64_encode($this->logo->get());
        } elseif ($this->logoEsistente && Storage::disk('local')->exists($this->logoEsistente)) {
            $logoUri = 'data:'.Storage::disk('local')->mimeType($this->logoEsistente).';base64,'.base64_encode(Storage::disk('local')->get($this->logoEsistente));
        }

        $salvati = $this->bloccato ? collect() : $this->salvati()->with(['cliente', 'sede'])->latest()->get();

        return view('livewire.generatore-qr', [
            'svg' => $gen->svg($this->contenuto !== '' ? $this->contenuto : ($this->titolo ?: 'Security Scanner'), $this->titolo, $logoUri, $this->colore),
            'clienti' => $this->clientiVisibili()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'sedi' => $this->clienteId ? $this->sediVisibili()->where('cliente_id', $this->clienteId)->orderBy('descrizione')->get(['id', 'descrizione']) : collect(),
            'salvati' => $salvati->map(fn ($q) => ['m' => $q, 'svg' => $gen->svg($q->contenuto, (string) $q->titolo, $q->logoDataUri(), $q->colore)]),
        ]);
    }
}
