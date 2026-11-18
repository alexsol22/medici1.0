<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuperTestAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_reserved_email_sees_sections_and_cases(): void
    {
        $this->actingAs($this->createUser('alessiosolari22@gmail.com'))
            ->get(route('supertest'))
            ->assertOk()
            ->assertSee('Sezioni')
            ->assertSee('COM-01')
            ->assertSee('AUTH-05')
            ->assertSee('Lancia sezione')
            ->assertSee('Apri sessione');
    }

    public function test_other_staff_user_is_forbidden(): void
    {
        $this->actingAs($this->createUser('admin@example.test', 2))
            ->get(route('supertest'))
            ->assertForbidden();
    }

    public function test_email_with_different_case_is_forbidden(): void
    {
        $this->actingAs($this->createUser('AlessioSolari22@gmail.com'))
            ->get(route('supertest'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('supertest'))
            ->assertRedirect(route('login'));
    }

    public function test_other_staff_user_cannot_run_a_case(): void
    {
        $this->actingAs($this->createUser('admin@example.test', 2))
            ->withSession(['_token' => 'token-collaudo'])
            ->postJson(route('supertest.esegui'), $this->payload('COM-01'))
            ->assertForbidden();
    }

    public function test_unknown_case_is_rejected(): void
    {
        $this->actingAs($this->createUser('alessiosolari22@gmail.com'))
            ->withSession(['_token' => 'token-collaudo'])
            ->postJson(route('supertest.esegui'), $this->payload('NOPE-99'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'id' => 'Il valore selezionato per id non è valido.',
            ]);
    }

    public function test_reserved_email_runs_one_isolated_case(): void
    {
        $this->actingAs($this->createUser('alessiosolari22@gmail.com'))
            ->withSession(['_token' => 'token-collaudo'])
            ->postJson(route('supertest.esegui'), $this->payload('COM-01'))
            ->assertOk()
            ->assertJsonPath('id', 'COM-01')
            ->assertJsonPath('esito', 'pass');
    }

    /**
     * @return array{id: string, _token: string}
     */
    private function payload(string $id): array
    {
        return ['id' => $id, '_token' => 'token-collaudo'];
    }

    private function createUser(string $email, int $userType = 4): User
    {
        return User::create([
            'user' => $email,
            'password' => 'secret',
            'attivo' => 'Y',
            'user_type' => $userType,
            'email' => $email,
        ]);
    }
}
