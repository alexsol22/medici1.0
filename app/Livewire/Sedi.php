<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAccesso;
use App\Models\CodiceQr;
use App\Models\Postazione;
use App\Models\QrAziendale;
use App\Models\Sede;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Sedi')]
class Sedi extends Component
{
    use ConAccesso, WithPagination;

    public string $cerca = '';

    public ?int $filtroCliente = null;

    public bool $modal = false;

    public ?int $sedeId = null;

    public array $f = [];

    public array $ore = [1 => '', 2 => '', 3 => '', 4 => '', 5 => '', 6 => '', 7 => ''];

    public bool $modalPostazioni = false;

    public ?int $sedeQr = null;

    public ?int $sedePostazioni = null;

    public array $p = ['titolo' => '', 'minuti_passaggio' => ''];

    private const CAMPI = ['cliente_id', 'descrizione', 'indirizzo', 'cap', 'citta', 'provincia', 'telefono', 'email', 'note', 'lat', 'lng', 'distanza_max', 'tariffa'];

    protected function rules(): array
    {
        return [
            'f.cliente_id' => 'required|exists:clienti,id',
            'f.descrizione' => 'required|string|max:255',
            'f.cap' => 'nullable|string|max:10',
            'f.provincia' => 'nullable|string|size:2',
            'f.email' => 'nullable|email',
            'f.lat' => 'nullable|numeric|between:-90,90',
            'f.lng' => 'nullable|numeric|between:-180,180',
            'f.distanza_max' => 'required|integer|min:10|max:5000',
            'f.tariffa' => 'nullable|numeric|min:0',
            'ore.*' => 'nullable|numeric|min:0|max:24',
        ];
    }

    public function updatingCerca(): void
    {
        $this->resetPage();
    }

    public function nuova(): void
    {
        $this->soloStaff();
        $this->sedeId = null;
        $this->f = array_fill_keys(self::CAMPI, '') + [];
        $this->f['distanza_max'] = 100;
        $this->f['cliente_id'] = $this->filtroCliente ?? '';
        $this->ore = array_fill_keys(range(1, 7), '');
        $this->resetValidation();
        $this->modal = true;
    }

    public function modifica(int $id): void
    {
        $this->soloStaff();
        $s = $this->sediVisibili()->findOrFail($id);
        $this->sedeId = $s->id;
        $this->f = collect(self::CAMPI)->mapWithKeys(fn ($k) => [$k => $s->$k ?? ''])->all();
        $this->ore = collect(range(1, 7))->mapWithKeys(fn ($g) => [$g => $s->ore_richieste[$g] ?? ''])->all();
        $this->resetValidation();
        $this->modal = true;
    }

    public function salva(): void
    {
        $this->soloStaff();
        $this->validate();
        $this->clientiVisibili()->findOrFail($this->f['cliente_id']);

        $dati = array_map(fn ($v) => $v === '' ? null : $v, $this->f);
        $dati['provincia'] = isset($dati['provincia']) ? strtoupper($dati['provincia']) : null;
        $dati['ore_richieste'] = array_map(fn ($v) => (float) $v, $this->ore);

        DB::transaction(function () use ($dati) {
            if ($this->sedeId) {
                $this->sediVisibili()->findOrFail($this->sedeId)->update($dati);

                return;
            }
            $sede = Sede::create($dati);
            $sede->update(['codice_qr_id' => CodiceQr::nuovo(CodiceQr::SEDE, $sede->id)->id]);
        });

        $this->modal = false;
    }

    public function elimina(int $id): void
    {
        $this->soloStaff();
        $this->sediVisibili()->findOrFail($id)->delete();
    }

    public function qr(int $id): void
    {
        $this->soloStaff();
        $this->sedeQr = $this->sediVisibili()->findOrFail($id)->id;
    }

    public function chiudiQr(): void
    {
        $this->sedeQr = null;
    }

    public function postazioni(int $id): void
    {
        $this->sedePostazioni = $this->sediVisibili()->findOrFail($id)->id;
        $this->p = ['titolo' => '', 'minuti_passaggio' => ''];
        $this->resetValidation();
        $this->modalPostazioni = true;
    }

    public function aggiungiPostazione(): void
    {
        $this->soloStaff();
        $this->validate([
            'p.titolo' => 'required|string|max:255',
            'p.minuti_passaggio' => 'nullable|integer|min:1|max:1440',
        ]);
        $sede = $this->sediVisibili()->findOrFail($this->sedePostazioni);

        DB::transaction(function () use ($sede) {
            $post = Postazione::create([
                'sede_id' => $sede->id, 'cliente_id' => $sede->cliente_id,
                'titolo' => $this->p['titolo'], 'minuti_passaggio' => $this->p['minuti_passaggio'] ?: null,
            ]);
            $post->update(['codice_qr_id' => CodiceQr::nuovo(CodiceQr::POSTAZIONE, $sede->id, $post->id)->id]);
        });
        $this->p = ['titolo' => '', 'minuti_passaggio' => ''];
    }

    public function eliminaPostazione(int $id): void
    {
        $this->soloStaff();
        Postazione::whereIn('sede_id', $this->sediVisibili()->select('sedi.id'))->findOrFail($id)->delete();
    }

    public function render()
    {
        $sedi = $this->sediVisibili()->with(['cliente', 'codiceQr'])->withCount('postazioni')
            ->when($this->filtroCliente, fn ($q) => $q->where('cliente_id', $this->filtroCliente))
            ->when($this->cerca, fn ($q) => $q->where(fn ($w) => $w->where('descrizione', 'like', "%{$this->cerca}%")->orWhere('citta', 'like', "%{$this->cerca}%")))
            ->orderBy('descrizione')->paginate(9);

        $loghi = QrAziendale::whereIn('sede_id', $sedi->pluck('id'))->whereNotNull('logo_path')->latest()->get()
            ->unique('sede_id')->mapWithKeys(fn ($q) => [$q->sede_id => $q->logoDataUri()])->filter();

        return view('livewire.sedi', [
            'sedi' => $sedi,
            'loghi' => $loghi,
            'clienti' => $this->clientiVisibili()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'sedeCorrente' => $this->sedePostazioni ? $this->sediVisibili()->with('postazioni.codiceQr', 'codiceQr')->find($this->sedePostazioni) : null,
            'giorni' => [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'],
        ]);
    }
}
