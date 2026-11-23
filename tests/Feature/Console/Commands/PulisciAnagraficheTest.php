<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PulisciAnagraficheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = ':memory:';
        $sqlite['foreign_key_constraints'] = false;
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => $sqlite,
        ]);
        DB::purge('mysql');
        Storage::fake(config('filesystems.files_disk'));

        Schema::create('tc_clienti', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('ragione_sociale')->nullable();
        });

        Schema::create('tc_admin', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_type')->nullable();
            $table->string('email')->nullable();
            $table->string('nome')->nullable();
            $table->string('cognome')->nullable();
            $table->unsignedInteger('id_cliente')->nullable();
            $table->string('avatar')->nullable();
        });

        Schema::create('tc_sedi', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
        });

        Schema::create('tc_postazioni', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_sede')->nullable();
        });

        Schema::create('tc_codici', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_sede')->nullable();
            $table->unsignedInteger('id_postazione')->nullable();
            $table->string('stato')->nullable();
            $table->string('tipo')->nullable();
            $table->string('codice')->nullable();
        });

        Schema::create('tc_turni', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->unsignedInteger('id_sede')->nullable();
        });

        Schema::create('tc_gda', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->unsignedInteger('id_turno')->nullable();
        });

        Schema::create('tc_ritardi', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_turno')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('tc_turni_step', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('id_sede')->nullable();
        });

        Schema::create('tc_documenti', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_tipo')->nullable();
            $table->string('tipo')->nullable();
        });

        Schema::create('tc_documenti_view', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_documento')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('tc_materiali', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
        });

        foreach (['tc_operatori_preferiti', 'tc_operatori_escludi'] as $tabella) {
            Schema::create($tabella, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('id_cliente')->nullable();
                $table->unsignedInteger('id_operatore')->nullable();
            });
        }

        Schema::create('tc_bacheca', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('tc_bacheca_view', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_bacheca')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('tc_email', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('tc_send_email', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('tc_send_sms', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('tc_send_sms_result', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('send_id')->nullable();
        });

        Schema::create('tc_send_sms_delivery', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('send_id')->nullable();
        });

        Schema::create('tc_send_whatsapp', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_operatore')->nullable();
        });

        Schema::create('_tracking', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_user')->nullable();
        });
    }

    public function test_preview_lists_records_and_deletes_nothing(): void
    {
        $this->inserisciAnagrafiche();

        $this->artisan('app:pulisci-anagrafiche')
            ->expectsOutputToContain('Ditta di Prova  Campori')
            ->expectsOutputToContain('Nessun record eliminato')
            ->expectsOutputToContain('php artisan app:pulisci-anagrafiche --esegui')
            ->assertSuccessful();

        $this->assertSame(5, DB::table('tc_clienti')->count());
        $this->assertSame(6, DB::table('tc_turni')->count());
        $this->assertTrue(Storage::disk(config('filesystems.files_disk'))->exists('protected/documenti/Cliente_4/Sede_21/foto.jpg'));
    }

    public function test_execution_keeps_named_people_and_their_shifts(): void
    {
        $this->inserisciAnagrafiche();

        $this->artisan('app:pulisci-anagrafiche', ['--esegui' => true])
            ->expectsOutputToContain('Eliminazione completata')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing([1, 2, 3], DB::table('tc_clienti')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([9, 10, 11, 13, 14], DB::table('tc_admin')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([50, 51, 53, 54], DB::table('tc_turni')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([60, 61, 63], DB::table('tc_gda')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([70], DB::table('tc_ritardi')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([20], DB::table('tc_sedi')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([30], DB::table('tc_postazioni')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([80], DB::table('tc_turni_step')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([90, 92, 95], DB::table('tc_documenti')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([100], DB::table('tc_documenti_view')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([110], DB::table('tc_operatori_preferiti')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([113], DB::table('tc_operatori_escludi')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([120], DB::table('tc_materiali')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([130], DB::table('tc_bacheca')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([140], DB::table('tc_bacheca_view')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([150], DB::table('tc_email')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([161], DB::table('tc_send_sms')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([171], DB::table('tc_send_sms_result')->pluck('id')->all());
        $this->assertSame(0, DB::table('tc_send_sms_delivery')->count());
        $this->assertSame(0, DB::table('tc_send_whatsapp')->count());
        $this->assertEqualsCanonicalizing([200], DB::table('tc_send_email')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([211], DB::table('_tracking')->pluck('id')->all());

        $this->assertSame('A', DB::table('tc_codici')->where('id', 40)->value('stato'));
        $this->assertSame(20, (int) DB::table('tc_codici')->where('id', 40)->value('id_sede'));
        $this->assertSame('C', DB::table('tc_codici')->where('id', 41)->value('stato'));
        $this->assertNull(DB::table('tc_codici')->where('id', 41)->value('id_sede'));
        $this->assertNull(DB::table('tc_codici')->where('id', 42)->value('id_postazione'));

        $disco = Storage::disk(config('filesystems.files_disk'));
        $this->assertFalse($disco->exists('protected/documenti/Cliente_4/Sede_21/foto.jpg'));
        $this->assertTrue($disco->exists('protected/documenti/Cliente_2/Sede_20/foto.jpg'));
        $this->assertFalse($disco->exists('protected/documenti/Operatore_15/doc.txt'));
        $this->assertTrue($disco->exists('protected/documenti/Operatore_13/doc.txt'));
        $this->assertFalse($disco->exists('images/avatar/mario.jpg'));
        $this->assertTrue($disco->exists('images/avatar/ales.jpg'));
    }

    public function test_execution_stops_when_a_client_to_keep_is_missing(): void
    {
        $this->inserisciAnagrafiche();
        DB::table('tc_clienti')->where('id', 3)->delete();

        $this->artisan('app:pulisci-anagrafiche', ['--esegui' => true])
            ->expectsOutputToContain('Nessun cliente con ragione sociale «Prova 2»')
            ->expectsOutputToContain('Nessuna eliminazione eseguita')
            ->assertFailed();

        $this->assertSame(4, DB::table('tc_clienti')->count());
        $this->assertTrue(DB::table('tc_admin')->where('id', 15)->exists());
    }

    public function test_execution_stops_when_a_client_name_matches_more_than_one_row(): void
    {
        $this->inserisciAnagrafiche();
        DB::table('tc_clienti')->insert(['id' => 6, 'ragione_sociale' => 'Prova']);

        $this->artisan('app:pulisci-anagrafiche', ['--esegui' => true])
            ->expectsOutputToContain('Trovati 2 clienti con ragione sociale «Prova»')
            ->assertFailed();

        $this->assertTrue(DB::table('tc_clienti')->where('id', 4)->exists());
    }

    public function test_execution_stops_when_an_operator_email_is_missing(): void
    {
        $this->inserisciAnagrafiche();
        DB::table('tc_admin')->where('id', 14)->update(['email' => 'altra@example.test']);

        $this->artisan('app:pulisci-anagrafiche', ['--esegui' => true])
            ->expectsOutputToContain('Nessun operatore con email «sandrabolt69@gmail.com»')
            ->assertFailed();

        $this->assertTrue(DB::table('tc_clienti')->where('id', 4)->exists());
        $this->assertTrue(DB::table('tc_admin')->where('id', 15)->exists());
    }

    private function inserisciAnagrafiche(): void
    {
        DB::table('tc_clienti')->insert([
            ['id' => 1, 'ragione_sociale' => 'Ditta di Prova  Campori'],
            ['id' => 2, 'ragione_sociale' => 'Prova'],
            ['id' => 3, 'ragione_sociale' => 'Prova 2'],
            ['id' => 4, 'ragione_sociale' => 'Acme'],
            ['id' => 5, 'ragione_sociale' => 'Altro'],
        ]);

        DB::table('tc_admin')->insert([
            ['id' => 9, 'user_type' => 1, 'email' => 'super@example.test', 'nome' => 'Super', 'cognome' => 'Admin', 'id_cliente' => null, 'avatar' => null],
            ['id' => 10, 'user_type' => 2, 'email' => 'admin@example.test', 'nome' => 'Ada', 'cognome' => 'Admin', 'id_cliente' => 4, 'avatar' => null],
            ['id' => 11, 'user_type' => 3, 'email' => 'prova@example.test', 'nome' => 'Prova', 'cognome' => 'Cliente', 'id_cliente' => 2, 'avatar' => null],
            ['id' => 12, 'user_type' => 3, 'email' => 'acme@example.test', 'nome' => 'Acme', 'cognome' => 'Cliente', 'id_cliente' => 4, 'avatar' => null],
            ['id' => 13, 'user_type' => 4, 'email' => 'Alesolax55@gmail.com', 'nome' => 'Alessio', 'cognome' => 'Test', 'id_cliente' => 4, 'avatar' => 'images/avatar/ales.jpg'],
            ['id' => 14, 'user_type' => 4, 'email' => 'sandrabolt69@gmail.com', 'nome' => 'Sandra', 'cognome' => 'Bolt', 'id_cliente' => 2, 'avatar' => null],
            ['id' => 15, 'user_type' => 4, 'email' => 'mario@example.test', 'nome' => 'Mario', 'cognome' => 'Rossi', 'id_cliente' => 4, 'avatar' => 'mario.jpg'],
            ['id' => 16, 'user_type' => 3, 'email' => 'orfano@example.test', 'nome' => 'Orfano', 'cognome' => 'Cliente', 'id_cliente' => null, 'avatar' => null],
        ]);

        DB::table('tc_sedi')->insert([
            ['id' => 20, 'id_cliente' => 2],
            ['id' => 21, 'id_cliente' => 4],
        ]);

        DB::table('tc_postazioni')->insert([
            ['id' => 30, 'id_cliente' => 2, 'id_sede' => 20],
            ['id' => 31, 'id_cliente' => 4, 'id_sede' => 21],
        ]);

        DB::table('tc_codici')->insert([
            ['id' => 40, 'id_sede' => 20, 'id_postazione' => null, 'stato' => 'A', 'tipo' => 'S', 'codice' => 'ITALPOL*000040'],
            ['id' => 41, 'id_sede' => 21, 'id_postazione' => null, 'stato' => 'A', 'tipo' => 'S', 'codice' => 'ITALPOL*000041'],
            ['id' => 42, 'id_sede' => null, 'id_postazione' => 31, 'stato' => 'A', 'tipo' => 'P', 'codice' => 'ITALPOL*000042'],
        ]);

        DB::table('tc_turni')->insert([
            ['id' => 50, 'id_cliente' => 2, 'id_operatore' => 15, 'id_sede' => 20],
            ['id' => 51, 'id_cliente' => 4, 'id_operatore' => 13, 'id_sede' => 21],
            ['id' => 52, 'id_cliente' => 4, 'id_operatore' => 15, 'id_sede' => 21],
            ['id' => 53, 'id_cliente' => 1, 'id_operatore' => 14, 'id_sede' => 20],
            ['id' => 54, 'id_cliente' => null, 'id_operatore' => 13, 'id_sede' => null],
            ['id' => 55, 'id_cliente' => null, 'id_operatore' => null, 'id_sede' => null],
        ]);

        DB::table('tc_gda')->insert([
            ['id' => 60, 'id_cliente' => 2, 'id_operatore' => 15, 'id_turno' => 50],
            ['id' => 61, 'id_cliente' => 4, 'id_operatore' => 13, 'id_turno' => 51],
            ['id' => 62, 'id_cliente' => 4, 'id_operatore' => 15, 'id_turno' => 52],
            ['id' => 63, 'id_cliente' => 4, 'id_operatore' => 13, 'id_turno' => null],
            ['id' => 64, 'id_cliente' => 4, 'id_operatore' => 15, 'id_turno' => null],
        ]);

        DB::table('tc_ritardi')->insert([
            ['id' => 70, 'id_turno' => 50, 'id_operatore' => 15],
            ['id' => 71, 'id_turno' => 52, 'id_operatore' => 15],
        ]);

        DB::table('tc_turni_step')->insert([
            ['id' => 80, 'id_cliente' => 2, 'id_sede' => 20],
            ['id' => 81, 'id_cliente' => 4, 'id_sede' => 21],
        ]);

        DB::table('tc_documenti')->insert([
            ['id' => 90, 'id_tipo' => 2, 'tipo' => 'C'],
            ['id' => 91, 'id_tipo' => 4, 'tipo' => 'C'],
            ['id' => 92, 'id_tipo' => 13, 'tipo' => 'O'],
            ['id' => 93, 'id_tipo' => 15, 'tipo' => 'O'],
            ['id' => 94, 'id_tipo' => 21, 'tipo' => 'S'],
            ['id' => 95, 'id_tipo' => 20, 'tipo' => 'S'],
        ]);

        DB::table('tc_documenti_view')->insert([
            ['id' => 100, 'id_documento' => 90, 'id_operatore' => 13],
            ['id' => 101, 'id_documento' => 93, 'id_operatore' => 13],
            ['id' => 102, 'id_documento' => 90, 'id_operatore' => 15],
        ]);

        DB::table('tc_operatori_preferiti')->insert([
            ['id' => 110, 'id_cliente' => 2, 'id_operatore' => 13],
            ['id' => 111, 'id_cliente' => 2, 'id_operatore' => 15],
            ['id' => 112, 'id_cliente' => 4, 'id_operatore' => 13],
        ]);

        DB::table('tc_operatori_escludi')->insert([
            ['id' => 113, 'id_cliente' => 2, 'id_operatore' => 14],
            ['id' => 114, 'id_cliente' => 4, 'id_operatore' => 14],
        ]);

        DB::table('tc_materiali')->insert([
            ['id' => 120, 'id_operatore' => 13],
            ['id' => 121, 'id_operatore' => 15],
        ]);

        DB::table('tc_bacheca')->insert([
            ['id' => 130, 'id_operatore' => 13],
            ['id' => 131, 'id_operatore' => 15],
        ]);

        DB::table('tc_bacheca_view')->insert([
            ['id' => 140, 'id_bacheca' => 130, 'id_operatore' => 14],
            ['id' => 141, 'id_bacheca' => 130, 'id_operatore' => 15],
            ['id' => 142, 'id_bacheca' => 131, 'id_operatore' => 14],
        ]);

        DB::table('tc_email')->insert([
            ['id' => 150, 'id_operatore' => 13],
            ['id' => 151, 'id_operatore' => 15],
        ]);

        DB::table('tc_send_sms')->insert([
            ['id' => 160, 'id_operatore' => 15],
            ['id' => 161, 'id_operatore' => 13],
        ]);

        DB::table('tc_send_sms_result')->insert([
            ['id' => 170, 'send_id' => 160],
            ['id' => 171, 'send_id' => 161],
        ]);

        DB::table('tc_send_sms_delivery')->insert([
            ['id' => 180, 'send_id' => 160],
        ]);

        DB::table('tc_send_whatsapp')->insert([
            ['id' => 190, 'id_operatore' => 15],
        ]);

        DB::table('tc_send_email')->insert([
            ['id' => 200, 'id_operatore' => 14],
            ['id' => 201, 'id_operatore' => 15],
        ]);

        DB::table('_tracking')->insert([
            ['id' => 210, 'id_user' => 15],
            ['id' => 211, 'id_user' => 10],
            ['id' => 212, 'id_user' => 12],
        ]);

        $disco = Storage::disk(config('filesystems.files_disk'));
        $disco->put('protected/documenti/Cliente_4/Sede_21/foto.jpg', 'acme');
        $disco->put('protected/documenti/Cliente_2/Sede_20/foto.jpg', 'prova');
        $disco->put('protected/documenti/Operatore_15/doc.txt', 'mario');
        $disco->put('protected/documenti/Operatore_13/doc.txt', 'alessio');
        $disco->put('images/avatar/mario.jpg', 'mario');
        $disco->put('images/avatar/ales.jpg', 'alessio');
    }
}
