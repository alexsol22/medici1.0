<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAccesso;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Documenti')]
class Documenti extends Component
{
    use ConAccesso, WithFileUploads, WithPagination;

    public string $filtroTipo = '';

    public bool $modal = false;

    public array $f = [];

    public $file;

    public function nuovo(): void
    {
        $this->soloStaff();
        $this->f = ['titolo' => '', 'tipo' => 'cliente', 'cliente_id' => '', 'user_id' => '', 'scadenza' => '', 'formazione' => false];
        $this->file = null;
        $this->resetValidation();
        $this->modal = true;
    }

    public function salva(): void
    {
        $this->soloStaff();
        $this->validate([
            'f.titolo' => 'required|string|max:255',
            'f.tipo' => 'required|in:cliente,operatore',
            'f.cliente_id' => 'nullable|required_if:f.tipo,cliente|exists:clienti,id',
            'f.user_id' => 'nullable|required_if:f.tipo,operatore|exists:users,id',
            'f.scadenza' => 'nullable|date',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        if ($this->f['tipo'] === 'cliente') {
            $this->clientiVisibili()->findOrFail($this->f['cliente_id']);
        }

        Documento::create([
            'titolo' => $this->f['titolo'],
            'tipo' => $this->f['tipo'],
            'cliente_id' => $this->f['tipo'] === 'cliente' ? $this->f['cliente_id'] : null,
            'user_id' => $this->f['tipo'] === 'operatore' ? $this->f['user_id'] : null,
            'scadenza' => $this->f['scadenza'] ?: null,
            'formazione' => (bool) $this->f['formazione'],
            'file' => $this->file->store('documenti', 'local'),
        ]);

        $this->modal = false;
    }

    public function elimina(int $id): void
    {
        $this->soloStaff();
        $d = $this->query()->findOrFail($id);
        Storage::disk('local')->delete($d->file);
        $d->delete();
    }

    private function query()
    {
        $u = auth()->user();

        return Documento::query()->when(true, fn ($q) => match (true) {
            $u->isOperatore() => $q->where('user_id', $u->id),
            $u->isCliente() => $q->where('cliente_id', $u->cliente_id),
            default => $q->where(fn ($w) => $w->whereIn('cliente_id', $this->clientiVisibili()->select('clienti.id'))->orWhere('tipo', 'operatore')),
        });
    }

    public function render()
    {
        return view('livewire.documenti', [
            'documenti' => $this->query()->with(['cliente', 'user'])
                ->when($this->filtroTipo, fn ($q) => $q->where('tipo', $this->filtroTipo))
                ->orderByRaw('scadenza is null')->orderBy('scadenza')->paginate(12),
            'clienti' => $this->clientiVisibili()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'operatori' => User::where('ruolo', 'operatore')->orderBy('cognome')->get(),
        ]);
    }
}
