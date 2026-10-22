<?php

namespace Tests\Feature;

use App\Livewire\Login;
use App\Models\Accesso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessiTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'ada@example.test';

    private const NOME_COMPLETO = 'Ada Lovelace';

    /**
     * @param  array<string, mixed>  $attributi
     */
    private function utente(array $attributi = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributi);

        return $user;
    }

    public function test_successful_login_records_user_time_and_ip(): void
    {
        $user = $this->utente([
            'name' => 'Ada',
            'cognome' => 'Lovelace',
            'email' => self::EMAIL,
            'ruolo' => 'admin',
            'attivo' => true,
        ]);
        $accessoAt = now()->startOfSecond();
        $this->travelTo($accessoAt);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('accedi')
            ->assertRedirect('/');

        $this->assertDatabaseHas('accessi', [
            'user_id' => $user->id,
            'nome_completo' => self::NOME_COMPLETO,
            'email' => self::EMAIL,
            'ip_address' => '127.0.0.1',
            'accesso_at' => $accessoAt->toDateTimeString(),
        ]);
        $this->assertDatabaseCount('accessi', 1);
    }

    public function test_failed_login_does_not_create_access_record(): void
    {
        $user = $this->utente([
            'email' => self::EMAIL,
            'attivo' => true,
        ]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password-sbagliata')
            ->call('accedi')
            ->assertHasErrors('email');

        $this->assertDatabaseCount('accessi', 0);
    }

    public function test_staff_can_view_access_logs_with_the_requested_fields(): void
    {
        $admin = $this->utente([
            'ruolo' => 'admin',
            'attivo' => true,
        ]);
        Accesso::create([
            'user_id' => $admin->id,
            'nome_completo' => self::NOME_COMPLETO,
            'email' => self::EMAIL,
            'ip_address' => '203.0.113.10',
            'accesso_at' => '2026-10-22 13:45:00',
        ]);

        $this->actingAs($admin)->get(route('accessi'))
            ->assertOk()
            ->assertSee('Log accessi utenti')
            ->assertSee('Nome completo')
            ->assertSee('Email')
            ->assertSee('Ora accesso')
            ->assertSee('Indirizzo IP')
            ->assertSee(self::NOME_COMPLETO)
            ->assertSee(self::EMAIL)
            ->assertSee('22/10/2026 13:45:00')
            ->assertSee('203.0.113.10');
    }

    public function test_access_logs_are_available_only_to_staff(): void
    {
        $this->get(route('accessi'))->assertRedirect(route('login'));

        $this->actingAs($this->utente([
            'ruolo' => 'super_admin',
            'attivo' => true,
        ]))->get(route('accessi'))->assertOk();

        foreach (['cliente', 'operatore'] as $ruolo) {
            $this->actingAs($this->utente([
                'ruolo' => $ruolo,
                'attivo' => true,
            ]))->get(route('accessi'))->assertForbidden();
        }
    }
}
