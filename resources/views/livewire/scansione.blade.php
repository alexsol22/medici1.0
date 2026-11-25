<div class="mx-auto w-full max-w-xl" x-data="scanner">
    <div class="space-y-4">
        <div class="card overflow-hidden">
        <div x-show="!camera && !codice">
        <div x-show="sostituzione" x-cloak class="space-y-2 px-5 py-6 text-center sm:px-8">
            <p class="text-xl font-semibold text-slate-800">Sostituisci turno</p>
            <p class="text-base text-slate-600">Inquadra il QR della sede.</p>
        </div>
        <div x-show="!sostituzione">
        @if (($risultato['ok'] ?? false) === true)
            @php
                $esito = match ($risultato['tipo'] ?? '') {
                    'I' => ['Turno iniziato', 'bg-emerald-600'],
                    'F' => ['Turno chiuso', 'bg-sky-700'],
                    'P' => ['Passaggio registrato', 'bg-sky-600'],
                    default => ['Timbratura segnata', 'bg-emerald-600'],
                };
            @endphp
            <div wire:key="esito-{{ $esitoId }}" role="status" class="space-y-3 px-5 py-6 text-center text-white sm:px-8 {{ $esito[1] }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white text-3xl font-bold text-emerald-600">✓</span>
                <h2 class="text-3xl font-extrabold leading-tight">{{ $esito[0] }}</h2>
                <p class="text-base leading-relaxed text-white/90">{{ $risultato['message'] }}</p>
                @if (! empty($risultato['nota']))
                    <p class="text-base leading-relaxed text-white/90">{{ $risultato['nota'] }}</p>
                @endif
                <button type="button" wire:click="nascondiEsito({{ $esitoId }})" class="inline-flex min-h-14 w-full items-center justify-center rounded-xl bg-white px-4 text-lg font-semibold text-slate-800">Ho capito</button>
            </div>
        @elseif ($turno)
            @php $inCorso = $turno->stato === 'I'; @endphp
            @if ($fotoSede)
                <div class="flex justify-center bg-sky-50">
                    <img src="{{ $fotoSede }}" alt="Foto di {{ $turno->sede->descrizione }}" class="max-h-20 w-auto object-contain">
                </div>
            @endif
            <div class="space-y-2 px-5 py-5 text-center">
                <h2 class="text-3xl font-extrabold leading-tight text-slate-800">{{ $inCorso ? 'Chiudi il turno' : 'Inizia il turno' }}</h2>
                <p class="text-4xl font-extrabold tabular-nums text-slate-800">{{ $turno->inizio->format('H:i') }} – {{ $turno->fine->format('H:i') }}</p>
                <p class="text-base font-medium text-slate-600">{{ $turno->sede->descrizione }}</p>
            </div>
        @else
            <div class="space-y-1 px-5 py-6 text-center">
                <p class="text-xl font-semibold text-slate-800">Nessun turno attivo</p>
                <p class="text-base text-slate-600">Da 15 minuti prima dell'inizio.</p>
            </div>
        @endif
        </div>
        </div>

        @unless (($risultato['ok'] ?? false) === true)
        <div class="space-y-4 border-t border-sky-100 p-4 sm:space-y-5 sm:p-6">
            <div x-ref="inquadratura" x-show="camera" x-cloak class="relative mx-auto w-full max-w-xl overflow-hidden rounded-2xl bg-sky-50">
                <video playsinline webkit-playsinline autoplay muted class="aspect-square w-full bg-slate-900 object-cover"></video>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center" x-show="!preparazione && !letto">
                    <div class="h-2/3 w-2/3 rounded-3xl border-4 border-white/80 shadow-[0_0_0_999px_rgba(15,23,42,.45)]"></div>
                </div>
                <p x-show="preparazione" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-sky-50 px-6 text-center text-lg font-semibold text-slate-700">Attendi, apro la fotocamera…</p>
                <div x-show="letto" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-emerald-600 px-6 text-center text-white">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white text-3xl font-bold text-emerald-600">✓</span>
                    <span class="text-2xl font-bold">QR letto</span>
                </div>
            </div>

            <p x-show="camera && !preparazione && !letto" x-cloak class="text-center text-lg font-semibold text-slate-800">Inquadra il QR</p>

            <div x-show="errore" x-cloak class="rounded-xl bg-rose-50 p-4 text-base leading-relaxed text-rose-800 ring-1 ring-rose-200" x-text="errore"></div>

            <div x-show="codice" x-cloak class="space-y-3 text-left">
                <p class="text-center text-xl font-bold text-slate-800" x-show="!sostituzione">
                    @if ($turno && $turno->stato === 'I')
                        Conferma la chiusura del turno
                    @else
                        Conferma l'inizio del turno
                    @endif
                </p>
                <p class="text-center text-xl font-bold text-slate-800" x-show="sostituzione" x-cloak>Conferma la sostituzione</p>
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

            <p class="text-center text-sm leading-relaxed" x-show="gps !== 'ok'" x-cloak :class="gps === 'attesa' ? 'text-slate-500' : 'text-rose-700'">
                <span x-show="gps === 'attesa'">Rilevamento posizione…</span>
                <span x-show="gps === 'negato' || gps === 'assente'">Posizione non disponibile: consenti il GPS. <button type="button" class="min-h-11 px-1 font-semibold underline underline-offset-2" @click="posizione()">Riprova</button></span>
            </p>
        </div>
        @endunless
        </div>

        @if ($risultato && ! $risultato['ok'])
            <div wire:key="esito-{{ $esitoId }}" role="status" class="rounded-2xl bg-rose-50 p-5 text-rose-800 ring-1 ring-rose-200 sm:p-6">
                <p class="text-2xl font-extrabold">Non è andata a buon fine</p>
                <p class="mt-2 text-lg leading-relaxed">{{ $risultato['message'] }}</p>
            </div>
        @endif
    </div>
</div>
