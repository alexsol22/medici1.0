<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 gap-2">
            <input type="search" wire:model.live.debounce.300ms="cerca" placeholder="Cerca per nome o email…" class="input sm:max-w-xs">
            <select wire:model.live="filtroRuolo" class="input sm:max-w-[11rem]">
                <option value="">Tutti i ruoli</option>
                @foreach ($ruoli as $r) <option value="{{ $r->value }}">{{ $r->label() }}</option> @endforeach
            </select>
        </div>
        <button wire:click="nuovo" class="btn-primary">+ Nuovo utente</button>
    </div>

    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-sky-100">
            <thead class="bg-sky-50/60"><tr><th class="th">Nome</th><th class="th">Ruolo</th><th class="th">Contatti</th><th class="th">Stato</th><th class="th"></th></tr></thead>
            <tbody class="divide-y divide-sky-50">
                @foreach ($utenti as $u)
                    <tr wire:key="u{{ $u->id }}" class="hover:bg-sky-50/40">
                        <td class="td"><p class="font-semibold text-slate-800">{{ $u->nome_completo }}</p><p class="text-xs text-slate-500">{{ $u->codice }}</p></td>
                        <td class="td">{{ $u->ruolo->label() }}<p class="text-xs text-slate-500">{{ $u->cliente?->ragione_sociale }}</p></td>
                        <td class="td"><p>{{ $u->email }}</p><p class="text-xs text-slate-500">{{ $u->cellulare }}</p></td>
                        <td class="td"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $u->attivo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $u->attivo ? 'Attivo' : 'Disattivo' }}</span></td>
                        <td class="td whitespace-nowrap text-right">
                            <button wire:click="modifica({{ $u->id }})" class="btn-soft !px-3 !py-1.5">Modifica</button>
                            @if ($u->id !== auth()->id())
                                <button wire:click="attivaDisattiva({{ $u->id }})" class="btn-danger !px-3 !py-1.5">{{ $u->attivo ? 'Disattiva' : 'Attiva' }}</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div></div>
    {{ $utenti->links() }}

    @if ($modal)
        <x-ui.modal :title="$userId ? 'Modifica utente' : 'Nuovo utente'">
            <form wire:submit="salva" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nome" name="f.name"><input wire:model="f.name" class="input"></x-ui.field>
                <x-ui.field label="Cognome" name="f.cognome"><input wire:model="f.cognome" class="input"></x-ui.field>
                <x-ui.field label="Email (login)" name="f.email" class="sm:col-span-2"><input type="email" wire:model="f.email" class="input"></x-ui.field>
                <x-ui.field label="Cellulare" name="f.cellulare"><input wire:model="f.cellulare" class="input"></x-ui.field>
                <x-ui.field label="Ruolo" name="f.ruolo">
                    <select wire:model.live="f.ruolo" class="input">
                        @foreach ($ruoli as $r)
                            @continue(in_array($r->value, ['admin', 'super_admin']) && auth()->user()->ruolo !== \App\Enums\Ruolo::SuperAdmin)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                @if (($f['ruolo'] ?? '') === 'cliente')
                    <x-ui.field label="Cliente" name="f.cliente_id" class="sm:col-span-2">
                        <select wire:model="f.cliente_id" class="input"><option value="">—</option>@foreach ($clienti as $c)<option value="{{ $c->id }}">{{ $c->ragione_sociale }}</option>@endforeach</select>
                    </x-ui.field>
                @endif
                @if (($f['ruolo'] ?? '') === 'operatore')
                    <x-ui.field label="Nome in codice" name="f.codice"><input wire:model="f.codice" class="input"></x-ui.field>
                    <x-ui.field label="Costo orario (€)" name="f.costo_orario"><input wire:model="f.costo_orario" class="input"></x-ui.field>
                @endif
                <x-ui.field :label="$userId ? 'Nuova password (opzionale)' : 'Password'" name="f.password" class="sm:col-span-2"><input type="password" wire:model="f.password" class="input" autocomplete="new-password"></x-ui.field>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Annulla</button>
                    <button class="btn-primary">Salva</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
