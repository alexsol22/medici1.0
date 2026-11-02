<div class="space-y-5">
    @if (! $editing)
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
    @endif

    @if ($editing)
        @php($isOperatore = ($f['ruolo'] ?? '') === 'operatore')
        <div class="card p-5 sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-slate-800">{{ $userId ? 'Modifica utente' : 'Nuovo utente' }}</h2>
                <button type="button" wire:click="$set('editing', false)" class="btn-soft">← Torna all'elenco</button>
            </div>

            <div class="mb-4 flex gap-1 overflow-x-auto rounded-2xl bg-sky-50 p-1.5">
                @php($sezioni = ['dati' => 'Dati '.($isOperatore ? 'operatore '.($f['codice'] ?? '') : 'utente')])
                @if ($isOperatore)
                    @php($sezioni += ['formazioni' => 'Formazioni', 'attestati' => 'Documenti / Attestati', 'materiali' => 'Materiali / Abbigliamento'])
                @endif
                @foreach ($sezioni as $chiave => $etichetta)
                    <button type="button" wire:click="$set('tab', '{{ $chiave }}')" @disabled($chiave !== 'dati' && ! $userId)
                        class="flex-1 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition disabled:opacity-40 {{ $tab === $chiave ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:bg-white' }}">{{ $etichetta }}</button>
                @endforeach
            </div>

            <form wire:submit="salva" @class(['hidden' => $tab !== 'dati'])>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.switch model="f.attivo" label="Abilita accesso" class="sm:col-span-2" />
                    <x-ui.field label="Colore" name="f.colore"><div class="flex items-center gap-2"><input type="color" wire:model.live="f.colore" class="h-10 w-14 cursor-pointer rounded-lg border border-sky-100 bg-white p-1"><input wire:model.live.debounce.400ms="f.colore" class="input" maxlength="7"></div></x-ui.field>
                    @if ($isOperatore)
                        <x-ui.field label="Nome in codice *" name="f.codice" class="sm:col-span-2 lg:col-span-3"><input wire:model="f.codice" class="input"></x-ui.field>
                    @endif
                    <x-ui.field label="Nome *" name="f.name"><input wire:model="f.name" class="input"></x-ui.field>
                    <x-ui.field label="Cognome *" name="f.cognome"><input wire:model="f.cognome" class="input"></x-ui.field>
                    <x-ui.field label="Email *" name="f.email"><input type="email" wire:model="f.email" class="input"></x-ui.field>
                    <x-ui.field label="Cellulare" name="f.cellulare"><input wire:model="f.cellulare" class="input"></x-ui.field>
                    @if ($isOperatore)
                        <x-ui.field label="Nato il" name="f.data_nascita"><input wire:model="f.data_nascita" class="input" maxlength="10" placeholder="gg/mm/aaaa"></x-ui.field>
                        <x-ui.field label="Nato a" name="f.luogo_nascita"><input wire:model="f.luogo_nascita" class="input"></x-ui.field>
                        <x-ui.field label="Indirizzo di residenza" name="f.indirizzo"><input wire:model="f.indirizzo" class="input"></x-ui.field>
                        <x-ui.field label="Provincia di residenza" name="f.provincia"><x-ui.select-search model="f.provincia" :options="$province->map(fn ($p) => ['id' => (string) $p->id, 'nome' => $p->sigla.' — '.$p->nome])->values()" placeholder="Scegli provincia" /></x-ui.field>
                        <x-ui.field label="Codice IBAN" name="f.iban"><input wire:model="f.iban" class="input"></x-ui.field>
                        <x-ui.field label="Costo orario" name="f.costo_orario"><input type="number" step="0.01" wire:model="f.costo_orario" class="input"></x-ui.field>
                    @endif
                    @if (auth()->user()->ruolo->value === 'admin' || auth()->user()->ruolo === \App\Enums\Ruolo::SuperAdmin)
                        <x-ui.field :label="$userId ? 'Nuova password (lascia vuoto per non cambiarla)' : 'Password (vuoto = generata)'" name="f.password"><input type="password" wire:model="f.password" class="input" autocomplete="new-password" minlength="8"></x-ui.field>
                    @endif
                    <x-ui.field label="Avatar / Fotografia" name="avatar"><input type="file" wire:model="avatar" accept="image/*" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-sky-700"></x-ui.field>
                </div>
                <div class="mt-5 flex items-center justify-between gap-2">
                    <p class="text-xs text-slate-500">I campi contrassegnati con * sono obbligatori.</p>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('editing', false)" class="btn-soft">Annulla</button>
                        <button class="btn-primary" wire:loading.attr="disabled" wire:target="avatar">{{ $userId ? 'Aggiorna' : 'Inserisci' }}</button>
                    </div>
                </div>
            </form>

            @if ($tab === 'formazioni' && $userId)
                <div class="space-y-4">
                    <ul class="divide-y divide-sky-100 rounded-2xl ring-1 ring-sky-100">
                        @forelse ($formazioni as $d)
                            <li wire:key="fo{{ $d->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                                <a href="{{ route('documenti.scarica', $d, false) }}" class="min-w-0 truncate font-medium text-sky-700 hover:underline">{{ $d->descrizione }} <span class="text-xs text-slate-400">{{ $d->inizio?->format('d/m/Y') }}{{ $d->fine ? ' → '.$d->fine->format('d/m/Y') : '' }}</span></a>
                                <button type="button" wire:click="eliminaDocumento({{ $d->id }})" wire:confirm="Eliminare la formazione?" class="btn-danger !px-3 !py-1">Elimina</button>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-slate-500">Nessuna formazione.</li>
                        @endforelse
                    </ul>
                    <div class="grid gap-3 rounded-2xl bg-sky-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Data inizio" name="formazione.inizio"><input type="date" wire:model="formazione.inizio" class="input"></x-ui.field>
                        <x-ui.field label="Data fine" name="formazione.fine"><input type="date" wire:model="formazione.fine" class="input"></x-ui.field>
                        <x-ui.field label="Titolo / Descrizione" name="formazione.descrizione"><input wire:model="formazione.descrizione" class="input"></x-ui.field>
                        <x-ui.field label="Allega file" name="fileFormazione"><input type="file" wire:model="fileFormazione" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-sky-700"></x-ui.field>
                        <button type="button" wire:click="aggiungiFormazione" wire:loading.attr="disabled" wire:target="fileFormazione,aggiungiFormazione" class="btn-primary sm:col-span-2 lg:col-span-4">Aggiungi formazione</button>
                    </div>
                </div>
            @endif

            @if ($tab === 'attestati' && $userId)
                <div class="space-y-4">
                    <ul class="divide-y divide-sky-100 rounded-2xl ring-1 ring-sky-100">
                        @forelse ($attestati as $d)
                            <li wire:key="at{{ $d->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 text-sm">
                                <div class="min-w-0">
                                    <select wire:change="aggiornaTipologia({{ $d->id }}, $event.target.value)" class="input !w-auto !py-1 font-medium">
                                        @foreach ($tipiCertificato as $t)
                                            <option value="{{ $t->id }}" @selected((string) $d->tipologia === (string) $t->id)>{{ $t->nome }}</option>
                                        @endforeach
                                    </select>
                                    @if ($d->descrizione) <span class="ml-2 text-slate-500">{{ $d->descrizione }}</span> @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="flex items-center gap-1 text-xs text-slate-500">Scadenza
                                        <input type="date" value="{{ $d->scadenza?->format('Y-m-d') }}" wire:change="aggiornaScadenza({{ $d->id }}, $event.target.value)" class="input !w-auto !py-1 {{ $d->inScadenza() ? '!border-rose-300 !text-rose-600' : '' }}">
                                    </label>
                                    <a href="{{ route('documenti.scarica', [$d, 'visualizza' => 1], false) }}" target="_blank" class="btn-soft !px-3 !py-1">Visualizza</a>
                                    <a href="{{ route('documenti.scarica', $d, false) }}" class="btn-soft !px-3 !py-1">Scarica</a>
                                    <button type="button" wire:click="eliminaDocumento({{ $d->id }})" wire:confirm="Eliminare il documento?" class="btn-danger !px-3 !py-1">Elimina</button>
                                </div>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-slate-500">Nessun documento.</li>
                        @endforelse
                    </ul>
                    <div class="grid gap-3 rounded-2xl bg-sky-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Documento / Attestato" name="attestato.tipologia"><x-ui.select-search wire:key="tipo-attestato" model="attestato.tipologia" :options="$tipiCertificato->map(fn ($t) => ['id' => (string) $t->id, 'nome' => $t->nome])->values()" placeholder="Scegli tipo" /></x-ui.field>
                        <x-ui.field label="Descrizione" name="attestato.descrizione"><input wire:model="attestato.descrizione" class="input"></x-ui.field>
                        <x-ui.field label="Scadenza" name="attestato.scadenza"><input type="date" wire:model="attestato.scadenza" class="input"></x-ui.field>
                        <x-ui.field label="Allega file" name="fileAttestato"><input type="file" wire:model="fileAttestato" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-sky-700"></x-ui.field>
                        <button type="button" wire:click="aggiungiAttestato" wire:loading.attr="disabled" wire:target="fileAttestato,aggiungiAttestato" class="btn-primary sm:col-span-2 lg:col-span-4">Aggiungi documento</button>
                    </div>
                </div>
            @endif

            @if ($tab === 'materiali' && $userId)
                <div class="space-y-4">
                    <ul class="divide-y divide-sky-100 rounded-2xl ring-1 ring-sky-100">
                        @forelse ($materiali as $m)
                            <li wire:key="ma{{ $m->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 text-sm">
                                <div class="min-w-0"><span class="font-medium text-slate-800">{{ $m->tipo?->nome }}</span> <span class="text-slate-500">{{ $m->descrizione }} {{ $m->taglia ? '· taglia '.$m->taglia : '' }} {{ $m->riconsegna ? '· riconsegna '.$m->riconsegna->format('d/m/Y') : '' }}</span></div>
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="consegnaMateriale({{ $m->id }})" class="rounded-full px-3 py-1 text-xs font-bold {{ $m->consegnato ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $m->consegnato ? 'Consegnato' : 'Non consegnato' }}</button>
                                    <button type="button" wire:click="eliminaMateriale({{ $m->id }})" wire:confirm="Eliminare il materiale?" class="btn-danger !px-3 !py-1">Elimina</button>
                                </div>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-slate-500">Nessun materiale.</li>
                        @endforelse
                    </ul>
                    <div class="grid gap-3 rounded-2xl bg-sky-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Materiale / abbigliamento" name="materiale.id_materiale"><x-ui.select-search wire:key="tipo-materiale" model="materiale.id_materiale" :options="$tipiMateriale->map(fn ($t) => ['id' => (string) $t->id, 'nome' => $t->nome])->values()" placeholder="Scegli materiale" /></x-ui.field>
                        <x-ui.field label="Descrizione" name="materiale.descrizione"><input wire:model="materiale.descrizione" class="input"></x-ui.field>
                        <x-ui.field label="Taglia" name="materiale.taglia"><input wire:model="materiale.taglia" class="input"></x-ui.field>
                        <x-ui.field label="Riconsegna" name="materiale.riconsegna"><input type="date" wire:model="materiale.riconsegna" class="input"></x-ui.field>
                        <x-ui.switch model="materiale.consegnato" label="Consegnato" class="sm:col-span-2 lg:col-span-4" />
                        <button type="button" wire:click="aggiungiMateriale" class="btn-primary sm:col-span-2 lg:col-span-4">Aggiungi materiale</button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
