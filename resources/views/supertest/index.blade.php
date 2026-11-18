<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Supertest · Omnia Control</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #263648;
            --muted: #64748b;
            --line: #dce8f0;
            --blue: #1677a8;
            --blue-dark: #125d83;
            --blue-pale: #eff8fc;
            --rust: #b65d3b;
            --green: #18794e;
            --amber: #9a5b08;
            --red: #b42318;
            --surface: #fff;
            --page: #f4f9fc;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--page); color: var(--ink); line-height: 1.5; }
        button, input { font: inherit; }
        button { cursor: pointer; }
        button:disabled { cursor: progress; opacity: .65; }
        button:focus-visible, input:focus-visible { outline: 3px solid #70c3ea; outline-offset: 2px; }
        .topbar { position: sticky; top: 0; z-index: 6; display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 64px; padding: 10px 20px; border-bottom: 1px solid var(--line); background: rgb(255 255 255 / 94%); backdrop-filter: blur(12px); }
        .brand { display: flex; align-items: center; gap: 11px; font-weight: 750; letter-spacing: -.03em; }
        .brand-mark { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 12px; background: var(--blue-pale); color: var(--blue); font-size: 18px; }
        .brand-accent { color: var(--rust); }
        .topbar-label { color: var(--muted); font-size: 13px; }
        .shell { display: grid; grid-template-columns: 300px minmax(0, 1fr); min-height: calc(100vh - 64px); }
        .sidebar { position: sticky; top: 64px; height: calc(100vh - 64px); overflow: auto; padding: 18px 14px 24px; border-right: 1px solid var(--line); background: #fff; }
        .sidebar-kicker { margin: 0 8px 10px; color: var(--muted); font-size: 12px; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
        .nav-button { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; width: 100%; margin: 0 0 6px; padding: 12px; border: 1px solid transparent; border-radius: 14px; background: transparent; color: var(--ink); text-align: left; }
        .nav-button strong { display: block; font-size: 14px; }
        .nav-button small { display: block; color: var(--muted); font-size: 12px; font-weight: 500; }
        .nav-button:hover { background: var(--blue-pale); }
        .nav-button.active { border-color: #cfe7f3; background: var(--blue-pale); color: var(--blue-dark); }
        .nav-count { min-width: 28px; padding: 2px 7px; border-radius: 999px; background: #eef2f6; color: #526579; font-size: 12px; font-weight: 800; text-align: center; }
        .nav-button.active .nav-count { background: white; color: var(--blue-dark); }
        .content { padding: 22px clamp(16px, 3vw, 36px) 48px; }
        .content-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        h1 { margin: 0; color: #172b3b; font-size: clamp(26px, 3vw, 34px); letter-spacing: -.04em; }
        .sintesi { margin: 6px 0 0; color: var(--muted); }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin-bottom: 16px; }
        .field { display: grid; gap: 5px; flex: 1; min-width: min(100%, 220px); }
        .field label, .stat span { color: #526579; font-size: 12px; font-weight: 700; }
        .field input { min-height: 40px; width: 100%; padding: 8px 10px; border: 1px solid #cbd9e3; border-radius: 10px; background: white; color: var(--ink); }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 8px 13px; border: 1px solid var(--line); border-radius: 10px; background: white; color: #405367; font-size: 13px; font-weight: 700; }
        .button:hover:not(:disabled) { border-color: #9cc9df; background: #f6fbfe; }
        .button-primary { border-color: var(--blue); background: var(--blue); color: white; }
        .button-primary:hover:not(:disabled) { background: var(--blue-dark); }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
        .stat { padding: 12px 14px; border: 1px solid var(--line); border-radius: 16px; background: white; }
        .stat strong { display: block; margin-top: 2px; font-size: 24px; letter-spacing: -.04em; }
        .caso { display: grid; grid-template-columns: 88px minmax(0, 1fr) minmax(180px, 320px); gap: 16px; margin-bottom: 12px; padding: 16px; border: 1px solid var(--line); border-radius: 18px; background: white; }
        .caso[hidden] { display: none; }
        .id-cell { color: var(--blue-dark); font-size: 13px; font-weight: 800; }
        .caso h2 { margin: 0; font-size: 17px; }
        .caso p { margin: 6px 0 0; color: var(--muted); font-size: 14px; }
        .produzione { display: inline-block; margin-top: 8px; padding: 3px 8px; border-radius: 999px; background: var(--blue-pale); color: var(--blue-dark); font-size: 12px; font-weight: 700; }
        .esito { display: inline-flex; min-width: 92px; justify-content: center; padding: 4px 8px; border-radius: 999px; background: #eef2f6; color: #526579; font-size: 12px; font-weight: 800; }
        .esito.pass { background: #e7f6ee; color: var(--green); }
        .esito.fail, .esito.error { background: #fdecec; color: var(--red); }
        .esito.running { background: #fff4df; color: var(--amber); }
        .messaggio { margin: 8px 0 0; color: #405367; font-size: 12px; white-space: pre-wrap; }
        .azioni { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .empty { display: none; padding: 28px; border: 1px dashed var(--line); border-radius: 16px; background: white; color: var(--muted); text-align: center; }
        .empty.visible { display: block; }
        .sessione { margin-bottom: 18px; padding: 16px; border: 1px solid var(--line); border-radius: 18px; background: white; }
        .sessione h2 { margin: 0 0 6px; font-size: 18px; }
        .sessione p { margin: 0 0 10px; color: var(--muted); }
        .sessione-azioni { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .check { display: flex; gap: 8px; align-items: center; color: #405367; font-size: 13px; font-weight: 700; }
        .tabelle, .passi { margin: 8px 0 0; padding-left: 18px; color: #405367; font-size: 13px; }
        .passi { max-height: 180px; overflow: auto; }
        .esito.skip { background: #fff4df; color: var(--amber); }
        .menu-toggle { display: none; }
        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; display: none; }
            .sidebar.open { display: block; }
            .menu-toggle { display: inline-flex; }
            .caso, .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">✓</span>
            <span>Omnia <span class="brand-accent">Control</span></span>
        </div>
        <span class="topbar-label">/supertest</span>
        <button class="button menu-toggle" id="menu-toggle" type="button">Sezioni</button>
    </header>
    <div class="shell">
        <aside class="sidebar" id="sidebar" aria-label="Sezioni di test">
            <p class="sidebar-kicker">Sezioni</p>
            @foreach ($sezioni as $indice => $sezione)
                <button class="nav-button {{ $indice === 0 ? 'active' : '' }}" type="button" data-nav="{{ $sezione['id'] }}">
                    <span>
                        <strong>{{ $sezione['titolo'] }}</strong>
                        <small>{{ $sezione['sintesi'] }}</small>
                    </span>
                    <span class="nav-count" data-count="{{ $sezione['id'] }}">{{ count($sezione['casi']) }}</span>
                </button>
            @endforeach
            <button class="button" id="run-all" type="button" style="width: 100%; margin-top: 12px;">Lancia tutte le sezioni</button>
        </aside>
        <main class="content">
            <section class="sessione" id="sessione" aria-label="Sessione con dati reali">
                <h2>Sessione con dati reali</h2>
                <p id="sessione-testo">Copia clienti, turni, presenze e registri in un file temporaneo. I casi leggono quei dati e le scritture restano sulla copia. Mail e SMS non partono. Dopo 30 minuti il file viene eliminato.</p>
                <p id="sessione-scadenza"></p>
                <ol class="tabelle" id="sessione-tabelle"></ol>
                <div class="sessione-azioni">
                    <button class="button button-primary" id="sessione-avvia" type="button">Apri sessione</button>
                    <button class="button" id="sessione-elimina" type="button" hidden>Elimina ora</button>
                    <label class="check"><input id="usa-reali" type="checkbox" checked> Lancia i casi su questa sessione</label>
                </div>
            </section>
            @foreach ($sezioni as $indice => $sezione)
                <section class="pannello" data-pannello="{{ $sezione['id'] }}" @if ($indice !== 0) hidden @endif>
                    <div class="content-head">
                        <div>
                            <h1>{{ $sezione['titolo'] }}</h1>
                            <p class="sintesi">{{ $sezione['sintesi'] }}</p>
                        </div>
                        <button class="button button-primary run-section" type="button" data-department="{{ $sezione['id'] }}">Lancia sezione</button>
                    </div>
                    <div class="stats" aria-label="Riepilogo sezione">
                        <div class="stat"><span>Casi</span><strong data-stat="totali">{{ count($sezione['casi']) }}</strong></div>
                        <div class="stat"><span>Passati</span><strong data-stat="passati">0</strong></div>
                        <div class="stat"><span>Falliti</span><strong data-stat="falliti">0</strong></div>
                        <div class="stat"><span>In corso</span><strong data-stat="corso">0</strong></div>
                    </div>
                    <div class="toolbar">
                        <div class="field">
                            <label for="search-{{ $sezione['id'] }}">Cerca in questa sezione</label>
                            <input id="search-{{ $sezione['id'] }}" class="search" type="search" data-department="{{ $sezione['id'] }}" placeholder="Es. PRE-03, QR, login">
                        </div>
                    </div>
                    @foreach ($sezione['casi'] as $caso)
                        <article class="caso" data-id="{{ $caso['id'] }}" data-department="{{ $sezione['id'] }}">
                            <div class="id-cell">{{ $caso['id'] }}</div>
                            <div>
                                <h2>{{ $caso['titolo'] }}</h2>
                                <p>{{ $caso['passaggi'] }}</p>
                                <p>{{ $caso['atteso'] }}</p>
                                <span class="produzione">{{ $caso['produzione'] }}</span>
                            </div>
                            <div>
                                <div class="azioni">
                                    <button class="button run-one" type="button" data-id="{{ $caso['id'] }}">Lancia</button>
                                    <span class="esito" data-esito="{{ $caso['id'] }}">Da eseguire</span>
                                </div>
                                <p class="messaggio" data-messaggio="{{ $caso['id'] }}"></p>
                                <ol class="passi" data-passi="{{ $caso['id'] }}"></ol>
                            </div>
                        </article>
                    @endforeach
                    <div class="empty" data-empty="{{ $sezione['id'] }}">Nessun caso corrisponde alla ricerca.</div>
                </section>
            @endforeach
        </main>
    </div>
    <script>
        (() => {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const pannelli = [...document.querySelectorAll('[data-pannello]')];
            const pulsantiNav = [...document.querySelectorAll('[data-nav]')];
            const casi = [...document.querySelectorAll('.caso')];
            const pulsanti = [...document.querySelectorAll('.run-one, .run-section, #run-all')];
            let coda = Promise.resolve();
            let inCorso = 0;
            let sessioneId = null;
            let diarioDa = 0;

            const mostra = (id) => {
                pannelli.forEach((pannello) => { pannello.hidden = pannello.dataset.pannello !== id; });
                pulsantiNav.forEach((pulsante) => { pulsante.classList.toggle('active', pulsante.dataset.nav === id); });
                document.querySelector('#sidebar').classList.remove('open');
            };

            const aggiorna = (sezione) => {
                const righe = casi.filter((caso) => caso.dataset.department === sezione);
                const stati = righe.map((caso) => document.querySelector(`[data-esito="${caso.dataset.id}"]`).dataset.stato || '');
                const pannello = document.querySelector(`[data-pannello="${sezione}"]`);
                pannello.querySelector('[data-stat="passati"]').textContent = String(stati.filter((stato) => stato === 'pass').length);
                pannello.querySelector('[data-stat="falliti"]').textContent = String(stati.filter((stato) => stato === 'fail' || stato === 'error').length);
                pannello.querySelector('[data-stat="corso"]').textContent = String(righe.filter((caso) => document.querySelector(`[data-esito="${caso.dataset.id}"]`).dataset.stato === 'running').length);
                const passati = stati.filter((stato) => stato === 'pass').length;
                const falliti = stati.filter((stato) => stato === 'fail' || stato === 'error').length;
                const count = document.querySelector(`[data-count="${sezione}"]`);
                count.textContent = passati + falliti > 0 ? `${passati}/${righe.length}` : String(righe.length);
            };

            const filtra = (sezione) => {
                const input = document.querySelector(`.search[data-department="${sezione}"]`);
                const query = input.value.trim().toLocaleLowerCase('it');
                let visibili = 0;
                casi.filter((caso) => caso.dataset.department === sezione).forEach((caso) => {
                    const visibile = !query || caso.innerText.toLocaleLowerCase('it').includes(query);
                    caso.hidden = !visibile;
                    if (visibile) visibili++;
                });
                document.querySelector(`[data-empty="${sezione}"]`).classList.toggle('visible', visibili === 0);
            };

            const scrivi = (id, esito, messaggio) => {
                const badge = document.querySelector(`[data-esito="${id}"]`);
                const testi = { pass: 'Passato', fail: 'Fallito', error: 'Errore', running: 'In corso', skip: 'Dato assente' };
                badge.dataset.stato = esito;
                badge.className = `esito ${esito}`;
                badge.textContent = testi[esito] || esito;
                document.querySelector(`[data-messaggio="${id}"]`).textContent = messaggio || '';
                aggiorna(document.querySelector(`[data-id="${id}"]`).dataset.department);
            };

            const disegnaSessione = (corpo) => {
                sessioneId = corpo.stato === 'pronta' ? corpo.id : null;
                const testo = document.querySelector('#sessione-testo');
                const scadenza = document.querySelector('#sessione-scadenza');
                const elenco = document.querySelector('#sessione-tabelle');
                document.querySelector('#sessione-elimina').hidden = corpo.stato === 'assente';
                document.querySelector('#sessione-avvia').disabled = corpo.stato === 'copia';
                if (corpo.stato === 'copia') testo.textContent = 'Copia in corso. I casi partono quando la sessione è pronta.';
                if (corpo.stato === 'pronta') testo.textContent = 'Sessione pronta. I casi usano questa copia, non il database dell’applicazione.';
                if (corpo.stato === 'errore') testo.textContent = corpo.errore || 'Copia non riuscita.';
                if (corpo.stato === 'assente') testo.textContent = 'Copia clienti, turni, presenze e registri in un file temporaneo. I casi leggono quei dati e le scritture restano sulla copia. Mail e SMS non partono. Dopo 30 minuti il file viene eliminato.';
                scadenza.textContent = corpo.scade ? `Eliminazione alle ${new Date(corpo.scade).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}.` : '';
                elenco.replaceChildren();
                (corpo.tabelle || []).forEach((tabella) => {
                    const voce = document.createElement('li');
                    voce.textContent = `${tabella.nome}: ${tabella.copiate} di ${tabella.totale}`;
                    elenco.appendChild(voce);
                });
                (corpo.diario || []).forEach((riga) => {
                    const lista = document.querySelector(`[data-passi="${riga.caso}"]`);
                    if (!lista) return;
                    const voce = document.createElement('li');
                    voce.textContent = `${riga.t} ${riga.testo}`;
                    lista.appendChild(voce);
                });
                diarioDa = corpo.prossimo ?? diarioDa;
            };

            const leggiSessione = () => fetch(@json(route('supertest.sessione')) + `?da=${diarioDa}`, {
                headers: { 'Accept': 'application/json' },
            }).then((risposta) => risposta.ok ? risposta.json() : null).then((corpo) => {
                if (corpo) disegnaSessione(corpo);
            }).catch(() => {});

            const lancia = (id) => {
                if (document.querySelector('#usa-reali').checked && !sessioneId) {
                    scrivi(id, 'error', 'Apri prima la sessione con i dati reali.');
                    return Promise.resolve();
                }
                inCorso++;
                scrivi(id, 'running', '');
                pulsanti.forEach((pulsante) => { pulsante.disabled = true; });
                const corpoRichiesta = { id };
                if (document.querySelector('#usa-reali').checked && sessioneId) corpoRichiesta.sessione = sessioneId;
                return fetch(@json(route('supertest.esegui')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(corpoRichiesta),
                }).then(async (risposta) => {
                    const corpo = await risposta.json().catch(() => ({}));
                    if (!risposta.ok) {
                        scrivi(id, 'error', corpo.message || `Richiesta non riuscita (${risposta.status}).`);
                        return;
                    }
                    scrivi(id, corpo.esito || 'error', `${corpo.durata_ms ?? 0} ms — ${corpo.messaggio || ''}`);
                }).catch(() => {
                    scrivi(id, 'error', 'Rete non disponibile.');
                }).finally(() => {
                    inCorso--;
                    if (inCorso === 0) pulsanti.forEach((pulsante) => { pulsante.disabled = false; });
                });
            };

            const accoda = (ids) => {
                ids.forEach((id) => { coda = coda.then(() => lancia(id)); });
            };

            pulsantiNav.forEach((pulsante) => pulsante.addEventListener('click', () => mostra(pulsante.dataset.nav)));
            document.querySelectorAll('.run-one').forEach((pulsante) => pulsante.addEventListener('click', () => accoda([pulsante.dataset.id])));
            document.querySelectorAll('.run-section').forEach((pulsante) => {
                pulsante.addEventListener('click', () => {
                    accoda([...document.querySelectorAll(`.caso[data-department="${pulsante.dataset.department}"]`)].filter((caso) => !caso.hidden).map((caso) => caso.dataset.id));
                });
            });
            document.querySelector('#run-all').addEventListener('click', () => accoda(casi.map((caso) => caso.dataset.id)));
            document.querySelectorAll('.search').forEach((input) => input.addEventListener('input', () => filtra(input.dataset.department)));
            document.querySelector('#menu-toggle').addEventListener('click', () => document.querySelector('#sidebar').classList.toggle('open'));
            document.querySelector('#sessione-avvia').addEventListener('click', () => {
                document.querySelector('#sessione-avvia').disabled = true;
                diarioDa = 0;
                document.querySelectorAll('[data-passi]').forEach((lista) => { lista.replaceChildren(); });
                fetch(@json(route('supertest.sessione.avvia')), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                }).then(() => leggiSessione());
            });
            document.querySelector('#sessione-elimina').addEventListener('click', () => {
                fetch(@json(route('supertest.sessione.elimina')), {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                }).then(() => {
                    diarioDa = 0;
                    document.querySelectorAll('[data-passi]').forEach((lista) => { lista.replaceChildren(); });
                    return leggiSessione();
                });
            });
            leggiSessione();
            setInterval(leggiSessione, 1000);
        })();
    </script>
</body>
</html>
