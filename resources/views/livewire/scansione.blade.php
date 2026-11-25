<div class="mx-auto grid w-full max-w-7xl grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)] lg:gap-6" x-data="scanner">

    <div class="min-w-0 space-y-5">
        <div class="card overflow-hidden">
        <div x-show="sostituzione" x-cloak class="space-y-2 px-5 py-8 text-center sm:px-8">
            <p class="text-xl font-semibold text-slate-800">Sostituisci turno</p>
            <p class="text-base leading-relaxed text-slate-600">Scansiona il QR principale della sede per la sostituzione del turno.</p>
        </div>
        <div x-show="!sostituzione">
        @if (($risultato['ok'] ?? false) === true)
            @php
                $esito = match ($risultato['tipo'] ?? '') {
                    'I' => ['Turno iniziato', 'L\'ingresso è segnato. Quando il turno finisce, torna qui e premi Chiudi il turno.', 'bg-emerald-600'],
                    'F' => ['Turno chiuso', 'L\'uscita è segnata. Hai finito: puoi chiudere la pagina.', 'bg-sky-700'],
                    'P' => ['Passaggio registrato', 'Il passaggio è segnato. Continua il giro e scansiona il prossimo QR.', 'bg-sky-600'],
                    default => ['Timbratura segnata', 'La timbratura è registrata.', 'bg-emerald-600'],
                };
            @endphp
            <div wire:key="esito-{{ $esitoId }}" role="status" class="space-y-4 px-5 py-10 text-center text-white sm:px-8 {{ $esito[2] }}">
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white text-4xl font-bold text-emerald-600">✓</span>
                <h2 class="text-3xl font-extrabold leading-tight sm:text-4xl">{{ $esito[0] }}</h2>
                <p class="text-lg leading-relaxed">{{ $esito[1] }}</p>
                <p class="text-base leading-relaxed text-white/90">{{ $risultato['message'] }}</p>
                <button type="button" wire:click="nascondiEsito({{ $esitoId }})" class="inline-flex min-h-14 w-full items-center justify-center rounded-xl bg-white px-4 text-lg font-semibold text-slate-800">Ho capito</button>
            </div>
        @elseif ($turno)
            @php $inCorso = $turno->stato === 'I'; @endphp
            <div wire:key="foto-turno-{{ $turno->id }}-{{ $fotoSede ?? 'vuota' }}" x-data="{ fotoDisponibile: @js((bool) $fotoSede) }" class="flex items-center justify-center bg-sky-50">
                @if ($fotoSede)
                    <img x-show="fotoDisponibile" x-on:error="fotoDisponibile = false" src="{{ $fotoSede }}" alt="Foto di {{ $turno->sede->descrizione }}" class="block max-h-64 w-auto max-w-full object-contain sm:max-h-80">
                @endif
                <div x-cloak x-show="!fotoDisponibile" class="flex h-28 w-full items-center justify-center text-sky-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 10h.01M12 10h.01M15 10h.01M9 13h.01M12 13h.01M15 13h.01"/>
                    </svg>
                </div>
            </div>
            <div class="space-y-3 px-5 py-6 text-center sm:px-8 sm:py-8">
                <p class="text-sm font-bold uppercase tracking-wide {{ $inCorso ? 'text-orange-700' : 'text-emerald-700' }}">{{ $inCorso ? 'Uscita' : 'Ingresso' }}</p>
                <h2 class="text-3xl font-extrabold leading-tight text-slate-800 sm:text-4xl">{{ $inCorso ? 'Ora chiudi il turno' : 'Ora inizia il turno' }}</h2>
                <p class="text-lg leading-relaxed text-slate-600">
                    @if ($inCorso)
                        Quando hai finito, premi il pulsante e inquadra il QR della sede. Se devi segnare un passaggio, inquadra il QR di quel punto.
                    @else
                        Premi il pulsante e inquadra il QR della sede.
                    @endif
                </p>
                <p class="text-4xl font-extrabold leading-tight tracking-tight tabular-nums text-slate-800 sm:text-5xl">{{ $turno->inizio->format('H:i') }} – {{ $turno->fine->format('H:i') }}</p>
                <p class="text-base font-semibold leading-snug text-slate-700 sm:text-lg">{{ mb_convert_case($turno->inizio->locale('it')->translatedFormat('l d F'), MB_CASE_TITLE, 'UTF-8') }}</p>
                <p class="break-words text-sm leading-relaxed text-slate-500 sm:text-base">{{ $turno->sede->descrizione }}</p>
            </div>
        @else
            <div class="space-y-2 px-5 py-8 text-center sm:px-8">
                <p class="text-xl font-semibold text-slate-800">Nessun turno attivo</p>
                <p class="text-lg leading-relaxed text-slate-600">Adesso non devi timbrare. Puoi farlo da 15 minuti prima dell'inizio.</p>
            </div>
        @endif
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
                    <span class="text-lg font-medium">Ora premi Invia</span>
                </div>
            </div>

            <p x-show="camera && !preparazione && !letto && sostituzione" x-cloak class="text-center text-lg font-semibold leading-relaxed text-slate-800">Inquadra il QR principale della sede.</p>
            <p x-show="camera && !preparazione && !letto && !sostituzione" x-cloak class="text-center text-lg font-semibold leading-relaxed text-slate-800">
                @if ($turno && $turno->stato === 'I')
                    Inquadra il QR. Con il QR della sede chiudi il turno.
                @else
                    Inquadra il QR della sede per iniziare il turno.
                @endif
            </p>

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
                <p class="text-center text-base leading-relaxed text-slate-600">La nota puoi lasciarla vuota. Poi premi Invia.</p>
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
                <p class="mt-2 text-base leading-relaxed">Leggi il messaggio e premi di nuovo il pulsante.</p>
            </div>
        @endif

        @if ($turno)
            @php
                $ingresso = $presenze->firstWhere('tipo', \App\Models\Legacy\Gda::INIZIO);
                $uscita = $presenze->firstWhere('tipo', \App\Models\Legacy\Gda::FINE);
                $passaggi = $presenze->where('tipo', \App\Models\Legacy\Gda::PASSAGGIO);
                $stato = fn ($m) => $m === null ? '' : ($m === 0 ? 'Puntuale' : ($m > 0 ? '+'.$m.' min' : $m.' min'));
            @endphp
            <div class="card p-5 sm:p-6">
                <h2 class="mb-5 text-lg font-bold text-slate-800 sm:text-xl">Timeline del turno</h2>
                <ol class="relative space-y-7 border-l-2 border-sky-100 pl-7">
                    <li class="relative">
                        <span class="absolute -left-[37px] flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold text-white ring-4 ring-white {{ $ingresso ? 'bg-emerald-500' : 'bg-slate-300' }}">{{ $ingresso ? '✓' : '1' }}</span>
                        <p class="text-base font-semibold text-slate-800">Ingresso</p>
                        <p class="mt-1 text-sm text-slate-600">Previsto {{ $turno->inizio->format('H:i') }}</p>
                        @if ($ingresso)
                            <p class="mt-1 text-base text-emerald-700">Timbrato alle {{ $ingresso->rilevata_at->format('H:i') }} · {{ $stato($ingresso->ritardo_minuti) }}</p>
                            @if ($ingresso->note_operatore)
                                <p class="mt-1 break-words text-sm text-slate-600">{{ $ingresso->note_operatore }}</p>
                            @endif
                        @else
                            <p class="mt-1 text-sm text-slate-500">In attesa di timbratura</p>
                        @endif
                    </li>
                    @foreach ($passaggi as $p)
                        <li class="relative">
                            <span class="absolute -left-[33px] h-4 w-4 rounded-full bg-sky-400 ring-4 ring-white"></span>
                            <p class="break-words text-base font-medium text-slate-700">{{ $p->postazione?->titolo ?? 'Passaggio' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $p->rilevata_at->format('H:i') }}@if ($p->ritardo_minuti !== null) · {{ $stato($p->ritardo_minuti) }}@endif</p>
                            @if ($p->note_operatore)
                                <p class="mt-1 break-words text-sm text-slate-600">{{ $p->note_operatore }}</p>
                            @endif
                        </li>
                    @endforeach
                    <li class="relative">
                        <span class="absolute -left-[37px] flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold text-white ring-4 ring-white {{ $uscita ? 'bg-emerald-500' : 'bg-slate-300' }}">{{ $uscita ? '✓' : '2' }}</span>
                        <p class="text-base font-semibold text-slate-800">Uscita</p>
                        <p class="mt-1 text-sm text-slate-600">Prevista {{ $turno->fine->format('H:i') }}</p>
                        @if ($uscita)
                            <p class="mt-1 text-base text-emerald-700">Timbrata alle {{ $uscita->rilevata_at->format('H:i') }} · {{ $stato($uscita->ritardo_minuti) }}</p>
                            @if ($uscita->note_operatore)
                                <p class="mt-1 break-words text-sm text-slate-600">{{ $uscita->note_operatore }}</p>
                            @endif
                        @else
                            <p class="mt-1 text-sm text-slate-500">{{ $ingresso ? 'Turno in corso' : 'Dopo l\'ingresso' }}</p>
                        @endif
                    </li>
                </ol>
            </div>
        @endif
    </div>

    <div class="card min-w-0 overflow-hidden lg:sticky lg:top-20">
        <div class="border-b border-sky-100 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-800 sm:text-xl">Prossimi turni</h2>
        </div>
        <ul class="divide-y divide-sky-50">
            @forelse ($prossimi as $p)
                <li class="space-y-2 px-5 py-4 sm:px-6">
                    <p class="break-words text-base font-medium text-slate-700">{{ $p->sede?->descrizione }}</p>
                    <p class="break-words text-base font-semibold leading-snug text-slate-800">{{ mb_convert_case($p->inizio->locale('it')->translatedFormat('l d F'), MB_CASE_TITLE, 'UTF-8') }}</p>
                    <p class="text-base font-medium tabular-nums text-slate-600">{{ $p->inizio->format('H:i') }} <span aria-hidden="true">–</span> {{ $p->fine->format('H:i') }}</p>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-base text-slate-600 sm:px-6">Nessun turno in programma.</li>
            @endforelse
        </ul>
    </div>
</div>
