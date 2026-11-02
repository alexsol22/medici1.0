<div class="mx-auto max-w-5xl space-y-8">
<div class="grid gap-6 {{ $bloccato ? 'md:grid-cols-2' : 'lg:grid-cols-2' }}"
     x-data="{
        scarica(tipo) {
            const svg = this.$refs.anteprima.querySelector('svg');
            const xml = new XMLSerializer().serializeToString(svg);
            const nome = ($wire.titolo || 'qrcode').toLowerCase().replace(/[^a-z0-9]+/g, '-');
            if (tipo === 'svg') {
                this.salva(new Blob([xml], { type: 'image/svg+xml' }), nome + '.svg');
                return;
            }
            const img = new Image();
            img.onload = () => {
                const k = 3, c = document.createElement('canvas');
                c.width = svg.width.baseVal.value * k; c.height = svg.height.baseVal.value * k;
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
                ctx.drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(b => this.salva(b, nome + '.png'), 'image/png');
            };
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
        },
        salva(blob, nome) {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob); a.download = nome; a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        }
     }">
    <div class="{{ $bloccato ? 'space-y-4' : 'card space-y-4 p-5 sm:p-6' }}">
        @unless ($bloccato)<h2 class="text-lg font-bold text-slate-800">Crea il tuo QR code</h2>@endunless
        <x-ui.field label="Titolo sotto il QR" name="titolo"><input wire:model.live.debounce.400ms="titolo" maxlength="40" class="input"></x-ui.field>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Colore" name="colore">
                <div class="flex items-center gap-2"><input type="color" wire:model.live="colore" class="h-10 w-14 cursor-pointer rounded-lg border-0 bg-white p-1 ring-1 ring-sky-200"><span class="font-mono text-sm text-slate-500">{{ $colore }}</span></div>
            </x-ui.field>
            <x-ui.field label="Logo aziendale (PNG/JPG, max 2 MB)" name="logo">
                <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:font-semibold file:text-sky-700">
            </x-ui.field>
        </div>
        @if ($logo)
            <button type="button" wire:click="rimuoviLogo" class="btn-danger !py-1.5">Rimuovi logo</button>
        @endif
        @unless ($bloccato)
        <div class="rounded-2xl bg-sky-50 p-4">
            <p class="mb-3 text-sm font-semibold text-slate-700">Associa a (facoltativo)</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Cliente</label><select wire:model.live="clienteId" class="input"><option value="">—</option>@foreach ($clienti as $c)<option value="{{ $c->id }}">{{ $c->ragione_sociale }}</option>@endforeach</select></div>
                <div><label class="label">Sede</label><select wire:model.live="sedeId" class="input" @disabled(! $clienteId)><option value="">—</option>@foreach ($sedi as $s)<option value="{{ $s->id }}">{{ $s->descrizione }}</option>@endforeach</select></div>
            </div>
            <p class="mt-3 text-xs text-slate-500">{{ $sedeId ? 'Il QR contiene il codice di timbratura della sede.' : 'Senza sede il QR contiene il testo del titolo.' }}</p>
        </div>
        @endunless
        <p class="text-xs text-slate-500">Il logo è al centro: il QR usa la massima correzione degli errori, ma verifica sempre la lettura con il telefono prima di stampare.</p>
    </div>

    <div class="{{ $bloccato ? '' : 'card p-5 sm:p-6' }} flex flex-col items-center gap-4">
        <div x-ref="anteprima" class="w-full max-w-sm overflow-hidden rounded-2xl ring-1 ring-sky-100 [&_svg]:h-auto [&_svg]:w-full" wire:loading.class="opacity-50">{!! $svg !!}</div>
        <div class="flex gap-2">
            <button type="button" @click="scarica('png')" class="btn-primary">Scarica PNG</button>
            <button type="button" @click="scarica('svg')" class="btn-soft">Scarica SVG</button>
        </div>
    </div>
</div>
