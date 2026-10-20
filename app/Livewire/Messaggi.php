<?php

namespace App\Livewire;

use App\Models\ModelloMessaggio;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Modelli di messaggio')]
class Messaggi extends Component
{
    public bool $modal = false;

    public ?int $modelloId = null;

    public array $f = [];

    protected function rules(): array
    {
        return [
            'f.nome' => 'required|string|max:255',
            'f.giorni_prima' => 'required|integer|min:0|max:365',
            'f.oggetto' => 'nullable|required_if:f.invia_email,true|string|max:255',
            'f.testo_email' => 'nullable|required_if:f.invia_email,true|string|max:5000',
            'f.testo_sms' => 'nullable|required_if:f.invia_sms,true|string|max:320',
        ];
    }

    public function nuovo(): void
    {
        $this->modelloId = null;
        $this->f = ['nome' => '', 'giorni_prima' => 1, 'invia_email' => true, 'invia_sms' => false, 'oggetto' => 'Promemoria turno del [DATA TURNO]', 'testo_email' => "Ciao [OPERATORE],\nti ricordiamo il turno presso [SEDE] il [DATA TURNO] ([ORA TURNO]).\n[INDIRIZZO]", 'testo_sms' => ''];
        $this->resetValidation();
        $this->modal = true;
    }

    public function modifica(int $id): void
    {
        $m = ModelloMessaggio::findOrFail($id);
        $this->modelloId = $m->id;
        $this->f = $m->only(['nome', 'giorni_prima', 'invia_email', 'invia_sms', 'oggetto', 'testo_email', 'testo_sms']);
        $this->resetValidation();
        $this->modal = true;
    }

    public function salva(): void
    {
        $this->validate();
        $this->modelloId ? ModelloMessaggio::findOrFail($this->modelloId)->update($this->f) : ModelloMessaggio::create($this->f);
        $this->modal = false;
    }

    public function elimina(int $id): void
    {
        ModelloMessaggio::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.messaggi', ['modelli' => ModelloMessaggio::orderBy('nome')->get()]);
    }
}
