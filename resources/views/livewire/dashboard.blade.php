<div class="space-y-6">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-sky-700">Panoramica amministrativa</p>
            <p class="mt-1 text-sm text-slate-500">Un riepilogo aggiornato delle attività e delle risorse gestite.</p>
        </div>
        <p class="text-sm text-slate-500">{{ now()->format('d/m/Y') }}</p>
    </div>

    <section aria-label="Riepilogo" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($metriche as $metrica)
            <a href="{{ route($metrica['destinazione']) }}" wire:navigate class="card group flex items-center justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div>
                    <p class="text-sm font-medium text-slate-500">{{ $metrica['nome'] }}</p>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-slate-800">{{ number_format($metrica['valore'], 0, ',', '.') }}</p>
                    <span class="mt-3 inline-flex text-xs font-semibold text-sky-700 group-hover:text-sky-800">Apri sezione <span aria-hidden="true" class="ml-1">→</span></span>
                </div>
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                    @if ($metrica['destinazione'] === 'clienti')
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                    @elseif ($metrica['destinazione'] === 'sedi')
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="2.5"/></svg>
                    @elseif ($metrica['destinazione'] === 'operatori')
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m16 0v-2a4 4 0 00-3-3.87M14 3.13a4 4 0 010 7.75"/><circle cx="10" cy="7" r="4"/></svg>
                    @else
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 3v4M8 3v4M3 11h18"/></svg>
                    @endif
                </span>
            </a>
        @endforeach
    </section>

    <section class="card overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-sky-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-800">Prossimi turni</h2>
                <p class="mt-0.5 text-sm text-slate-500">I prossimi servizi programmati per i clienti visibili.</p>
            </div>
            <a href="{{ route('turni') }}" wire:navigate class="text-sm font-semibold text-sky-700 hover:text-sky-800">Vai ai turni <span aria-hidden="true">→</span></a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-sky-100">
                <thead class="bg-sky-50/60">
                    <tr><th class="th">Data e ora</th><th class="th">Cliente e sede</th><th class="th">Operatore</th></tr>
                </thead>
                <tbody class="divide-y divide-sky-50">
                    @forelse ($prossimiTurni as $turno)
                        <tr wire:key="dashboard-turno-{{ $turno->id }}" class="hover:bg-sky-50/40">
                            <td class="td whitespace-nowrap">
                                <p class="font-semibold text-slate-800">{{ $turno->inizio->format('d/m/Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $turno->inizio->format('H:i') }}–{{ $turno->fine->format('H:i') }}</p>
                            </td>
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $turno->cliente->ragione_sociale }}</p>
                                <p class="text-xs text-slate-500">{{ $turno->sede->descrizione }}</p>
                            </td>
                            <td class="td">{{ $turno->operatore->nome_completo }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="td py-10 text-center text-slate-500">Non ci sono turni futuri programmati.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
