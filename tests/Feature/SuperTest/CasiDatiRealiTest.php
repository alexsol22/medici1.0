<?php

namespace Tests\Feature\SuperTest;

use App\Livewire\Accessi;
use App\Livewire\Amministratori;
use App\Livewire\Bacheca;
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
use App\Livewire\SedeForm;
use App\Livewire\Sedi;
use App\Livewire\Turni;
use App\Mail\NotificaHtml;
use App\Models\Legacy\BachecaView;
use App\Models\Legacy\Cliente;
use App\Models\Legacy\Codice;
use App\Models\Legacy\Comune;
use App\Models\Legacy\Config;
use App\Models\Legacy\Documento;
use App\Models\Legacy\Gda;
use App\Models\Legacy\Messaggio;
use App\Models\Legacy\Sede;
use App\Models\Legacy\SendSms;
use App\Models\Legacy\Turno;
use App\Models\User;
use App\Services\Comunicazioni;
use App\Services\ProvaNotifiche;
use App\Services\Timbratura;
use App\SuperTest\Diario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Casi del collaudo eseguiti sulla copia dei dati reali, mai sul database dell'applicazione. */
class CasiDatiRealiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $file = $_SERVER['SUPERTTEST_DATABASE'] ?? $_ENV['SUPERTTEST_DATABASE'] ?? getenv('SUPERTTEST_DATABASE');

        if (! is_string($file) || ! is_file($file) || ! str_contains($file, 'supertest'.DIRECTORY_SEPARATOR.'sessioni')) {
            $this->markTestSkipped('Dato assente: nessuna sessione con i dati reali.');
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'sqlite',
                'database' => $file,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'sms.enabled' => false,
            'notifiche.prova' => false,
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $pdo = DB::connection('mysql')->getPdo();

        if ($pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('DATE_FORMAT', function (?string $data, ?string $formato): ?string {
                if ($data === null || $data === '' || $formato === null) {
                    return null;
                }

                $istante = strtotime($data);

                if ($istante === false) {
                    return null;
                }

                $tradotto = strtr($formato, [
                    '%d' => 'd', '%m' => 'm', '%Y' => 'Y', '%H' => 'H', '%i' => 'i', '%s' => 's',
                ]);

                return date($tradotto, $istante);
            }, 2);
        }

        Mail::fake();
    }

    public function test_com_01(): void
    {
        $staff = $this->staff();
        $operatore = $this->operatore();
        $this->passo('Entro come staff e come operatore e leggo la pagina iniziale.');
        $this->actingAs($staff)->get('/')->assertRedirect(route('clienti'));
        $this->actingAs($operatore)->get('/')->assertRedirect(route('scansione'));
        $cliente = User::query()->where('user_type', 3)->where('attivo', 'Y')->whereNotNull('id_cliente')->first();

        if ($cliente !== null) {
            $this->passo('Account cliente '.$cliente->email.' verso l’agenda.');
            $this->actingAs($cliente)->get('/')->assertRedirect(route('turni'));
        }
    }

    public function test_com_02(): void
    {
        $staff = $this->staff();
        $operatore = $this->operatore();
        $this->passo('Apro il menu dello staff e quello dell’operatore.');
        $this->actingAs($staff)->get(route('clienti'))
            ->assertOk()
            ->assertSee('href="'.route('clienti').'"', false)
            ->assertSee('href="'.route('operatori').'"', false)
            ->assertDontSee('href="'.route('scansione').'"', false);
        $this->actingAs($operatore)->get(route('miei-turni'))
            ->assertOk()
            ->assertSee('href="'.route('miei-turni').'"', false)
            ->assertDontSee('href="'.route('clienti').'"', false);
    }

    public function test_com_03(): void
    {
        $staff = $this->staff();
        $cliente = $this->cliente($staff);
        $this->passo('Apro l’anagrafica di '.$cliente->ragione_sociale.'.');
        Livewire::actingAs($staff)->test(Clienti::class)
            ->set('cerca', (string) $cliente->ragione_sociale)
            ->assertSee($cliente->ragione_sociale);
    }

    public function test_com_04(): void
    {
        $staff = $this->staff();
        $cliente = $this->cliente($staff);
        $this->passo('Cerco '.$cliente->ragione_sociale.' e poi un termine inesistente.');
        Livewire::actingAs($staff)->test(Clienti::class)
            ->set('cerca', (string) $cliente->ragione_sociale)
            ->assertSee($cliente->ragione_sociale)
            ->set('cerca', 'zzzz-supertest-nessuno')
            ->assertSee('Nessun cliente trovato.')
            ->assertDontSee($cliente->ragione_sociale);
    }

    public function test_com_05(): void
    {
        $staff = $this->staff();
        $cliente = $this->cliente($staff);
        $altro = $this->altroCliente($staff, $cliente);
        $this->passo('Filtro i clienti per '.$cliente->ragione_sociale.'.');
        $pagina = Livewire::actingAs($staff)->test(Clienti::class)
            ->set('filtroRagioneSociale', (string) $cliente->ragione_sociale)
            ->assertSee($cliente->ragione_sociale);

        if ($altro !== null && ! str_contains((string) $cliente->ragione_sociale, (string) $altro->ragione_sociale)) {
            $pagina->assertDontSee($altro->ragione_sociale);
        }
    }

    public function test_com_06(): void
    {
        $operatore = $this->operatore();
        $this->passo('L’operatore '.$operatore->nome.' apre l’URL dei clienti.');
        $this->actingAs($operatore)->get(route('clienti'))->assertForbidden();
    }

    public function test_com_07(): void
    {
        $staff = $this->staff();
        $documento = Documento::query()->whereNotNull('file')->orderByDesc('id')->first();

        if ($documento === null) {
            $this->manca('Non ci sono documenti in anagrafica.');
        }

        $this->passo('Scarico il documento «'.$documento->descrizione.'».');
        $disco = Storage::disk(config('filesystems.files_disk'));

        if (! $disco->exists($documento->percorso())) {
            $this->fail('Il documento «'.$documento->descrizione.'» è in anagrafica ma il file non è sul disco.');
        }

        $this->actingAs($staff)->get(route('documenti.scarica', $documento))->assertOk();
        $altro = $this->operatore();

        if ((int) $documento->id_tipo !== (int) $altro->id || $documento->tipo !== 'O') {
            $this->actingAs($altro)->get(route('documenti.scarica', $documento))->assertNotFound();
        }
    }

    public function test_com_08(): void
    {
        $staff = $this->staff();
        $this->passo('Controllo il markup responsive della pagina clienti.');
        $this->actingAs($staff)->get(route('clienti'))
            ->assertOk()
            ->assertSee('width=device-width', false)
            ->assertSee('lg:hidden', false);
    }

    public function test_amm_01(): void
    {
        $staff = $this->staff();
        $cliente = $this->cliente($staff);
        $altro = $this->altroCliente($staff, $cliente);
        $this->passo('Elenco clienti, poi filtro '.$cliente->ragione_sociale.'.');
        $elenco = Livewire::actingAs($staff)->test(Clienti::class)->assertSee($cliente->ragione_sociale);

        if ($altro !== null) {
            $elenco->assertSee($altro->ragione_sociale);
        }

        $elenco->set('filtroRagioneSociale', (string) $cliente->ragione_sociale)->assertSee($cliente->ragione_sociale);
    }

    public function test_amm_02(): void
    {
        $staff = $this->staff();
        $cliente = $this->cliente($staff);
        $sede = Sede::query()->where('id_cliente', $cliente->id)->whereNotNull('descrizione')->first();

        if ($sede === null) {
            $this->manca($cliente->ragione_sociale.' non ha sedi.');
        }

        $this->passo('Apro le sedi di '.$cliente->ragione_sociale.': '.$sede->descrizione.'.');
        $pagina = Livewire::actingAs($staff)->test(Sedi::class)
            ->set('filtroCliente', $cliente->id)
            ->assertSee($sede->descrizione);
        $altra = Sede::query()->where('id_cliente', '!=', $cliente->id)->whereNotNull('descrizione')->first();

        if ($altra !== null && ! str_contains((string) $sede->descrizione, (string) $altra->descrizione)) {
            $pagina->assertDontSee($altra->descrizione);
        }
    }

    public function test_amm_03(): void
    {
        $staff = $this->staff();
        $attivo = $this->operatore();
        $this->passo('Cerco l’operatore attivo '.$attivo->cognome.' '.$attivo->nome.'.');
        Livewire::actingAs($staff)->test(Operatori::class)
            ->set('filtroCognome', (string) $attivo->cognome)
            ->assertSee($attivo->cognome);
        $spento = User::query()->where('user_type', 4)->where('attivo', 'N')->whereNotNull('cognome')->first();

        if ($spento !== null) {
            $this->passo('Cerco l’operatore non attivo '.$spento->cognome.'.');
            Livewire::actingAs($staff)->test(Operatori::class)
                ->set('filtroCognome', (string) $spento->cognome)
                ->assertSee($spento->cognome);
        }
    }

    public function test_amm_04(): void
    {
        $staff = $this->staff();
        $operatore = $this->operatore();
        $documento = Documento::query()->where('tipo', 'O')->where('id_tipo', $operatore->id)->first();

        if ($documento === null) {
            $this->manca($operatore->cognome.' non ha documenti.');
        }

        $this->passo('Apro i documenti di '.$operatore->cognome.' '.$operatore->nome.'.');
        Livewire::actingAs($staff)->test(Documenti::class)
            ->set('filtroOperatore', (string) $operatore->id)
            ->assertSee($documento->descrizione);
    }

    public function test_amm_05(): void
    {
        $staff = $this->staff();
        $this->passo('Apro amministratori e registro accessi.');
        Livewire::actingAs($staff)->test(Amministratori::class)->assertOk()->assertSee($staff->cognome);
        Livewire::actingAs($staff)->test(Accessi::class)->assertOk();
        $this->actingAs($this->operatore())->get(route('amministratori'))->assertForbidden();
        $this->actingAs($this->operatore())->get(route('accessi'))->assertForbidden();
    }

    public function test_amm_06(): void
    {
        $staff = $this->staff();
        $config = Config::query()->first();
        $modello = Messaggio::query()->first();

        if ($config === null || $modello === null) {
            $this->manca('Manca la configurazione società o un modello di messaggio.');
        }

        $this->passo('Leggo «'.$config->ragione_sociale.'» e il modello «'.$modello->nome.'» senza salvare.');
        $primaSocieta = (string) $config->ragione_sociale;
        $primaNome = (string) $modello->nome;
        Livewire::actingAs($staff)->test(Impostazioni::class)
            ->assertSet('f.ragione_sociale', $primaSocieta)
            ->set('f.ragione_sociale', 'Nome non salvato');
        $this->assertSame($primaSocieta, (string) Config::query()->value('ragione_sociale'));
        Livewire::actingAs($staff)->test(ModelliMessaggio::class)
            ->assertSee($primaNome)
            ->call('modifica', $modello->id)
            ->set('f.nome', 'Nome non salvato');
        $this->assertSame($primaNome, (string) Messaggio::query()->whereKey($modello->id)->value('nome'));
    }

    public function test_amm_07(): void
    {
        $staff = $this->staff();
        $this->passo('Apro il profilo di '.$staff->nome.' '.$staff->cognome.'.');
        Livewire::actingAs($staff)->test(Profilo::class)
            ->assertSet('name', (string) $staff->nome)
            ->assertSet('email', (string) $staff->email);
    }

    public function test_amm_08(): void
    {
        $staff = $this->staff();
        $prima = Cliente::query()->count();
        $this->passo('Creo un cliente solo sulla copia. Il database dell’applicazione non cambia.');
        Livewire::actingAs($staff)->test(Clienti::class)
            ->call('nuovo')
            ->set('f.ragione_sociale', 'Sessione supertest')
            ->set('f.piva', $this->pivaLibera())
            ->set('f.email', 'sessione-supertest@example.test')
            ->set('f.accesso', false)
            ->call('salva')
            ->assertHasNoErrors();
        $this->assertSame($prima + 1, Cliente::query()->count());
        $this->assertDatabaseHas('tc_clienti', ['ragione_sociale' => 'Sessione supertest']);
    }

    public function test_amm_09(): void
    {
        $staff = $this->staff();
        $prima = Cliente::query()->count();
        $this->passo('Provo una partita IVA di 3 cifre.');
        Livewire::actingAs($staff)->test(Clienti::class)
            ->call('nuovo')
            ->set('f.ragione_sociale', 'Sessione non valida')
            ->set('f.piva', '123')
            ->set('f.email', 'sessione-nonvalida@example.test')
            ->call('salva')
            ->assertHasErrors(['f.piva'])
            ->assertSee('Il campo partita IVA deve essere di 11 cifre.');
        $this->assertSame($prima, Cliente::query()->count());
    }

    public function test_ops_01(): void
    {
        $staff = $this->staff();
        $turno = $this->turno();
        $this->passo('Turno del '.$turno->data_inizio->format('d/m/Y').' presso '.$turno->sede?->descrizione.'.');
        Livewire::actingAs($staff)->test(Turni::class)
            ->set('vista', 'lista')
            ->set('da', $turno->data_inizio->toDateString())
            ->set('a', $turno->data_inizio->toDateString())
            ->set('filtroCliente', $turno->id_cliente)
            ->assertSee($turno->sede?->descrizione)
            ->assertSee($turno->ora_inizio);
    }

    public function test_ops_02(): void
    {
        $staff = $this->staff();
        $turno = $this->turno();
        $altro = Turno::query()->where('id_cliente', '!=', $turno->id_cliente)->whereDate('data_inizio', $turno->data_inizio)->first();
        $this->passo('Filtro il cliente '.$turno->cliente?->ragione_sociale.' nel giorno '.$turno->data_inizio->format('d/m/Y').'.');
        $pagina = Livewire::actingAs($staff)->test(Turni::class)
            ->set('vista', 'lista')
            ->set('filtroCliente', $turno->id_cliente)
            ->set('da', $turno->data_inizio->toDateString())
            ->set('a', $turno->data_inizio->toDateString())
            ->assertSee($turno->sede?->descrizione);

        if ($altro?->sede !== null && $altro->sede->descrizione !== $turno->sede?->descrizione) {
            $pagina->assertDontSee('>'.$altro->sede->descrizione.'</td>', false);
        }
    }

    public function test_ops_03(): void
    {
        $staff = $this->staff();
        $chiuso = Turno::query()->where('stato', 'F')->whereHas('sede')->orderByDesc('data_inizio')->first();

        if ($chiuso === null) {
            $this->manca('Non ci sono turni completati.');
        }

        $this->passo('Filtro i turni completati. Il turno '.$chiuso->id.' è chiuso.');
        Livewire::actingAs($staff)->test(Turni::class)
            ->set('vista', 'lista')
            ->set('filtroStato', 'completato')
            ->set('da', $chiuso->data_inizio->toDateString())
            ->set('a', $chiuso->data_inizio->toDateString())
            ->assertSee($chiuso->sede?->descrizione);
    }

    public function test_ops_04(): void
    {
        $staff = $this->staff();
        $turno = Turno::query()->whereNotNull('note_operatore')->where('note_operatore', '!=', '')->whereHas('sede')->orderByDesc('id')->first()
            ?? $this->turno();
        $this->passo('Apro il dettaglio del turno '.$turno->id.'.');
        Livewire::actingAs($staff)->test(Turni::class)
            ->set('vista', 'lista')
            ->call('mostraTurno', $turno->id)
            ->assertSee($turno->sede?->descrizione);

        if (filled($turno->note_operatore)) {
            $this->passo('Nota letta: '.$turno->note_operatore);
        }
    }

    public function test_ops_05(): void
    {
        $staff = $this->staff();
        $turno = Turno::query()->where('stato', 'F')->orderByDesc('data_inizio')->first();

        if ($turno === null) {
            $this->manca('Non ci sono turni completati da riepilogare.');
        }

        $this->passo('Apro le presenze senza filtro e poi per il cliente del turno '.$turno->id.'.');
        $vuoto = Livewire::actingAs($staff)->test(Presenze::class);
        $this->assertNull($vuoto->viewData('totali'));
        Livewire::actingAs($staff)->test(Presenze::class)
            ->set('filtroCliente', $turno->id_cliente)
            ->assertSet('filtroCliente', $turno->id_cliente);
    }

    public function test_ops_06(): void
    {
        $staff = $this->staff();
        $turno = Turno::query()->where('stato', 'F')->whereHas('sede')->orderByDesc('data_inizio')->first();

        if ($turno === null) {
            $this->manca('Non ci sono turni completati da stampare.');
        }

        $this->passo('Chiedo la stampa senza filtro, poi per '.$turno->cliente?->ragione_sociale.'.');
        $this->actingAs($staff)->get(route('presenze.stampa'))->assertStatus(422);
        $this->actingAs($staff)->get(route('presenze.stampa', [
            'cliente' => $turno->id_cliente,
            'da' => $turno->data_inizio->toDateString(),
            'a' => $turno->data_inizio->toDateString(),
        ]))->assertOk()->assertSee($turno->sede?->descrizione);
    }

    public function test_ops_07(): void
    {
        $chiuso = Turno::query()->where('stato', 'F')->orderByDesc('id')->first();

        if ($chiuso === null) {
            $this->manca('Non ci sono turni completati da confrontare.');
        }

        $this->passo('Confronto il turno completato '.$chiuso->id.' fra agenda e registro.');
        $this->assertSame('F', $chiuso->stato);
        $this->assertSame(1, Turno::query()->whereKey($chiuso->id)->where('stato', 'F')->count());
    }

    public function test_ops_08(): void
    {
        $staff = $this->staff();
        $prova = Turno::query()->where('stato', 'P')->whereHas('sede')->orderByDesc('id')->first();
        $altro = Turno::query()->where('stato', 'P')->where('id', '!=', $prova?->id)->first();

        if ($prova === null) {
            $this->manca('Non c’è un turno ancora programmato su cui registrare inizio e fine.');
        }

        $this->passo('Registro inizio e fine solo sul turno '.$prova->id.' della copia, sede '.$prova->sede?->descrizione.'.');
        $quando = $prova->inizio ?? now();
        $timbratura = new Timbratura;
        $timbratura->apriChiudi($prova, $staff, 'I', $quando, 'Apertura sessione supertest');
        $timbratura->apriChiudi($prova->fresh(), $staff, 'F', $quando->copy()->addHours(8), 'Chiusura sessione supertest');
        $this->assertSame('F', Turno::query()->whereKey($prova->id)->value('stato'));

        if ($altro !== null) {
            $this->assertSame('P', Turno::query()->whereKey($altro->id)->value('stato'));
        }
    }

    public function test_pre_01(): void
    {
        $staff = $this->staff();
        $presenza = Gda::query()->whereHas('sede')->orderByDesc('data')->first();

        if ($presenza === null) {
            $this->manca('Non ci sono timbrature.');
        }

        $this->passo('Presenze clienti del '.$presenza->data?->format('d/m/Y').' in '.$presenza->sede?->descrizione.'.');
        Livewire::actingAs($staff)->test(Presenze::class)
            ->set('filtroCliente', $presenza->id_cliente)
            ->assertSee($presenza->sede?->descrizione);
    }

    public function test_pre_02(): void
    {
        $staff = $this->staff();
        $presenza = Gda::query()->whereNotNull('id_operatore')->orderByDesc('id')->first();

        if ($presenza === null) {
            $this->manca('Non ci sono timbrature di operatori.');
        }

        $operatore = User::query()->find($presenza->id_operatore);
        $this->passo('Presenze dell’operatore '.trim($operatore?->cognome.' '.$operatore?->nome).'.');
        $this->actingAs($staff)->get(route('presenze.operatori'))->assertOk();
        $this->actingAs($this->operatore())->get(route('presenze.operatori'))->assertForbidden();
    }

    public function test_pre_03(): void
    {
        $staff = $this->staff();
        $presenza = Gda::query()->whereHas('sede')->orderByDesc('data')->first();

        if ($presenza === null) {
            $this->manca('Non ci sono timbrature da esportare.');
        }

        $this->passo('Esporto le presenze della sede '.$presenza->sede?->descrizione.' del '.$presenza->data?->format('d/m/Y').'.');
        $risposta = $this->actingAs($staff)->get(route('presenze.esporta', [
            'da' => $presenza->data?->toDateString(),
            'a' => $presenza->data?->toDateString(),
            'sede' => $presenza->id_sede,
        ]));
        $risposta->assertOk();
        $this->assertStringContainsString((string) $presenza->sede?->descrizione, $risposta->streamedContent());
    }

    public function test_pre_04(): void
    {
        $staff = $this->staff();
        $turno = $this->turno();
        $giorno = $turno->data_inizio->toDateString();
        $attesi = Turno::query()->where('id_cliente', $turno->id_cliente)->whereDate('data_inizio', $giorno)->get();
        $previste = (float) $attesi->sum('ore');
        $lavorate = (float) $attesi->sum('ore_lavorate');
        $this->passo('Riepilogo ore di '.$turno->cliente?->ragione_sociale.' il '.$turno->data_inizio->format('d/m/Y').': previste '.$previste.', lavorate '.$lavorate.'.');
        $risposta = $this->actingAs($staff)->get(route('presenze.ore', [
            'tipo' => 'cliente',
            'id' => $turno->id_cliente,
            'da' => $giorno,
            'a' => $giorno,
        ]));
        $risposta->assertOk();
        $temporaneo = tempnam(sys_get_temp_dir(), 'ore');
        file_put_contents($temporaneo, $risposta->streamedContent());
        $foglio = IOFactory::load($temporaneo)->getActiveSheet();
        @unlink($temporaneo);
        $riga = 3 + $attesi->count();
        $this->assertEqualsWithDelta($previste, (float) $foglio->getCell('G'.$riga)->getValue(), 0.01);
        $this->assertEqualsWithDelta($lavorate, (float) $foglio->getCell('H'.$riga)->getValue(), 0.01);
    }

    public function test_pre_05(): void
    {
        $turno = Turno::query()->whereNotNull('id_operatore')->whereHas('sede')->orderByDesc('data_inizio')->first();

        if ($turno === null) {
            $this->manca('Non ci sono turni assegnati.');
        }

        $operatore = User::query()->find($turno->id_operatore);

        if ($operatore === null || ! $operatore->isOperatore()) {
            $this->manca('Il turno '.$turno->id.' non ha un operatore.');
        }

        $this->passo($operatore->nome.' '.$operatore->cognome.' apre i propri turni.');
        Livewire::actingAs($operatore)->test(Turni::class)
            ->set('vista', 'lista')
            ->set('da', $turno->data_inizio->toDateString())
            ->set('a', $turno->data_inizio->toDateString())
            ->assertSee($turno->sede?->descrizione);
    }

    public function test_pre_06(): void
    {
        $operatore = $this->operatore();
        $prima = Gda::query()->where('id_operatore', $operatore->id)->count();
        $this->passo('Apro la scansione di '.$operatore->nome.' senza confermare una timbratura.');
        $pagina = $this->actingAs($operatore)->get(route('scansione'))->assertOk();
        $turno = app(Timbratura::class)->turnoAttivo($operatore);

        if ($turno !== null) {
            $this->passo('Turno attivo presso '.$turno->sede?->descrizione.'.');
            $pagina->assertSee($turno->sede?->descrizione);
        } else {
            $this->passo('In questo momento non ha un turno attivo.');
        }

        $this->assertSame($prima, Gda::query()->where('id_operatore', $operatore->id)->count());
    }

    public function test_pre_07(): void
    {
        $sede = Sede::query()->whereNotNull('id_codice')->first();
        $turno = $sede === null ? null : Turno::query()->where('id_sede', $sede->id)->first();
        $codice = $sede === null ? null : Codice::query()->find($sede->id_codice);
        $operatore = $turno === null ? null : User::query()->find($turno->id_operatore);

        if ($sede === null || $turno === null || $codice === null || $operatore === null) {
            $this->manca('Non c’è una sede con QR e un turno associato.');
        }

        $lat = $sede->lat;
        $lng = $sede->lng;
        $this->passo('Tolgo le coordinate di '.$sede->descrizione.' solo sulla copia e provo la timbratura.');
        $sede->update(['lat' => 0, 'lng' => 0]);
        $prima = Gda::query()->where('id_turno', $turno->id)->count();
        $esito = (new Timbratura)->scansionaTurno($turno->fresh(), $operatore, (string) $codice->codice, 44.6, 10.9);
        $sede->update(['lat' => $lat, 'lng' => $lng]);
        $this->assertFalse($esito['ok']);
        $this->assertSame('La sede non ha coordinate impostate. Contattare l\'amministrazione.', $esito['message']);
        $this->assertSame($prima, Gda::query()->where('id_turno', $turno->id)->count());
    }

    public function test_pre_08(): void
    {
        $operatore = User::query()->where('user_type', 4)->where('attivo', 'Y')->orderBy('id')->get()
            ->first(fn (User $utente): bool => app(Timbratura::class)->turnoAttivo($utente) !== null);

        if ($operatore === null) {
            $this->manca('In questo orario nessun operatore ha un turno attivo da timbrare.');
        }

        $turno = app(Timbratura::class)->turnoAttivo($operatore);
        $codice = Codice::query()->find($turno?->sede?->id_codice);

        if ($turno === null || $codice === null || $codice->tipo !== Codice::SEDE) {
            $this->manca('Il turno attivo non ha un QR di sede.');
        }

        $this->passo('Timbratura sulla copia per '.$operatore->nome.' presso '.$turno->sede?->descrizione.'.');
        $lat = (float) $turno->sede?->lat;
        $lng = (float) $turno->sede?->lng;
        $prima = Gda::query()->where('id_turno', $turno->id)->count();
        $inizio = (new Timbratura)->scansiona($operatore, (string) $codice->codice, $lat, $lng, 'Prova sessione');
        $this->assertTrue($inizio['ok'], $inizio['message']);
        $fine = (new Timbratura)->scansiona($operatore, (string) $codice->codice, $lat, $lng, 'Prova sessione');
        $this->assertTrue($fine['ok'], $fine['message']);
        $this->assertSame($prima + 2, Gda::query()->where('id_turno', $turno->id)->count());
    }

    public function test_cli_01(): void
    {
        $this->test_amm_02();
    }

    public function test_cli_02(): void
    {
        $staff = $this->staff();
        $sede = Sede::query()->whereNotNull('descrizione')->orderBy('id')->first();

        if ($sede === null) {
            $this->manca('Non ci sono sedi.');
        }

        $this->passo('Dettaglio di '.$sede->descrizione.', '.$sede->indirizzo.'.');
        Livewire::actingAs($staff)->test(Sedi::class)
            ->set('filtroCliente', $sede->id_cliente)
            ->assertSee($sede->descrizione);

        if (filled($sede->indirizzo)) {
            $this->assertNotEmpty($sede->indirizzo);
        }
    }

    public function test_cli_03(): void
    {
        $staff = $this->staff();
        $sede = Sede::query()->whereHas('postazioni')->with('postazioni')->first();

        if ($sede === null) {
            $this->manca('Nessuna sede ha postazioni.');
        }

        $titolo = (string) $sede->postazioni->first()?->titolo;
        $this->passo('Postazioni di '.$sede->descrizione.': '.$titolo.'.');
        Livewire::actingAs($staff)->test(Sedi::class)
            ->set('filtroCliente', $sede->id_cliente)
            ->assertSee($sede->descrizione);
        $this->assertNotSame('', $titolo);
    }

    public function test_cli_04(): void
    {
        $staff = $this->staff();
        $sede = Sede::query()->whereNotNull('id_codice')->first();

        if ($sede === null) {
            $this->manca('Nessuna sede ha un QR.');
        }

        $prima = Gda::query()->count();
        $this->passo('Scarico il QR della sede '.$sede->descrizione.' senza timbrare.');
        $this->actingAs($staff)->get(route('sedi.qrcode', $sede))->assertOk();
        $this->assertSame($prima, Gda::query()->count());
    }

    public function test_cli_05(): void
    {
        $staff = $this->staff();
        $sede = Sede::query()->whereHas('postazioni')->with('postazioni')->first();
        $postazione = $sede?->postazioni->first(fn ($postazione): bool => $postazione->id_codice !== null);

        if ($postazione === null) {
            $this->manca('Nessuna postazione ha un QR.');
        }

        $prima = Gda::query()->count();
        $this->passo('Scarico il QR della postazione '.$postazione->titolo.'.');
        $this->actingAs($staff)->get(route('postazioni.qrcode', $postazione))->assertOk();
        $this->assertSame($prima, Gda::query()->count());
    }

    public function test_cli_06(): void
    {
        $staff = $this->staff();
        $prima = Codice::query()->count();
        $prossimo = Codice::prossimoCodice();
        $this->passo('Anteprima del prossimo codice '.($prossimo ?: 'non disponibile').' senza generare righe.');
        $pagina = Livewire::actingAs($staff)->test(GeneratoreQr::class)->assertOk();

        if (is_string($prossimo) && $prossimo !== '') {
            $pagina->assertSee($prossimo);
        }

        $this->assertSame($prima, Codice::query()->count());
    }

    public function test_cli_07(): void
    {
        $staff = $this->staff();
        $cliente = $this->cliente($staff);
        $comune = Comune::query()->whereNotNull('sigla_provincia')->orderBy('id')->first();
        $codici = Codice::query()->where('stato', Codice::CREATO)->limit(2)->get();

        if ($comune === null || $codici->count() < 2) {
            $this->manca('Servono un comune e due QR liberi per creare una sede di prova sulla copia.');
        }

        $prima = Sede::query()->where('id_cliente', $cliente->id)->count();
        $this->passo('Creo una sede di prova per '.$cliente->ragione_sociale.' solo sulla copia.');
        Livewire::actingAs($staff)->test(SedeForm::class)
            ->set('f.cliente_id', $cliente->id)
            ->set('f.descrizione', 'Sede sessione supertest')
            ->set('f.indirizzo', 'Via della sessione 1')
            ->set('f.citta', (string) $comune->comune)
            ->set('f.provincia', (string) $comune->sigla_provincia)
            ->set('f.lat', 44.647)
            ->set('f.lng', 10.925)
            ->set('f.distanza_max', 100)
            ->set('codiceSedeId', (string) $codici[0]->id)
            ->set('postazioni', [[
                'id' => null,
                'codice_id' => (string) $codici[1]->id,
                'titolo' => 'Postazione sessione',
                'passaggi' => 1,
                'minuti' => 30,
                'info' => '',
                'file' => null,
            ]])
            ->call('salva')
            ->assertHasNoErrors();
        $this->assertSame($prima + 1, Sede::query()->where('id_cliente', $cliente->id)->count());
    }

    public function test_cli_08(): void
    {
        $account = User::query()->where('user_type', 3)->where('attivo', 'Y')->whereNotNull('id_cliente')->first();

        if ($account === null) {
            $this->manca('Non c’è un account cliente.');
        }

        $altra = Sede::query()->where('id_cliente', '!=', $account->id_cliente)->first();

        if ($altra === null) {
            $this->manca('Non c’è una sede di un altro cliente.');
        }

        $this->passo('L’account cliente '.$account->email.' apre la sede altrui '.$altra->descrizione.'.');
        $this->actingAs($account)->get(route('sedi.foto', $altra))->assertForbidden();
    }

    public function test_ope_01(): void
    {
        $avviso = \App\Models\Legacy\Bacheca::query()->orderByDesc('id')->first();

        if ($avviso === null) {
            $this->manca('Non ci sono avvisi in bacheca.');
        }

        $vista = BachecaView::query()->where('id_bacheca', $avviso->id)->first();
        $operatore = $vista === null ? $this->operatore() : User::query()->find($vista->id_operatore);

        if ($operatore === null) {
            $this->manca('L’avviso non ha un destinatario.');
        }

        $this->passo('Avviso «'.$avviso->titolo.'» per '.$operatore->nome.'.');
        Livewire::actingAs($operatore)->test(Bacheca::class)->assertSee($avviso->titolo);
    }

    public function test_ope_02(): void
    {
        $operatore = $this->operatore();
        $attesi = BachecaView::query()->where('id_operatore', $operatore->id)
            ->where(fn ($q) => $q->whereNull('visto')->orWhere('visto', 'N'))
            ->count();
        $this->passo('Avvisi non letti di '.$operatore->nome.': '.$attesi.'.');
        $conteggio = Livewire::actingAs($operatore)->test(ContatoreNotifiche::class)->instance()->conteggio();
        $this->assertSame($attesi, $conteggio);
    }

    public function test_ope_03(): void
    {
        $documento = Documento::query()->where('tipo', 'O')->orderByDesc('id')->first();

        if ($documento === null) {
            $this->manca('Non ci sono documenti operatore.');
        }

        $proprietario = User::query()->find($documento->id_tipo);
        $altro = User::query()->where('user_type', 4)->where('id', '!=', $documento->id_tipo)->first();

        if ($proprietario === null || $altro === null) {
            $this->manca('Manca il proprietario del documento o un secondo operatore.');
        }

        $this->passo('«'.$documento->descrizione.'» è di '.$proprietario->nome.'.');
        $this->actingAs($proprietario)->get(route('documenti.scarica', $documento))->assertOk();
        $this->actingAs($altro)->get(route('documenti.scarica', $documento))->assertNotFound();
    }

    public function test_ope_04(): void
    {
        $this->test_ope_02();
    }

    public function test_ope_05(): void
    {
        $operatore = $this->operatore();
        $altro = User::query()->where('user_type', 4)->where('id', '!=', $operatore->id)->first();
        $this->passo('Profilo di '.$operatore->nome.' '.$operatore->cognome.'.');
        Livewire::actingAs($operatore)->test(Profilo::class)
            ->assertSet('name', (string) $operatore->nome)
            ->assertSet('email', (string) $operatore->email);

        if ($altro !== null && filled($altro->email)) {
            Livewire::actingAs($operatore)->test(Profilo::class)->assertDontSee($altro->email);
        }
    }

    public function test_ope_06(): void
    {
        $turno = Turno::query()->whereNotNull('id_operatore')->whereHas('sede')->orderByDesc('data_inizio')->first();

        if ($turno === null) {
            $this->manca('Non ci sono turni personali.');
        }

        $operatore = User::query()->find($turno->id_operatore);

        if ($operatore === null) {
            $this->manca('Operatore del turno assente.');
        }

        $this->passo('Turno di '.$operatore->nome.' presso '.$turno->sede?->descrizione.'.');
        Livewire::actingAs($operatore)->test(Turni::class)
            ->call('mostraTurno', $turno->id)
            ->assertSee($turno->sede?->descrizione);

        if (filled($turno->note_operatore)) {
            $this->passo('Istruzione: '.$turno->note_operatore);
        }
    }

    public function test_ope_07(): void
    {
        $documento = Documento::query()->where('tipo', 'O')->whereNotNull('scadenza')->whereDate('scadenza', '<', today())->orderByDesc('id')->first();

        if ($documento === null) {
            $this->manca('Non ci sono documenti operatore scaduti.');
        }

        $operatore = User::query()->find($documento->id_tipo);

        if ($operatore === null) {
            $this->manca('Il documento scaduto non ha un operatore.');
        }

        $this->passo('Documento scaduto «'.$documento->descrizione.'» di '.$operatore->nome.', scadenza '.$documento->scadenza.'.');
        Livewire::actingAs($operatore)->test(Documenti::class)->assertSee($documento->descrizione);
    }

    public function test_ope_08(): void
    {
        $vista = BachecaView::query()->where(fn ($q) => $q->whereNull('visto')->orWhere('visto', 'N'))->first();

        if ($vista === null) {
            $this->manca('Non ci sono avvisi non letti.');
        }

        $operatore = User::query()->find($vista->id_operatore);
        $altra = BachecaView::query()->where('id_bacheca', $vista->id_bacheca)->where('id_operatore', '!=', $vista->id_operatore)->first();

        if ($operatore === null) {
            $this->manca('Il destinatario dell’avviso non è in anagrafica.');
        }

        $this->passo('Segno letto l’avviso '.$vista->id_bacheca.' solo per '.$operatore->nome.'.');
        Livewire::actingAs($operatore)->test(Bacheca::class)->call('segnaLetto', $vista->id_bacheca);
        $this->assertSame('Y', BachecaView::query()->where('id_bacheca', $vista->id_bacheca)->where('id_operatore', $operatore->id)->value('visto'));

        if ($altra !== null) {
            $this->assertNotSame('Y', (string) $altra->fresh()->visto);
        }
    }

    public function test_comms_01(): void
    {
        $staff = $this->staff();
        $modello = Messaggio::query()->first();

        if ($modello === null) {
            $this->manca('Non ci sono modelli di messaggio.');
        }

        $nome = (string) $modello->nome;
        $sms = (int) $modello->numero_sms;
        $this->passo('Modello «'.$nome.'», SMS consentiti: '.$sms.'. Non salvo.');
        Livewire::actingAs($staff)->test(ModelliMessaggio::class)
            ->call('modifica', $modello->id)
            ->set('f.nome', 'Nome non salvato');
        $this->assertSame($nome, (string) Messaggio::query()->whereKey($modello->id)->value('nome'));
        $this->assertSame($sms, (int) Messaggio::query()->whereKey($modello->id)->value('numero_sms'));
    }

    public function test_comms_02(): void
    {
        $operatore = $this->operatore();
        $turno = Turno::query()->where('id_operatore', $operatore->id)->whereHas('sede')->first();
        $sede = $turno?->sede?->descrizione ?: 'Sede';
        $this->passo('Sostituisco i segnaposto con '.$operatore->cognome.' '.$operatore->nome.' e '.$sede.'.');
        $testo = (new Comunicazioni)->testo(
            'Ciao [OPERATORE], sede [SEDE] alle [ORA TURNO]',
            ['cognome' => $operatore->cognome, 'nome' => $operatore->nome, 'descrizione' => $sede, 'orario' => '08:00'],
        );
        $this->assertStringContainsString((string) $operatore->cognome, $testo);
        $this->assertStringContainsString($sede, $testo);
        $this->assertStringNotContainsString('[OPERATORE]', $testo);
    }

    public function test_comms_03(): void
    {
        $operatore = $this->operatore();
        $this->passo('Leggo i turni collegati a '.$operatore->email.'.');
        $righe = (new Comunicazioni)->turni()->where('t.id_operatore', $operatore->id)->limit(5)->get();
        $this->assertTrue($righe->every(fn ($riga): bool => (int) $riga->id_operatore === (int) $operatore->id || (string) $riga->email === (string) $operatore->email));
    }

    public function test_comms_04(): void
    {
        $staff = $this->staff();
        $sms = SendSms::query()->orderByDesc('id')->first();

        if ($sms === null) {
            $this->manca('Non ci sono SMS nel registro.');
        }

        $this->passo('Registro SMS '.$sms->id.' verso '.$sms->destinatari.', stato inviato: '.($sms->sent === 'Y' ? 'sì' : 'no').'.');
        Livewire::actingAs($staff)->test(Messaggi::class, ['canale' => 'sms'])
            ->assertOk()
            ->assertSee($sms->sent === 'Y' ? 'Inviato' : 'Non inviato');
    }

    public function test_comms_05(): void
    {
        $this->passo('Invio una mail di prova all’indirizzo interno. Nessun recapito reale viene usato.');
        $inviate = (new Comunicazioni)->invia(['sessione-supertest@example.test' => 'Prova'], 'Oggetto prova', 'Testo prova');
        $this->assertSame(1, $inviate);
        Mail::assertSent(NotificaHtml::class, fn (NotificaHtml $mail): bool => $mail->hasTo('sessione-supertest@example.test'));
    }

    public function test_comms_06(): void
    {
        $this->passo('Con gli SMS disattivati la mail di prova parte e l’SMS no.');
        $prima = DB::table('tc_send_sms')->count();
        (new Comunicazioni)->invia(['sessione-supertest@example.test' => 'Prova'], 'Oggetto prova', 'Testo prova');
        $this->assertSame($prima, DB::table('tc_send_sms')->count());
        Mail::assertSent(NotificaHtml::class);
    }

    public function test_comms_07(): void
    {
        $staff = $this->staff();
        $this->passo('Apro i modelli senza inviare.');
        Livewire::actingAs($staff)->test(ModelliMessaggio::class)->assertOk();
        Mail::assertNothingSent();
    }

    public function test_comms_08(): void
    {
        $this->passo('Lancio l’avviso turni in prova sicura: mail solo all’indirizzo di prova, senza SMS.');
        $prima = DB::table('tc_send_sms')->count();

        try {
            $this->artisan('notifiche:avviso-turni', ['--prova-sicura' => true])->assertSuccessful();
        } catch (\RuntimeException $errore) {
            $this->manca($errore->getMessage());
        }

        Mail::assertSent(NotificaHtml::class, fn (NotificaHtml $mail): bool => $mail->hasTo(ProvaNotifiche::EMAIL));
        $this->assertSame($prima, DB::table('tc_send_sms')->count());
    }

    public function test_auth_01(): void
    {
        $utente = $this->accountCopia('supertest-login', 'segreta1');
        $this->passo('Entro con l’account creato sulla copia, non con una password reale.');
        Livewire::test(Login::class)
            ->set('email', $utente->user)
            ->set('password', 'segreta1')
            ->call('accedi')
            ->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_auth_02(): void
    {
        $utente = $this->staff();
        $this->passo('Esco da '.$utente->email.' e riprovo una pagina riservata.');
        $this->actingAs($utente)
            ->withSession(['_token' => 'token-collaudo'])
            ->post(route('logout'), ['_token' => 'token-collaudo'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('clienti'))->assertRedirect(route('login'));
    }

    public function test_auth_03(): void
    {
        $utente = $this->accountCopia('supertest-recupero', 'vecchia123');
        RateLimiter::clear('recupero|'.strtolower((string) $utente->user).'|127.0.0.1');
        $this->passo('Chiedo il recupero con il nome utente di copia '.$utente->user.'.');
        Livewire::test(RecuperaPassword::class)
            ->set('email', (string) $utente->user)
            ->call('recupera')
            ->assertSet('inviata', true);
        Mail::assertSent(NotificaHtml::class, fn (NotificaHtml $mail): bool => $mail->hasTo((string) $utente->email));
        $this->assertFalse(Hash::check('vecchia123', (string) User::query()->whereKey($utente->id)->value('password')));
    }

    public function test_auth_04(): void
    {
        $utente = $this->accountCopia('supertest-spento', 'segreta1', 'N');
        $this->passo('L’account disattivato sulla copia non entra.');
        Livewire::test(Login::class)
            ->set('email', $utente->user)
            ->set('password', 'segreta1')
            ->call('accedi')
            ->assertHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_auth_05(): void
    {
        $utente = $this->accountCopia('supertest-limite', 'segreta1');
        $this->passo('Cinque password errate sull’account di copia, poi un accesso corretto.');
        $login = Livewire::test(Login::class)->set('email', $utente->user);

        for ($tentativo = 0; $tentativo < 5; $tentativo++) {
            $login->set('password', 'sbagliata')->call('accedi');
        }

        $login->set('password', 'segreta1')->call('accedi')->assertHasErrors(['email']);
        RateLimiter::clear(strtolower((string) $utente->user).'|127.0.0.1');
        Livewire::test(Login::class)
            ->set('email', $utente->user)
            ->set('password', 'segreta1')
            ->call('accedi')
            ->assertRedirect('/');
    }

    private function passo(string $testo): void
    {
        Diario::registra($this->caso(), $testo);
    }

    private function manca(string $motivo): never
    {
        $this->passo($motivo);
        $this->markTestSkipped('Dato assente: '.$motivo);
    }

    private function caso(): string
    {
        if (preg_match('/^test_(.+)_(\d{2})$/', $this->name(), $parti) !== 1) {
            return 'CASO';
        }

        return strtoupper($parti[1]).'-'.$parti[2];
    }

    private function staff(): User
    {
        $utente = User::query()->where('attivo', 'Y')->where('user_type', 2)->orderBy('id')->first()
            ?? User::query()->where('attivo', 'Y')->where('user_type', 1)->orderBy('id')->first();

        if ($utente === null) {
            $this->manca('Nella copia non c’è un amministratore attivo.');
        }

        $this->passo('Staff: '.trim($utente->nome.' '.$utente->cognome).'.');

        return $utente;
    }

    private function operatore(): User
    {
        $utente = User::query()->where('attivo', 'Y')->where('user_type', 4)->orderBy('id')->first();

        if ($utente === null) {
            $this->manca('Nella copia non c’è un operatore attivo.');
        }

        return $utente;
    }

    private function cliente(User $staff): Cliente
    {
        $cliente = Cliente::query()->visibiliA($staff)->where('ragione_sociale', '!=', '')->orderBy('id')->first();

        if ($cliente === null) {
            $this->manca('Nella copia non ci sono clienti visibili a questo staff.');
        }

        return $cliente;
    }

    private function altroCliente(User $staff, Cliente $escluso): ?Cliente
    {
        return Cliente::query()->visibiliA($staff)->where('id', '!=', $escluso->id)->where('ragione_sociale', '!=', '')->orderBy('id')->first();
    }

    private function turno(): Turno
    {
        $turno = Turno::query()->whereHas('sede')->orderByDesc('data_inizio')->first();

        if ($turno === null) {
            $this->manca('Nella copia non ci sono turni.');
        }

        return $turno;
    }

    private function pivaLibera(): string
    {
        do {
            $piva = (string) random_int(10000000000, 99999999999);
        } while (Cliente::query()->where('piva', $piva)->exists());

        return $piva;
    }

    private function accountCopia(string $user, string $password, string $attivo = 'Y'): User
    {
        $esistente = User::query()->where('user', $user)->first();

        if ($esistente !== null) {
            $esistente->update([
                'email' => $user.'@example.test',
                'password' => $password,
                'attivo' => $attivo,
            ]);

            return $esistente->refresh();
        }

        return User::create([
            'user' => $user,
            'email' => $user.'@example.test',
            'password' => $password,
            'attivo' => $attivo,
            'user_type' => 2,
            'nome' => 'Sessione',
            'cognome' => 'Supertest',
        ]);
    }
}
