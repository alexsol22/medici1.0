@php($staff = auth()->user()->isStaff())
<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:flex-1">
            @if ($vista === 'lista')
                <div><label class="label">Dal</label><input type="date" wire:model.live="da" class="input"></div>
                <div><label class="label">Al</label><input type="date" wire:model.live="a" class="input"></div>
            @endif
            @unless (auth()->user()->isOperatore())
                <div><label class="label">Sede</label><select wire:model.live="filtroSede" class="input"><option value="">Tutte</option>@foreach ($sedi as $s)<option value="{{ $s->id }}">{{ $s->descrizione }}</option>@endforeach</select></div>
                <div><label class="label">Operatore</label><select wire:model.live="filtroOperatore" class="input"><option value="">Tutti</option>@foreach ($operatori as $o)<option value="{{ $o->id }}">{{ $o->nome_completo }}</option>@endforeach</select></div>
            @endunless
        </div>
        <div class="flex items-center gap-2">
            <div class="inline-flex rounded-xl bg-white p-1 ring-1 ring-sky-200">
                <button wire:click="$set('vista', 'calendario')" class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $vista === 'calendario' ? 'bg-sky-500 text-white' : 'text-slate-600' }}">Calendario</button>
                <button wire:click="$set('vista', 'lista')" class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $vista === 'lista' ? 'bg-sky-500 text-white' : 'text-slate-600' }}">Lista</button>
            </div>
            @if ($staff) <button wire:click="nuovo" class="btn-primary">+ Nuovo turno</button> @endif
        </div>
    </div>

    @if ($vista === 'calendario')
        <div class="flex items-center gap-2">
            <button wire:click="cambiaMese(-1)" class="btn-soft !px-3">←</button>
            <span class="min-w-40 text-center text-base font-bold capitalize text-slate-800">{{ $calendario['primo']->translatedFormat('F Y') }}</span>
            <button wire:click="cambiaMese(1)" class="btn-soft !px-3">→</button>
            <button wire:click="oggi" class="btn-soft">Oggi</button>
        </div>
        <div class="card overflow-hidden">
            <div class="grid grid-cols-7 border-b border-sky-100 bg-sky-50/60 text-center">
                @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'] as $g)<div class="py-2 text-xs font-semibold uppercase text-slate-500">{{ $g }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7">
                @for ($d = $calendario['da']->copy(); $d->lte($calendario['a']); $d->addDay())
                    @php($k = $d->toDateString())
                    @php($giorno = $calendario['turni']->get($k, collect()))
                    <div wire:key="g{{ $k }}" class="group min-h-24 border-b border-r border-sky-50 p-1 sm:min-h-32 sm:p-1.5 {{ $d->month !== $calendario['primo']->month ? 'bg-slate-50/60' : '' }}">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold {{ $d->isToday() ? 'bg-sky-500 text-white' : ($d->month !== $calendario['primo']->month ? 'text-slate-400' : 'text-slate-700') }}">{{ $d->day }}</span>
                            @if ($staff)<button wire:click="nuovoGiorno('{{ $k }}')" class="hidden h-5 w-5 rounded text-sky-600 hover:bg-sky-100 group-hover:block" title="Aggiungi turno">+</button>@endif
                        </div>
                        <div class="space-y-1">
                            @foreach ($giorno->take(3) as $t)
                                @php($c = ['programmato' => 'bg-sky-100 text-sky-800', 'in_corso' => 'bg-emerald-100 text-emerald-800', 'completato' => 'bg-teal-100 text-teal-800', 'non_timbrato' => 'bg-amber-100 text-amber-800', 'non_completato' => 'bg-orange-100 text-orange-800', 'non_eseguito' => 'bg-rose-100 text-rose-800'][$t->statoEffettivo()])
                                <div class="truncate rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-tight {{ $c }}" title="{{ $t->sede->descrizione }} · {{ $t->operatore->nome_completo }}">
                                    <span class="font-bold">{{ $t->inizio->format('H:i') }}</span> <span class="hidden sm:inline">{{ $t->operatore->cognome ?: $t->operatore->name }}</span>
                                </div>
                            @endforeach
                            @if ($giorno->count() > 3)<p class="px-1 text-[11px] font-semibold text-sky-600">+{{ $giorno->count() - 3 }} altri</p>@endif
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    @endif

    @if ($vista === 'lista')
    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-sky-100">
            <thead class="bg-sky-50/60"><tr><th class="th">Data</th><th class="th">Orario</th><th class="th">Sede</th><th class="th">Operatore</th><th class="th">Ore</th><th class="th">Stato</th>@if ($staff)<th class="th"></th>@endif</tr></thead>
            <tbody class="divide-y divide-sky-50">
                @forelse ($turni as $t)
                    @php([$et, $cl] = \App\Models\Turno::etichette()[$t->statoEffettivo()])
                    <tr wire:key="t{{ $t->id }}" class="hover:bg-sky-50/40">
                        <td class="td whitespace-nowrap font-medium text-slate-800">{{ $t->inizio->translatedFormat('D d/m/Y') }}</td>
                        <td class="td whitespace-nowrap">{{ $t->inizio->format('H:i') }}–{{ $t->fine->format('H:i') }}</td>
                        <td class="td"><p>{{ $t->sede->descrizione }}</p><p class="text-xs text-slate-500">{{ $t->sede->cliente->ragione_sociale }}</p></td>
                        <td class="td">{{ $t->operatore->nome_completo }}</td>
                        <td class="td">{{ $t->ore }}@if ($t->ore_lavorate !== null) <span class="text-xs text-slate-500">({{ $t->ore_lavorate }} lav.)</span>@endif</td>
                        <td class="td"><span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $cl }}">{{ $et }}</span></td>
                        @if ($staff)
                            <td class="td whitespace-nowrap text-right">
                                @if ($t->stato !== \App\Enums\StatoTurno::Completato)
                                    <button wire:click="apriChiudi({{ $t->id }})" wire:confirm="Registrare manualmente {{ $t->stato === \App\Enums\StatoTurno::Programmato ? 'l\'inizio' : 'la fine' }} del turno?" class="btn-soft !px-3 !py-1.5">{{ $t->stato === \App\Enums\StatoTurno::Programmato ? 'Apri' : 'Chiudi' }}</button>
                                @endif
                                <button wire:click="elimina({{ $t->id }})" wire:confirm="Eliminare il turno?" class="btn-danger !px-3 !py-1.5">Elimina</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="td py-10 text-center text-slate-500">Nessun turno nel periodo selezionato.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $turni->links() }}
    @endif

    @if ($modal)
        <x-ui.modal title="Nuovo turno" wide>
            <form wire:submit="salva" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Cliente" name="f.cliente_id"><select wire:model.live="f.cliente_id" class="input"><option value="">—</option>@foreach ($clienti as $c)<option value="{{ $c->id }}">{{ $c->ragione_sociale }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Sede" name="f.sede_id"><select wire:model="f.sede_id" class="input"><option value="">—</option>@foreach ($sediForm as $s)<option value="{{ $s->id }}">{{ $s->descrizione }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Data inizio" name="f.data_inizio"><input type="date" wire:model.live="f.data_inizio" class="input"></x-ui.field>
                <x-ui.field label="Ora inizio" name="f.ora_inizio"><input type="time" wire:model.live="f.ora_inizio" class="input"></x-ui.field>
                <x-ui.field label="Data fine" name="f.data_fine"><input type="date" wire:model.live="f.data_fine" class="input"></x-ui.field>
                <x-ui.field label="Ora fine" name="f.ora_fine"><input type="time" wire:model.live="f.ora_fine" class="input"></x-ui.field>
                <x-ui.field label="Operatore (solo disponibili)" name="f.operatore_id" class="sm:col-span-2">
                    <select wire:model="f.operatore_id" class="input"><option value="">—</option>@foreach ($disponibili as $o)<option value="{{ $o->id }}">{{ $o->preferito ? '★ ' : '' }}{{ $o->nome_completo }}</option>@endforeach</select>
                </x-ui.field>
                <div class="rounded-2xl bg-sky-50 p-4 sm:col-span-2">
                    <p class="mb-3 text-sm font-semibold text-slate-700">Ripeti turno (facoltativo)</p>
                    <div class="mb-3 flex flex-wrap gap-2">
                        @foreach ($giorniSettimana as $n => $g)
                            <label class="cursor-pointer"><input type="checkbox" wire:model="giorni" value="{{ $n }}" class="peer sr-only"><span class="block rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-slate-600 ring-1 ring-sky-200 peer-checked:bg-sky-500 peer-checked:text-white">{{ $g }}</span></label>
                        @endforeach
                    </div>
                    <x-ui.field label="Fino al" name="f.ripeti_fino"><input type="date" wire:model="f.ripeti_fino" class="input sm:max-w-xs"></x-ui.field>
                </div>
                <x-ui.field label="Note" name="f.note" class="sm:col-span-2"><textarea wire:model="f.note" rows="2" class="input"></textarea></x-ui.field>
                @if ($esito) <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 sm:col-span-2">{{ $esito }}</p> @endif
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Chiudi</button>
                    <button class="btn-primary">Crea turno</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
