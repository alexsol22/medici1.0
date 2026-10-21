<?php

namespace Tests\Feature;

use App\Livewire\Impostazioni;
use App\Models\Impostazione;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ImpostazioniTest extends TestCase
{
    use RefreshDatabase;

    private function utenteConRuolo(string $ruolo): User
    {
        return User::factory()->create([
            'ruolo' => $ruolo,
            'attivo' => true,
        ]);
    }

    public function test_impostazioni_sono_disponibili_solo_agli_amministratori(): void
    {
        $this->get(route('impostazioni'))->assertRedirect(route('login'));

        $admin = $this->utenteConRuolo('admin');
        $this->actingAs($admin)->get(route('impostazioni'))
            ->assertOk()
            ->assertSee('Google Maps')
            ->assertSee('SMS')
            ->assertSee('WhatsApp')
            ->assertSee('Telegram')
            ->assertSee('OpenAI')
            ->assertSee('Altra integrazione')
            ->assertSee('Impostazioni');

        $this->actingAs($this->utenteConRuolo('super_admin'))->get(route('impostazioni'))->assertOk();
        $this->actingAs($this->utenteConRuolo('cliente'))->get(route('impostazioni'))->assertForbidden();
        $this->actingAs($this->utenteConRuolo('operatore'))->get(route('impostazioni'))->assertForbidden();
    }

    public function test_salva_credenziale_cifrata_e_non_la_mostra_nella_pagina(): void
    {
        $segreto = 'sk-test-value-not-rendered';

        Livewire::actingAs($this->utenteConRuolo('admin'))
            ->test(Impostazioni::class)
            ->set('dati.openai_api_key', $segreto)
            ->set('dati.openai_model', 'gpt-4o-mini')
            ->call('salva', 'openai')
            ->assertHasNoErrors()
            ->assertSet('dati.openai_api_key', '')
            ->assertSet('dati.openai_model', 'gpt-4o-mini')
            ->assertDontSee($segreto);

        $impostazione = Impostazione::where('chiave', 'openai_api_key')->firstOrFail();
        $this->assertSame($segreto, $impostazione->valore);
        $this->assertNotSame($segreto, DB::table('impostazioni')->where('chiave', 'openai_api_key')->value('valore'));
        $this->assertDatabaseHas('impostazioni', ['chiave' => 'openai_model']);

        $this->actingAs($this->utenteConRuolo('admin'))->get(route('impostazioni'))
            ->assertOk()
            ->assertDontSee($segreto);
    }

    public function test_campi_vuoti_mantengono_le_credenziali_esistenti_e_rimuovi_le_cancella(): void
    {
        $admin = $this->utenteConRuolo('admin');
        Impostazione::create(['chiave' => 'telegram_bot_token', 'valore' => 'token-esistente']);

        Livewire::actingAs($admin)
            ->test(Impostazioni::class)
            ->call('salva', 'telegram')
            ->assertHasNoErrors();

        $this->assertSame('token-esistente', Impostazione::where('chiave', 'telegram_bot_token')->firstOrFail()->valore);

        Livewire::actingAs($admin)
            ->test(Impostazioni::class)
            ->call('rimuovi', 'telegram')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('impostazioni', ['chiave' => 'telegram_bot_token']);
    }

    public function test_rifiuta_endpoint_non_validi_e_non_persiste_chiavi_impreviste(): void
    {
        $admin = $this->utenteConRuolo('admin');
        $componente = Livewire::actingAs($admin)
            ->test(Impostazioni::class)
            ->set('dati.custom_endpoint', 'non-un-url')
            ->set('dati.chiave_imprevista', 'non deve essere salvata')
            ->call('salva', 'altro')
            ->assertHasErrors('dati.custom_endpoint');

        $this->assertDatabaseMissing('impostazioni', ['chiave' => 'chiave_imprevista']);

        $componente->set('dati.custom_endpoint', 'https://api.example.com')
            ->call('salva', 'altro')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('impostazioni', [
            'chiave' => 'custom_endpoint',
        ]);
        $this->assertDatabaseMissing('impostazioni', ['chiave' => 'chiave_imprevista']);
    }
}
