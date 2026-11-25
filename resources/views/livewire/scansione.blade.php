<div class="mx-auto w-full max-w-xl" x-data="scanner">
    <div class="space-y-4">
        <div class="card overflow-hidden">
        <div x-show="!camera && !codice">
        <div x-show="sostituzione" x-cloak class="space-y-2 px-4 py-6 text-left">
            <p class="text-xl font-semibold text-slate-800">Sostituisci turno</p>
            <p class="text-base text-slate-600">Inquadra il QR della sede.</p>
        </div>
        <div x-show="!sostituzione">
        @if ($turno)
            @if ($fotoSede)
                <div class="relative aspect-[16/9] max-h-64 w-full bg-sky-100">
                    <img src="{{ $fotoSede }}" alt="Foto di {{ $turno->sede->descrizione }}" class="absolute inset-0 h-full w-full object-cover">
                </div>
            @endif
            <div class="px-4 pt-4 text-left">
                @unless ($fotoSede)
                    <div class="mb-4 flex h-28 items-center justify-center rounded-2xl bg-sky-50 text-sky-300 ring-1 ring-sky-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 10h.01M12 10h.01M15 10h.01M9 13h.01M12 13h.01M15 13h.01"/>
                        </svg>
                    </div>
                @endunless
                <p class="text-sm font-medium text-slate-500">{{ $turno->sede->descrizione }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums text-slate-800">{{ $turno->inizio->format('H:i') }} – {{ $turno->fine->format('H:i') }}</p>
            </div>
        @else
            <div class="space-y-1 px-4 py-6 text-left">
                <p class="text-xl font-semibold text-slate-800">Nessun turno attivo</p>
                <p class="text-base text-slate-600">Da 15 minuti prima dell'inizio.</p>
            </div>
        @endif
        </div>
        </div>

        @unless (($risultato['ok'] ?? false) === true)
        <div class="space-y-4 p-4 sm:p-5">
            <div x-ref="inquadratura" x-show="camera" x-cloak class="relative mx-auto w-full max-w-xl overflow-hidden rounded-2xl bg-sky-50">
                <video playsinline webkit-playsinline autoplay muted class="aspect-square w-full bg-slate-900 object-cover"></video>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center" x-show="!preparazione && !letto">
                    <div class="h-2/3 w-2/3 rounded-3xl border-4 border-white/80 shadow-[0_0_0_999px_rgba(15,23,42,.45)]"></div>
                </div>
                <p x-show="preparazione" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-sky-50 px-6 text-center text-lg font-semibold text-slate-700">Attendi, apro la fotocamera…</p>
                <div x-show="letto" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-[#059669] px-6 text-center text-white">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-[#ffffff] text-3xl font-bold text-[#047857]">✓</span>
                    <span class="text-2xl font-bold">QR letto</span>
                </div>
            </div>

            <p x-show="camera && !preparazione && !letto" x-cloak class="text-left text-base font-semibold text-slate-800">Inquadra il QR</p>

            <div x-show="errore" x-cloak class="rounded-xl bg-rose-50 p-4 text-base leading-relaxed text-rose-800 ring-1 ring-rose-200" x-text="errore"></div>

            <div x-show="codice" x-cloak class="space-y-3 text-left">
                <p class="text-left text-xl font-bold text-slate-800" x-show="!sostituzione">
                    @if ($turno && $turno->stato === 'I')
                        Conferma la chiusura del turno
                    @else
                        Conferma l'inizio del turno
                    @endif
                </p>
                <p class="text-left text-xl font-bold text-slate-800" x-show="sostituzione" x-cloak>Conferma la sostituzione</p>
                <label for="nota-scansione" class="block text-sm font-medium text-slate-700">Inserisci note</label>
                <textarea id="nota-scansione" x-ref="nota" rows="3" maxlength="255" class="input" placeholder="Nota facoltativa"></textarea>
                @error('nota') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                <div class="flex gap-3">
                    <button type="button" class="btn-soft min-h-14 flex-1 text-base" @click="annulla()" :disabled="caricamento">Annulla</button>
                    <button type="button" class="btn-primary min-h-14 flex-1 text-base" @click="conferma()" :disabled="caricamento || gps !== 'ok'">
                        <span x-show="!caricamento">Invia</span>
                        <span x-show="caricamento" x-cloak>Invio in corso…</span>
                    </button>
                </div>
            </div>

            <button type="button" class="btn-primary min-h-14 w-full !px-5 !py-4 text-base sm:text-lg" x-show="!camera && !codice" @click="avvia()" :disabled="gps !== 'ok' || (!sostituzione && !{{ $turno ? 'true' : 'false' }})">
                <span class="inline-flex items-center justify-center gap-3"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2M7 12h10"/></svg>
                    <span x-show="sostituzione">Scansiona il QR della sede</span>
                    <span x-show="!sostituzione && errore" x-cloak>Riprova</span>
                    <span x-show="!sostituzione && !errore">
                        @if ($turno && $turno->stato === 'I')
                            Chiudi il turno
                        @elseif ($turno)
                            Inizia il turno
                        @else
                            Nessun turno adesso
                        @endif
                    </span>
                </span>
            </button>
            <button type="button" class="btn-soft min-h-12 w-full text-base" x-show="!camera && !codice && !sostituzione" @click="apriSostituzione()">Sostituisci turno</button>
            <button type="button" class="btn-soft min-h-12 w-full text-base" x-show="sostituzione && !camera && !codice" x-cloak @click="chiudiSostituzione()">Chiudi</button>
            <button type="button" class="btn-soft min-h-12 w-full text-base" x-show="camera && !letto" x-cloak @click="ferma()">Annulla</button>

            <p class="text-left text-sm leading-relaxed" x-show="gps !== 'ok'" x-cloak :class="gps === 'attesa' ? 'text-slate-500' : 'text-rose-700'">
                <span x-show="gps === 'attesa'">Rilevamento posizione…</span>
                <span x-show="gps === 'negato' || gps === 'assente'">Posizione non disponibile: consenti il GPS. <button type="button" class="min-h-11 px-1 font-semibold underline underline-offset-2" @click="posizione()">Riprova</button></span>
            </p>
        </div>
        @endunless
        </div>

    </div>

    @if ($risultato)
        @php
            $esitoOk = ($risultato['ok'] ?? false) === true;
            $esito = $esitoOk
                ? match ($risultato['tipo'] ?? '') {
                    'F' => ['Turno chiuso', '#0369a1'],
                    'P' => ['Passaggio registrato', '#0284c7'],
                    'I' => ['Turno iniziato', '#059669'],
                    default => ['Timbratura segnata', '#059669'],
                }
                : ['Non è andata a buon fine', '#e11d48'];
        @endphp
        <div wire:key="esito-{{ $esitoId }}" role="alertdialog" aria-modal="true" aria-labelledby="esito-titolo" class="esito-fondo fixed inset-0 z-[60] flex items-end justify-center bg-[#0f172a]/70 p-4 sm:items-center">
            <div class="esito-scheda w-full max-w-md rounded-3xl bg-[#ffffff] p-6 text-center shadow-2xl sm:p-8">
                <div class="esito-segno mx-auto flex h-20 w-20 items-center justify-center rounded-full text-[#ffffff]" style="background-color: {{ $esito[1] }}">
                    @if ($esitoOk)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    @endif
                </div>
                <h2 id="esito-titolo" class="mt-5 text-2xl font-bold tracking-tight text-[#0f172a]">{{ $esito[0] }}</h2>
                <p class="mt-2 text-base leading-relaxed text-[#334155]">{{ $risultato['message'] }}</p>
                @if (! empty($risultato['nota']))
                    <p class="mt-2 text-base leading-relaxed text-[#334155]">{{ $risultato['nota'] }}</p>
                @endif
                <button type="button" wire:click="nascondiEsito({{ $esitoId }})" class="mt-6 inline-flex min-h-14 w-full items-center justify-center rounded-xl px-4 text-base font-semibold text-[#ffffff]" style="background-color: {{ $esito[1] }}">Ho capito</button>
            </div>
        </div>
    @endif
</div>
