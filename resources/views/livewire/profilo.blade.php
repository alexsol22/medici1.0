<div class="mx-auto max-w-3xl space-y-6">
    <form wire:submit="salvaProfilo" class="card space-y-5 p-4 sm:p-6">
        <h2 class="text-lg font-bold text-slate-800">Dati profilo</h2>

        @if (session('profilo'))
            <p class="rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">{{ session('profilo') }}</p>
        @endif

        <div class="flex flex-col items-center gap-4 sm:flex-row">
            <x-ui.avatar :user="$utente" :preview="$foto" class="h-20 w-20 text-2xl" />
            <div class="w-full min-w-0 space-y-2 text-center sm:text-left">
                <input type="file" wire:model="foto" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100">
                <p class="text-xs text-slate-400">JPG, PNG o WebP, massimo 2 MB. Facoltativa.</p>
                @error('foto') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                @if ($utente->foto_path)
                    <button type="button" wire:click="rimuoviFoto" wire:confirm="Rimuovere la foto profilo?" class="btn-danger !px-3 !py-1.5">Rimuovi foto</button>
                @endif
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Nome" name="name"><input wire:model="name" class="input" autocomplete="given-name"></x-ui.field>
            <x-ui.field label="Cognome" name="cognome"><input wire:model="cognome" class="input" autocomplete="family-name"></x-ui.field>
            <x-ui.field label="Email (login)" name="email" class="sm:col-span-2"><input type="email" wire:model="email" class="input" autocomplete="email"></x-ui.field>
            <x-ui.field label="Cellulare" name="cellulare"><input wire:model="cellulare" class="input" autocomplete="tel"></x-ui.field>
            <x-ui.field label="Ruolo" name="ruolo"><input value="{{ $utente->ruolo->label() }}" class="input bg-slate-50" disabled></x-ui.field>
        </div>

        <div class="flex justify-end">
            <button class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="foto,salvaProfilo">Salva profilo</button>
        </div>
    </form>

    <form wire:submit="cambiaPassword" class="card space-y-5 p-4 sm:p-6">
        <h2 class="text-lg font-bold text-slate-800">Cambia password</h2>

        @if (session('password'))
            <p class="rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">{{ session('password') }}</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Password attuale" name="password_attuale" class="sm:col-span-2"><input type="password" wire:model="password_attuale" class="input" autocomplete="current-password"></x-ui.field>
            <x-ui.field label="Nuova password" name="password"><input type="password" wire:model="password" class="input" autocomplete="new-password"></x-ui.field>
            <x-ui.field label="Conferma nuova password" name="password_confirmation"><input type="password" wire:model="password_confirmation" class="input" autocomplete="new-password"></x-ui.field>
        </div>

        <div class="flex justify-end">
            <button class="btn-primary w-full sm:w-auto">Aggiorna password</button>
        </div>
    </form>
</div>
