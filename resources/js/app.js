// Livewire include Alpine e navigazione
import jsQR from 'jsqr';
import $ from 'jquery';
import select2 from 'select2';
import 'select2/dist/css/select2.min.css';

window.$ = window.jQuery = $;
select2(window, $);

function inizializzaSelect2Qr(radice = document) {
    const select = radice.matches?.('.select2-qr') ? [radice] : radice.querySelectorAll?.('.select2-qr');

    $(select ?? []).each(function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({ width: '100%' });
        }
    });
}

function distruggiSelect2Qr(radice) {
    const select = radice.matches?.('.select2-qr') ? [radice] : radice.querySelectorAll?.('.select2-qr');

    $(select ?? []).each(function () {
        if ($(this).hasClass('select2-hidden-accessible')) {
            $(this).select2('destroy');
        }
    });
}

function collegaSelect2Qr() {
    if (!window.Livewire || window.select2QrCollegato) return;

    window.select2QrCollegato = true;
    window.Livewire.hook('morphed', ({ el }) => inizializzaSelect2Qr(el));
    window.Livewire.hook('morph.removing', ({ el }) => distruggiSelect2Qr(el));
    inizializzaSelect2Qr();
}

document.addEventListener('livewire:init', collegaSelect2Qr);
document.addEventListener('livewire:initialized', collegaSelect2Qr);
collegaSelect2Qr();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('scanner', () => ({
        camera: false, stream: null, caricamento: false, errore: null,
        preparazione: false, apertura: false, sospesa: false,
        lettura: 0, riavvii: 0,
        gps: 'attesa', lat: null, lng: null, codice: null, sostituzione: false,

        init() {
            this.posizione();
            this.alRitorno = () => {
                if (document.visibilityState === 'hidden') {
                    this.sospendi();
                    return;
                }
                this.posizione();
                this.riprendi();
            };
            this.alRipristino = (evento) => {
                if (evento.persisted) this.riprendiDopoBlocco();
            };
            document.addEventListener('visibilitychange', this.alRitorno);
            window.addEventListener('pageshow', this.alRipristino);
            if (new URLSearchParams(window.location.search).get('sostituzione') === '1') {
                this.sostituzione = true;
            }
        },

        destroy() {
            document.removeEventListener('visibilitychange', this.alRitorno);
            window.removeEventListener('pageshow', this.alRipristino);
            this.ferma();
        },

        apriSostituzione() {
            this.errore = null;
            this.sostituzione = true;
        },

        chiudiSostituzione() {
            this.sostituzione = false;
            this.ferma();
            this.annulla();
            this.togliSostituzioneDallIndirizzo();
        },

        posizione() {
            if (!navigator.geolocation) {
                this.gps = 'assente';
                return Promise.resolve(null);
            }
            if (this.richiestaPosizione) {
                return this.richiestaPosizione;
            }

            this.gps = 'attesa';
            this.richiestaPosizione = new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition(
                    (p) => {
                        this.lat = p.coords.latitude;
                        this.lng = p.coords.longitude;
                        this.gps = 'ok';
                        resolve({ lat: this.lat, lng: this.lng });
                    },
                    () => {
                        this.lat = null;
                        this.lng = null;
                        this.gps = 'negato';
                        resolve(null);
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
                );
            }).finally(() => {
                this.richiestaPosizione = null;
            });

            return this.richiestaPosizione;
        },

        async conferma() {
            if (!this.codice || this.caricamento) return;
            this.caricamento = true;
            this.errore = null;
            try {
                const posizione = await this.posizione();
                if (!posizione) {
                    this.errore = 'Posizione non disponibile. Consenti il GPS per registrare la timbratura.';
                    return;
                }
                await this.$wire.timbra(this.codice, posizione.lat, posizione.lng, this.sostituzione, this.$refs.nota?.value ?? '');
                const riuscita = this.$wire.risultato?.ok === true;
                this.codice = null;
                if (this.$refs.nota) this.$refs.nota.value = '';
                if (riuscita) {
                    this.sostituzione = false;
                    this.togliSostituzioneDallIndirizzo();
                }
            } catch {
            } finally { this.caricamento = false; }
        },

        annulla() {
            this.codice = null;
            if (this.$refs.nota) this.$refs.nota.value = '';
        },

        async avvia() {
            if (this.apertura || this.camera) return;
            this.errore = null;
            this.riavvii = 0;
            this.sospesa = false;
            await this.accendi();
        },

        async accendi() {
            if (this.apertura) return;
            if (!navigator.mediaDevices?.getUserMedia) {
                this.fallisci('La fotocamera non si può aprire da questa pagina.');
                return;
            }

            this.apertura = true;
            this.lettura++;
            const lettura = this.lettura;
            this.camera = true;
            this.preparazione = true;
            this.rilascia();
            await this.$nextTick();
            await this.dopoLayout();

            const vincoli = [
                { video: { facingMode: { ideal: 'environment' } }, audio: false },
                { video: { facingMode: { ideal: 'environment' } }, audio: false },
                { video: true, audio: false },
            ];
            let ultimoErrore = null;

            for (let i = 0; i < vincoli.length; i++) {
                if (this.lettura !== lettura) return;

                let stream = null;
                try {
                    stream = await navigator.mediaDevices.getUserMedia(vincoli[i]);
                } catch (e) {
                    ultimoErrore = e?.name ?? 'errore';
                    if (ultimoErrore === 'NotAllowedError' || ultimoErrore === 'PermissionDeniedError') {
                        this.fallisci('Per leggere il QR consenti la fotocamera, poi premi Riprova.', lettura);
                        return;
                    }
                    continue;
                }

                if (this.lettura !== lettura) {
                    stream.getTracks().forEach((t) => t.stop());
                    return;
                }

                this.rilascia();
                this.stream = stream;
                const video = this.nuovoVideo();
                const ok = video && await this.collega(video, stream);
                if (this.lettura !== lettura) {
                    this.rilascia();
                    return;
                }
                if (!ok || (i < vincoli.length - 1 && this.fotogrammaVuoto(video))) {
                    this.rilascia();
                    await this.attesa(200);
                    continue;
                }

                this.preparazione = false;
                this.apertura = false;
                this.sorveglia(lettura);
                this.ciclo(video, lettura);
                return;
            }

            if (ultimoErrore === 'NotFoundError' || ultimoErrore === 'DevicesNotFoundError') {
                this.fallisci('Su questo telefono non trovo la fotocamera.', lettura);
                return;
            }
            this.fallisci('La fotocamera non si vede. Premi Riprova.', lettura);
        },

        async collega(video, stream) {
            video.srcObject = stream;
            video.muted = true;
            video.playsInline = true;
            try {
                await video.play();
            } catch {
                return false;
            }
            if (!await this.attendiImmagine(video, stream)) return false;
            await this.attesa(350);
            return true;
        },

        attendiImmagine(video, stream) {
            return new Promise((resolve) => {
                const track = stream.getVideoTracks()[0];
                if (!track || track.readyState === 'ended') {
                    resolve(false);
                    return;
                }
                let chiuso = false;
                const chiudi = (ok) => {
                    if (chiuso) return;
                    chiuso = true;
                    clearTimeout(timer);
                    track.removeEventListener('unmute', controlla);
                    video.removeEventListener('loadeddata', controlla);
                    video.removeEventListener('playing', controlla);
                    resolve(ok);
                };
                const controlla = () => {
                    if (track.readyState === 'ended') {
                        chiudi(false);
                        return;
                    }
                    if (video.videoWidth > 0 && video.readyState >= 2 && !track.muted) chiudi(true);
                };
                const timer = setTimeout(() => chiudi(false), 1500);
                track.addEventListener('unmute', controlla);
                video.addEventListener('loadeddata', controlla);
                video.addEventListener('playing', controlla);
                controlla();
            });
        },

        fotogrammaVuoto(video) {
            if (!video || video.videoWidth === 0 || video.videoHeight === 0) return true;
            const canvas = document.createElement('canvas');
            canvas.width = 32;
            canvas.height = 32;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            if (!ctx) return false;
            try {
                ctx.drawImage(video, 0, 0, 32, 32);
                const dati = ctx.getImageData(0, 0, 32, 32).data;
                for (let i = 0; i < dati.length; i += 4) {
                    if (dati[i] !== 0 || dati[i + 1] !== 0 || dati[i + 2] !== 0) return false;
                }
                return true;
            } catch {
                return false;
            }
        },

        nuovoVideo() {
            const box = this.$refs.inquadratura;
            const vecchio = box?.querySelector('video');
            if (!vecchio) return null;
            const nuovo = document.createElement('video');
            nuovo.playsInline = true;
            nuovo.muted = true;
            nuovo.autoplay = true;
            nuovo.setAttribute('playsinline', '');
            nuovo.setAttribute('webkit-playsinline', '');
            nuovo.className = vecchio.className;
            vecchio.replaceWith(nuovo);
            return nuovo;
        },

        videoAttivo() {
            return this.$refs.inquadratura?.querySelector('video') ?? null;
        },

        sorveglia(lettura) {
            const track = this.stream?.getVideoTracks()[0];
            if (!track) return;
            track.addEventListener('ended', () => {
                if (this.lettura !== lettura || !this.camera || this.codice) return;
                this.riapri();
            });
        },

        riapri() {
            if (this.apertura) return;
            this.riavvii++;
            if (this.riavvii > 2) {
                this.fallisci('La fotocamera non si vede. Premi Riprova.');
                return;
            }
            this.accendi();
        },

        sospendi() {
            if (!this.camera || this.codice) return;
            this.lettura++;
            this.apertura = false;
            this.preparazione = true;
            this.sospesa = true;
            this.rilascia();
            const video = this.videoAttivo();
            if (video) {
                video.pause();
                video.srcObject = null;
            }
        },

        riprendi() {
            if (!this.sospesa || !this.camera || this.codice) return;
            this.sospesa = false;
            this.riavvii = 0;
            this.accendi();
        },

        riprendiDopoBlocco() {
            if (!this.camera || this.codice) return;
            this.sospesa = false;
            this.apertura = false;
            this.riavvii = 0;
            this.lettura++;
            this.rilascia();
            this.accendi();
        },

        fallisci(messaggio, lettura) {
            if (lettura !== undefined && this.lettura !== lettura) return;
            this.preparazione = false;
            this.apertura = false;
            this.camera = false;
            this.sospesa = false;
            this.rilascia();
            const video = this.videoAttivo();
            if (video) {
                video.pause();
                video.srcObject = null;
            }
            this.errore = messaggio;
        },

        rilascia() {
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
        },

        dopoLayout() {
            return new Promise((resolve) => {
                requestAnimationFrame(() => requestAnimationFrame(resolve));
            });
        },

        attesa(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        },

        ciclo(video, lettura) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const nativo = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null;

            const passo = async () => {
                if (!this.camera || this.lettura !== lettura || this.codice) return;
                let valore = null;
                if (video.readyState >= 2 && video.videoWidth > 0) {
                    if (nativo) {
                        valore = (await nativo.detect(video).catch(() => []))[0]?.rawValue ?? null;
                    } else {
                        const w = Math.min(video.videoWidth, 640);
                        canvas.width = w;
                        canvas.height = Math.round(w * video.videoHeight / video.videoWidth);
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        valore = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
                    }
                }
                if (this.lettura !== lettura || this.codice) return;
                if (valore) {
                    this.ferma();
                    navigator.vibrate?.(80);
                    this.codice = valore;
                    return;
                }
                requestAnimationFrame(passo);
            };
            passo();
        },

        ferma() {
            this.lettura++;
            this.camera = false;
            this.preparazione = false;
            this.apertura = false;
            this.sospesa = false;
            this.rilascia();
            const video = this.videoAttivo();
            if (video) {
                video.pause();
                video.srcObject = null;
            }
        },

        togliSostituzioneDallIndirizzo() {
            const url = new URL(window.location.href);
            if (!url.searchParams.has('sostituzione')) return;
            url.searchParams.delete('sostituzione');
            history.replaceState({}, '', url);
        },
    }));
});
