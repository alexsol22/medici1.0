<div class="mx-auto max-w-md space-y-4" x-data="scanner">

    <div class="card overflow-hidden">
        @if ($turno)
            @php $inCorso = $turno->stato === \App\Enums\StatoTurno::Iniziato; @endphp
            <div class="p-6 text-center">
                <span class="inline-block rounded-full px-4 py-1 text-sm font-bold uppercase tracking-wide {{ $inCorso ? 'bg-orange-100 text-orange-700' : 'bg-emerald-100 text-emerald-700' }}">
                    {{ $inCorso ? 'Uscita' : 'Ingresso' }}
                </span>
                <p class="mt-4 text-4xl font-extrabold tracking-tight text-slate-800">{{ $turno->inizio->format('H:i') }} – {{ $turno->fine->format('H:i') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $turno->inizio->translatedFormat('l d F') }} · {{ $turno->sede->descrizione }}</p>
            </div>
        @else
            <div class="p-6 text-center">
                <p class="text-lg font-semibold text-slate-700">Nessun turno attivo</p>
                <p class="mt-1 text-sm text-slate-500">Puoi timbrare da 15 minuti prima dell'inizio.</p>
            </div>
        @endif

        <div class="space-y-3 border-t border-sky-100 p-5">
            <div x-show="camera" x-cloak class="relative overflow-hidden rounded-2xl bg-slate-900">
                <video x-ref="video" playsinline muted class="aspect-square w-full object-cover"></video>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="h-2/3 w-2/3 rounded-3xl border-4 border-white/80 shadow-[0_0_0_999px_rgba(15,23,42,.45)]"></div>
                </div>
            </div>

            <div x-show="errore" x-cloak class="rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200" x-text="errore"></div>

            <button type="button" class="btn-primary w-full !py-4 text-base" x-show="!camera" @click="avvia()" :disabled="caricamento || !{{ $turno ? 'true' : 'false' }}">
                <span x-show="!caricamento" class="inline-flex items-center justify-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2M7 12h10"/></svg> Scansiona il QR</span>
                <span x-show="caricamento" x-cloak>Invio in corso…</span>
            </button>
            <button type="button" class="btn-soft w-full" x-show="camera" x-cloak @click="ferma()">Annulla</button>

            <p class="text-center text-xs" x-show="gps !== 'ok'" x-cloak :class="gps === 'attesa' ? 'text-slate-400' : 'text-rose-600'">
                <span x-show="gps === 'attesa'">Rilevamento posizione…</span>
                <span x-show="gps === 'negato' || gps === 'assente'">Posizione non disponibile: consenti il GPS. <button type="button" class="font-medium underline" @click="posizione()">Riprova</button></span>
            </p>
        </div>
    </div>

    @if ($risultato)
        <div class="rounded-2xl p-4 text-sm font-medium ring-1 {{ $risultato['ok'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-rose-50 text-rose-800 ring-rose-200' }}">
            {{ $risultato['ok'] ? '✅' : '⚠️' }}
            @if ($risultato['ok'] && isset($risultato['tipo']))<span class="mr-1 rounded-md bg-white/70 px-2 py-0.5 text-xs font-bold uppercase">{{ ['I' => 'Ingresso', 'F' => 'Uscita', 'P' => 'Passaggio'][$risultato['tipo']] ?? '' }}</span>@endif
            {{ $risultato['message'] }}
        </div>
    @endif

    @if ($turno)
        @php
            $ingresso = $presenze->firstWhere('tipo', \App\Models\Presenza::INIZIO);
            $uscita = $presenze->firstWhere('tipo', \App\Models\Presenza::FINE);
            $passaggi = $presenze->where('tipo', \App\Models\Presenza::PASSAGGIO);
            $stato = fn ($m) => $m === null ? '' : ($m === 0 ? 'Puntuale' : ($m > 0 ? '+'.$m.' min' : $m.' min'));
        @endphp
        <div class="card p-5">
            <p class="mb-4 font-semibold text-slate-800">Timeline del turno</p>
            <ol class="relative space-y-6 border-l-2 border-sky-100 pl-6">
                <li class="relative">
                    <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full text-xs text-white ring-4 ring-white {{ $ingresso ? 'bg-emerald-500' : 'bg-slate-300' }}">{{ $ingresso ? '✓' : '1' }}</span>
                    <p class="text-sm font-semibold text-slate-800">Ingresso</p>
                    <p class="text-xs text-slate-500">Previsto {{ $turno->inizio->format('H:i') }}</p>
                    @if ($ingresso)
                        <p class="mt-1 text-sm text-emerald-700">Timbrato alle {{ $ingresso->rilevata_at->format('H:i') }} · {{ $stato($ingresso->ritardo_minuti) }}</p>
                    @else
                        <p class="mt-1 text-sm text-slate-400">In attesa di timbratura</p>
                    @endif
                </li>
                @foreach ($passaggi as $p)
                    <li class="relative">
                        <span class="absolute -left-[29px] h-4 w-4 rounded-full bg-sky-400 ring-4 ring-white"></span>
                        <p class="text-sm font-medium text-slate-700">{{ $p->postazione?->titolo ?? 'Passaggio' }}</p>
                        <p class="text-xs text-slate-500">{{ $p->rilevata_at->format('H:i') }}@if ($p->ritardo_minuti !== null) · {{ $stato($p->ritardo_minuti) }}@endif</p>
                    </li>
                @endforeach
                <li class="relative">
                    <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full text-xs text-white ring-4 ring-white {{ $uscita ? 'bg-emerald-500' : 'bg-slate-300' }}">{{ $uscita ? '✓' : '2' }}</span>
                    <p class="text-sm font-semibold text-slate-800">Uscita</p>
                    <p class="text-xs text-slate-500">Prevista {{ $turno->fine->format('H:i') }}</p>
                    @if ($uscita)
                        <p class="mt-1 text-sm text-emerald-700">Timbrata alle {{ $uscita->rilevata_at->format('H:i') }} · {{ $stato($uscita->ritardo_minuti) }}</p>
                    @else
                        <p class="mt-1 text-sm text-slate-400">{{ $ingresso ? 'Turno in corso' : 'Dopo l\'ingresso' }}</p>
                    @endif
                </li>
            </ol>
        </div>
    @endif

    <div class="card">
        <div class="border-b border-sky-100 px-5 py-4 font-semibold text-slate-800">Prossimi turni</div>
        <ul class="divide-y divide-sky-50">
            @forelse ($prossimi as $p)
                <li class="px-5 py-3 text-sm"><p class="font-medium text-slate-800">{{ $p->sede->descrizione }}</p><p class="text-xs text-slate-500">{{ $p->inizio->translatedFormat('D d/m') }} · {{ $p->inizio->format('H:i') }}–{{ $p->fine->format('H:i') }}</p></li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Nessun turno in programma.</li>
            @endforelse
        </ul>
    </div>
</div>
