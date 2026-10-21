<div class="mx-auto max-w-5xl space-y-6">
    <div class="card border-sky-100 bg-sky-50/70 p-4 text-sm text-sky-900 sm:p-5">
        Le credenziali sono globali e visibili solo agli amministratori. I valori vengono cifrati nel database;
        per sicurezza non vengono mostrati dopo il salvataggio. Lascia un campo vuoto per mantenere il valore esistente.
        La configurazione delle credenziali non attiva automaticamente l’integrazione nei servizi.
    </div>

    @if (session('impostazioni'))
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">{{ session('impostazioni') }}</p>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead class="bg-sky-50/70 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Integrazione</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Configurazione</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Stato</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sky-100">
                    @foreach ($gruppi as $id => $gruppo)
                        <tr wire:key="integrazione-{{ $id }}" class="align-top">
                            <th scope="row" class="min-w-48 px-5 py-4">
                                <p class="font-semibold text-slate-800">{{ $gruppo['nome'] }}</p>
                                <p class="mt-1 max-w-xs text-xs font-normal leading-5 text-slate-500">{{ $gruppo['descrizione'] }}</p>
                            </th>
                            <td class="min-w-[32rem] px-5 py-4">
                                <form id="form-impostazioni-{{ $id }}" wire:submit="salva('{{ $id }}')">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @foreach ($gruppo['campi'] as $campo)
                                            <label wire:key="campo-{{ $campo['chiave'] }}" class="block space-y-1.5">
                                                <span class="text-xs font-medium text-slate-700">{{ $campo['etichetta'] }}</span>
                                                <input
                                                    type="{{ $campo['tipo'] === 'password' ? 'password' : 'text' }}"
                                                    wire:model="dati.{{ $campo['chiave'] }}"
                                                    placeholder="{{ $campo['placeholder'] }}"
                                                    autocomplete="{{ $campo['tipo'] === 'password' ? 'new-password' : 'off' }}"
                                                    class="input"
                                                >
                                                @error('dati.'.$campo['chiave'])
                                                    <span class="block text-xs text-rose-600">{{ $message }}</span>
                                                @enderror
                                            </label>
                                        @endforeach
                                    </div>
                                </form>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-50 text-emerald-700' => $gruppo['configurata'],
                                    'bg-slate-100 text-slate-600' => ! $gruppo['configurata'],
                                ])>
                                    {{ $gruppo['configurata'] ? 'Configurata' : 'Da configurare' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex flex-col items-start gap-2">
                                    <button type="submit" form="form-impostazioni-{{ $id }}" wire:loading.attr="disabled" wire:target="salva" class="btn-primary !px-3 !py-1.5">
                                        Salva
                                    </button>
                                    @if ($gruppo['configurata'])
                                        <button type="button" wire:click="rimuovi('{{ $id }}')" wire:confirm="Rimuovere tutte le credenziali di {{ $gruppo['nome'] }}?" class="btn-danger !px-3 !py-1.5">
                                            Rimuovi
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
