@php
    $tabs = [
        'generale' => ['Generale', 'M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M12 10h.01M15 10h.01M9 13h.01M12 13h.01M15 13h.01M10 21v-4h4v4', ['f.cliente_id', 'f.descrizione', 'f.telefono', 'f.email', 'f.note']],
        'posizione' => ['Posizione', 'M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z', ['f.indirizzo', 'f.cap', 'f.citta', 'f.provincia', 'f.lat', 'f.lng', 'f.distanza_max']],
        'servizio' => ['Servizio', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', ['f.tariffa', 'ore.*']],
    ];
@endphp
<div class="space-y-5" x-data="{ tab: 'generale' }" @tab-errore.window="tab = $event.detail.tab">
    <a href="{{ route('sedi') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-sky-700 hover:underline">← Torna alle sedi</a>

    <div class="flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-sky-100">
        @foreach ($tabs as $chiave => [$etichetta, $icona, $campi])
            <button type="button" @click="tab = '{{ $chiave }}'; $dispatch('tab-{{ $chiave }}')"
                :class="tab === '{{ $chiave }}' ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:bg-sky-50 hover:text-sky-700'"
                class="relative flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icona }}"/></svg>
                {{ $etichetta }}
                @if ($errors->hasAny($campi)) <span class="absolute right-2 top-1.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white"></span> @endif
            </button>
        @endforeach
    </div>

    <form wire:submit="salva" class="space-y-5">
        <section x-show="tab === 'generale'" class="card p-5 sm:p-6">
            <h2 class="mb-4 text-base font-bold text-slate-800">Dati generali</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Cliente" name="f.cliente_id"><select wire:model="f.cliente_id" class="input"><option value="">—</option>@foreach ($clienti as $c)<option value="{{ $c->id }}">{{ $c->ragione_sociale }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Descrizione" name="f.descrizione"><input wire:model="f.descrizione" class="input"></x-ui.field>
                <x-ui.field label="Telefono" name="f.telefono"><input wire:model="f.telefono" class="input"></x-ui.field>
                <x-ui.field label="Email" name="f.email"><input wire:model="f.email" class="input"></x-ui.field>
                <x-ui.field label="Note" name="f.note" class="sm:col-span-2"><textarea wire:model="f.note" rows="2" class="input"></textarea></x-ui.field>
            </div>
        </section>

        <section x-show="tab === 'posizione'" x-cloak class="card p-5 sm:p-6">
            <h2 class="mb-4 text-base font-bold text-slate-800">Posizione</h2>
            <div class="grid gap-5 lg:grid-cols-5">
                <div class="lg:col-span-3">
                <div wire:ignore @tab-posizione.window="setTimeout(() => { if (mappa) { google.maps.event.trigger(mappa, 'resize'); const c = marker.getPosition(); if (c) { mappa.setCenter(c); } } }, 50)"
                    x-data="{
                        mappa: null, marker: null, cerchio: null, errore: false, suggerimenti: [], timer: null, token: null,
                        async init() {
                            const key = @js(config('services.google_maps.key'));
                            if (!key) { this.errore = true; return; }
                            window.gmapsPronta ??= new Promise((ok, ko) => {
                                if (window.google?.maps?.importLibrary) { return ok(); }
                                window.__gmapsCb = ok;
                                const s = document.createElement('script');
                                s.src = 'https://maps.googleapis.com/maps/api/js?key=' + key + '&loading=async&callback=__gmapsCb';
                                s.onerror = ko;
                                document.head.appendChild(s);
                            });
                            try {
                                await window.gmapsPronta;
                                const { Map } = await google.maps.importLibrary('maps');
                                const { Marker } = await google.maps.importLibrary('marker');
                                await google.maps.importLibrary('places');
                                await google.maps.importLibrary('geocoding');
                                this.token = new google.maps.places.AutocompleteSessionToken();
                                const lat = parseFloat($wire.f.lat), lng = parseFloat($wire.f.lng);
                                const ha = !isNaN(lat) && !isNaN(lng);
                                this.mappa = new Map(this.$refs.mappa, { center: ha ? { lat, lng } : { lat: 41.9, lng: 12.5 }, zoom: ha ? 17 : 6, streetViewControl: false, mapTypeControl: false });
                                this.marker = new Marker({ map: this.mappa, position: ha ? { lat, lng } : null, draggable: true });
                                const raggio = () => Math.min(5000, Math.max(10, parseInt($wire.f.distanza_max) || 100));
                                this.cerchio = new google.maps.Circle({ map: this.mappa, center: ha ? { lat, lng } : null, radius: raggio(), editable: true, strokeColor: '#0ea5e9', strokeWeight: 2, fillColor: '#0ea5e9', fillOpacity: 0.15, clickable: false });
                                this.cerchio.addListener('radius_changed', () => {
                                    const r = Math.min(5000, Math.max(10, Math.round(this.cerchio.getRadius())));
                                    if (r !== parseInt($wire.f.distanza_max)) { $wire.set('f.distanza_max', r); }
                                });
                                this.cerchio.addListener('center_changed', () => { const c = this.cerchio.getCenter(); if (c && this.marker.getPosition() && !c.equals(this.marker.getPosition())) { this.cerchio.setCenter(this.marker.getPosition()); } });
                                Alpine.effect(() => { const r = raggio(); if (this.cerchio && Math.round(this.cerchio.getRadius()) !== r) { this.cerchio.setRadius(r); } });
                                this.mappa.addListener('click', e => this.scegli(e.latLng, true));
                                this.marker.addListener('dragend', e => this.scegli(e.latLng, true));
                                this.marker.addListener('drag', e => this.cerchio.setCenter(e.latLng));
                            } catch (e) { this.errore = true; }
                        },
                        cerca() {
                            clearTimeout(this.timer);
                            const q = document.getElementById('sede-indirizzo').value.trim();
                            if (q.length < 3 || this.errore) { this.suggerimenti = []; return; }
                            this.timer = setTimeout(async () => {
                                try {
                                    const r = await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions({ input: q, includedRegionCodes: ['it'], sessionToken: this.token });
                                    this.suggerimenti = r.suggestions.map(x => x.placePrediction);
                                } catch (e) { this.suggerimenti = []; }
                            }, 250);
                        },
                        async scelta(pred) {
                            this.suggerimenti = [];
                            const place = pred.toPlace();
                            await place.fetchFields({ fields: ['location', 'addressComponents'] });
                            this.token = new google.maps.places.AutocompleteSessionToken();
                            this.scegli(place.location, false);
                            this.indirizzo(place.addressComponents.map(a => ({ types: a.types, long_name: a.longText, short_name: a.shortText })));
                            this.mappa.setZoom(17);
                        },
                        scegli(pos, inverso) {
                            this.marker.setPosition(pos);
                            this.cerchio.setCenter(pos);
                            this.mappa.panTo(pos);
                            $wire.set('f.lat', pos.lat().toFixed(7));
                            $wire.set('f.lng', pos.lng().toFixed(7));
                            if (inverso) {
                                new google.maps.Geocoder().geocode({ location: pos }, (r, st) => { if (st === 'OK' && r[0]) { this.indirizzo(r[0].address_components); } });
                            }
                        },
                        indirizzo(comp) {
                            const c = (t, n = 'long_name') => (comp.find(a => a.types.includes(t)) || {})[n] || '';
                            const via = (c('route') + ' ' + c('street_number')).trim();
                            if (via) { $wire.set('f.indirizzo', via); const i = document.getElementById('sede-indirizzo'); if (i) { i.value = via; } }
                            $wire.set('f.cap', c('postal_code'));
                            $wire.set('f.citta', c('locality') || c('administrative_area_level_3'));
                            $wire.set('f.provincia', c('administrative_area_level_2', 'short_name'));
                        }
                    }">
                    <label class="label">Indirizzo</label>
                    <div class="relative" @click.outside="suggerimenti = []">
                        <input id="sede-indirizzo" value="{{ $f['indirizzo'] ?? '' }}" @input="$wire.set('f.indirizzo', $event.target.value, false); cerca()" class="input" autocomplete="off" placeholder="Inizia a scrivere l'indirizzo...">
                        <ul x-show="suggerimenti.length" x-cloak class="absolute inset-x-0 top-full z-50 mt-1 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-sky-100">
                            <template x-for="p in suggerimenti" :key="p.placeId">
                                <li><button type="button" @click="scelta(p)" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-sky-50" x-text="p.text.text"></button></li>
                            </template>
                        </ul>
                    </div>
                    <div x-ref="mappa" x-show="!errore" class="mt-3" style="height:22rem;width:100%;border-radius:.75rem;border:1px solid #e0f2fe"></div>
                    <p x-show="!errore" class="mt-1 text-xs text-slate-500">Scegli un suggerimento, clicca sulla mappa o trascina il marker per la posizione. Trascina il punto sul bordo del cerchio per impostare il raggio d'azione.</p>
                    <p x-show="errore" x-cloak class="rounded-lg bg-amber-50 p-3 text-xs text-amber-700">Mappa non disponibile: inserisci latitudine e longitudine manualmente.</p>
                </div>
                </div>
                <div class="grid content-start gap-4 sm:grid-cols-2 lg:col-span-2">
                    <x-ui.field label="CAP" name="f.cap"><input wire:model="f.cap" class="input"></x-ui.field>
                    <x-ui.field label="Provincia" name="f.provincia"><input wire:model="f.provincia" class="input" maxlength="2"></x-ui.field>
                    <x-ui.field label="Città" name="f.citta" class="sm:col-span-2"><input wire:model="f.citta" class="input"></x-ui.field>
                    <x-ui.field label="Latitudine" name="f.lat"><input wire:model="f.lat" class="input"></x-ui.field>
                    <x-ui.field label="Longitudine" name="f.lng"><input wire:model="f.lng" class="input"></x-ui.field>
                    <x-ui.field label="Distanza max scansione (m)" name="f.distanza_max" class="sm:col-span-2"><input type="number" wire:model="f.distanza_max" class="input"></x-ui.field>
                </div>
            </div>
        </section>

        <section x-show="tab === 'servizio'" x-cloak class="card p-5 sm:p-6">
            <h2 class="mb-4 text-base font-bold text-slate-800">Servizio</h2>
            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.field label="Tariffa €/h" name="f.tariffa"><input wire:model="f.tariffa" class="input"></x-ui.field>
                <div class="sm:col-span-3">
                    <label class="label">Ore di servizio richieste per giorno</label>
                    <div class="grid grid-cols-7 gap-2">
                        @foreach ($giorni as $n => $g)
                            <div><span class="block text-center text-xs text-slate-500">{{ $g }}</span><input wire:model="ore.{{ $n }}" class="input !px-1 text-center" inputmode="decimal"></div>
                        @endforeach
                    </div>
                    @error('ore.*') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('sedi') }}" wire:navigate class="btn-soft">Annulla</a>
            <button class="btn-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Salva sede</button>
        </div>
    </form>
</div>
