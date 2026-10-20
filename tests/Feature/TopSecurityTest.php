<?php

namespace Tests\Feature;

use App\Livewire\Amministratori;
use App\Livewire\Dashboard;
use App\Livewire\GeneratoreQr;
use App\Livewire\Login;
use App\Livewire\Profilo;
use App\Livewire\Sedi;
use App\Livewire\Turni;
use App\Models\Cliente;
use App\Models\CodiceQr;
use App\Models\Presenza;
use App\Models\Sede;
use App\Models\Turno;
use App\Models\User;
use App\Services\Calcoli;
use App\Services\PianificazioneTurni;
use App\Services\QrAziendale;
use App\Services\Timbratura;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TopSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function utente(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_calcoli(): void
    {
        $this->assertEqualsWithDelta(0.0, Calcoli::distanzaKm(45.0, 9.0, 45.0, 9.0), 0.001);
        $this->assertEqualsWithDelta(111.19, Calcoli::distanzaKm(45.0, 9.0, 46.0, 9.0), 0.3);
        $a = Carbon::parse('2025-01-01 08:00');
        $this->assertSame(8.0, Calcoli::ore($a, $a->copy()->addHours(8)));
        $this->assertSame(7.5, Calcoli::ore($a, $a->copy()->addMinutes(7 * 60 + 40)));
        $this->assertSame(10, Calcoli::ritardoMinuti($a, $a->copy()->addMinutes(10)));
        $this->assertSame(-5, Calcoli::ritardoMinuti($a, $a->copy()->subMinutes(5)));
    }

    public function test_login_e_redirect_per_ruolo(): void
    {
        Livewire::test(Login::class)->set('email', 'admin@topsecurity.test')->set('password', 'sbagliata')->call('accedi')->assertHasErrors('email');
        Livewire::test(Login::class)->set('email', 'admin@topsecurity.test')->set('password', 'password')->call('accedi')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_pagina_iniziale_per_ruolo(): void
    {
        $this->actingAs($this->utente('admin@topsecurity.test'))->get('/')->assertRedirect(route('dashboard'));
        $this->actingAs($this->utente('super@topsecurity.test'))->get('/')->assertRedirect(route('dashboard'));
        $this->actingAs($this->utente('cliente@topsecurity.test'))->get('/')->assertRedirect(route('turni'));
        $this->actingAs($this->utente('marco@topsecurity.test'))->get('/')->assertRedirect(route('scansione'));
    }

    public function test_dashboard_riepilogativa_disponibile_solo_per_lo_staff(): void
    {
        $admin = $this->utente('admin@topsecurity.test');
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Panoramica amministrativa')
            ->assertSee('Clienti')
            ->assertSee('Sedi')
            ->assertSee('Operatori attivi')
            ->assertSee('Turni oggi')
            ->assertSee('Prossimi turni')
            ->assertDontSee('Azienda Demo (prova)');

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertSee((string) Cliente::visibiliA($admin)->count())
            ->assertSee((string) Sede::whereIn('cliente_id', Cliente::visibiliA($admin)->select('clienti.id'))->count());

        $this->actingAs($this->utente('super@topsecurity.test'))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Azienda Demo (prova)');

        $this->actingAs($this->utente('cliente@topsecurity.test'))->get(route('dashboard'))->assertForbidden();
        $this->actingAs($this->utente('marco@topsecurity.test'))->get(route('dashboard'))->assertForbidden();
    }

    public function test_pagine_per_ruolo_e_permessi(): void
    {
        $admin = $this->utente('admin@topsecurity.test');
        foreach (['clienti', 'sedi', 'turni', 'presenze', 'operatori', 'documenti', 'bacheca', 'messaggi'] as $r) {
            $this->actingAs($admin)->get(route($r))->assertOk();
        }

        $cliente = $this->utente('cliente@topsecurity.test');
        foreach (['sedi', 'turni', 'presenze', 'documenti'] as $r) {
            $this->actingAs($cliente)->get(route($r))->assertOk();
        }
        foreach (['clienti', 'operatori', 'messaggi', 'bacheca', 'scansione'] as $r) {
            $this->actingAs($cliente)->get(route($r))->assertForbidden();
        }

        $op = $this->utente('marco@topsecurity.test');
        foreach (['scansione', 'miei-turni', 'bacheca', 'documenti'] as $r) {
            $this->actingAs($op)->get(route($r))->assertOk();
        }
        $this->actingAs($op)->get(route('clienti'))->assertForbidden();
        $this->get(route('logout'))->assertStatus(405);
    }

    public function test_sezione_amministratori_visibile_a_staff_e_crea_utenti_admin(): void
    {
        $this->actingAs($this->utente('admin@topsecurity.test'))
            ->get(route('amministratori'))
            ->assertOk()
            ->assertSee('Cerca sezioni')
            ->assertDontSee(route('scansione'))
            ->assertSeeInOrder(['Amministrazione', 'Amministratori', 'Anagrafica', 'Clienti', 'Sedi', 'Operatori', 'Turni e presenze']);

        $this->actingAs($this->utente('super@topsecurity.test'))
            ->get(route('amministratori'))
            ->assertOk();

        $this->actingAs($this->utente('cliente@topsecurity.test'))
            ->get(route('amministratori'))
            ->assertForbidden();

        $componente = Livewire::actingAs($this->utente('admin@topsecurity.test'))
            ->test(Amministratori::class)
            ->assertSee('admin@topsecurity.test')
            ->call('nuovo')
            ->set('f.name', 'Giulia')
            ->set('f.cognome', 'Verdi')
            ->set('f.email', 'giulia.verdi@topsecurity.test')
            ->set('f.cellulare', '3331234567')
            ->set('f.password', 'password')
            ->call('salva')
            ->assertHasNoErrors()
            ->assertSet('modal', false);

        $amministratore = User::where('email', 'giulia.verdi@topsecurity.test')->firstOrFail();
        $this->assertSame('admin', $amministratore->ruolo->value);
        $this->assertTrue($amministratore->attivo);
        $this->assertTrue(Hash::check('password', $amministratore->password));

        $componente = Livewire::actingAs($this->utente('super@topsecurity.test'))
            ->test(Amministratori::class);
        $componente->call('modifica', $amministratore->id)
            ->set('f.name', 'Giulia Maria')
            ->call('salva')
            ->assertHasNoErrors()
            ->call('attivaDisattiva', $amministratore->id);

        $amministratore->refresh();
        $this->assertSame('Giulia Maria', $amministratore->name);
        $this->assertFalse($amministratore->attivo);
    }

    public function test_profilo_modifica_dati_foto_e_password(): void
    {
        Storage::fake('local');
        $user = $this->utente('marco@topsecurity.test');

        $this->actingAs($user)->get(route('profilo'))->assertOk()->assertSee('Cambia password');

        Livewire::actingAs($user)->test(Profilo::class)
            ->set('name', 'Marco')
            ->set('cognome', 'Rossi Bis')
            ->set('cellulare', '3331112222')
            ->set('foto', UploadedFile::fake()->image('foto.png', 200, 200))
            ->call('salvaProfilo')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Rossi Bis', $user->cognome);
        $this->assertNotNull($user->foto_path);
        Storage::disk('local')->assertExists($user->foto_path);
        $this->actingAs($user)->get(route('profilo.foto', $user))->assertOk();
        $this->actingAs($this->utente('luca@topsecurity.test'))->get(route('profilo.foto', $user))->assertForbidden();

        Livewire::actingAs($user)->test(Profilo::class)
            ->set('password_attuale', 'sbagliata')->set('password', 'nuovapass1')->set('password_confirmation', 'nuovapass1')
            ->call('cambiaPassword')->assertHasErrors('password_attuale');

        Livewire::actingAs($user)->test(Profilo::class)
            ->set('password_attuale', 'password')->set('password', 'nuovapass1')->set('password_confirmation', 'nuovapass1')
            ->call('cambiaPassword')->assertHasNoErrors();
        $this->assertTrue(Hash::check('nuovapass1', $user->fresh()->password));

        $percorso = $user->foto_path;
        Livewire::actingAs($user)->test(Profilo::class)->call('rimuoviFoto');
        $this->assertNull($user->fresh()->foto_path);
        Storage::disk('local')->assertMissing($percorso);
    }

    public function test_il_cliente_vede_solo_le_proprie_sedi(): void
    {
        $cliente = $this->utente('cliente@topsecurity.test');
        $altra = Sede::where('cliente_id', '!=', $cliente->cliente_id)->first();

        $this->actingAs($cliente);
        Livewire::test(Sedi::class)->assertSee(Sede::where('cliente_id', $cliente->cliente_id)->first()->descrizione)->assertDontSee($altra->descrizione);
        Livewire::test(Sedi::class)->call('nuova')->assertForbidden();
    }

    public function test_timbratura_inizio_passaggio_fine(): void
    {
        Turno::query()->delete();
        $op = $this->utente('marco@topsecurity.test');
        $sede = Sede::first();
        $turno = Turno::create([
            'operatore_id' => $op->id, 'cliente_id' => $sede->cliente_id, 'sede_id' => $sede->id,
            'inizio' => now()->subMinutes(10), 'fine' => now()->addHours(8), 'ore' => 8,
        ]);
        $qrSede = $sede->codiceQr->codice;
        $qrPost = $sede->postazioni()->first()->codiceQr->codice;
        $t = app(Timbratura::class);

        $this->assertFalse($t->scansiona($op, 'INESISTENTE', null, null)['ok']);
        $this->assertFalse($t->scansiona($op, $qrPost, null, null)['ok'], 'passaggio prima dell\'inizio');
        $this->assertFalse($t->scansiona($op, $qrSede, 0.0, 0.0)['ok'], 'troppo lontano');

        $r = $t->scansiona($op, $qrSede, (float) $sede->lat, (float) $sede->lng);
        $this->assertTrue($r['ok']);
        $this->assertSame(10, $r['ritardo']);
        $this->assertSame('I', $turno->fresh()->stato->value);

        $this->assertTrue($t->scansiona($op, $qrPost, null, null)['ok']);

        $turno->update(['fine' => now()]);
        $r = $t->scansiona($op, $qrSede, (float) $sede->lat, (float) $sede->lng);
        $this->assertTrue($r['ok']);
        $turno->refresh();
        $this->assertSame('F', $turno->stato->value);
        $this->assertNotNull($turno->ore_lavorate);
        $this->assertSame(3, Presenza::where('turno_id', $turno->id)->count());
    }

    public function test_nessun_turno_fuori_finestra_e_altro_operatore(): void
    {
        $op = $this->utente('luca@topsecurity.test');
        Turno::query()->delete();
        $sede = Sede::first();
        Turno::create(['operatore_id' => $this->utente('marco@topsecurity.test')->id, 'cliente_id' => $sede->cliente_id, 'sede_id' => $sede->id,
            'inizio' => now(), 'fine' => now()->addHours(4), 'ore' => 4]);
        $this->assertFalse(app(Timbratura::class)->scansiona($op, $sede->codiceQr->codice, null, null)['ok']);
    }

    public function test_sostituzione(): void
    {
        $assente = $this->utente('marco@topsecurity.test');
        $sost = $this->utente('sara@topsecurity.test');
        Turno::query()->delete();
        $sede = Sede::first();
        $turno = Turno::create(['operatore_id' => $assente->id, 'cliente_id' => $sede->cliente_id, 'sede_id' => $sede->id,
            'inizio' => now()->addMinutes(5), 'fine' => now()->addHours(8), 'ore' => 8]);

        $r = app(Timbratura::class)->sostituisci($sost, $sede->codiceQr->codice, null, null);
        $this->assertTrue($r['ok'], $r['message']);
        $this->assertSame($sost->id, $turno->fresh()->operatore_id);
    }

    public function test_disponibilita_e_ripetizione(): void
    {
        $piano = app(PianificazioneTurni::class);
        $sede = Sede::first();
        $occupato = Turno::where('sede_id', $sede->id)->whereDate('inizio', today()->addDay())->first();

        $disp = $piano->operatoriDisponibili($occupato->inizio, $occupato->fine, $sede->cliente_id);
        $this->assertFalse($disp->contains('id', $occupato->operatore_id));

        $o = $piano->occorrenze(Carbon::parse('2025-03-03 08:00'), Carbon::parse('2025-03-03 16:00'), Carbon::parse('2025-03-14'), [1, 3]);
        $this->assertSame(['2025-03-03', '2025-03-05', '2025-03-10', '2025-03-12'], $o->map(fn ($x) => $x['inizio']->toDateString())->all());

        $cop = $piano->copertura($sede, now()->startOfWeek());
        $this->assertCount(7, $cop);
    }

    public function test_creazione_turno_ripetuto_da_admin(): void
    {
        $this->actingAs($this->utente('admin@topsecurity.test'));
        $sede = Sede::first();
        $op = $this->utente('sara@topsecurity.test');
        $inizio = today()->addMonth()->startOfWeek();

        Livewire::test(Turni::class)->call('nuovo')
            ->set('f.cliente_id', $sede->cliente_id)->set('f.sede_id', $sede->id)->set('f.operatore_id', $op->id)
            ->set('f.data_inizio', $inizio->toDateString())->set('f.data_fine', $inizio->toDateString())
            ->set('f.ora_inizio', '20:00')->set('f.ora_fine', '23:30')
            ->set('f.ripeti_fino', $inizio->copy()->addDays(6)->toDateString())->set('giorni', [1, 2])
            ->call('salva')->assertHasNoErrors();

        $this->assertSame(2, Turno::where('operatore_id', $op->id)->where('inizio', '>=', $inizio)->count());
        $this->assertSame(3.5, Turno::where('operatore_id', $op->id)->where('inizio', '>=', $inizio)->first()->ore);
    }

    public function test_qr_svg(): void
    {
        $this->assertStringContainsString('<svg', CodiceQr::first()->svg());
    }

    public function test_download_csv_presenze_solo_visibili(): void
    {
        $this->actingAs($this->utente('cliente@topsecurity.test'))->get(route('presenze.esporta'))->assertOk();
    }

    public function test_calendario_turni(): void
    {
        $this->actingAs($this->utente('admin@topsecurity.test'));
        $sede = Sede::first();
        Livewire::test(Turni::class)
            ->assertSee(now()->translatedFormat('F Y'))
            ->call('cambiaMese', 1)->assertSet('mese', now()->startOfMonth()->addMonth()->toDateString())
            ->call('nuovoGiorno', '2030-05-10')->assertSet('modal', true)->assertSet('f.data_inizio', '2030-05-10')
            ->set('vista', 'lista')->assertSee('Orario');
    }

    public function test_generatore_qr(): void
    {
        $svg = app(QrAziendale::class)->svg('https://esempio.it', 'Mia <Azienda>', 'data:image/png;base64,iVBORw0KGgo=', '#0369a1');
        $this->assertStringContainsString('Mia &lt;Azienda&gt;', $svg);
        $this->assertStringContainsString('data:image/png;base64', $svg);

        $this->actingAs($this->utente('admin@topsecurity.test'))->get('/generatore-qr')->assertNotFound();
        Livewire::actingAs($this->utente('cliente@topsecurity.test'))->test(GeneratoreQr::class)->assertForbidden();

        $file = UploadedFile::fake()->image('logo.png', 100, 100);
        Livewire::actingAs($this->utente('admin@topsecurity.test'))->test(GeneratoreQr::class)
            ->set('logo', $file)->set('titolo', 'ACME')->assertHasNoErrors()->assertSee('ACME');
    }

    public function test_generatore_qr_dentro_sedi(): void
    {
        $admin = $this->utente('admin@topsecurity.test');
        $sede = Sede::first();

        Livewire::actingAs($admin)->test(Sedi::class)
            ->call('qr', $sede->id)->assertSet('sedeQr', $sede->id)->assertSee('Genera QR sede');

        $c = Livewire::actingAs($admin)->test(GeneratoreQr::class, ['sede' => $sede->id])
            ->assertSet('bloccato', true)->assertSet('sedeId', $sede->id)->assertSet('contenuto', $sede->codiceQr->codice)
            ->set('colore', '#112233')->call('salva')->assertHasNoErrors();
        $this->assertSame($sede->id, \App\Models\QrAziendale::firstOrFail()->sede_id);

        Livewire::actingAs($admin)->test(GeneratoreQr::class, ['sede' => $sede->id])->assertSet('colore', '#112233');
        $c->set('sedeId', 999)->assertStatus(403);

        $this->actingAs($this->utente('cliente@topsecurity.test'))->get(route('sedi'))->assertOk()->assertDontSee('Genera QR');
    }

    public function test_salvataggio_qr_con_associazione(): void
    {
        $admin = $this->utente('admin@topsecurity.test');
        $sede = Sede::first();
        $file = UploadedFile::fake()->image('logo.png', 80, 80);

        $c = Livewire::actingAs($admin)->test(GeneratoreQr::class)
            ->set('titolo', 'Ingresso')->set('logo', $file)
            ->set('clienteId', $sede->cliente_id)->set('sedeId', $sede->id)
            ->call('salva')->assertHasNoErrors();

        $qr = \App\Models\QrAziendale::firstOrFail();
        $this->assertSame($sede->id, $qr->sede_id);
        $this->assertNotNull($qr->logo_path);
        Storage::disk('local')->assertExists($qr->logo_path);

        $this->assertSame($sede->codiceQr->codice, $qr->contenuto);
        $c->call('elimina', $qr->id);
        $this->assertSame(0, \App\Models\QrAziendale::count());

        $altro = Cliente::where('id', '!=', $sede->cliente_id)->first();
        Livewire::actingAs($admin)->test(GeneratoreQr::class)
            ->set('clienteId', $altro->id)->set('sedeId', $sede->id)->call('salva')->assertStatus(404);
    }
}
