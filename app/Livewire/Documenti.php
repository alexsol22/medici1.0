<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAccesso;
use App\Models\Legacy\Admin;
use App\Models\Legacy\Certificato;
use App\Models\Legacy\Documento;
use App\Models\Legacy\DocumentoView;
use Illuminate\Support\Facades\File;
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

    private const ICONE = ['png' => 'file-image', 'jpg' => 'file-image', 'jpeg' => 'file-image', 'pdf' => 'file-pdf', 'doc' => 'file-word', 'docx' => 'file-word', 'xls' => 'file-excel', 'xlsx' => 'file-excel'];

    public string $filtroTipo = '';

    public bool $modal = false;

    public array $f = [];

    public $file;

    public function nuovo(): void
    {
        $this->soloStaff();
        $this->f = ['titolo' => '', 'tipo' => 'cliente', 'cliente_id' => '', 'user_id' => '', 'tipologia' => '', 'scadenza' => '', 'formazione' => false];
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
            'f.cliente_id' => 'nullable|required_if:f.tipo,cliente|exists:tc_clienti,id',
            'f.user_id' => 'nullable|required_if:f.tipo,operatore|exists:tc_admin,id',
            'f.tipologia' => 'nullable|exists:_certificati,id',
            'f.scadenza' => 'nullable|date',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $cliente = $this->f['tipo'] === 'cliente';
        if ($cliente) {
            $this->clientiVisibili()->findOrFail($this->f['cliente_id']);
        }

        $idTipo = (int) ($cliente ? $this->f['cliente_id'] : $this->f['user_id']);
        $estensione = strtolower($this->file->getClientOriginalExtension());
        $documento = new Documento([
            'id_tipo' => $idTipo,
            'tipo' => $cliente ? 'C' : 'O',
            'tipologia' => $this->f['tipologia'] ?: null,
            'descrizione' => $this->f['titolo'],
            'icona' => self::ICONE[$estensione] ?? 'file-alt',
            'scadenza' => $this->f['scadenza'] ?: null,
            'admin' => 'N',
            'formazione' => $this->f['formazione'] ? 'Y' : 'N',
        ]);
        $documento->file = ($cliente ? 'Doc_' : 'Documento_').$idTipo.'_'.time().'.'.$estensione;

        File::ensureDirectoryExists(dirname($documento->percorso()));
        File::move($this->file->getRealPath(), $documento->percorso());
        $documento->save();

        if (! $cliente) {
            DocumentoView::create(['id_documento' => $documento->id, 'id_operatore' => $idTipo]);
        }

        $this->modal = false;
    }

    public function elimina(int $id): void
    {
        $this->soloStaff();
        $d = $this->query()->findOrFail($id);
        File::delete($d->percorso());
        DocumentoView::where('id_documento', $d->id)->delete();
        $d->delete();
    }

    private function query()
    {
        $u = auth()->user();

        return Documento::query()->whereIn('tipo', ['C', 'O'])->where(fn ($q) => match (true) {
            $u->isOperatore() => $q->where('tipo', 'O')->where('id_tipo', $u->id),
            $u->isCliente() => $q->where('tipo', 'C')->where('id_tipo', $u->cliente_id),
            default => $q->where(fn ($w) => $w->where('tipo', 'C')->whereIn('id_tipo', $this->clientiVisibili()->select('tc_clienti.id'))->orWhere('tipo', 'O')),
        });
    }

    public function render()
    {
        return view('livewire.documenti', [
            'documenti' => $this->query()->with(['cliente', 'operatore'])
                ->when($this->filtroTipo, fn ($q) => $q->where('tipo', $this->filtroTipo))
                ->orderByRaw('scadenza is null')->orderBy('scadenza')->orderByDesc('id')->paginate(12),
            'clienti' => $this->clientiVisibili()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'operatori' => Admin::where('user_type', 4)->orderBy('cognome')->get(),
            'tipologie' => Certificato::orderBy('nome')->get(),
        ]);
    }
}
