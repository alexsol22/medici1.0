<?php

namespace App\SuperTest;

/** Sezioni e casi del collaudo, ciascuno legato a un metodo PHPUnit isolato. */
class Catalogo
{
    /**
     * @return list<array{id: string, titolo: string, sintesi: string, casi: list<array{id: string, titolo: string, passaggi: string, atteso: string, produzione: string, metodo: string}>}>
     */
    public static function sezioni(): array
    {
        return [
            self::sezione('comuni', 'Tutti i reparti — accesso e navigazione', 'Controlli comuni a tutti gli utenti', [
                self::voce('COM-01', 'Ingresso nell’app', 'Esci da eventuali sessioni precedenti; accedi con un account del reparto e annota la pagina iniziale.', 'Accesso riuscito e pagina iniziale corretta per ruolo.', 'Sola lettura'),
                self::voce('COM-02', 'Menu e navigazione abituale', 'Apri ogni voce usata dal reparto; torna indietro e riapri la pagina.', 'Voci previste per ruolo e pagine caricate, senza schermate vuote.', 'Sola lettura'),
                self::voce('COM-03', 'Record noto', 'Apri un cliente identificato in anticipo e verifica i dati principali.', 'Record giusto, stato e dati principali coerenti con la fonte.', 'Sola lettura'),
                self::voce('COM-04', 'Ricerca positiva e senza risultati', 'Cerca prima un elemento noto, poi un termine che non deve corrispondere.', 'Il record atteso compare; con zero risultati appare uno stato vuoto.', 'Sola lettura'),
                self::voce('COM-05', 'Filtri, paginazione e ritorno', 'Applica un filtro noto e verifica che il contesto resti.', 'Filtro e contesto sono conservati; i risultati non si duplicano.', 'Sola lettura'),
                self::voce('COM-06', 'Controllo dei permessi', 'Apri una pagina non prevista per il ruolo usando l’URL diretto.', 'Accesso negato; nessun dato riservato è visibile.', 'Consultazione'),
                self::voce('COM-07', 'Download autorizzato', 'Scarica un documento già autorizzato e confrontalo con il record.', 'File integro e riferito al record selezionato; gli altri destinatari sono esclusi.', 'Sola lettura'),
                self::voce('COM-08', 'Schermo e dispositivo', 'Controlla che menu e contenuti essenziali restino nel markup responsive.', 'Contenuti essenziali, menu e pulsanti restano raggiungibili anche su mobile.', 'Sola lettura'),
            ]),
            self::sezione('amministrazione', 'Amministrazione — anagrafiche e impostazioni', 'Clienti, operatori, amministratori, profilo, messaggi', [
                self::voce('AMM-01', 'Elenco clienti e stato', 'Apri l’elenco e cerca un cliente per volta.', 'Nominativi e risultati coincidono; il filtro non include clienti non pertinenti.', 'Sola lettura'),
                self::voce('AMM-02', 'Dettaglio cliente e sedi', 'Apri un cliente noto e controlla le sedi associate.', 'Nessuna sede di un altro cliente compare.', 'Sola lettura'),
                self::voce('AMM-03', 'Elenco operatori e stato', 'Cerca un operatore attivo e uno non attivo.', 'Nominativo, codice e stato sono quelli attesi.', 'Sola lettura'),
                self::voce('AMM-04', 'Dettaglio e assegnazioni operatore', 'Apri il dettaglio e i documenti visibili.', 'Nessun documento di un altro operatore è esposto.', 'Sola lettura'),
                self::voce('AMM-05', 'Utenti amministratori e registro accessi', 'Apri utenti e accessi e confronta una registrazione nota.', 'Pagine solo per lo staff; utente e data della registrazione corretti.', 'Sola lettura'),
                self::voce('AMM-06', 'Impostazioni e modelli', 'Apri configurazioni e modelli senza salvarli.', 'I valori restano quelli già presenti.', 'Consultazione, non salvare'),
                self::voce('AMM-07', 'Profilo del proprio account', 'Apri il profilo e verifica nome e recapiti.', 'Il profilo è quello dell’account autenticato.', 'Sola lettura'),
                self::voce('AMM-08', 'Creazione di anagrafica', 'Crea un cliente di test con i campi obbligatori.', 'Salvataggio completo e nessuna anagrafica aggiuntiva.', 'Solo record di test'),
                self::voce('AMM-09', 'Campo obbligatorio o formato non valido', 'Prova una partita IVA non valida.', 'Messaggio sul campo e nessuna riga parziale.', 'Solo record di test'),
            ]),
            self::sezione('operativo', 'Operativo — pianificazione turni e GDA', 'Agenda, filtri, turni completati, giornale delle attività', [
                self::voce('OPS-01', 'Calendario turni noto', 'Apri il periodo del turno di prova.', 'Giorno, sede e orario coincidono con il piano.', 'Sola lettura'),
                self::voce('OPS-02', 'Filtro cliente e intervallo date', 'Seleziona un cliente e un intervallo.', 'Restano solo i turni di quel cliente e di quel periodo.', 'Sola lettura'),
                self::voce('OPS-03', 'Filtro e legenda stato turno', 'Filtra i turni completati.', 'Un turno aperto non è contato come completato.', 'Sola lettura'),
                self::voce('OPS-04', 'Dettaglio di turno completato', 'Apri un turno noto e leggi orari e note.', 'Cliente, sede, operatore e nota corrispondono.', 'Sola lettura'),
                self::voce('OPS-05', 'GDA senza filtro e con criterio', 'Apri il GDA senza selezioni, poi con un cliente.', 'Senza criterio non c’è un totale; con criterio il totale è quello dei turni completati.', 'Sola lettura'),
                self::voce('OPS-06', 'Stampa / anteprima GDA', 'Apri l’anteprima su un cliente noto.', 'Compaiono i suoi turni e non quelli di un altro cliente. Senza criterio la stampa è rifiutata.', 'Anteprima'),
                self::voce('OPS-07', 'Coerenza dei conteggi', 'Confronta i turni completati fra agenda e GDA.', 'Lo stesso turno non è duplicato e la classificazione coincide.', 'Sola lettura'),
                self::voce('OPS-08', 'Apertura e chiusura turno manuale', 'Registra inizio e fine solo sul turno di prova.', 'Data, ora e nota sono sul turno previsto; l’altro turno resta invariato.', 'Solo turno di prova'),
            ]),
            self::sezione('presenze', 'Presenze — timbrature e conteggio ore', 'Presenze clienti e operatori, turni personali, stampe ed esportazioni', [
                self::voce('PRE-01', 'Presenze clienti', 'Apri la vista clienti per il giorno del turno.', 'Cliente, sede e orario corrispondono al registro.', 'Sola lettura'),
                self::voce('PRE-02', 'Presenze operatori', 'Apri la vista operatori per lo stesso periodo.', 'Operatore e orari sono corretti e la pagina è solo per lo staff.', 'Sola lettura'),
                self::voce('PRE-03', 'Esportazione presenze', 'Esporta l’intervallo del registro di prova.', 'Il file contiene le righe filtrate e non quelle di un altro cliente.', 'Non inoltrare il file'),
                self::voce('PRE-04', 'Riepilogo ore cliente', 'Apri il riepilogo di un cliente e di un periodo.', 'Ore previste, lavorate e saldo coincidono con i turni.', 'Sola lettura'),
                self::voce('PRE-05', 'Miei turni operatore', 'Con account operatore apri i turni.', 'L’utente vede solo i propri turni.', 'Sola lettura'),
                self::voce('PRE-06', 'Scansione QR di prova', 'Apri la scansione sul turno designato senza confermare la timbratura.', 'La sede è quella del turno e nessuna presenza viene creata.', 'QR e account di test'),
                self::voce('PRE-07', 'Sede senza coordinate', 'Tenta la timbratura su una sede senza coordinate.', 'Operazione rifiutata e nessuna presenza registrata.', 'Solo osservazione'),
                self::voce('PRE-08', 'Timbratura inizio e fine', 'Esegui inizio e fine sul turno di prova.', 'Una sola inizio e una sola fine, con la sede corretta.', 'Solo turno di prova'),
            ]),
            self::sezione('clienti', 'Clienti — sedi, postazioni e codici QR', 'Consultazione delle sedi e verifica dei codici senza timbrare', [
                self::voce('CLI-01', 'Elenco sedi cliente', 'Apri le sedi di un cliente noto.', 'Sono elencate solo le sedi di quel cliente.', 'Sola lettura'),
                self::voce('CLI-02', 'Dettaglio sede, indirizzo e foto', 'Apri una sede nota e scarica la foto.', 'Indirizzo e immagine sono della sede corretta.', 'Sola lettura'),
                self::voce('CLI-03', 'Postazioni della sede', 'Apri le postazioni della sede.', 'Nessuna postazione di un’altra sede appare.', 'Sola lettura'),
                self::voce('CLI-04', 'QR sede senza timbratura', 'Scarica il QR della sede senza avviare una scansione.', 'Il file è quello del codice associato e non nasce una presenza.', 'Non scansionare'),
                self::voce('CLI-05', 'QR postazione e destinazione', 'Scarica il QR della postazione.', 'Il codice è quello della postazione e non viene registrato un evento.', 'Solo visualizzazione'),
                self::voce('CLI-06', 'Generatore QR: selezione e anteprima', 'Controlla l’anteprima senza generare codici.', 'L’anteprima usa il prossimo codice e il titolo, senza scrivere righe.', 'Anteprima'),
                self::voce('CLI-07', 'Nuova sede e postazione', 'Crea una sede di test con QR e postazione.', 'Il record è del cliente scelto; l’altra sede resta invariata.', 'Solo record di test'),
                self::voce('CLI-08', 'Isolamento dati tra clienti', 'Con un account cliente apri menu e URL di una sede altrui.', 'La sede altrui non è visibile e la foto risponde con accesso negato.', 'Solo consultazione'),
            ]),
            self::sezione('operatori', 'Operatori — bacheca, documenti e profilo', 'Visibilità, lettura e notifiche personali', [
                self::voce('OPE-01', 'Bacheca e destinatari', 'Apri un avviso noto.', 'Il testo è visibile solo agli operatori previsti.', 'Sola lettura'),
                self::voce('OPE-02', 'Conteggio avvisi non letti', 'Confronta il badge con gli avvisi non letti.', 'Il conteggio è limitato all’account dell’operatore.', 'Sola lettura'),
                self::voce('OPE-03', 'Elenco e download documenti', 'Scarica un documento dell’account.', 'Il file è integro e un altro operatore non lo scarica.', 'Sola lettura'),
                self::voce('OPE-04', 'Campanella e badge notifiche', 'Apri l’elenco delle notifiche non lette.', 'Compaiono solo gli avvisi dell’operatore.', 'Aprire può segnare letto'),
                self::voce('OPE-05', 'Profilo e fotografia', 'Visualizza profilo e foto senza modificarli.', 'Sono dell’account autenticato; la foto altrui è negata.', 'Sola lettura'),
                self::voce('OPE-06', 'Turni e istruzioni personali', 'Apri i turni assegnati.', 'Solo i turni propri, con la nota operativa.', 'Sola lettura'),
                self::voce('OPE-07', 'Documento scaduto o mancante', 'Consulta un documento scaduto e un elenco vuoto.', 'La scadenza è esplicita e non compare un documento di un altro operatore.', 'Sola lettura'),
                self::voce('OPE-08', 'Marcare come letto', 'Segna letto un solo avviso.', 'Cambia solo quello; testo e altri destinatari restano invariati.', 'Modifica una registrazione'),
            ]),
            self::sezione('comunicazioni', 'Comunicazioni — SMS, email e notifiche', 'Modelli, anteprime, stato consegna e invii controllati', [
                self::voce('COMMS-01', 'Modelli, canali e limiti invio', 'Consulta un modello senza salvarlo.', 'Limiti e testi visibili restano invariati nel database.', 'Non salvare'),
                self::voce('COMMS-02', 'Anteprima e segnaposto', 'Componi il testo con un dato di prova.', 'I segnaposto sono sostituiti e non compare un altro operatore.', 'Solo anteprima'),
                self::voce('COMMS-03', 'Destinatario e contenuti della prova', 'Leggi turni e documenti dello stesso operatore.', 'I dati appartengono solo al destinatario selezionato.', 'Non inviare'),
                self::voce('COMMS-04', 'Registro invii e stato consegna', 'Consulta un invio storico fallito.', 'Lo stato non è presentato come consegnato.', 'Consultazione'),
                self::voce('COMMS-05', 'Invio email di prova', 'Invia solo al recapito interno di test.', 'La mail arriva soltanto a quell’indirizzo.', 'Solo recapito di test'),
                self::voce('COMMS-06', 'Esito errore del provider', 'Registra un esito negativo del provider.', 'Il fallimento non è presentato come successo.', 'Consultazione'),
                self::voce('COMMS-07', 'SMS disattivati', 'Invia con gli SMS spenti e l’email accesa.', 'Nessun SMS parte; l’email segue la regola del modello.', 'Non modificare la configurazione'),
                self::voce('COMMS-08', 'Azioni di invio non autorizzate', 'Lancia il promemoria in prova sicura insieme a un operatore reale.', 'Riceve la mail solo l’indirizzo di prova e non partono SMS.', 'Non eseguire su utenti reali'),
            ]),
            self::sezione('accesso', 'Accesso — login, recupero password e sessione', 'Verifiche con account di test', [
                self::voce('AUTH-01', 'Login valido', 'Accedi con un account attivo.', 'Accesso riuscito e pagina iniziale del ruolo.', 'Account di test'),
                self::voce('AUTH-02', 'Logout e protezione sessione', 'Esci e richiedi di nuovo una pagina riservata.', 'La sessione è terminata e i dati non sono consultabili.', 'Account di test'),
                self::voce('AUTH-03', 'Recupero password', 'Chiedi il recupero sull’account di test.', 'L’email va solo a quella casella e la nuova password è salvata.', 'Solo account di test'),
                self::voce('AUTH-04', 'Account disattivato', 'Prova l’accesso con un account già inattivo.', 'Accesso negato, senza contenuti riservati.', 'Account di test'),
                self::voce('AUTH-05', 'Protezione da tentativi ripetuti', 'Sbaglia la password più volte, poi riprova con quella giusta dopo lo sblocco.', 'I tentativi sono limitati e l’account non resta disattivato.', 'Non testare su account reali'),
            ]),
        ];
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_column(self::casi(), 'id');
    }

