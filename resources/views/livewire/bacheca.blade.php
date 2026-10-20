<div class="mx-auto max-w-3xl space-y-5">
    @if (auth()->user()->isStaff())
        <div class="flex justify-end"><button wire:click="nuovo" class="btn-primary">+ Nuovo avviso</button></div>
    @endif

    @forelse ($avvisi as $a)
        <article wire:key="a{{ $a->id }}" class="card p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">{{ $a->titolo }}</h3>
                    <p class="text-xs text-slate-500">{{ $a->created_at->translatedFormat('d F Y, H:i') }}</p>
                </div>
                @if (auth()->user()->isOperatore())
                    @if (! $a->letto_at)<span class="rounded-full bg-sky-500 px-2.5 py-1 text-xs font-semibold text-white">Nuovo</span>@endif
                @else
                    <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $a->letti_count }}/{{ $a->destinatari_count }} letti</span>
                @endif
            </div>
            <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $a->testo }}</p>
            <div class="mt-4 flex gap-2">
                @if (auth()->user()->isOperatore() && ! $a->letto_at)<button wire:click="segnaLetto({{ $a->id }})" class="btn-soft !px-3 !py-1.5">Segna come letto</button>@endif
                @if (auth()->user()->isStaff())<button wire:click="elimina({{ $a->id }})" wire:confirm="Eliminare l'avviso?" class="btn-danger !px-3 !py-1.5">Elimina</button>@endif
            </div>
        </article>
    @empty
        <p class="card p-10 text-center text-sm text-slate-500">Nessun avviso in bacheca.</p>
    @endforelse

    @if ($modal)
        <x-ui.modal title="Nuovo avviso">
            <form wire:submit="salva" class="space-y-4">
                <x-ui.field label="Titolo" name="f.titolo"><input wire:model="f.titolo" class="input"></x-ui.field>
                <x-ui.field label="Testo" name="f.testo"><textarea wire:model="f.testo" rows="5" class="input"></textarea></x-ui.field>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model.live="f.per_tutti" class="rounded border-sky-300 text-sky-500"> Invia a tutti gli operatori</label>
                @unless ($f['per_tutti'])
                    <div class="max-h-40 space-y-1 overflow-y-auto rounded-xl bg-sky-50 p-3">
                        @foreach ($operatori as $o)<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="destinatari" value="{{ $o->id }}" class="rounded border-sky-300 text-sky-500"> {{ $o->nome_completo }}</label>@endforeach
                    </div>
                    @error('destinatari') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                @endunless
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('modal', false)" class="btn-soft">Annulla</button>
                    <button class="btn-primary">Pubblica</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
