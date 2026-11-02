<div class="mx-auto max-w-4xl space-y-5">
    @if (session('impostazioni'))
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">{{ session('impostazioni') }}</p>
    @endif

    <form wire:submit="salva" class="card space-y-5 p-5">
        <p class="text-sm text-slate-500">Dati della società e dell'applicazione (tabella <code>tc_config</code>), condivisi con l'app originale.</p>
        <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nome applicazione" name="f.app"><input type="text" wire:model="f.app" class="input"></x-ui.field>
                <x-ui.field label="Ragione sociale" name="f.ragione_sociale"><input type="text" wire:model="f.ragione_sociale" class="input"></x-ui.field>
                <x-ui.field label="Partita IVA" name="f.societa_iva"><input type="text" wire:model="f.societa_iva" class="input"></x-ui.field>
                <x-ui.field label="Indirizzo" name="f.societa_indirizzo"><input type="text" wire:model="f.societa_indirizzo" class="input"></x-ui.field>
                <x-ui.field label="Comune" name="f.societa_comune"><input type="text" wire:model="f.societa_comune" class="input"></x-ui.field>
                <x-ui.field label="CAP" name="f.societa_cap"><input type="text" wire:model="f.societa_cap" class="input"></x-ui.field>
                <x-ui.field label="Provincia" name="f.societa_provincia"><input type="text" wire:model="f.societa_provincia" class="input"></x-ui.field>
                <x-ui.field label="Email società" name="f.societa_email"><input type="email" wire:model="f.societa_email" class="input"></x-ui.field>
                <x-ui.field label="Telefono società" name="f.societa_telefono"><input type="text" wire:model="f.societa_telefono" class="input"></x-ui.field>
                <x-ui.field label="Email di contatto" name="f.email"><input type="email" wire:model="f.email" class="input"></x-ui.field>
                <x-ui.field label="Telefono di contatto" name="f.phone"><input type="text" wire:model="f.phone" class="input"></x-ui.field>
                <x-ui.field label="Colore (#rrggbb)" name="f.colore"><input type="text" wire:model="f.colore" class="input"></x-ui.field>
                <x-ui.field label="Colore primario (#rrggbb)" name="f.colore_primario"><input type="text" wire:model="f.colore_primario" class="input"></x-ui.field>
        </div>
        <div class="flex justify-end"><button class="btn-primary">Salva</button></div>
    </form>
</div>