    /**
     * @return array{id: string, titolo: string, passaggi: string, atteso: string, produzione: string, metodo: string, sezione: string}|null
     */
    public static function caso(string $id): ?array
    {
        foreach (self::casi() as $caso) {
            if ($caso['id'] === $id) {
                return $caso;
            }
        }

        return null;
    }

    /**
     * @return list<array{id: string, titolo: string, passaggi: string, atteso: string, produzione: string, metodo: string, sezione: string}>
     */
    public static function casi(): array
    {
        $casi = [];

        foreach (self::sezioni() as $sezione) {
            foreach ($sezione['casi'] as $caso) {
                $casi[] = $caso + ['sezione' => $sezione['id']];
            }
        }

        return $casi;
    }

    /**
     * @param  list<array{id: string, titolo: string, passaggi: string, atteso: string, produzione: string}>  $casi
     * @return array{id: string, titolo: string, sintesi: string, casi: list<array{id: string, titolo: string, passaggi: string, atteso: string, produzione: string, metodo: string}>}
     */
    private static function sezione(string $id, string $titolo, string $sintesi, array $casi): array
    {
        return [
            'id' => $id,
            'titolo' => $titolo,
            'sintesi' => $sintesi,
            'casi' => array_map(
                fn (array $caso): array => $caso + ['metodo' => 'test_'.strtolower(str_replace('-', '_', $caso['id']))],
                $casi,
            ),
        ];
    }

    /**
     * @return array{id: string, titolo: string, passaggi: string, atteso: string, produzione: string}
     */
    private static function voce(string $id, string $titolo, string $passaggi, string $atteso, string $produzione): array
    {
        return compact('id', 'titolo', 'passaggi', 'atteso', 'produzione');
    }
}
