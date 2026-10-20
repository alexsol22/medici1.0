<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if (auth()->user()->isStaff())
            <select wire:model.live="filtroTipo" class="input sm:max-w-[12rem]"><option value="">Tutti i documenti</option><option value="cliente">Clienti</option><option value="operatore">Operatori</option></select>
            <button wire:click="nuovo" class="btn-primary">+ Carica documento</button>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($documenti as $d)
            <div wire:key="d{{ $d->id }}" class="card flex flex-col p-5">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold text-slate-800">{{ $d->titolo }}</h3>
                        <p class="text-xs text-slate-500">{{ $d->tipo === 'cliente' ? $d->cliente?->ragione_sociale : $d->user?->nome_completo }}</p>
                    </div>
                    @if ($d->scadenza)
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $d->scadenza->isPast() ? 'bg-rose-100 text-rose-700' : ($d->inScadenza() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">{{ $d->scadenza->format('d/m/Y') }}</span>
                    @endif
                </div>
                <div class="mt-4 flex gap-2">
                    <a href="{{ route('documenti.scarica', $d) }}" class="btn-soft !px-3 !py-1.5">Scarica</a>
                    @if (auth()->user()->isStaff())<button wire:click="elimina({{ $d->id }})" wire:confirm="Eliminare il documento?" class="btn-danger !px-3 !py-1.5">Elimina</button>@endif
                </div>
            </div>
        @empty
            <p class="card col-span-full p-10 text-center text-sm text-slate-500">Nessun documento disponibile.</p>
        @endforelse
    </div>
    {{ $documenti->links() }}

    @if ($modal)
        <x-ui.modal title="Carica documento">
            <form wire:submit="salva" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Titolo" name="f.titolo" class="sm:col-span-2"><input wire:model="f.titolo" class="input"></x-ui.field>
                <x-ui.field label="Tipo" name="f.tipo"><select wire:model.live="f.tipo" class="input"><option value="cliente">Cliente</option><option value="operatore">Operatore</option></select></x-ui.field>
                @if ($f['tipo'] === 'cliente')
                    <x-ui.field label="Cliente" name="f.cliente_id"><select wire:model="f.cliente_id" class="input"><option value="">—</option>@foreach ($clienti as $c)<option value="{{ $c->id }}">{{ $c->ragione_sociale }}</option>@endforeach</select></x-ui.field>
                @else
                    <x-ui.field label="Operatore" name="f.user_id"><select wire:model="f.user_id" class="input"><option value="">—</option>@foreach ($operatori as $o)<option value="{{ $o->id }}">{{ $o->nome_completo }}</option>@endforeach</select></x-ui.field>
                @endif
                <x-ui.field label="Scadenza" name="f.scadenza"><input type="date" wire:model="f.scadenza" class="input"></x-ui.field>
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-600"><input type="checkbox" wire:model="f.formazione" class="rounded border-sky-300 text-sky-500"> Formazione</label>
                <x-ui.field label="File (max 10 MB)" name="file" class="sm:col-span-2"><input type="file" wire:model="file" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:font-semibold file:text-sky-700"></x-ui.field>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Annulla</button>
                    <button class="btn-primary" wire:loading.attr="disabled" wire:target="file">Salva</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
