<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Promemoria automatici inviati agli operatori prima dei turni. Segnaposto: <code class="rounded bg-sky-50 px-1">[OPERATORE] [SEDE] [DATA TURNO] [ORA TURNO] [INDIRIZZO]</code></p>
        <button wire:click="nuovo" class="btn-primary shrink-0">+ Nuovo modello</button>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        @forelse ($modelli as $m)
            <div wire:key="m{{ $m->id }}" class="card p-5">
                <h3 class="font-bold text-slate-800">{{ $m->nome }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $m->giorni_prima }} giorni prima · {{ collect([$m->invia_email ? 'Email' : null, $m->invia_sms ? 'SMS' : null])->filter()->implode(' + ') ?: 'disattivato' }}</p>
                <p class="mt-3 line-clamp-3 whitespace-pre-line text-sm text-slate-600">{{ $m->testo_email }}</p>
                <div class="mt-4 flex gap-2">
                    <button wire:click="modifica({{ $m->id }})" class="btn-soft !px-3 !py-1.5">Modifica</button>
                    <button wire:click="elimina({{ $m->id }})" wire:confirm="Eliminare il modello?" class="btn-danger !px-3 !py-1.5">Elimina</button>
                </div>
            </div>
        @empty
            <p class="card col-span-full p-10 text-center text-sm text-slate-500">Nessun modello configurato.</p>
        @endforelse
    </div>

    @if ($modal)
        <x-ui.modal :title="$modelloId ? 'Modifica modello' : 'Nuovo modello'" wide>
            <form wire:submit="salva" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nome" name="f.nome"><input wire:model="f.nome" class="input"></x-ui.field>
                <x-ui.field label="Giorni prima del turno" name="f.giorni_prima"><input type="number" wire:model="f.giorni_prima" class="input"></x-ui.field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="f.invia_email" class="rounded border-sky-300 text-sky-500"> Invia email</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="f.invia_sms" class="rounded border-sky-300 text-sky-500"> Invia SMS</label>
                <x-ui.field label="Oggetto email" name="f.oggetto" class="sm:col-span-2"><input wire:model="f.oggetto" class="input"></x-ui.field>
                <x-ui.field label="Testo email" name="f.testo_email" class="sm:col-span-2"><textarea wire:model="f.testo_email" rows="5" class="input"></textarea></x-ui.field>
                <x-ui.field label="Testo SMS" name="f.testo_sms" class="sm:col-span-2"><textarea wire:model="f.testo_sms" rows="2" class="input"></textarea></x-ui.field>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Annulla</button>
                    <button class="btn-primary">Salva</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
