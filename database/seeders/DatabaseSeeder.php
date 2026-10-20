<?php

namespace Database\Seeders;

use App\Enums\StatoTurno;
use App\Models\Avviso;
use App\Models\Cliente;
use App\Models\CodiceQr;
use App\Models\ModelloMessaggio;
use App\Models\Postazione;
use App\Models\Presenza;
use App\Models\Sede;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $mk = fn ($name, $cognome, $email, $ruolo, $extra = []) => User::create([
            'name' => $name, 'cognome' => $cognome, 'email' => $email, 'password' => 'password', 'ruolo' => $ruolo,
        ] + $extra);

        $mk('Super', 'Admin', 'super@topsecurity.test', 'super_admin');
        $mk('Alessio', 'Admin', 'admin@topsecurity.test', 'admin');

        mt_srand(420);

        $nomiOperatori = [
            ['Marco', 'Rossi', 'Milano'], ['Luca', 'Bianchi', 'Milano'], ['Giulia', 'Verdi', 'Roma'], ['Sara', 'Neri', 'Roma'],
            ['Davide', 'Colombo', 'Milano'], ['Elena', 'Ricci', 'Torino'], ['Andrea', 'Marino', 'Roma'], ['Francesca', 'Greco', 'Milano'],
            ['Matteo', 'Bruno', 'Torino'], ['Chiara', 'Gallo', 'Roma'], ['Simone', 'Conti', 'Milano'], ['Valentina', 'De Luca', 'Torino'],
        ];
        $operatori = collect($nomiOperatori)->map(fn ($n, $i) => $mk($n[0], $n[1], strtolower(str_replace(' ', '', $n[0])).'@topsecurity.test', 'operatore', [
            'codice' => 'GUARDIA '.($i + 1), 'costo_orario' => [9, 9.5, 10, 10.5][$i % 4],
            'cellulare' => '3'.mt_rand(20, 49).mt_rand(1000000, 9999999),
        ]));

        $anagrafica = [
            ['Banca Centrale Spa', 'Milano', 'MI', '20121', 'Via Monte Napoleone 12', 45.4686, 9.1952, 'Giovanni', 'Ferrari', 'Filiale Centro', 'Caveau e sportelli', false],
            ['Centro Commerciale Il Giglio', 'Roma', 'RM', '00198', 'Via Salaria 450', 41.9232, 12.5101, 'Paola', 'Romano', 'Galleria principale', 'Parcheggio multipiano', false],
            ['Logistica Nord Srl', 'Torino', 'TO', '10148', 'Corso Unione Sovietica 612', 45.0207, 7.6560, 'Stefano', 'Fontana', 'Magazzino A', 'Varco merci', false],
            ['Hotel Bellavista', 'Milano', 'MI', '20124', 'Via Vittor Pisani 22', 45.4849, 9.2040, 'Laura', 'Esposito', 'Reception', 'Garage', false],
            ['Azienda Demo (prova)', 'Roma', 'RM', '00144', 'Viale Europa 100', 41.8267, 12.4670, 'Test', 'Prova', 'Sede demo', 'Ingresso', true],
        ];

        $clienti = collect($anagrafica)->map(fn ($c) => Cliente::create([
            'ragione_sociale' => $c[0], 'citta' => $c[1], 'provincia' => $c[2], 'cap' => $c[3], 'indirizzo' => $c[4],
            'lat' => $c[5], 'lng' => $c[6], 'referente_nome' => $c[7], 'referente_cognome' => $c[8],
            'telefono' => '02'.mt_rand(10000000, 99999999), 'email' => 'info@'.str($c[0])->slug().'.it',
            'codice_fiscale' => (string) mt_rand(10000000000, 99999999999), 'piva' => (string) mt_rand(10000000000, 99999999999),
            'inizio_contratto' => today()->subMonths(8), 'fine_contratto' => today()->addMonths(16), 'is_test' => $c[11],
        ]));

        $mk('Cliente', 'Demo', 'cliente@topsecurity.test', 'cliente', ['cliente_id' => $clienti[0]->id]);
        $mk('Paola', 'Romano', 'paola.romano@topsecurity.test', 'cliente', ['cliente_id' => $clienti[1]->id]);

        foreach ($clienti as $i => $cliente) {
            $c = $anagrafica[$i];
            $sede = Sede::create([
                'cliente_id' => $cliente->id, 'descrizione' => $c[9], 'indirizzo' => $c[4], 'citta' => $c[1], 'provincia' => $c[2], 'cap' => $c[3],
                'lat' => $c[5], 'lng' => $c[6], 'distanza_max' => 150, 'tariffa' => 16 + $i, 'telefono' => $cliente->telefono, 'email' => $cliente->email,
                'ore_richieste' => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 4, 7 => 0],
            ]);
            $sede->update(['codice_qr_id' => CodiceQr::nuovo(CodiceQr::SEDE, $sede->id)->id]);

            $postazioni = collect(['Ingresso', $c[10]])->map(function ($titolo) use ($sede, $cliente) {
                $p = Postazione::create(['sede_id' => $sede->id, 'cliente_id' => $cliente->id, 'titolo' => $titolo, 'minuti_passaggio' => 60]);
                $p->update(['codice_qr_id' => CodiceQr::nuovo(CodiceQr::POSTAZIONE, $sede->id, $p->id)->id]);

                return $p->fresh();
            });

            if ($i === 1 || $i === 3) {
                $sec = Sede::create([
                    'cliente_id' => $cliente->id, 'descrizione' => 'Sede secondaria', 'indirizzo' => 'Via Garibaldi 8', 'citta' => $c[1], 'provincia' => $c[2], 'cap' => $c[3],
                    'lat' => $c[5] + 0.01, 'lng' => $c[6] + 0.01, 'distanza_max' => 120, 'tariffa' => 15,
                ]);
                $sec->update(['codice_qr_id' => CodiceQr::nuovo(CodiceQr::SEDE, $sec->id)->id]);
            }

            $zona = $operatori->filter(fn ($o, $k) => $nomiOperatori[$k][2] === $c[1])->values();
            if ($zona->count() < 2) {
                $zona = $operatori;
            }

            foreach (range(-10, 14) as $d) {
                $giorno = today()->addDays($d);
                if ($giorno->isSunday()) {
                    continue;
                }
                foreach ($giorno->isSaturday() ? [[8, 12]] : [[8, 16], [16, 20]] as $k => [$da, $a]) {
                    $op = $zona[(($d + $k + $i) % $zona->count() + $zona->count()) % $zona->count()];
                    $inizio = $giorno->copy()->setTime($da, 0);
                    $fine = $giorno->copy()->setTime($a, 0);
                    $passato = $fine->lt(now());
                    $ritardo = $passato ? [0, 0, 3, 5, 12][mt_rand(0, 4)] : null;
                    $turno = Turno::create([
                        'operatore_id' => $op->id, 'cliente_id' => $cliente->id, 'sede_id' => $sede->id,
                        'inizio' => $inizio, 'fine' => $fine, 'ore' => $a - $da,
                        'stato' => $passato ? StatoTurno::Completato : StatoTurno::Programmato,
                        'ore_lavorate' => $passato ? $a - $da : null,
                    ]);
                    if ($passato) {
                        $base = ['turno_id' => $turno->id, 'operatore_id' => $op->id, 'cliente_id' => $cliente->id, 'sede_id' => $sede->id,
                            'lat' => $c[5], 'lng' => $c[6], 'distanza_km' => mt_rand(1, 9) / 100];
                        Presenza::create($base + ['tipo' => 'I', 'codice_qr_id' => $sede->codice_qr_id, 'rilevata_at' => $inizio->copy()->addMinutes($ritardo), 'ritardo_minuti' => $ritardo]);
                        foreach ($postazioni as $p) {
                            Presenza::create($base + ['tipo' => 'P', 'postazione_id' => $p->id, 'codice_qr_id' => $p->codice_qr_id, 'rilevata_at' => $inizio->copy()->addMinutes(mt_rand(60, 180))]);
                        }
                        Presenza::create($base + ['tipo' => 'F', 'codice_qr_id' => $sede->codice_qr_id, 'rilevata_at' => $fine->copy()->addMinutes(mt_rand(0, 6))]);
                    }
                }
            }
        }

        ModelloMessaggio::create([
            'nome' => 'Promemoria turno', 'giorni_prima' => 1, 'oggetto' => 'Promemoria turno del [DATA TURNO]',
            'testo_email' => "Ciao [OPERATORE],\nti ricordiamo il turno presso [SEDE] il [DATA TURNO] ([ORA TURNO]).\n[INDIRIZZO]",
        ]);

        foreach ([
            ['Benvenuti in Security Scanner', 'Da oggi le timbrature avvengono tramite QR code e GPS.'],
            ['Nuove divise', 'Il ritiro delle nuove divise è previsto in sede il prossimo lunedì dalle 9 alle 12.'],
            ['Corso sicurezza', 'Ricordiamo il corso di aggiornamento obbligatorio: controlla la scadenza dei tuoi documenti.'],
        ] as [$titolo, $testo]) {
            Avviso::create(['titolo' => $titolo, 'testo' => $testo, 'autore_id' => 2])->destinatari()->attach($operatori->pluck('id'));
        }
    }
}
