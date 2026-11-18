<?php

namespace Tests\Feature\SuperTest;

use App\Models\User;
use App\SuperTest\SessioneReale;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessioneRealeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Schema::connection('mysql')->create('tc_admin', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('user')->nullable();
            $table->string('password')->nullable();
            $table->string('attivo')->default('Y');
            $table->unsignedInteger('user_type')->nullable();
            $table->string('nome')->nullable();
            $table->string('cognome')->nullable();
            $table->string('email')->nullable();
        });

        Schema::connection('mysql')->create('tc_clienti', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('ragione_sociale')->nullable();
        });

        DB::connection('mysql')->table('tc_clienti')->insert([
            'ragione_sociale' => 'Cliente reale di prova',
        ]);
    }

    protected function tearDown(): void
    {
        SessioneReale::elimina();
        SessioneReale::pulisciScadute();

        parent::tearDown();
    }

    public function test_reserved_email_copies_real_rows_into_a_session_file(): void
    {
        $this->actingAs($this->utente())
            ->withSession(['_token' => 'token-collaudo'])
            ->postJson(route('supertest.sessione.avvia'), ['_token' => 'token-collaudo'])
            ->assertOk()
            ->assertJsonPath('stato', 'pronta');

        $stato = SessioneReale::stato();
        $this->assertNotNull($stato['id']);
        $this->assertFileExists(SessioneReale::database((string) $stato['id']));

        config([
            'database.connections.controllo_sessione' => [
                'driver' => 'sqlite',
                'database' => SessioneReale::database((string) $stato['id']),
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        $this->assertSame(
            'Cliente reale di prova',
            DB::connection('controllo_sessione')->table('tc_clienti')->value('ragione_sociale'),
        );
    }

    public function test_expired_session_is_deleted(): void
    {
        $id = SessioneReale::nuova();
        file_put_contents(SessioneReale::database($id), '');
        $meta = SessioneReale::leggi($id);
        $this->assertNotNull($meta);
        $meta['stato'] = 'pronta';
        $meta['scade'] = now()->subMinute()->toIso8601String();
        file_put_contents(
            SessioneReale::cartella().DIRECTORY_SEPARATOR.$id.'.json',
            json_encode($meta),
        );

        $this->assertSame(1, SessioneReale::pulisciScadute());
        $this->assertFileDoesNotExist(SessioneReale::database($id));
    }

    public function test_missing_session_does_not_start_phpunit(): void
    {
        $this->actingAs($this->utente())
            ->withSession(['_token' => 'token-collaudo'])
            ->postJson(route('supertest.esegui'), [
                'id' => 'COM-01',
                'sessione' => str_repeat('ab', 16),
                '_token' => 'token-collaudo',
            ])
            ->assertOk()
            ->assertJsonPath('esito', 'error');
    }

    public function test_other_staff_user_cannot_open_a_session(): void
    {
        $this->actingAs($this->utente('admin@example.test'))
            ->withSession(['_token' => 'token-collaudo'])
            ->postJson(route('supertest.sessione.avvia'), ['_token' => 'token-collaudo'])
            ->assertForbidden();
    }

    private function utente(string $email = 'alessiosolari22@gmail.com'): User
    {
        return User::create([
            'user' => $email,
            'password' => 'secret',
            'attivo' => 'Y',
            'user_type' => 2,
            'email' => $email,
        ]);
    }
}
