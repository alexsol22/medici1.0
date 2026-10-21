@props(['title', 'close' => 'chiudi', 'wide' => false])
<div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 backdrop-blur-sm sm:items-center sm:p-4" x-data @keydown.escape.window="$wire.set('modal', false)">
    <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-3xl sm:p-6 {{ $wide ? 'sm:max-w-3xl' : 'sm:max-w-xl' }}" @mousedown.outside="$wire.set('modal', false)">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">{{ $title }}</h3>
            <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-sky-50 hover:text-slate-600" @click="$wire.set('modal', false)">✕</button>
        </div>
        {{ $slot }}
    </div>
</div>
