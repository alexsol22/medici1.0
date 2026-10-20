// Livewire include Alpine e navigazione
import jsQR from 'jsqr';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('navSearch', (items) => ({
        items,
        ricerca: '',
        aperta: false,
        indice: -1,

        init() {
            this.$watch('ricerca', () => { this.indice = -1; });
        },

        normalizza(value) {
            return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('it');
        },

        distanza(a, b) {
            const riga = Array.from({ length: b.length + 1 }, (_, i) => i);
            for (let i = 1; i <= a.length; i++) {
                let diagonale = riga[0];
                riga[0] = i;
                for (let j = 1; j <= b.length; j++) {
                    const sopra = riga[j];
                    riga[j] = Math.min(riga[j] + 1, riga[j - 1] + 1, diagonale + (a[i - 1] === b[j - 1] ? 0 : 1));
                    diagonale = sopra;
                }
            }
            return riga[b.length];
        },

        risultati() {
            const query = this.normalizza(this.ricerca.trim());
            if (!query) return [];

            const termini = query.split(/\s+/);
            return this.items.map((voce) => {
                const titolo = this.normalizza(voce.nome);
                const parole = this.normalizza(`${voce.nome} ${voce.gruppo} ${voce.paroleChiave}`)
                    .split(/[^a-z0-9]+/)
                    .filter(Boolean);
                let punteggio = 0;

                for (const termine of termini) {
                    const corrispondenza = parole.reduce((migliore, parola) => {
                        if (parola === termine) return Math.max(migliore, 100);
                        if (parola.startsWith(termine)) return Math.max(migliore, 80);
                        if (parola.includes(termine)) return Math.max(migliore, 60);
                        if (termine.length >= 4 && this.distanza(parola, termine) <= 1) return Math.max(migliore, 45);
                        return migliore;
                    }, 0);

                    if (!corrispondenza) return null;
                    punteggio += corrispondenza;
                }

                if (titolo.startsWith(query)) punteggio += 30;
                return { ...voce, punteggio };
            })
                .filter(Boolean)
                .sort((a, b) => b.punteggio - a.punteggio || a.nome.localeCompare(b.nome, 'it'))
                .slice(0, 7);
        },

        sposta(direzione) {
            const totale = this.risultati().length;
            if (!totale) return;
            this.indice = (this.indice + direzione + totale) % totale;
        },

        vaiAlRisultato() {
            const voce = this.risultati()[this.indice] ?? this.risultati()[0];
            if (voce) window.location.assign(voce.url);
        },
    }));

    window.Alpine.data('scanner', () => ({
        camera: false, stream: null, caricamento: false, errore: null,
        gps: 'attesa', lat: null, lng: null, ultimo: 0,

        init() { this.posizione(); },

        posizione() {
            if (!navigator.geolocation) { this.gps = 'assente'; return; }
            this.gps = 'attesa';
            navigator.geolocation.getCurrentPosition(
                p => { this.lat = p.coords.latitude; this.lng = p.coords.longitude; this.gps = 'ok'; },
                () => { this.gps = 'negato'; },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
        },

        async invia(codice) {
            this.caricamento = true;
            try { await this.$wire.timbra(codice, this.lat, this.lng); }
            finally { this.caricamento = false; }
        },

        async avvia() {
            this.errore = null;
            if (!navigator.mediaDevices?.getUserMedia) { this.errore = 'Fotocamera non disponibile: serve una connessione HTTPS.'; return; }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            } catch (e) {
                this.errore = 'Accesso alla fotocamera negato. Consentilo dalle impostazioni del browser.';
                return;
            }
            this.camera = true;
            await this.$nextTick();
            const video = this.$refs.video;
            video.srcObject = this.stream;
            await video.play();
            this.ciclo();
        },

        async ciclo() {
            const video = this.$refs.video;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const nativo = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null;

            const passo = async () => {
                if (!this.camera) return;
                let valore = null;
                if (video.readyState >= 2) {
                    if (nativo) {
                        valore = (await nativo.detect(video).catch(() => []))[0]?.rawValue ?? null;
                    } else {
                        const w = Math.min(video.videoWidth, 640);
                        canvas.width = w; canvas.height = Math.round(w * video.videoHeight / video.videoWidth);
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        valore = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
                    }
                }
                if (valore && Date.now() - this.ultimo > 3000) {
                    this.ultimo = Date.now();
                    this.ferma();
                    navigator.vibrate?.(80);
                    await this.invia(valore);
                    return;
                }
                requestAnimationFrame(passo);
            };
            passo();
        },

        ferma() {
            this.camera = false;
            this.stream?.getTracks().forEach(t => t.stop());
            this.stream = null;
        },
    }));
});
