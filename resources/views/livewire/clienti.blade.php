<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <input type="search" wire:model.live.debounce.300ms="cerca" placeholder="Cerca cliente o città…" class="input sm:max-w-xs">
        <button wire:click="nuovo" class="btn-primary">+ Nuovo cliente</button>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-sky-100">
                <thead class="bg-sky-50/60"><tr><th class="th">Cliente</th><th class="th">Referente</th><th class="th">Contatti</th><th class="th">Sedi</th><th class="th"></th></tr></thead>
                <tbody class="divide-y divide-sky-50">
                    @forelse ($clienti as $c)
                        <tr wire:key="c{{ $c->id }}" class="hover:bg-sky-50/40">
                            <td class="td"><p class="font-semibold text-slate-800">{{ $c->ragione_sociale }}</p><p class="text-xs text-slate-500">{{ $c->citta }} {{ $c->provincia ? "({$c->provincia})" : '' }}</p></td>
                            <td class="td">{{ $c->referente_nome }} {{ $c->referente_cognome }}</td>
                            <td class="td"><p>{{ $c->telefono }}</p><p class="text-xs text-slate-500">{{ $c->email }}</p></td>
                            <td class="td"><span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $c->sedi_count }}</span></td>
                            <td class="td whitespace-nowrap text-right">
                                <button wire:click="modifica({{ $c->id }})" class="btn-soft !px-3 !py-1.5">Modifica</button>
                                <button wire:click="elimina({{ $c->id }})" wire:confirm="Eliminare il cliente e tutti i suoi dati?" class="btn-danger !px-3 !py-1.5">Elimina</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="td py-10 text-center text-slate-500">Nessun cliente trovato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $clienti->links() }}

    @if ($modal)
        <x-ui.modal :title="$clienteId ? 'Modifica cliente' : 'Nuovo cliente'" wide>
            <form wire:submit="salva" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Ragione sociale" name="f.ragione_sociale" class="sm:col-span-2"><input wire:model="f.ragione_sociale" class="input"></x-ui.field>
                <x-ui.field label="Codice fiscale" name="f.codice_fiscale"><input wire:model="f.codice_fiscale" class="input"></x-ui.field>
                <x-ui.field label="P. IVA" name="f.piva"><input wire:model="f.piva" class="input" maxlength="11"></x-ui.field>
                <x-ui.field label="Indirizzo" name="f.indirizzo" class="sm:col-span-2"><input wire:model="f.indirizzo" class="input"></x-ui.field>
                <x-ui.field label="CAP" name="f.cap"><input wire:model="f.cap" class="input"></x-ui.field>
                <x-ui.field label="Città" name="f.citta"><input wire:model="f.citta" class="input"></x-ui.field>
                <x-ui.field label="Provincia" name="f.provincia"><input wire:model="f.provincia" class="input" maxlength="2"></x-ui.field>
                <x-ui.field label="Telefono" name="f.telefono"><input wire:model="f.telefono" class="input"></x-ui.field>
                <x-ui.field label="Referente nome" name="f.referente_nome"><input wire:model="f.referente_nome" class="input"></x-ui.field>
                <x-ui.field label="Referente cognome" name="f.referente_cognome"><input wire:model="f.referente_cognome" class="input"></x-ui.field>
                <x-ui.field label="Email" name="f.email"><input type="email" wire:model="f.email" class="input"></x-ui.field>
                <x-ui.field label="PEC" name="f.pec"><input type="email" wire:model="f.pec" class="input"></x-ui.field>
                <x-ui.field label="Latitudine" name="f.lat"><input wire:model="f.lat" class="input"></x-ui.field>
                <x-ui.field label="Longitudine" name="f.lng"><input wire:model="f.lng" class="input"></x-ui.field>
                <x-ui.field label="Inizio contratto" name="f.inizio_contratto"><input type="date" wire:model="f.inizio_contratto" class="input"></x-ui.field>
                <x-ui.field label="Fine contratto" name="f.fine_contratto"><input type="date" wire:model="f.fine_contratto" class="input"></x-ui.field>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Annulla</button>
                    <button class="btn-primary">Salva</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
