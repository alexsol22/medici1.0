<?php

namespace App\SuperTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Copia in sola lettura le tabelle applicative dentro un file SQLite. */
class Copiatore
{
    /** @var list<string> */
    public const TABELLE = [
        'tc_config',
        'tc_admin',
        'tc_clienti',
        'tc_sedi',
        'tc_postazioni',
        'tc_codici',
        'tc_turni',
        'tc_gda',
        'tc_turni_step',
        'tc_documenti',
        'tc_documenti_view',
        'tc_bacheca',
        'tc_bacheca_view',
        'tc_messaggi',
        'tc_email',
        'tc_send_email',
        'tc_send_sms',
        'tc_send_sms_result',
        'tc_send_sms_delivery',
        'tc_send_whatsapp',
        'tc_operatori_preferiti',
        'tc_operatori_escludi',
        'tc_materiali',
        'tc_ritardi',
        '_provincia',
        '_comuni',
        '_certificati',
        '_materiali',
        '_config',
        '_nazione',
        '_colori',
        '_mesi',
        '_tracking',
    ];

    public function copia(string $destinazione, string $id, string $origine = 'mysql'): void
    {
        if (! SessioneReale::validaId($id)) {
            return;
        }

        if (! is_file($destinazione)) {
            touch($destinazione);
        }

        chmod($destinazione, 0600);

        config([
            'database.connections.supertest_copia' => [
                'driver' => 'sqlite',
                'database' => $destinazione,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('supertest_copia');

        foreach (self::TABELLE as $tabella) {
            if (SessioneReale::fermata($id)) {
                return;
            }

            if (! Schema::connection($origine)->hasTable($tabella)) {
                continue;
            }

            $this->copiaTabella($origine, $tabella, $id);
        }

        chmod($destinazione, 0600);
    }

    private function copiaTabella(string $origine, string $tabella, string $id): void
    {
        $colonne = array_values(array_filter(
            Schema::connection($origine)->getColumns($tabella),
            fn (array $colonna): bool => preg_match('/^[A-Za-z0-9_]+$/', $colonna['name']) === 1,
        ));

        if ($colonne === []) {
            return;
        }

        $nomi = array_column($colonne, 'name');
        $totale = (int) DB::connection($origine)->table($tabella)->count();
        SessioneReale::aggiornaTabella($id, ['nome' => $tabella, 'totale' => $totale, 'copiate' => 0]);

        Schema::connection('supertest_copia')->dropIfExists($tabella);
        Schema::connection('supertest_copia')->create($tabella, function (Blueprint $blueprint) use ($colonne): void {
            foreach ($colonne as $colonna) {
                $this->aggiungiColonna($blueprint, $colonna);
            }
        });

        $chiave = in_array('id', $nomi, true) ? 'id' : $nomi[0];
        $copiate = 0;
        $bloccoMassimo = max(1, intdiv(900, count($nomi)));

        DB::connection($origine)->table($tabella)->orderBy($chiave)->chunk($bloccoMassimo, function ($righe) use ($tabella, $nomi, $id, $totale, &$copiate): bool {
            if (SessioneReale::fermata($id)) {
                return false;
            }

            $blocco = [];

            foreach ($righe as $riga) {
                $valori = (array) $riga;
                $record = [];

                foreach ($nomi as $nome) {
                    $record[$nome] = $this->normalizza($valori[$nome] ?? null);
                }

                $blocco[] = $record;
            }

            if ($blocco !== []) {
                DB::connection('supertest_copia')->table($tabella)->insert($blocco);
            }

            $copiate += count($blocco);
            SessioneReale::aggiornaTabella($id, ['nome' => $tabella, 'totale' => $totale, 'copiate' => $copiate]);

            return true;
        });
    }

    /**
     * @param  array{name: string, type_name: string, auto_increment: bool, nullable: bool}  $colonna
     */
    private function aggiungiColonna(Blueprint $blueprint, array $colonna): void
    {
        $nome = $colonna['name'];
        $tipo = strtolower($colonna['type_name']);

        if ($nome === 'id') {
            $definizione = $blueprint->increments($nome);
            $definizione->nullable(false);

            return;
        }

        $definizione = match (true) {
            in_array($tipo, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint', 'year'], true) => $blueprint->bigInteger($nome),
            in_array($tipo, ['decimal', 'float', 'double', 'real', 'numeric'], true) => $blueprint->double($nome),
            in_array($tipo, ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary'], true) => $blueprint->binary($nome),
            default => $blueprint->text($nome),
        };

        $definizione->nullable();
    }

    private function normalizza(mixed $valore): mixed
    {
        if (! is_string($valore)) {
            return $valore;
        }

        if (preg_match('/^0000-00-00/', $valore) === 1) {
            return null;
        }

        return $valore;
    }

    public static function messaggio(Throwable $errore): string
    {
        $testo = preg_replace('/\s+/', ' ', $errore->getMessage()) ?? 'Copia non riuscita.';

        return 'Copia non riuscita. '.mb_substr($testo, 0, 280);
    }
}
