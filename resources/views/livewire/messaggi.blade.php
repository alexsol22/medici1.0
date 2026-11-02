<div class="space-y-5">
    <p class="text-sm text-slate-500">Messaggi automatici del sistema (avvisi turno, ritardi, scadenze, riconsegna materiale). Segnaposto nei testi: <code class="rounded bg-sky-50 px-1">[OPERATORE] [SEDE] [DATA TURNO] [ORA TURNO] [INDIRIZZO]</code></p>

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-sky-50/70 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Messaggio</th>
                        <th class="px-5 py-3 font-semibold">Oggetto</th>
                        <th class="px-5 py-3 font-semibold">Tempi</th>
                        <th class="px-5 py-3 font-semibold">Canali</th>
                        <th class="px-5 py-3 font-semibold">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sky-50">
                    @foreach ($modelli as $m)
                        <tr wire:key="m{{ $m->id }}" class="align-top">
                            <td class="px-5 py-4 font-medium text-slate-800">{{ $m->nome }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $m->oggetto }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $m->giorni }} g · {{ $m->ore ?? 0 }} h · {{ $m->minuti ?? 0 }} min</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $m->invia_email ? 'Email' : '' }}{{ $m->invia_email && $m->invia_sms ? ' + ' : '' }}{{ $m->invia_sms ? 'SMS' : '' }}{{ ! $m->invia_email && ! $m->invia_sms ? 'Disattivato' : '' }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><button wire:click="modifica({{ $m->id }})" class="btn-soft !px-3 !py-1.5">Modifica</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($modal)
        <x-ui.modal title="Modifica messaggio" wide>
            <form wire:submit="salva" class="grid gap-4 sm:grid-cols-3">
                <x-ui.field label="Nome" name="f.nome" class="sm:col-span-3"><input wire:model="f.nome" class="input"></x-ui.field>
                <x-ui.field label="Giorni" name="f.giorni"><input type="number" wire:model="f.giorni" class="input"></x-ui.field>
                <x-ui.field label="Ore" name="f.ore"><input type="number" wire:model="f.ore" class="input"></x-ui.field>
                <x-ui.field label="Minuti" name="f.minuti"><input type="number" wire:model="f.minuti" class="input"></x-ui.field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="f.invia_email" class="rounded border-sky-300 text-sky-500"> Invia email</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="f.invia_sms" class="rounded border-sky-300 text-sky-500"> Invia SMS</label>
                <x-ui.field label="Mittente SMS" name="f.mittente"><input wire:model="f.mittente" class="input"></x-ui.field>
                <x-ui.field label="Oggetto email" name="f.oggetto" class="sm:col-span-3"><input wire:model="f.oggetto" class="input"></x-ui.field>
                <x-ui.field label="Testo email" name="f.modello_email" class="sm:col-span-3"><textarea wire:model="f.modello_email" rows="4" class="input"></textarea></x-ui.field>
                <x-ui.field label="Testo SMS" name="f.modello_sms" class="sm:col-span-3"><textarea wire:model="f.modello_sms" rows="2" class="input"></textarea></x-ui.field>
                <x-ui.field label="Email amministrazione (separate da virgola)" name="f.admin_email" class="sm:col-span-3"><input wire:model="f.admin_email" class="input"></x-ui.field>
                <x-ui.field label="Numeri SMS amministrazione" name="f.admin_sms" class="sm:col-span-3"><input wire:model="f.admin_sms" class="input"></x-ui.field>
                <div class="flex justify-end gap-2 sm:col-span-3">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Annulla</button>
                    <button class="btn-primary">Salva</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
