@props(['label', 'model', 'options', 'tone' => 'sky'])

<div
    wire:ignore.self
    x-data="{
        selected: $wire.$entangle('{{ $model }}'),
        options: @js($options),
        q: '',
        open: false,
        get chosen() { return this.options.filter(o => this.selected.includes(o.id)); },
        get found() {
            const q = this.q.toLowerCase();
            return this.options.filter(o => !this.selected.includes(o.id) && o.nome.toLowerCase().includes(q));
        },
        add(id) { this.selected = [...this.selected, id]; this.q = ''; },
        remove(id) { this.selected = this.selected.filter(i => i !== id); },
    }"
    @click.outside="open = false"
    class="relative rounded-2xl p-4 ring-1 ring-sky-100"
>
    <div class="mb-3 flex items-center justify-between">
        <p class="label !mb-0">{{ $label }}</p>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $tone === 'rose' ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700' }}" x-text="selected.length"></span>
    </div>

    <input type="search" x-model="q" @focus="open = true" @input="open = true" placeholder="Cerca operatore da aggiungere…" class="input">

    <div x-show="open" x-cloak class="absolute inset-x-4 z-20 mt-1 max-h-56 overflow-y-auto rounded-2xl bg-white p-1 shadow-xl ring-1 ring-sky-100">
        <template x-for="o in found" :key="o.id">
            <button type="button" @click="add(o.id)" class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm hover:bg-sky-50">
                <span x-text="o.nome"></span><span class="text-sky-500">+</span>
            </button>
        </template>
        <p x-show="found.length === 0" class="px-3 py-3 text-center text-sm text-slate-400">Nessun operatore trovato.</p>
    </div>

    <div class="mt-3 flex min-h-12 flex-wrap gap-2">
        <template x-for="o in chosen" :key="o.id">
            <span class="inline-flex items-center gap-1.5 rounded-full py-1 pl-3 pr-1.5 text-sm font-medium {{ $tone === 'rose' ? 'bg-rose-50 text-rose-800 ring-1 ring-rose-200' : 'bg-sky-50 text-sky-800 ring-1 ring-sky-200' }}">
                <span x-text="o.nome"></span>
                <button type="button" @click="remove(o.id)" class="flex size-5 items-center justify-center rounded-full bg-white/70 text-xs hover:bg-white" aria-label="Rimuovi">✕</button>
            </span>
        </template>
        <p x-show="chosen.length === 0" class="text-sm text-slate-400">Nessun operatore selezionato.</p>
    </div>
</div>
