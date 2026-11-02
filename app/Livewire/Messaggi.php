<?php

namespace App\Livewire;

use App\Models\Legacy\Messaggio;
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
            'f.giorni' => 'required|integer|min:0|max:365',
            'f.ore' => 'nullable|integer|min:0|max:999',
            'f.minuti' => 'nullable|integer|min:0|max:999',
            'f.oggetto' => 'nullable|string|max:255',
            'f.mittente' => 'required|string|max:16',
            'f.modello_email' => 'nullable|string|max:255',
            'f.modello_sms' => 'nullable|string|max:255',
            'f.admin_email' => 'nullable|string|max:150',
            'f.admin_sms' => 'nullable|string|max:150',
        ];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }

    public function modifica(int $id): void
    {
        $m = Messaggio::findOrFail($id);
        $this->modelloId = $m->id;
        $this->f = $m->only(['nome', 'giorni', 'ore', 'minuti', 'oggetto', 'mittente', 'modello_email', 'modello_sms', 'admin_email', 'admin_sms'])
            + ['invia_email' => $m->invia_email, 'invia_sms' => $m->invia_sms];
        $this->resetValidation();
        $this->modal = true;
    }

    public function salva(): void
    {
        $this->validate();
        $dati = $this->f;
        $dati['invia_email'] = ! empty($dati['invia_email']) ? 'Y' : 'N';
        $dati['invia_sms'] = ! empty($dati['invia_sms']) ? 'Y' : 'N';
        Messaggio::findOrFail($this->modelloId)->update($dati);
        $this->modal = false;
    }

    public function render()
    {
        return view('livewire.messaggi', ['modelli' => Messaggio::orderBy('id')->get()]);
    }
}
