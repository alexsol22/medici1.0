<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Promemoria automatici inviati agli operatori prima dei turni. Segnaposto: <code class="rounded bg-sky-50 px-1">[OPERATORE] [SEDE] [DATA TURNO] [ORA TURNO] [INDIRIZZO]</code></p>
        <button wire:click="nuovo" class="btn-primary shrink-0">+ Nuovo modello</button>
    </div>
    <section class="card overflow-hidden">
        <div class="border-b border-sky-100 px-5 py-4">
            <h2 class="font-bold text-slate-800">Messaggi Email</h2>
            <p class="mt-1 text-xs text-slate-500">Modelli e testi effettivi utilizzati per le email.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-sky-50/70 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Modello</th>
                        <th class="px-5 py-3 font-semibold">Oggetto</th>
                        <th class="px-5 py-3 font-semibold">Testo email</th>
                        <th class="px-5 py-3 font-semibold">Invio</th>
                        <th class="px-5 py-3 font-semibold">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sky-50">
                    @forelse ($modelli->where('invia_email', true) as $m)
                        <tr wire:key="email-{{ $m->id }}" class="align-top">
                            <td class="px-5 py-4 font-medium text-slate-800">{{ $m->nome }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $m->oggetto }}</td>
                            <td class="max-w-lg whitespace-pre-line px-5 py-4 text-slate-600">{{ $m->testo_email }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $m->giorni_prima }} {{ $m->giorni_prima === 1 ? 'giorno' : 'giorni' }} prima</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <button wire:click="modifica({{ $m->id }})" class="btn-soft !px-3 !py-1.5">Modifica</button>
                                <button wire:click="elimina({{ $m->id }})" wire:confirm="Eliminare il modello?" class="btn-danger !px-3 !py-1.5">Elimina</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">Nessun modello Email attivo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="border-b border-sky-100 px-5 py-4">
            <h2 class="font-bold text-slate-800">Messaggi SMS</h2>
            <p class="mt-1 text-xs text-slate-500">Testi SMS reali associati ai modelli. Il contenuto resta entro i 320 caratteri previsti.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-left text-sm">
                <thead class="bg-sky-50/70 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Modello</th>
                        <th class="px-5 py-3 font-semibold">Testo SMS</th>
                        <th class="px-5 py-3 font-semibold">Invio</th>
                        <th class="px-5 py-3 font-semibold">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sky-50">
                    @forelse ($modelli->where('invia_sms', true) as $m)
                        <tr wire:key="sms-{{ $m->id }}" class="align-top">
                            <td class="px-5 py-4 font-medium text-slate-800">{{ $m->nome }}</td>
                            <td class="max-w-2xl whitespace-pre-line px-5 py-4 text-slate-600">{{ $m->testo_sms }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $m->giorni_prima }} {{ $m->giorni_prima === 1 ? 'giorno' : 'giorni' }} prima</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <button wire:click="modifica({{ $m->id }})" class="btn-soft !px-3 !py-1.5">Modifica</button>
                                <button wire:click="elimina({{ $m->id }})" wire:confirm="Eliminare il modello?" class="btn-danger !px-3 !py-1.5">Elimina</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">Nessun modello SMS attivo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

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
