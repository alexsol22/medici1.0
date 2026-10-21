<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAccesso;
use App\Models\CodiceQr;
use App\Models\Sede;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class SedeForm extends Component
{
    use ConAccesso;

    public ?int $sedeId = null;

    public string $nomeSede = '';

    public array $f = [];

    public array $ore = [1 => '', 2 => '', 3 => '', 4 => '', 5 => '', 6 => '', 7 => ''];

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

    public function mount(?int $sede = null): void
    {
        $this->soloStaff();

        if ($sede === null) {
            $this->f = array_fill_keys(self::CAMPI, '');
            $this->f['distanza_max'] = 100;
            $this->f['cliente_id'] = request()->integer('cliente') ?: '';

            return;
        }

        $s = $this->sediVisibili()->with('cliente')->findOrFail($sede);
        $this->sedeId = $s->id;
        $this->nomeSede = trim($s->descrizione.' · '.$s->cliente->ragione_sociale, ' ·');
        $this->f = collect(self::CAMPI)->mapWithKeys(fn ($k) => [$k => $s->$k ?? ''])->all();
        $this->ore = collect(range(1, 7))->mapWithKeys(fn ($g) => [$g => $s->ore_richieste[$g] ?? ''])->all();
    }

    public function salva()
    {
        $this->soloStaff();
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('tab-errore', tab: $this->tabConErrori(array_keys($e->errors())));

            throw $e;
        }
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

        return $this->redirectRoute('sedi', navigate: true);
    }

    private function tabConErrori(array $campi): string
    {
        foreach ($campi as $campo) {
            if (in_array($campo, ['f.indirizzo', 'f.cap', 'f.citta', 'f.provincia', 'f.lat', 'f.lng', 'f.distanza_max'], true)) {
                return 'posizione';
            }
            if (in_array($campo, ['f.tariffa'], true) || str_starts_with($campo, 'ore.')) {
                return 'servizio';
            }

            return 'generale';
        }

        return 'generale';
    }

    public function render()
    {
        return view('livewire.sede-form', [
            'clienti' => $this->clientiVisibili()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'giorni' => [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'],
        ])->title($this->sedeId ? 'Modifica sede: '.$this->nomeSede : 'Nuova sede');
    }
}
