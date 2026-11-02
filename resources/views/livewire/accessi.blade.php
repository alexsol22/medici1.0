<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-800">Log accessi utenti</h1>
        <p class="mt-1 text-sm text-slate-500">Elenco degli accessi effettuati dagli utenti.</p>
    </div>

    <section aria-label="Registro accessi" class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-sky-100">
                <thead class="bg-sky-50/60">
                    <tr>
                        <th class="th">Nome completo</th>
                        <th class="th">Email</th>
                        <th class="th">Ora accesso</th>
                        <th class="th">Indirizzo IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sky-50">
                    @forelse ($accessi as $accesso)
                        <tr wire:key="accesso-{{ $accesso->id }}" class="hover:bg-sky-50/40">
                            <td class="td font-semibold text-slate-800">{{ $accesso->utente?->nome_completo ?? '—' }}</td>
                            <td class="td">{{ $accesso->utente?->user }}</td>
                            <td class="td whitespace-nowrap">{{ $accesso->data_view?->format('d/m/Y H:i') }}</td>
                            <td class="td">{{ $accesso->addr ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="td text-center text-slate-500">Nessun accesso registrato.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{ $accessi->links() }}
</div>
