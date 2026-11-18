<?php

namespace Tests\Feature\SuperTest;

use App\Livewire\Accessi;
use App\Livewire\Amministratori;
use App\Livewire\Bacheca;
use App\Livewire\CampanellaNotifiche;
use App\Livewire\Clienti;
use App\Livewire\ContatoreNotifiche;
use App\Livewire\Documenti;
use App\Livewire\GeneratoreQr;
use App\Livewire\Impostazioni;
use App\Livewire\Login;
use App\Livewire\Messaggi;
use App\Livewire\ModelliMessaggio;
use App\Livewire\Operatori;
use App\Livewire\Presenze;
use App\Livewire\Profilo;
use App\Livewire\RecuperaPassword;
use App\Livewire\Scansione;
use App\Livewire\SedeForm;
use App\Livewire\Sedi;
use App\Livewire\Turni;
use App\Mail\NotificaHtml;
use App\Models\Legacy\Messaggio;
use App\Models\Legacy\Turno;
use App\Models\User;
use App\Services\Comunicazioni;
use App\Services\ProvaNotifiche;
use App\Services\QrSede;
use App\Services\Timbratura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CasiCollaudoTest extends TestCase
{
    use AmbienteIsolato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparaAmbiente();
    }

    public function test_com_01(): void
    {
        $staff = $this->utente(['user_type' => 2]);
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $cliente = $this->utente(['user_type' => 3, 'user' => 'cli', 'email' => 'cli@example.test', 'id_cliente' => $this->cliente('Cliente Uno')]);

        $this->actingAs($staff)->get('/')->assertRedirect(route('clienti'));
        $this->actingAs($operatore)->get('/')->assertRedirect(route('scansione'));
        $this->actingAs($cliente)->get('/')->assertRedirect(route('turni'));
    }

    public function test_com_02(): void
    {
        $staff = $this->utente(['user_type' => 2]);
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);

        $this->actingAs($staff)->get(route('clienti'))
            ->assertOk()
            ->assertSee('href="'.route('clienti').'"', false)
            ->assertSee('href="'.route('operatori').'"', false)
            ->assertDontSee('href="'.route('scansione').'"', false);

        $this->actingAs($operatore)->get(route('scansione'))
            ->assertOk()
            ->assertSee('href="'.route('miei-turni').'"', false)
            ->assertDontSee('href="'.route('clienti').'"', false);
    }

    public function test_com_03(): void
    {
        $this->cliente('Alpha Srl', ['piva' => '12345678901', 'email' => 'alpha@example.test']);

        Livewire::actingAs($this->utente())->test(Clienti::class)
            ->assertSee('Alpha Srl')
            ->assertSee('12345678901')
            ->assertSee('alpha@example.test');
    }

    public function test_com_04(): void
    {
        $this->cliente('Alpha Srl');
        $this->cliente('Beta Srl');

        Livewire::actingAs($this->utente())->test(Clienti::class)
            ->set('cerca', 'Alpha')
            ->assertSee('Alpha Srl')
            ->assertDontSee('Beta Srl')
            ->set('cerca', 'nessun-cliente-zzzz')
            ->assertSee('Nessun cliente trovato.')
            ->assertDontSee('Alpha Srl');
    }

    public function test_com_05(): void
    {
        $this->cliente('Alpha Srl');
        $this->cliente('Beta Srl');

        Livewire::actingAs($this->utente())->test(Clienti::class)
            ->set('cerca', 'Alpha')
            ->set('perPagina', 10)
            ->assertSet('cerca', 'Alpha')
            ->assertSet('perPagina', 10)
            ->assertSee('Alpha Srl')
            ->assertDontSee('Beta Srl');
    }

    public function test_com_06(): void
    {
        $this->cliente('Segreto Srl');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);

        $this->actingAs($operatore)->get(route('clienti'))
            ->assertForbidden()
            ->assertDontSee('Segreto Srl');
    }

    public function test_com_07(): void
    {
        $staff = $this->utente();
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $altro = $this->utente(['user_type' => 4, 'user' => 'altro', 'email' => 'altro@example.test']);
        $id = $this->documento($operatore->id, 'O', 'Contratto operatore', 'contratto.pdf', 'contenuto-contratto');

        $this->actingAs($staff)->get(route('documenti.scarica', $id))->assertOk()->assertStreamedContent('contenuto-contratto');
        $this->actingAs($altro)->get(route('documenti.scarica', $id))->assertNotFound();
    }

    public function test_com_08(): void
    {
        $this->actingAs($this->utente())->get(route('clienti'))
            ->assertOk()
            ->assertSee('width=device-width', false)
            ->assertSee('lg:hidden', false)
            ->assertSee('aria-controls="navigazione-principale"', false);
    }

    public function test_amm_01(): void
    {
        $this->cliente('Alpha Srl');
        $this->cliente('Beta Srl');

        Livewire::actingAs($this->utente())->test(Clienti::class)
            ->assertSee('Alpha Srl')
            ->assertSee('Beta Srl')
            ->set('cerca', 'Alpha')
            ->assertSee('Alpha Srl')
            ->assertDontSee('Beta Srl');
    }

    public function test_amm_02(): void
    {
        $alpha = $this->cliente('Alpha Srl');
        $beta = $this->cliente('Beta Srl');
        $this->sede($alpha, 'Sede Alpha', 'Via Alpha 1');
        $this->sede($beta, 'Sede Beta', 'Via Beta 2');

        Livewire::actingAs($this->utente())->test(Sedi::class)
            ->set('filtroCliente', $alpha)
            ->assertSee('Sede Alpha')
            ->assertSee('Via Alpha 1')
            ->assertDontSee('Sede Beta');
    }

    public function test_amm_03(): void
    {
        $this->utente();
        $this->utente(['user_type' => 4, 'user' => 'mario', 'nome' => 'Mario', 'cognome' => 'Rossi', 'codice' => 'OP1', 'email' => 'mario@example.test', 'attivo' => 'Y']);
        $this->utente(['user_type' => 4, 'user' => 'luca', 'nome' => 'Luca', 'cognome' => 'Bianchi', 'codice' => 'OP2', 'email' => 'luca@example.test', 'attivo' => 'N']);

        Livewire::actingAs(User::query()->where('user_type', 2)->first())->test(Operatori::class)
            ->set('filtroCognome', 'Rossi')
            ->assertSee('Mario')
            ->assertSee('Disattiva Mario Rossi', false)
            ->assertDontSee('Luca')
            ->set('filtroCognome', 'Bianchi')
            ->assertSee('Attiva Luca Bianchi', false)
            ->assertDontSee('Mario');
    }

    public function test_amm_04(): void
    {
        $this->utente();
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'nome' => 'Mario', 'cognome' => 'Rossi', 'codice' => 'OP1', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'nome' => 'Luca', 'cognome' => 'Bianchi', 'codice' => 'OP2', 'email' => 'luca@example.test']);
        $this->documento($mario->id, 'O', 'Patente Mario', 'mario.pdf', 'mario');
        $this->documento($luca->id, 'O', 'Patente Luca', 'luca.pdf', 'luca');

        Livewire::actingAs(User::query()->where('user_type', 2)->first())->test(Operatori::class)
            ->call('mostraFormazioni', $mario->id)
            ->assertSee('Patente Mario')
            ->assertDontSee('Patente Luca');
    }

    public function test_amm_05(): void
    {
        $staff = $this->utente(['nome' => 'Chiara', 'cognome' => 'Verdi', 'user' => 'chiara', 'email' => 'chiara@example.test']);
        DB::table('_tracking')->insert([
            'id' => 1,
            'id_user' => $staff->id,
            'action' => 'Accesso utente chiara',
            'data_view' => '2026-11-02 09:30:00',
            'addr' => '127.0.0.1',
        ]);
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);

        Livewire::actingAs($staff)->test(Amministratori::class)->assertSee('Chiara')->assertSee('Verdi');
        Livewire::actingAs($staff)->test(Accessi::class)->assertSee('Chiara')->assertSee('02/11/2026 09:30');
        $this->actingAs($operatore)->get(route('amministratori'))->assertForbidden();
        $this->actingAs($operatore)->get(route('accessi'))->assertForbidden();
    }

    public function test_amm_06(): void
    {
        DB::table('tc_config')->insert(['id' => 1, 'ragione_sociale' => 'Top Security Srl']);
        DB::table('tc_messaggi')->insert(['id' => 1, 'nome' => 'Avviso turni', 'giorni' => 1, 'numero_sms' => 2, 'numero_email' => 1, 'mittente' => 'OMNIA', 'invia_email' => 'Y', 'invia_sms' => 'N']);

        Livewire::actingAs($this->utente())->test(Impostazioni::class)
            ->assertSet('f.ragione_sociale', 'Top Security Srl')
            ->set('f.ragione_sociale', 'Nome non salvato');
        $this->assertSame('Top Security Srl', DB::table('tc_config')->value('ragione_sociale'));

        Livewire::actingAs(User::query()->where('user_type', 2)->first())->test(ModelliMessaggio::class)
            ->assertSee('Avviso turni')
            ->call('modifica', 1)
            ->set('f.nome', 'Nome non salvato');
        $this->assertSame('Avviso turni', DB::table('tc_messaggi')->value('nome'));
        $this->assertSame(2, (int) DB::table('tc_messaggi')->value('numero_sms'));
    }

    public function test_amm_07(): void
    {
        $staff = $this->utente(['nome' => 'Chiara', 'cognome' => 'Verdi', 'email' => 'chiara@example.test', 'cellulare' => '3331111111']);
        $this->utente(['nome' => 'Altro', 'cognome' => 'Utente', 'user' => 'altro', 'email' => 'altro@example.test']);

        Livewire::actingAs($staff)->test(Profilo::class)
            ->assertSet('name', 'Chiara')
            ->assertSet('email', 'chiara@example.test')
            ->assertDontSee('altro@example.test');
    }

    public function test_amm_08(): void
    {
        Livewire::actingAs($this->utente())->test(Clienti::class)
            ->call('nuovo')
            ->set('f.ragione_sociale', 'Cliente Prova')
            ->set('f.piva', '12345678901')
            ->set('f.email', 'prova@example.test')
            ->set('f.accesso', false)
            ->call('salva')
            ->assertHasNoErrors();

        $this->assertSame(1, DB::table('tc_clienti')->count());
        $this->assertDatabaseHas('tc_clienti', ['ragione_sociale' => 'Cliente Prova', 'piva' => '12345678901']);
    }

    public function test_amm_09(): void
    {
        Livewire::actingAs($this->utente())->test(Clienti::class)
            ->call('nuovo')
            ->set('f.ragione_sociale', 'Cliente Prova')
            ->set('f.piva', '123')
            ->set('f.email', 'prova@example.test')
            ->call('salva')
            ->assertHasErrors(['f.piva'])
            ->assertSee('Il campo partita IVA deve essere di 11 cifre.');

        $this->assertSame(0, DB::table('tc_clienti')->count());
    }

    public function test_ops_01(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Calendario', 'Via Roma 1');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'op@example.test']);
        $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '08:00', '16:00');

        Livewire::actingAs($this->utente())->test(Turni::class)
            ->set('vista', 'lista')
            ->assertSee('Sede Calendario')
            ->assertSee('08:00')
            ->assertSee('Mario');
    }

    public function test_ops_02(): void
    {
        $alpha = $this->cliente('Alpha Srl');
        $beta = $this->cliente('Beta Srl');
        $sedeAlpha = $this->sede($alpha, 'Sede Alpha', 'Via Alpha 1');
        $sedeBeta = $this->sede($beta, 'Sede Beta', 'Via Beta 2');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $this->turno($alpha, $sedeAlpha, $operatore->id, today()->toDateString(), '08:00', '16:00');
        $this->turno($beta, $sedeBeta, $operatore->id, today()->subDay()->toDateString(), '09:00', '17:00');

        Livewire::actingAs($this->utente())->test(Turni::class)
            ->set('vista', 'lista')
            ->set('filtroCliente', $alpha)
            ->set('da', today()->toDateString())
            ->set('a', today()->toDateString())
            ->assertSee('Sede Alpha')
            ->assertDontSee('>Sede Beta</td>', false);
    }

    public function test_ops_03(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $chiusa = $this->sede($cliente, 'Sede Chiusa', 'Via 1');
        $aperta = $this->sede($cliente, 'Sede Aperta', 'Via 2');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $this->turno($cliente, $chiusa, $operatore->id, today()->toDateString(), '08:00', '09:00', ['stato' => 'F']);
        $this->turno($cliente, $aperta, $operatore->id, today()->toDateString(), '00:00', '23:59', ['stato' => 'I']);

        Livewire::actingAs($this->utente())->test(Turni::class)
            ->set('vista', 'lista')
            ->set('filtroStato', 'completato')
            ->assertSee('Sede Chiusa')
            ->assertDontSee('>Sede Aperta</td>', false);
    }

    public function test_ops_04(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Nota', 'Via Roma 4');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'op@example.test']);
        $turno = $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '08:00', '16:00', ['stato' => 'F', 'note_operatore' => 'Cancello laterale chiuso']);

        Livewire::actingAs($this->utente())->test(Turni::class)
            ->set('vista', 'lista')
            ->call('mostraTurno', $turno)
            ->assertSee('Sede Nota')
            ->assertSee('Alpha Srl')
            ->assertSee('Mario')
            ->assertSee('Cancello laterale chiuso');
    }

    public function test_ops_05(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Alpha', 'Via 1');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '08:00', '16:00', ['stato' => 'F', 'ore' => 8, 'ore_lavorate' => 7]);

        $senza = Livewire::actingAs($this->utente())->test(Presenze::class);
        $this->assertNull($senza->viewData('totali'));

        $con = Livewire::actingAs(User::query()->where('user_type', 2)->first())->test(Presenze::class)->set('filtroCliente', $cliente);
        $this->assertSame(8.0, $con->viewData('totali')['previste']);
        $this->assertSame(7.0, $con->viewData('totali')['lavorate']);
    }

    public function test_ops_06(): void
    {
        $alpha = $this->cliente('Alpha Srl');
        $beta = $this->cliente('Beta Srl');
        $sedeAlpha = $this->sede($alpha, 'Sede Alpha', 'Via Alpha 1');
        $sedeBeta = $this->sede($beta, 'Sede Beta', 'Via Beta 2');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $turnoAlpha = $this->turno($alpha, $sedeAlpha, $operatore->id, today()->toDateString(), '08:00', '16:00', ['stato' => 'F']);
        $turnoBeta = $this->turno($beta, $sedeBeta, $operatore->id, today()->toDateString(), '09:00', '17:00', ['stato' => 'F']);
        $this->presenza($turnoAlpha, $operatore->id, $alpha, $sedeAlpha, 'I', '08:00');
        $this->presenza($turnoAlpha, $operatore->id, $alpha, $sedeAlpha, 'F', '16:00');
        $this->presenza($turnoBeta, $operatore->id, $beta, $sedeBeta, 'I', '09:00');
        $this->presenza($turnoBeta, $operatore->id, $beta, $sedeBeta, 'F', '17:00');
        $staff = $this->utente();

        $this->actingAs($staff)->get(route('presenze.stampa'))->assertStatus(422);
        $this->actingAs($staff)->get(route('presenze.stampa', ['cliente' => $alpha]))
            ->assertOk()
            ->assertSee('Sede Alpha')
            ->assertDontSee('Sede Beta');
    }

    public function test_ops_07(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Unica', 'Via 1');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $chiuso = $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '08:00', '16:00', ['stato' => 'F']);
        $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '18:00', '20:00', ['stato' => 'P']);
        $staff = $this->utente();

        $agenda = Livewire::actingAs($staff)->test(Turni::class)->set('vista', 'lista')->set('filtroStato', 'completato')->viewData('turni');
        $gda = Livewire::actingAs($staff)->test(Presenze::class)->set('filtroCliente', $cliente)->viewData('turniGda');

        $this->assertCount(1, $agenda);
        $this->assertCount(1, $gda);
        $this->assertSame($chiuso, $agenda->first()->id);
        $this->assertSame($chiuso, $gda->first()->id);
    }

    public function test_ops_08(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Prova', 'Via 1');
        $altra = $this->sede($cliente, 'Sede Operativa', 'Via 2');
        $staff = $this->utente();
        $prova = $this->turno($cliente, $sede, $staff->id, today()->toDateString(), '08:00', '16:00');
        $operativo = $this->turno($cliente, $altra, $staff->id, today()->toDateString(), '08:00', '16:00');
        $quando = Carbon::parse(today()->toDateString().' 09:15');

        $timbratura = new Timbratura;
        $timbratura->apriChiudi(Turno::findOrFail($prova), $staff, 'I', $quando, 'Apertura di prova');
        $timbratura->apriChiudi(Turno::findOrFail($prova)->fresh(), $staff, 'F', $quando->copy()->addHours(7), 'Chiusura di prova');

        $this->assertSame('F', DB::table('tc_turni')->where('id', $prova)->value('stato'));
        $this->assertSame('P', DB::table('tc_turni')->where('id', $operativo)->value('stato'));
        $this->assertSame(['I', 'F'], DB::table('tc_gda')->where('id_turno', $prova)->orderBy('id')->pluck('tipo')->all());
        $this->assertSame('09:15', DB::table('tc_gda')->where('id_turno', $prova)->where('tipo', 'I')->value('orario'));
        $this->assertSame(0, DB::table('tc_gda')->where('id_turno', $operativo)->count());
    }

    public function test_pre_01(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Alpha', 'Via 1');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '08:00', '16:00', ['ore' => 8, 'ore_lavorate' => 8]);

        $this->actingAs($this->utente())->get(route('presenze.clienti'))
            ->assertOk()
            ->assertSee('Conteggio orario clienti')
            ->assertSee('Alpha Srl')
            ->assertSee('Sede Alpha');
    }

    public function test_pre_02(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Alpha', 'Via 1');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'nome' => 'Mario', 'cognome' => 'Rossi', 'codice' => 'OP1', 'email' => 'op@example.test']);
        $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '08:00', '16:00', ['ore' => 8, 'ore_lavorate' => 7]);

        $this->actingAs($this->utente())->get(route('presenze.operatori'))
            ->assertOk()
            ->assertSee('Conteggio orario operatori')
            ->assertSee('Rossi Mario')
            ->assertSee('OP1');
        $this->actingAs($operatore)->get(route('presenze.operatori'))->assertForbidden();
    }

    public function test_pre_03(): void
    {
        $alpha = $this->cliente('Alpha Srl');
        $beta = $this->cliente('Beta Srl');
        $sedeAlpha = $this->sede($alpha, 'Sede Alpha', 'Via 1');
        $sedeBeta = $this->sede($beta, 'Sede Beta', 'Via 2');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'op@example.test']);
        $turno = $this->turno($alpha, $sedeAlpha, $operatore->id, today()->toDateString(), '08:00', '16:00');
        $altro = $this->turno($beta, $sedeBeta, $operatore->id, today()->toDateString(), '09:00', '17:00');
        $this->presenza($turno, $operatore->id, $alpha, $sedeAlpha, 'I', '08:00');
        $this->presenza($altro, $operatore->id, $beta, $sedeBeta, 'I', '09:00');

        $csv = $this->actingAs($this->utente())
            ->get(route('presenze.esporta', ['da' => today()->toDateString(), 'a' => today()->toDateString(), 'sede' => $sedeAlpha]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Mario Rossi', $csv);
        $this->assertStringContainsString('Sede Alpha', $csv);
        $this->assertStringNotContainsString('Sede Beta', $csv);
    }

    public function test_pre_04(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Alpha', 'Via 1');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'op@example.test']);
        $this->turno($cliente, $sede, $operatore->id, today()->toDateString(), '08:00', '16:00', ['ore' => 8, 'ore_lavorate' => 6, 'data_fine' => today()->toDateString()]);

        $contenuto = $this->actingAs($this->utente())
            ->get(route('presenze.ore', ['tipo' => 'cliente', 'id' => $cliente, 'da' => today()->toDateString(), 'a' => today()->toDateString()]))
            ->assertOk()
            ->streamedContent();
        $file = tempnam(sys_get_temp_dir(), 'ore');
        file_put_contents($file, $contenuto);
        $foglio = IOFactory::load($file)->getActiveSheet();
        @unlink($file);

        $this->assertSame(8.0, (float) $foglio->getCell('G4')->getValue());
        $this->assertSame(6.0, (float) $foglio->getCell('H4')->getValue());
        $this->assertSame(-2.0, (float) $foglio->getCell('I4')->getValue());
    }

    public function test_pre_05(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $propria = $this->sede($cliente, 'Sede Propria', 'Via 1');
        $altrui = $this->sede($cliente, 'Sede Altrui', 'Via 2');
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'email' => 'op@example.test']);
        $altro = $this->utente(['user_type' => 4, 'user' => 'altro', 'email' => 'altro@example.test']);
        $this->turno($cliente, $propria, $operatore->id, today()->addDay()->toDateString(), '08:00', '16:00');
        $this->turno($cliente, $altrui, $altro->id, today()->addDay()->toDateString(), '09:00', '17:00');

        $this->actingAs($operatore)->get(route('miei-turni'))
            ->assertOk()
            ->assertSee('Sede Propria')
            ->assertDontSee('Sede Altrui');
    }

    public function test_pre_06(): void
    {
        $operatore = $this->preparaTimbratura('Sede Scansione', '45.0567356', '7.6693');

        Livewire::actingAs($operatore)->test(Scansione::class)
            ->assertSee('Sede Scansione');
        $this->assertSame(0, DB::table('tc_gda')->count());
    }

    public function test_pre_07(): void
    {
        $operatore = $this->preparaTimbratura('Sede Senza Coordinate', '0', '0');

        $esito = (new Timbratura)->scansiona($operatore, 'ITALPOL*000001', 45.0567356, 7.6693);

        $this->assertFalse($esito['ok']);
        $this->assertSame('La sede non ha coordinate impostate. Contattare l\'amministrazione.', $esito['message']);
        $this->assertSame(0, DB::table('tc_gda')->count());
    }

    public function test_pre_08(): void
    {
        $operatore = $this->preparaTimbratura('Sede Timbratura', '45.0567356', '7.6693');
        $timbratura = new Timbratura;

        $inizio = $timbratura->scansiona($operatore, 'ITALPOL*000001', 45.0567356, 7.6693);
        $fine = $timbratura->scansiona($operatore, 'ITALPOL*000001', 45.0567356, 7.6693);

        $this->assertTrue($inizio['ok']);
        $this->assertSame('I', $inizio['tipo']);
        $this->assertTrue($fine['ok']);
        $this->assertSame('F', $fine['tipo']);
        $this->assertSame(1, DB::table('tc_gda')->where('tipo', 'I')->count());
        $this->assertSame(1, DB::table('tc_gda')->where('tipo', 'F')->count());
        $this->assertSame(1, (int) DB::table('tc_gda')->where('tipo', 'I')->value('id_sede'));
    }

    public function test_cli_01(): void
    {
        $alpha = $this->cliente('Alpha Srl');
        $beta = $this->cliente('Beta Srl');
        $this->sede($alpha, 'Sede Alpha', 'Via Alpha 1');
        $this->sede($alpha, 'Sede Alpha Due', 'Via Alpha 2');
        $this->sede($beta, 'Sede Beta', 'Via Beta 9');

        Livewire::actingAs($this->utente())->test(Sedi::class)
            ->set('filtroCliente', $alpha)
            ->assertSee('Sede Alpha')
            ->assertSee('Sede Alpha Due')
            ->assertDontSee('Sede Beta');
    }

    public function test_cli_02(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Foto', 'Via Roma 12', ['foto' => 'ingresso.jpg']);
        Storage::disk('local')->put("protected/documenti/Cliente_{$cliente}/Sede_{$sede}/ingresso.jpg", 'foto-sede');

        Livewire::actingAs($this->utente())->test(Sedi::class)
            ->set('filtroCliente', $cliente)
            ->assertSee('Sede Foto')
            ->assertSee('Via Roma 12');
        $this->actingAs(User::query()->where('user_type', 2)->first())
            ->get(route('sedi.foto', $sede))
            ->assertOk()
            ->assertStreamedContent('foto-sede');
    }

    public function test_cli_03(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Alpha', 'Via 1');
        $altra = $this->sede($cliente, 'Sede Altra', 'Via 2');
        DB::table('tc_postazioni')->insert([
            ['id_cliente' => $cliente, 'id_sede' => $sede, 'titolo' => 'Ingresso nord'],
            ['id_cliente' => $cliente, 'id_sede' => $altra, 'titolo' => 'Ingresso altrui'],
        ]);

        Livewire::actingAs($this->utente())->test(Sedi::class)
            ->call('postazioni', $sede)
            ->assertSee('Ingresso nord')
            ->assertDontSee('Ingresso altrui');
    }

    public function test_cli_04(): void
    {
        [$sede] = $this->codiceSede('ITALPOL*000010');

        $this->actingAs($this->utente())->get(route('sedi.qrcode', $sede))
            ->assertOk()
            ->assertStreamedContent('qr-sede');
        $this->assertSame(0, DB::table('tc_gda')->count());
    }

    public function test_cli_05(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Alpha', 'Via 1');
        $codice = DB::table('tc_codici')->insertGetId(['codice' => 'ITALPOL*000011', 'stato' => 'A', 'tipo' => 'P', 'id_sede' => $sede]);
        $postazione = DB::table('tc_postazioni')->insertGetId(['id_cliente' => $cliente, 'id_sede' => $sede, 'id_codice' => $codice, 'titolo' => 'Ingresso']);
        Storage::disk('local')->put((new QrSede)->percorso('ITALPOL*000011'), 'qr-postazione');

        $this->actingAs($this->utente())->get(route('postazioni.qrcode', $postazione))
            ->assertOk()
            ->assertStreamedContent('qr-postazione');
        $this->assertSame(0, DB::table('tc_gda')->count());
    }

    public function test_cli_06(): void
    {
        Livewire::actingAs($this->utente())->test(GeneratoreQr::class)
            ->set('personalizza', true)
            ->set('titolo', 'Sede prova')
            ->assertSee('ITALPOL*000001')
            ->assertSee('Sede prova');

        $this->assertSame(0, DB::table('tc_codici')->count());
    }

    public function test_cli_07(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $altro = $this->cliente('Beta Srl');
        $this->sede($altro, 'Sede gia presente', 'Via Vecchia 1');
        $codiceSede = DB::table('tc_codici')->insertGetId(['codice' => 'ITALPOL*000201', 'stato' => 'C']);
        $codicePostazione = DB::table('tc_codici')->insertGetId(['codice' => 'ITALPOL*000202', 'stato' => 'C']);
        DB::table('_comuni')->insert(['id' => 1, 'comune' => 'Modena', 'sigla_provincia' => 'MO', 'id_provincia' => 1]);

        $form = Livewire::actingAs($this->utente())->test(SedeForm::class)
            ->set('codiceSedeId', (string) $codiceSede)
            ->set('f.cliente_id', $cliente)
            ->set('f.descrizione', 'Sede Nuova')
            ->set('f.indirizzo', 'Via Roma 1')
            ->set('f.citta', 'Modena')
            ->set('f.provincia', 'MO')
            ->set('f.lat', '44.5')
            ->set('f.lng', '10.9');
        $form->call('aggiungiPostazione');
        $chiave = array_key_first($form->get('postazioni'));
        $form->set("postazioni.$chiave.codice_id", (string) $codicePostazione)
            ->set("postazioni.$chiave.titolo", 'Ingresso')
            ->call('salva')
            ->assertHasNoErrors();

        $nuova = DB::table('tc_sedi')->where('descrizione', 'Sede Nuova')->first();
        $this->assertSame($cliente, (int) $nuova->id_cliente);
        $this->assertSame($codiceSede, (int) $nuova->id_codice);
        $this->assertSame('Sede gia presente', DB::table('tc_sedi')->where('id_cliente', $altro)->value('descrizione'));
        $this->assertSame($cliente, (int) DB::table('tc_postazioni')->where('titolo', 'Ingresso')->value('id_cliente'));
    }

    public function test_cli_08(): void
    {
        $proprio = $this->cliente('Alpha Srl');
        $altrui = $this->cliente('Beta Srl');
        $sedePropria = $this->sede($proprio, 'Sede Alpha', 'Via Alpha 1', ['foto' => 'a.jpg']);
        $sedeAltrui = $this->sede($altrui, 'Sede Beta', 'Via Beta 2', ['foto' => 'b.jpg']);
        Storage::disk('local')->put("protected/documenti/Cliente_{$proprio}/Sede_{$sedePropria}/a.jpg", 'foto-alpha');
        Storage::disk('local')->put("protected/documenti/Cliente_{$altrui}/Sede_{$sedeAltrui}/b.jpg", 'foto-beta');
        $account = $this->utente(['user_type' => 3, 'user' => 'cliente', 'email' => 'cliente@example.test', 'id_cliente' => $proprio]);

        Livewire::actingAs($account)->test(Sedi::class)
            ->assertSee('Sede Alpha')
            ->assertDontSee('Sede Beta');
        $this->actingAs($account)->get(route('sedi.foto', $sedeAltrui))->assertForbidden();
        $this->actingAs($account)->get(route('sedi.foto', $sedePropria))->assertOk()->assertStreamedContent('foto-alpha');
    }

    public function test_ope_01(): void
    {
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'email' => 'luca@example.test']);
        DB::table('tc_bacheca')->insert(['id' => 1, 'titolo' => 'Cambio turno', 'testo' => 'Domani alle 7', 'quanti' => 1, 'data_invio' => '2026-11-01', 'tutti' => 'N']);
        DB::table('tc_bacheca_view')->insert(['id' => 1, 'id_bacheca' => 1, 'id_operatore' => $mario->id, 'visto' => null]);

        Livewire::actingAs($mario)->test(Bacheca::class)->assertSee('Cambio turno')->assertSee('Domani alle 7');
        Livewire::actingAs($luca)->test(Bacheca::class)->assertDontSee('Domani alle 7');
    }

    public function test_ope_02(): void
    {
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'email' => 'luca@example.test']);
        DB::table('tc_bacheca')->insert(['id' => 1, 'titolo' => 'Avviso', 'testo' => 'Testo', 'quanti' => 2, 'data_invio' => '2026-11-01', 'tutti' => 'N']);
        DB::table('tc_bacheca_view')->insert([
            ['id' => 1, 'id_bacheca' => 1, 'id_operatore' => $mario->id, 'visto' => null],
            ['id' => 2, 'id_bacheca' => 1, 'id_operatore' => $mario->id, 'visto' => 'N'],
            ['id' => 3, 'id_bacheca' => 1, 'id_operatore' => $luca->id, 'visto' => null],
        ]);

        Livewire::actingAs($mario)->test(ContatoreNotifiche::class, ['tipo' => 'bacheca'])->assertViewHas('conteggio', 2);
        Livewire::actingAs($luca)->test(ContatoreNotifiche::class, ['tipo' => 'bacheca'])->assertViewHas('conteggio', 1);
    }

    public function test_ope_03(): void
    {
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'email' => 'luca@example.test']);
        $id = $this->documento($mario->id, 'O', 'Busta paga', 'busta.pdf', 'busta-di-mario');

        $this->actingAs($mario)->get(route('documenti.scarica', $id))->assertOk()->assertStreamedContent('busta-di-mario');
        $this->actingAs($luca)->get(route('documenti.scarica', $id))->assertNotFound();
    }

    public function test_ope_04(): void
    {
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'email' => 'luca@example.test']);
        DB::table('tc_bacheca')->insert([
            ['id' => 1, 'titolo' => 'Cambio turno', 'testo' => 'Solo Mario', 'quanti' => 1, 'data_invio' => '2026-11-01', 'tutti' => 'N'],
            ['id' => 2, 'titolo' => 'Riunione Luca', 'testo' => 'Solo Luca', 'quanti' => 1, 'data_invio' => '2026-11-02', 'tutti' => 'N'],
        ]);
        DB::table('tc_bacheca_view')->insert([
            ['id' => 1, 'id_bacheca' => 1, 'id_operatore' => $mario->id, 'visto' => null],
            ['id' => 2, 'id_bacheca' => 2, 'id_operatore' => $luca->id, 'visto' => null],
        ]);

        Livewire::actingAs($mario)->test(CampanellaNotifiche::class)
            ->assertSee('Cambio turno')
            ->assertSee('Solo Mario')
            ->assertDontSee('Riunione Luca');
    }

    public function test_ope_05(): void
    {
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@example.test', 'cellulare' => '3331111111', 'avatar' => 'mario.jpg']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'nome' => 'Luca', 'cognome' => 'Bianchi', 'email' => 'luca@example.test', 'avatar' => 'luca.jpg']);
        Storage::disk('local')->put('images/avatar/mario.jpg', 'foto-mario');
        Storage::disk('local')->put('images/avatar/luca.jpg', 'foto-luca');

        Livewire::actingAs($mario)->test(Profilo::class)
            ->assertSet('name', 'Mario')
            ->assertSet('email', 'mario@example.test')
            ->assertDontSee('luca@example.test');
        $this->actingAs($mario)->get(route('profilo.foto', $mario))->assertOk()->assertStreamedContent('foto-mario');
        $this->actingAs($mario)->get(route('profilo.foto', $luca))->assertForbidden();
    }

    public function test_ope_06(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Istruzioni', 'Via 1');
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'email' => 'luca@example.test']);
        $this->turno($cliente, $sede, $mario->id, today()->addDay()->toDateString(), '07:30', '15:30', ['note_operatore' => 'Chiavi in portineria']);
        $this->turno($cliente, $this->sede($cliente, 'Sede Luca', 'Via 2'), $luca->id, today()->addDay()->toDateString(), '08:00', '16:00');

        $turno = DB::table('tc_turni')->where('id_operatore', $mario->id)->value('id');

        Livewire::actingAs($mario)->test(Turni::class)
            ->assertSee('Sede Istruzioni')
            ->assertSee('07:30')
            ->assertDontSee('Sede Luca')
            ->call('mostraTurno', $turno)
            ->assertSee('Chiavi in portineria');
    }

    public function test_ope_07(): void
    {
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'email' => 'luca@example.test']);
        $this->documento($mario->id, 'O', 'Certificato scaduto', 'cert.pdf', 'x', today()->subDay()->toDateString());

        Livewire::actingAs($mario)->test(Documenti::class)
            ->assertSee('Certificato scaduto')
            ->assertSee(today()->subDay()->format('d/m/Y'))
            ->assertSee('bg-rose-100', false);
        Livewire::actingAs($luca)->test(Documenti::class)
            ->assertSee('Nessun documento disponibile.')
            ->assertDontSee('Certificato scaduto');
    }

    public function test_ope_08(): void
    {
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'email' => 'mario@example.test']);
        DB::table('tc_bacheca')->insert([
            ['id' => 1, 'titolo' => 'Primo', 'testo' => 'Testo primo', 'quanti' => 1, 'data_invio' => '2026-11-01', 'tutti' => 'N'],
            ['id' => 2, 'titolo' => 'Secondo', 'testo' => 'Testo secondo', 'quanti' => 1, 'data_invio' => '2026-11-02', 'tutti' => 'N'],
        ]);
        DB::table('tc_bacheca_view')->insert([
            ['id' => 1, 'id_bacheca' => 1, 'id_operatore' => $mario->id, 'visto' => null],
            ['id' => 2, 'id_bacheca' => 2, 'id_operatore' => $mario->id, 'visto' => null],
        ]);

        Livewire::actingAs($mario)->test(Bacheca::class)->call('segnaLetto', 1);

        $this->assertSame('Y', DB::table('tc_bacheca_view')->where('id', 1)->value('visto'));
        $this->assertNull(DB::table('tc_bacheca_view')->where('id', 2)->value('visto'));
        $this->assertSame('Testo primo', DB::table('tc_bacheca')->where('id', 1)->value('testo'));
        $this->assertSame('Testo secondo', DB::table('tc_bacheca')->where('id', 2)->value('testo'));
    }

    public function test_comms_01(): void
    {
        DB::table('tc_messaggi')->insert([
            'id' => 1, 'nome' => 'Avviso turni', 'giorni' => 1, 'ore' => 2, 'numero_sms' => 2, 'numero_email' => 3,
            'mittente' => 'OMNIA', 'oggetto' => 'Turni', 'modello_email' => 'Ciao [OPERATORE]', 'invia_email' => 'Y', 'invia_sms' => 'N',
        ]);

        Livewire::actingAs($this->utente())->test(ModelliMessaggio::class)
            ->assertSee('Avviso turni')
            ->call('modifica', 1)
            ->assertSet('f.numero_sms', 2)
            ->assertSet('f.numero_email', 3)
            ->set('f.nome', 'Non salvato');

        $this->assertSame('Avviso turni', DB::table('tc_messaggi')->value('nome'));
        $this->assertSame('Ciao [OPERATORE]', DB::table('tc_messaggi')->value('modello_email'));
    }

    public function test_comms_02(): void
    {
        $testo = (new Comunicazioni)->testo(
            'Ciao [OPERATORE], sede [SEDE] alle [ORA TURNO]',
            ['cognome' => 'Rossi', 'nome' => 'Mario', 'descrizione' => 'Sede Nord', 'orario' => '08:00'],
        );

        $this->assertSame('Ciao Rossi Mario, sede Sede Nord alle 08:00', $testo);
        $this->assertStringNotContainsString('Bianchi', $testo);
    }

    public function test_comms_03(): void
    {
        $cliente = $this->cliente('Alpha Srl');
        $sede = $this->sede($cliente, 'Sede Mario', 'Via 1');
        $mario = $this->utente(['user_type' => 4, 'user' => 'mario', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@example.test']);
        $luca = $this->utente(['user_type' => 4, 'user' => 'luca', 'nome' => 'Luca', 'cognome' => 'Bianchi', 'email' => 'luca@example.test']);
        $this->turno($cliente, $sede, $mario->id, today()->toDateString(), '08:00', '16:00');
        $this->turno($cliente, $sede, $luca->id, today()->toDateString(), '09:00', '17:00');
        $this->documento($mario->id, 'O', 'Documento Mario', 'mario.pdf', 'm');
        $this->documento($luca->id, 'O', 'Documento Luca', 'luca.pdf', 'l');

        $righe = (new Comunicazioni)->turni()->where('t.id_operatore', $mario->id)->get();
        $documenti = DB::table('tc_documenti')->where('tipo', 'O')->where('id_tipo', $mario->id)->pluck('descrizione')->all();

        $this->assertCount(1, $righe);
        $this->assertSame('mario@example.test', $righe[0]->email);
        $this->assertSame(['Documento Mario'], $documenti);
    }

    public function test_comms_04(): void
    {
        $this->smsFallito('Consegna fallita dal provider');

        Livewire::actingAs($this->utente())->test(Messaggi::class, ['canale' => 'sms'])
            ->assertSee('Fallita')
            ->assertDontSee('Consegnato');
    }

    public function test_comms_05(): void
    {
        Mail::fake();

        $inviate = (new Comunicazioni)->invia(['interno@example.test' => 'Prova'], 'Oggetto prova', 'Testo prova');

        $this->assertSame(1, $inviate);
        Mail::assertSent(NotificaHtml::class, fn (NotificaHtml $mail) => $mail->hasTo('interno@example.test'));
        Mail::assertNotSent(NotificaHtml::class, fn (NotificaHtml $mail) => $mail->hasTo('reale@example.test'));
    }

    public function test_comms_06(): void
    {
        $this->smsFallito('Provider 401 token rifiutato');

        Livewire::actingAs($this->utente())->test(Messaggi::class, ['canale' => 'sms'])
            ->assertSee('Non inviato')
            ->assertSee('Provider 401 token rifiutato')
            ->assertDontSee('Consegnato');
    }

    public function test_comms_07(): void
    {
        config(['sms.enabled' => false]);
        Mail::fake();
        $modello = Messaggio::create([
            'nome' => 'Avviso', 'giorni' => 0, 'mittente' => 'OMNIA', 'numero_sms' => 1, 'numero_email' => 1,
            'invia_sms' => 'Y', 'invia_email' => 'Y', 'modello_sms' => 'Sms', 'oggetto' => 'Oggetto', 'modello_email' => 'Email',
        ]);

        $sms = (new Comunicazioni)->inviaSms($modello, [], '3331234567', 1);
        $email = (new Comunicazioni)->invia(['interno@example.test' => 'Prova'], 'Oggetto', 'Corpo');

        $this->assertSame(0, $sms['inviati']);
        $this->assertSame('invio SMS disattivato', $sms['motivo']);
        $this->assertSame(0, DB::table('tc_send_sms')->count());
        $this->assertSame(1, $email);
        Mail::assertSent(NotificaHtml::class, fn (NotificaHtml $mail) => $mail->hasTo('interno@example.test'));
    }

    public function test_comms_08(): void
    {
        Mail::fake();
        config(['sms.enabled' => true, 'whatsapp.enabled' => false, 'notifiche.prova' => false]);
        $provaCliente = $this->cliente('prova');
        $realeCliente = $this->cliente('Cliente Reale');
        $prova = $this->utente(['user_type' => 4, 'user' => 'prova', 'nome' => 'Prova', 'cognome' => 'Test', 'email' => ProvaNotifiche::EMAIL, 'cellulare' => '3330000001']);
        $reale = $this->utente(['user_type' => 4, 'user' => 'reale', 'nome' => 'Reale', 'cognome' => 'Utente', 'email' => 'reale@example.test', 'cellulare' => '3330000002']);
        $ora = now()->startOfHour()->format('H:i');
        $this->turno($provaCliente, $this->sede($provaCliente, 'Sede Prova', 'Via 1'), $prova->id, today()->toDateString(), $ora, '23:59');
        $this->turno($realeCliente, $this->sede($realeCliente, 'Sede Reale', 'Via 2'), $reale->id, today()->toDateString(), $ora, '23:59');
        Messaggio::create([
            'nome' => 'Avviso', 'giorni' => 0, 'ore' => 0, 'mittente' => 'OMNIA', 'numero_sms' => 1, 'numero_email' => 1,
            'invia_sms' => 'Y', 'invia_email' => 'Y', 'oggetto' => 'Turno', 'modello_email' => 'Ciao [OPERATORE]', 'modello_sms' => 'Sms [OPERATORE]',
        ]);

        $this->artisan('notifiche:avviso-turni', ['--prova-sicura' => true])->assertSuccessful();

        Mail::assertSent(NotificaHtml::class, fn (NotificaHtml $mail) => $mail->hasTo(ProvaNotifiche::EMAIL));
        Mail::assertNotSent(NotificaHtml::class, fn (NotificaHtml $mail) => $mail->hasTo('reale@example.test'));
        $this->assertSame(0, DB::table('tc_send_sms')->count());
    }

    public function test_auth_01(): void
    {
        $this->utente(['user' => 'mario', 'password' => 'segreta1', 'user_type' => 2, 'attivo' => 'Y']);

        Livewire::test(Login::class)
            ->set('email', 'mario')
            ->set('password', 'segreta1')
            ->call('accedi')
            ->assertRedirect('/');

        $this->assertAuthenticated();
        $this->get('/')->assertRedirect(route('clienti'));
    }

    public function test_auth_02(): void
    {
        $utente = $this->utente();

        $this->actingAs($utente)
            ->withSession(['_token' => 'token-collaudo'])
            ->post(route('logout'), ['_token' => 'token-collaudo'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('clienti'))->assertRedirect(route('login'));
    }

    public function test_auth_03(): void
    {
        Mail::fake();
        $this->utente(['user' => 'mario@example.test', 'email' => 'mario@example.test', 'password' => 'vecchia123']);

        Livewire::test(RecuperaPassword::class)
            ->set('email', 'mario@example.test')
            ->call('recupera')
            ->assertSet('inviata', true);

        Mail::assertSent(NotificaHtml::class, fn (NotificaHtml $mail) => $mail->hasTo('mario@example.test'));
        Mail::assertSentCount(1);
        $this->assertFalse(Hash::check('vecchia123', (string) DB::table('tc_admin')->value('password')));
    }

    public function test_auth_04(): void
    {
        $this->utente(['user' => 'spento', 'password' => 'segreta1', 'attivo' => 'N']);

        Livewire::test(Login::class)
            ->set('email', 'spento')
            ->set('password', 'segreta1')
            ->call('accedi')
            ->assertHasErrors('email')
            ->assertSee('Credenziali non valide.');

        $this->assertGuest();
        $this->assertSame('N', DB::table('tc_admin')->value('attivo'));
    }

    public function test_auth_05(): void
    {
        $this->utente(['user' => 'mario', 'password' => 'segreta1', 'attivo' => 'Y']);
        $login = Livewire::test(Login::class)->set('email', 'mario');

        for ($tentativo = 0; $tentativo < 5; $tentativo++) {
            $login->set('password', 'sbagliata')->call('accedi');
        }
        $login->set('password', 'segreta1')->call('accedi')->assertHasErrors('email')->assertSee('Troppi tentativi.');
        $this->assertSame('Y', DB::table('tc_admin')->value('attivo'));

        RateLimiter::clear('mario|127.0.0.1');
        $login->set('password', 'segreta1')->call('accedi')->assertHasNoErrors()->assertRedirect('/');
    }

    private function cliente(string $ragione, array $extra = []): int
    {
        return DB::table('tc_clienti')->insertGetId(['ragione_sociale' => $ragione] + $extra);
    }

    private function sede(int $cliente, string $descrizione, string $indirizzo, array $extra = []): int
    {
        return DB::table('tc_sedi')->insertGetId([
            'id_cliente' => $cliente,
            'descrizione' => $descrizione,
            'indirizzo' => $indirizzo,
            'lat' => 0,
            'lng' => 0,
        ] + $extra);
    }

    private function turno(int $cliente, int $sede, int $operatore, string $giorno, string $inizio, string $fine, array $extra = []): int
    {
        return DB::table('tc_turni')->insertGetId([
            'id_cliente' => $cliente,
            'id_sede' => $sede,
            'id_operatore' => $operatore,
            'data_inizio' => $giorno,
            'data_fine' => $extra['data_fine'] ?? $giorno,
            'ora_inizio' => $inizio,
            'ora_fine' => $fine,
            'stato' => $extra['stato'] ?? 'P',
            'ore' => $extra['ore'] ?? 8,
            'ore_lavorate' => $extra['ore_lavorate'] ?? null,
            'note_operatore' => $extra['note_operatore'] ?? null,
        ]);
    }

    private function presenza(int $turno, int $operatore, int $cliente, int $sede, string $tipo, string $orario): void
    {
        DB::table('tc_gda')->insert([
            'id_turno' => $turno,
            'id_operatore' => $operatore,
            'id_cliente' => $cliente,
            'id_sede' => $sede,
            'tipo' => $tipo,
            'data' => today()->toDateString(),
            'orario' => $orario,
            'note_operatore' => '',
        ]);
    }

    private function documento(int $proprietario, string $tipo, string $descrizione, string $file, string $contenuto, ?string $scadenza = null): int
    {
        $cartella = $tipo === 'C' ? 'Cliente_'.$proprietario : 'Operatore_'.$proprietario;
        Storage::disk('local')->put("protected/documenti/{$cartella}/{$file}", $contenuto);

        return DB::table('tc_documenti')->insertGetId([
            'id_tipo' => $proprietario,
            'tipo' => $tipo,
            'descrizione' => $descrizione,
            'file' => $file,
            'icona' => 'file-pdf',
            'scadenza' => $scadenza,
            'admin' => 'N',
            'formazione' => 'N',
        ]);
    }

    private function preparaTimbratura(string $descrizione, string $lat, string $lng): User
    {
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op-'.$descrizione, 'email' => strtolower(str_replace(' ', '-', $descrizione)).'@example.test']);
        $cliente = $this->cliente('Alpha Srl');
        $sede = DB::table('tc_sedi')->insertGetId([
            'id' => 1,
            'id_cliente' => $cliente,
            'descrizione' => $descrizione,
            'lat' => $lat,
            'lng' => $lng,
            'distanza' => 100,
        ]);
        DB::table('tc_codici')->insert(['id' => 1, 'codice' => 'ITALPOL*000001', 'stato' => 'A', 'tipo' => 'S', 'id_sede' => $sede]);
        DB::table('tc_turni')->insert([
            'id' => 1,
            'id_cliente' => $cliente,
            'id_sede' => $sede,
            'id_operatore' => $operatore->id,
            'data_inizio' => today()->toDateString(),
            'data_fine' => today()->toDateString(),
            'ora_inizio' => now()->subMinutes(30)->format('H:i'),
            'ora_fine' => now()->addHours(2)->format('H:i'),
            'stato' => 'P',
        ]);

        return $operatore;
    }

    private function codiceSede(string $codice): array
    {
        $cliente = $this->cliente('Alpha Srl');
        $idCodice = DB::table('tc_codici')->insertGetId(['codice' => $codice, 'stato' => 'A', 'tipo' => 'S']);
        $sede = $this->sede($cliente, 'Sede QR', 'Via 1', ['id_codice' => $idCodice]);
        DB::table('tc_codici')->where('id', $idCodice)->update(['id_sede' => $sede]);
        Storage::disk('local')->put((new QrSede)->percorso($codice), 'qr-sede');

        return [$sede, $idCodice];
    }

    private function smsFallito(string $risposta): void
    {
        $operatore = $this->utente(['user_type' => 4, 'user' => 'op', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'op@example.test']);
        $sms = DB::table('tc_send_sms')->insertGetId([
            'destinatari' => '3331234567',
            'sender' => 'OMNIA',
            'testo' => 'Messaggio',
            'quanti' => 1,
            'data_invio' => now(),
            'id_operatore' => $operatore->id,
            'sent' => 'N',
        ]);
        DB::table('tc_send_sms_result')->insert([
            'send_id' => $sms,
            'adl_id' => 'ref-fallito',
            'response' => $risposta,
            'numero' => '3331234567',
            'message' => 'Messaggio',
        ]);
        DB::table('tc_send_sms_delivery')->insert([
            'send_id' => $sms,
            'refer_id' => 'ref-fallito',
            'delivery_status' => 'F',
            'delivery_data' => now(),
        ]);
    }
}
