<div class="space-y-5">
    @if (! $modal)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-1">
                <input type="search" wire:model.live.debounce.300ms="cerca" placeholder="Cerca cliente o città…" class="input sm:max-w-xs">
            </div>
            <button wire:click="nuovo" class="btn-primary">+ Nuovo cliente</button>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-sky-100">
                    <thead class="bg-sky-50/60"><tr><th class="th">Ragione Sociale</th><th class="th">Iva / Codice Fiscale</th><th class="th">Referente</th><th class="th">Email</th><th class="th">Telefono</th><th class="th text-right">Azioni</th></tr></thead>
                    <tbody class="divide-y divide-sky-50">
                        @forelse ($clienti as $c)
                            <tr wire:key="c{{ $c->id }}" class="hover:bg-sky-50/40">
                                <td class="td font-semibold text-slate-800">{{ $c->ragione_sociale }}</td>
                                <td class="td">{{ $c->piva ?: $c->codice_fiscale }}</td>
                                <td class="td">{{ trim($c->nome.' '.$c->cognome) }}</td>
                                <td class="td">{{ $c->email }}</td>
                                <td class="td">{{ $c->telefono }}</td>
                                <td class="td whitespace-nowrap text-right">
                                    <button wire:click="modifica({{ $c->id }})" class="btn-soft !px-3 !py-1.5">Modifica</button>
                                    <button wire:click="elimina({{ $c->id }})" wire:confirm="Eliminare il cliente e tutti i suoi dati?" class="btn-danger !px-3 !py-1.5">Elimina</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="td py-10 text-center text-slate-500">Nessun cliente trovato.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $clienti->links() }}
    @endif

    @if ($modal)
        <div class="card p-5 sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-slate-800">{{ $clienteId ? 'Modifica cliente' : 'Nuovo cliente' }}</h2>
                <button type="button" wire:click="$set('modal', false)" class="btn-soft">← Torna all'elenco</button>
            </div>
            <form wire:submit="salva">
                <div class="mb-4 flex gap-1 overflow-x-auto rounded-2xl bg-sky-50 p-1.5">
                    @foreach (['dati' => 'Dati '.($f['ragione_sociale'] ?? ''), 'documenti' => 'Documenti', 'operatori' => 'Gestione operatori'] as $chiave => $etichetta)
                        <button type="button" wire:click="$set('tab', '{{ $chiave }}')" @disabled($chiave === 'documenti' && ! $clienteId)
                            class="flex-1 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition disabled:opacity-40 {{ $tab === $chiave ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:bg-white' }}">{{ $etichetta }}</button>
                    @endforeach
                </div>

                <div @class(['grid gap-4 sm:grid-cols-2 lg:grid-cols-3', 'hidden' => $tab !== 'dati'])>
                <x-ui.switch model="f.accesso" label="Accesso" class="sm:col-span-2" />
                <x-ui.field label="Colore" name="f.colore"><div class="flex items-center gap-2"><input type="color" wire:model.live="f.colore" class="h-10 w-14 cursor-pointer rounded-lg border border-sky-100 bg-white p-1"><input wire:model.live.debounce.400ms="f.colore" class="input" maxlength="7"></div></x-ui.field>
                <x-ui.field label="Ragione sociale *" name="f.ragione_sociale" class="sm:col-span-2 lg:col-span-3"><input wire:model="f.ragione_sociale" class="input"></x-ui.field>
                <x-ui.field label="Nome referente" name="f.nome"><input wire:model="f.nome" class="input"></x-ui.field>
                <x-ui.field label="Cognome referente" name="f.cognome"><input wire:model="f.cognome" class="input"></x-ui.field>
                <x-ui.field label="Codice fiscale" name="f.codice_fiscale"><input wire:model="f.codice_fiscale" class="input"></x-ui.field>
                <x-ui.field label="P. IVA *" name="f.piva"><input wire:model="f.piva" class="input" maxlength="11"></x-ui.field>
                <x-ui.field label="Indirizzo" name="f.indirizzo" class="sm:col-span-2"><input wire:model="f.indirizzo" class="input"></x-ui.field>
                <x-ui.field label="CAP" name="f.cap"><input wire:model="f.cap" class="input" maxlength="5"></x-ui.field>
                <x-ui.field label="Provincia" name="f.provincia_id"><x-ui.select-search model="f.provincia_id" :options="$province->map(fn ($p) => ['id' => (string) $p->id, 'nome' => $p->sigla.' — '.$p->nome])->values()" placeholder="Scegli provincia" live /></x-ui.field>
                <x-ui.field label="Città" name="f.citta_id"><x-ui.select-search wire:key="citta-{{ $f['provincia_id'] ?? 0 }}" model="f.citta_id" :options="$comuni->map(fn ($c) => ['id' => (string) $c->id, 'nome' => $c->comune])->values()" placeholder="Scegli città" /></x-ui.field>
                <x-ui.field label="Email *" name="f.email"><input type="email" wire:model="f.email" class="input"></x-ui.field>
                <x-ui.field label="PEC" name="f.pec"><input type="email" wire:model="f.pec" class="input"></x-ui.field>
                <x-ui.field label="Cellulare" name="f.cellulare"><input wire:model="f.cellulare" class="input"></x-ui.field>
                <x-ui.field label="Telefono" name="f.telefono"><input wire:model="f.telefono" class="input"></x-ui.field>
                <x-ui.field label="Fax" name="f.fax"><input wire:model="f.fax" class="input"></x-ui.field>
                </div>

                @if ($tab === 'documenti' && $clienteId)
                    <div class="space-y-4">
                        <ul class="divide-y divide-sky-100 rounded-2xl ring-1 ring-sky-100">
                            @forelse ($documenti as $d)
                                <li wire:key="d{{ $d->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                                    <a href="{{ route('documenti.scarica', $d) }}" class="truncate font-medium text-sky-700 hover:underline">{{ $d->descrizione }} <span class="text-xs text-slate-400">{{ $d->file }}</span></a>
                                    <button type="button" wire:click="eliminaDocumento({{ $d->id }})" wire:confirm="Eliminare il documento?" class="btn-danger !px-3 !py-1">Elimina</button>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-center text-sm text-slate-500">Nessun documento.</li>
                            @endforelse
                        </ul>
                        <div class="grid gap-3 rounded-2xl bg-sky-50 p-4 sm:grid-cols-2">
                            <x-ui.field label="Titolo / Descrizione" name="titoloDocumento"><input wire:model="titoloDocumento" class="input"></x-ui.field>
                            <x-ui.field label="Allega file" name="fileDocumento"><input type="file" wire:model="fileDocumento" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:font-semibold file:text-sky-700"></x-ui.field>
                            <button type="button" wire:click="aggiungiDocumento" wire:loading.attr="disabled" wire:target="fileDocumento,aggiungiDocumento" class="btn-primary sm:col-span-2">Aggiungi documento</button>
                        </div>
                        <p class="text-xs text-slate-500">Formati: png, jpg, xls, xlsx, pdf, mp4, zip.</p>
                    </div>
                @endif

                @if ($tab === 'operatori')
                    @php($elencoOperatori = $operatori->map(fn ($o) => ['id' => (string) $o->id, 'nome' => trim($o->cognome.' '.$o->nome)])->values())
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-ui.multi-select label="Operatori preferiti" model="preferiti" :options="$elencoOperatori" />
                        <x-ui.multi-select label="Escludi operatori" model="escludi" :options="$elencoOperatori" tone="rose" />
                    </div>
                @endif

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Annulla</button>
                    <button class="btn-primary">Salva</button>
                </div>
            </form>
        </div>
    @endif
</div>
