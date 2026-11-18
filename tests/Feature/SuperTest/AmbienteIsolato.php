<?php

namespace Tests\Feature\SuperTest;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;

trait AmbienteIsolato
{
    protected function preparaAmbiente(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'filesystems.files_disk' => 'local',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Schema::create('tc_admin', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['user', 'password', 'attivo', 'nome', 'cognome', 'email', 'cellulare', 'codice', 'avatar', 'colore', 'data_nascita', 'luogo_nascita', 'indirizzo', 'iban'] as $colonna) {
                $table->string($colonna)->nullable();
            }
            $table->unsignedInteger('user_type')->nullable();
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('provincia')->nullable();
            $table->float('costo_orario')->nullable();
            $table->dateTime('last_login')->nullable();
        });

        Schema::create('_tracking', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('id_user')->nullable();
            $table->string('addr')->nullable();
            $table->string('host')->nullable();
            $table->string('agent')->nullable();
            $table->string('pagina')->nullable();
            $table->dateTime('data_view')->nullable();
            $table->text('action')->nullable();
        });

        Schema::create('tc_clienti', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['accesso', 'ragione_sociale', 'codice_fiscale', 'piva', 'indirizzo', 'cap', 'nome', 'cognome', 'telefono', 'cellulare', 'fax', 'email', 'pec', 'colore', 'pos'] as $colonna) {
                $table->string($colonna)->nullable();
            }
            $table->integer('citta_id')->nullable();
            $table->integer('provincia_id')->nullable();
            $table->float('lat')->nullable();
            $table->float('lng')->nullable();
            $table->integer('isTest')->nullable();
        });

        Schema::create('tc_sedi', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_codice')->nullable();
            foreach (['descrizione', 'indirizzo', 'cap', 'telefono', 'email', 'note', 'colore', 'pos', 'foto'] as $colonna) {
                $table->string($colonna)->nullable();
            }
            $table->integer('citta_id')->nullable();
            $table->integer('provincia_id')->nullable();
            $table->float('lat')->default(0);
            $table->float('lng')->default(0);
            $table->date('inizio_contratto')->nullable();
            $table->date('fine_contratto')->nullable();
            $table->float('tariffa')->nullable();
            $table->integer('distanza')->default(80);
            foreach (['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'] as $colonna) {
                $table->integer($colonna)->nullable();
            }
        });

        Schema::create('tc_postazioni', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_sede')->nullable();
            $table->unsignedInteger('id_codice')->nullable();
            $table->string('titolo')->nullable();
            $table->text('info')->nullable();
            $table->string('file')->nullable();
            $table->integer('passaggi')->nullable();
            $table->integer('minuti')->nullable();
        });

        Schema::create('tc_codici', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('tipo')->nullable();
            $table->unsignedInteger('id_sede')->nullable();
            $table->unsignedInteger('id_postazione')->nullable();
            $table->string('codice')->nullable();
            $table->string('stato')->nullable();
        });

        Schema::create('tc_turni', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_sede')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->date('data_inizio')->nullable();
            $table->date('data_fine')->nullable();
            $table->string('ora_inizio')->nullable();
            $table->string('ora_fine')->nullable();
            $table->float('ore')->nullable();
            $table->float('ore_lavorate')->nullable();
            $table->string('stato')->nullable();
            $table->text('note_operatore')->nullable();
        });

        Schema::create('tc_gda', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_sede')->nullable();
            $table->unsignedInteger('id_turno')->nullable();
            $table->unsignedInteger('id_postazione')->nullable();
            $table->unsignedInteger('id_codice')->nullable();
            $table->date('data')->nullable();
            $table->string('orario')->nullable();
            $table->text('note_operatore')->nullable();
            $table->float('lat')->nullable();
            $table->float('lng')->nullable();
            $table->float('distanza')->nullable();
            $table->integer('ritardo')->nullable();
            $table->string('tipo')->nullable();
        });

        Schema::create('tc_turni_step', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
        });

        Schema::create('_provincia', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nome')->nullable();
            $table->string('sigla')->nullable();
        });

        Schema::create('_comuni', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_provincia')->nullable();
            $table->string('comune')->nullable();
            $table->string('sigla_provincia')->nullable();
        });

        Schema::create('tc_documenti', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_tipo')->nullable();
            $table->string('tipo')->nullable();
            $table->unsignedInteger('tipologia')->nullable();
            $table->text('descrizione')->nullable();
            $table->string('file')->nullable();
            $table->string('icona')->nullable();
            $table->date('inizio')->nullable();
            $table->date('fine')->nullable();
            $table->date('scadenza')->nullable();
            $table->string('admin')->nullable();
            $table->string('formazione')->nullable();
        });

        Schema::create('tc_documenti_view', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_documento')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->string('visto')->nullable();
            $table->dateTime('data_vista')->nullable();
        });

        Schema::create('_certificati', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nome')->nullable();
        });

        Schema::create('_materiali', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nome')->nullable();
        });

        Schema::create('tc_materiali', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
            $table->unsignedInteger('id_materiale')->nullable();
        });

        foreach (['tc_operatori_preferiti', 'tc_operatori_escludi'] as $tabella) {
            Schema::create($tabella, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('id_cliente');
                $table->unsignedInteger('id_operatore');
            });
        }

        Schema::create('tc_bacheca', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
            $table->string('titolo')->nullable();
            $table->text('testo')->nullable();
            $table->integer('quanti')->nullable();
            $table->date('data_invio')->nullable();
            $table->string('tutti')->nullable();
        });

        Schema::create('tc_bacheca_view', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_bacheca')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->string('visto')->nullable();
            $table->dateTime('data_vista')->nullable();
        });

        Schema::create('tc_messaggi', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nome')->nullable();
            $table->integer('giorni')->nullable();
            $table->integer('ore')->nullable();
            $table->integer('minuti')->nullable();
            $table->string('oggetto')->nullable();
            $table->string('mittente')->nullable();
            $table->text('modello_email')->nullable();
            $table->text('modello_sms')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('admin_sms')->nullable();
            $table->integer('numero_sms')->nullable();
            $table->integer('numero_email')->nullable();
            $table->string('invia_email')->nullable();
            $table->string('invia_sms')->nullable();
            $table->date('segnato_fino_al')->nullable();
        });

        Schema::create('tc_config', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['app', 'ragione_sociale', 'colore', 'colore_primario', 'societa_indirizzo', 'societa_cap', 'societa_comune', 'societa_provincia', 'societa_telefono', 'societa_email', 'societa_iva', 'phone', 'email'] as $colonna) {
                $table->string($colonna)->nullable();
            }
        });

        Schema::create('tc_email', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
            $table->string('oggetto')->nullable();
            $table->text('email')->nullable();
            $table->text('destinatario')->nullable();
            $table->dateTime('data_invio')->nullable();
        });

        Schema::create('tc_send_sms', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('destinatari')->nullable();
            $table->string('sender')->nullable();
            $table->text('testo')->nullable();
            $table->integer('quanti')->nullable();
            $table->dateTime('data_invio')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->string('sent')->nullable();
        });

        Schema::create('tc_send_sms_result', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('send_id')->nullable();
            $table->string('adl_id')->nullable();
            $table->float('credits')->nullable();
            $table->float('costo')->nullable();
            $table->string('dest')->nullable();
            $table->text('response')->nullable();
            $table->text('message')->nullable();
            $table->string('numero')->nullable();
        });

        Schema::create('tc_send_sms_delivery', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('send_id')->nullable();
            $table->string('refer_id')->nullable();
            $table->string('delivery_status')->nullable();
            $table->dateTime('delivery_data')->nullable();
        });

        Schema::create('tc_send_whatsapp', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
            $table->dateTime('data_invio')->nullable();
        });

        Schema::create('tc_ritardi', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_turno')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
        });

        $pdo = DB::connection('mysql')->getPdo();
        if ($pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('DATE_FORMAT', function (?string $data, ?string $formato): ?string {
                if ($data === null || $data === '') {
                    return null;
                }

                $istante = strtotime($data);
                $formatoPhp = strtr((string) $formato, ['%d' => 'd', '%m' => 'm', '%Y' => 'Y', '%H' => 'H', '%i' => 'i', '%s' => 's']);

                return $istante === false ? null : date($formatoPhp, $istante);
            }, 2);
        }

        Storage::fake('local');
    }

    protected function utente(array $dati = []): User
    {
        return User::create($dati + [
            'user' => 'utente-'.uniqid(),
            'password' => 'secret',
            'attivo' => 'Y',
            'user_type' => 2,
            'nome' => 'Ada',
            'cognome' => 'Neri',
            'email' => 'ada@example.test',
        ]);
    }
}
