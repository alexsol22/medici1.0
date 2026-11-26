<?php

namespace Tests\Feature;

use App\Livewire\Turni;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class TurniGestioneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => config('database.connections.sqlite'),
        ]);
        DB::purge('mysql');
        DB::connection('mysql')->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($data) => date('d-m-Y', strtotime($data)));

        Carbon::setTestNow('2026-11-06 12:00:00');

        Schema::create('tc_admin', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_type')->nullable();
            $table->string('attivo')->nullable();
            $table->string('nome')->nullable();
            $table->string('cognome')->nullable();
            $table->string('email')->nullable();
            $table->string('cellulare')->nullable();
            $table->string('codice')->nullable();
            $table->string('user')->nullable();
            $table->string('id_cliente')->nullable();
            $table->float('costo_orario')->nullable();
        });
        Schema::create('tc_clienti', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('ragione_sociale')->nullable();
            $table->integer('isTest')->nullable();
        });
        Schema::create('tc_sedi', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->unsignedInteger('citta_id')->nullable();
            $table->unsignedInteger('provincia_id')->nullable();
            $table->unsignedInteger('id_codice')->nullable();
            $table->string('descrizione')->nullable();
            $table->string('indirizzo')->nullable();
            $table->string('cap')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
        });
        Schema::create('_comuni', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('comune')->nullable();
        });
        Schema::create('_provincia', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('sigla')->nullable();
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
        foreach (['tc_operatori_preferiti', 'tc_operatori_escludi'] as $tabella) {
            Schema::create($tabella, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('id_cliente');
                $table->unsignedInteger('id_operatore');
            });
        }
        Schema::create('tc_ritardi', function (Blueprint $table): void {
            $table->integer('id');
            $table->unsignedInteger('id_turno')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->integer('inizio')->default(0);
            $table->integer('fine')->default(0);
        });
        Schema::create('tc_send_email', function (Blueprint $table): void {
            $table->integer('id')->nullable();
            $table->integer('code')->nullable();
            $table->string('email')->nullable();
            $table->string('nome')->nullable();
            $table->text('testo')->nullable();
            $table->unsignedInteger('id_operatore')->nullable();
            $table->string('sent')->default('N');
            $table->dateTime('data_sent')->nullable();
        });

        DB::table('tc_admin')->insert([
            ['id' => 1, 'user_type' => 2, 'attivo' => 'Y', 'nome' => 'Admin', 'cognome' => 'Test', 'email' => null, 'cellulare' => null, 'codice' => null],
            ['id' => 2, 'user_type' => 4, 'attivo' => 'Y', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@example.com', 'cellulare' => null, 'codice' => 'OP1'],
            ['id' => 3, 'user_type' => 4, 'attivo' => 'Y', 'nome' => 'Luca', 'cognome' => 'Bianchi', 'email' => 'luca@example.com', 'cellulare' => null, 'codice' => 'OP2'],
        ]);
        DB::table('tc_clienti')->insert([['id' => 1, 'ragione_sociale' => 'Cliente Uno'], ['id' => 2, 'ragione_sociale' => 'Cliente Due']]);
        DB::table('_comuni')->insert(['id' => 1, 'comune' => 'Torino']);
        DB::table('_provincia')->insert(['id' => 1, 'sigla' => 'TO']);
        DB::table('tc_sedi')->insert([
            ['id' => 1, 'id_cliente' => 1, 'citta_id' => 1, 'provincia_id' => 1, 'id_codice' => null, 'descrizione' => 'Sede Uno', 'indirizzo' => 'Via Roma 1', 'cap' => '10100', 'lat' => '45.0', 'lng' => '7.6'],
            ['id' => 2, 'id_cliente' => 2, 'citta_id' => 1, 'provincia_id' => 1, 'id_codice' => null, 'descrizione' => 'Sede Due', 'indirizzo' => 'Via Po 2', 'cap' => '10100', 'lat' => '45.0', 'lng' => '7.6'],
        ]);

        $turno = fn (int $id, int $cliente, int $sede, int $operatore, string $inizio, string $fine, string $stato, float $ore) => [
            'id' => $id, 'id_cliente' => $cliente, 'id_sede' => $sede, 'id_operatore' => $operatore,
            'data_inizio' => substr($inizio, 0, 10), 'ora_inizio' => substr($inizio, 11),
            'data_fine' => substr($fine, 0, 10), 'ora_fine' => substr($fine, 11),
            'ore' => $ore, 'ore_lavorate' => null, 'stato' => $stato, 'note_operatore' => null,
        ];
        DB::table('tc_turni')->insert([
            $turno(1, 1, 1, 2, '2026-11-06 08:00', '2026-11-06 10:00', 'P', 2),   // non timbrato? fine passata -> non eseguito
            $turno(2, 1, 1, 2, '2026-11-06 11:00', '2026-11-06 13:00', 'P', 2),   // iniziato da un'ora, non timbrato
            $turno(3, 1, 1, 3, '2026-11-06 14:00', '2026-11-06 18:00', 'P', 4),   // programmato
            $turno(4, 2, 2, 3, '2026-11-06 06:00', '2026-11-06 09:00', 'I', 3),   // iniziato non completato
            $turno(5, 2, 2, 3, '2026-11-06 11:00', '2026-11-06 15:00', 'I', 4),   // in corso
            $turno(6, 2, 2, 3, '2026-11-05 08:00', '2026-11-05 16:00', 'F', 8),   // completato
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function lista()
    {
        return Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->set('vista', 'lista')->set('da', '2026-11-01')->set('a', '2026-11-30');
    }

    private function contenutoPdf(string $pdf): string
    {
        $this->assertStringStartsWith('%PDF', $pdf);

        $parti = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $corrispondenze)) {
            foreach ($corrispondenze[1] as $flusso) {
                $testo = @gzuncompress($flusso);
                if (is_string($testo)) {
                    $parti[] = str_replace(['\\(', '\\)', "\0"], ['(', ')', ''], $testo);
                }
            }
        }

        return implode("\n", $parti);
    }

    private function idsLista($componente): array
    {
        return $componente->viewData('turni')->pluck('id')->sort()->values()->all();
    }

    public function test_il_calendario_elimina_un_turno_con_la_stessa_conferma_della_lista(): void
    {
        DB::table('tc_gda')->insert([
            ['id_turno' => 1, 'tipo' => 'I'],
        ]);

        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->set('vista', 'calendario')
            ->set('periodoCalendario', 'giorno')
            ->assertSee('Elimina il turno delle 08:00 presso Sede Uno di Mario Rossi')
            ->call('richiediEliminazione', 1)
            ->assertSee('Eliminare il turno del 06/11/2026 08:00?')
            ->assertSee('Verranno eliminati')
            ->assertSee('definitivamente')
            ->assertSee('Per confermare scrivi')
            ->set('conferma', 'no')
            ->call('elimina')
            ->assertHasErrors(['conferma'])
            ->assertSee('Il testo non coincide: eliminazione non eseguita.')
            ->set('conferma', ' CONFERMA ')
            ->call('elimina')
            ->assertHasNoErrors()
            ->assertSee('Eliminazione completata')
            ->assertSee('Turno eliminato insieme ai GDA collegati.')
            ->assertDontSee('Elimina il turno delle 08:00 presso Sede Uno di Mario Rossi');

        $this->assertNull(DB::table('tc_turni')->find(1));
        $this->assertSame(0, DB::table('tc_gda')->where('id_turno', 1)->count());
        $this->assertNotNull(DB::table('tc_turni')->find(2));
    }

    public function test_modificare_un_turno_aggiorna_la_stessa_riga_e_non_ne_crea_un_altra(): void
    {
        DB::table('tc_gda')->insert([
            ['id_turno' => 1, 'tipo' => 'I'],
        ]);

        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('modifica', 1)
            ->set('m.data_inizio', '2026-11-07')
            ->set('m.ora_inizio', '09:00')
            ->set('m.data_fine', '2026-11-07')
            ->set('m.ora_fine', '12:00')
            ->call('aggiorna')
            ->assertHasNoErrors();

        $turno = DB::table('tc_turni')->where('id', 1)->first();

        $this->assertSame(6, DB::table('tc_turni')->count());
        $this->assertSame('2026-11-07', Carbon::parse($turno->data_inizio)->toDateString());
        $this->assertSame('09:00', Carbon::parse($turno->ora_inizio)->format('H:i'));
        $this->assertSame('12:00', Carbon::parse($turno->ora_fine)->format('H:i'));
        $this->assertSame(1, DB::table('tc_gda')->where('id_turno', 1)->count());
        $this->assertSame('2026-11-06', Carbon::parse(DB::table('tc_turni')->where('id', 2)->value('data_inizio'))->toDateString());
    }

    public function test_operatore_non_vede_eliminazione_nel_calendario(): void
    {
        Livewire::actingAs(User::findOrFail(2))->test(Turni::class)
            ->set('vista', 'calendario')
            ->set('periodoCalendario', 'giorno')
            ->assertSee('Visualizza timbrature turno delle 08:00 presso Sede Uno per Mario Rossi')
            ->assertDontSee('Elimina il turno delle');
    }

    public function test_eliminare_un_turno_cancella_gda_e_ritardi_collegati_e_lascia_gli_altri(): void
    {
        DB::table('tc_gda')->insert([
            ['id_turno' => 2, 'id_operatore' => 2, 'id_cliente' => 1, 'id_sede' => 1, 'tipo' => 'I'],
            ['id_turno' => 2, 'id_operatore' => 2, 'id_cliente' => 1, 'id_sede' => 1, 'tipo' => 'P'],
            ['id_turno' => 2, 'id_operatore' => 2, 'id_cliente' => 1, 'id_sede' => 1, 'tipo' => 'F'],
            ['id_turno' => 3, 'id_operatore' => 3, 'id_cliente' => 1, 'id_sede' => 1, 'tipo' => 'I'],
        ]);
        DB::table('tc_ritardi')->insert([
            ['id' => 1, 'id_turno' => 2, 'id_operatore' => 2, 'inizio' => 1, 'fine' => 0],
            ['id' => 2, 'id_turno' => 3, 'id_operatore' => 3, 'inizio' => 1, 'fine' => 0],
        ]);

        $componente = Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('richiediEliminazione', 2)
            ->set('conferma', 'no')
            ->call('elimina')
            ->assertHasErrors(['conferma'])
            ->assertSee('Il testo non coincide: eliminazione non eseguita.');

        $this->assertNotNull(DB::table('tc_turni')->find(2));
        $this->assertSame(1, DB::table('tc_ritardi')->where('id_turno', 2)->count());

        $componente
            ->set('conferma', ' CONFERMA ')
            ->call('elimina')
            ->assertHasNoErrors()
            ->assertSet('esitoEliminazione.ok', true)
            ->assertSee('Eliminazione completata')
            ->assertSee('Turno eliminato insieme ai GDA collegati.');

        $this->assertNull(DB::table('tc_turni')->find(2));
        $this->assertSame(0, DB::table('tc_gda')->where('id_turno', 2)->count());
        $this->assertSame(0, DB::table('tc_ritardi')->where('id_turno', 2)->count());
        $this->assertNotNull(DB::table('tc_turni')->find(3));
        $this->assertSame('I', DB::table('tc_gda')->where('id_turno', 3)->value('tipo'));
        $this->assertSame(1, DB::table('tc_ritardi')->where('id_turno', 3)->count());
    }

    public function test_eliminazione_multipla_cancella_i_turni_selezionati_e_i_gda_collegati(): void
    {
        DB::table('tc_clienti')->insert(['id' => 9, 'ragione_sociale' => 'Cliente Test', 'isTest' => 1]);
        DB::table('tc_sedi')->insert([
            'id' => 9, 'id_cliente' => 9, 'citta_id' => 1, 'provincia_id' => 1, 'id_codice' => null,
            'descrizione' => 'Sede Test', 'indirizzo' => 'Via Test 9', 'cap' => '10100', 'lat' => '45.0', 'lng' => '7.6',
        ]);
        DB::table('tc_turni')->insert([
            'id' => 9, 'id_cliente' => 9, 'id_sede' => 9, 'id_operatore' => 2,
            'data_inizio' => '2026-11-06', 'ora_inizio' => '08:00', 'data_fine' => '2026-11-06', 'ora_fine' => '10:00',
            'ore' => 2, 'ore_lavorate' => null, 'stato' => 'P', 'note_operatore' => null,
        ]);
        DB::table('tc_gda')->insert([
            ['id_turno' => 1, 'tipo' => 'I'],
            ['id_turno' => 2, 'tipo' => 'F'],
            ['id_turno' => 3, 'tipo' => 'P'],
            ['id_turno' => 9, 'tipo' => 'I'],
        ]);

        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->set('vista', 'lista')->set('da', '2026-11-01')->set('a', '2026-11-30')
            ->assertSee('Elimina selezionati')
            ->set('selezionati', [1, '2', 9, 999])
            ->call('richiediEliminazioneSelezionati')
            ->assertSee('Per confermare scrivi')
            ->set('conferma', 'CONFERMA')
            ->call('eliminaSelezionati')
            ->assertSet('selezionati', [])
            ->assertSet('esitoEliminazione.ok', true)
            ->assertSee('2 turni eliminati insieme ai GDA collegati.');

        $this->assertNull(DB::table('tc_turni')->find(1));
        $this->assertNull(DB::table('tc_turni')->find(2));
        $this->assertSame(0, DB::table('tc_gda')->whereIn('id_turno', [1, 2])->count());
        $this->assertNotNull(DB::table('tc_turni')->find(3));
        $this->assertSame('P', DB::table('tc_gda')->where('id_turno', 3)->value('tipo'));
        $this->assertNotNull(DB::table('tc_turni')->find(9));
        $this->assertSame('I', DB::table('tc_gda')->where('id_turno', 9)->value('tipo'));
    }

    public function test_selezione_pagina_ignora_turni_non_visibili_e_si_annulla(): void
    {
        DB::table('tc_clienti')->insert(['id' => 9, 'ragione_sociale' => 'Cliente Test', 'isTest' => 1]);
        DB::table('tc_turni')->insert([
            'id' => 9, 'id_cliente' => 9, 'id_sede' => 1, 'id_operatore' => 2,
            'data_inizio' => '2026-11-06', 'ora_inizio' => '08:00', 'data_fine' => '2026-11-06', 'ora_fine' => '10:00',
            'ore' => 2, 'stato' => 'P',
        ]);

        $componente = Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('alternaSelezionePagina', [1, 2, 9]);

        $selezionati = $componente->get('selezionati');
        sort($selezionati);
        $this->assertSame(['1', '2'], $selezionati);

        $componente->call('alternaSelezionePagina', [1, 2, 9])->assertSet('selezionati', []);
    }

    public function test_senza_turni_selezionati_l_eliminazione_multipla_fallisce(): void
    {
        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('richiediEliminazioneSelezionati')
            ->assertSet('esitoEliminazione.ok', false)
            ->assertSee('Eliminazione non riuscita')
            ->assertSee('Nessun turno selezionato.');

        $this->assertSame(6, DB::table('tc_turni')->count());
    }

    public function test_lista_offre_la_stampa_pdf_dei_turni_selezionati(): void
    {
        $this->lista()
            ->assertSee('Scarica PDF')
            ->assertDontSee(route('turni.stampa'));

        $this->lista()->set('selezionati', ['1', 2])
            ->assertSee(route('turni.stampa', ['ids' => [1, 2]]));
    }

    public function test_stampa_pdf_include_solo_i_turni_selezionati_visibili(): void
    {
        Schema::create('tc_config', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('app')->nullable();
        });
        DB::table('tc_config')->insert(['id' => 1, 'app' => 'Omnia Control']);
        DB::table('tc_clienti')->insert(['id' => 9, 'ragione_sociale' => 'Cliente Test', 'isTest' => 1]);
        DB::table('tc_sedi')->insert([
            'id' => 9, 'id_cliente' => 9, 'citta_id' => 1, 'provincia_id' => 1, 'id_codice' => null,
            'descrizione' => 'Sede Nascosta', 'indirizzo' => 'Via Test 9', 'cap' => '10100', 'lat' => '45.0', 'lng' => '7.6',
        ]);
        DB::table('tc_sedi')->where('id', 1)->update(['descrizione' => 'Sede <script>alert(1)</script>']);
        DB::table('tc_turni')->insert([
            'id' => 9, 'id_cliente' => 9, 'id_sede' => 9, 'id_operatore' => 2,
            'data_inizio' => '2026-11-06', 'ora_inizio' => '08:00', 'data_fine' => '2026-11-06', 'ora_fine' => '10:00',
            'ore' => 2, 'ore_lavorate' => null, 'stato' => 'P', 'note_operatore' => null,
        ]);

        $risposta = $this->actingAs(User::findOrFail(1))
            ->get(route('turni.stampa', ['ids' => [1, 9, 'abc']]));

        $risposta->assertOk()
            ->assertDownload('turni-20261106.pdf');

        $pdf = $risposta->streamedContent();
        $testo = $this->contenutoPdf($pdf);
        $this->assertStringContainsString('Turni selezionati (1)', $testo);
        $this->assertStringContainsString('Cliente Uno', $testo);
        $this->assertStringContainsString('Sede <script>alert(1)</script>', $testo);
        $this->assertStringContainsString('Mario Rossi', $testo);
        $this->assertStringContainsString('06/11/2026 08:00', $testo);
        $this->assertStringContainsString('Non eseguito', $testo);
        $this->assertStringContainsString('/Width 192', $pdf);
        $this->assertStringContainsString('/Height 192', $pdf);
        $this->assertStringNotContainsString('logo-security-scanner', $pdf);
        $this->assertStringNotContainsString('Cliente Test', $testo);
        $this->assertStringNotContainsString('Sede Nascosta', $testo);

        $this->actingAs(User::findOrFail(1))
            ->get(route('turni.stampa', ['ids' => [9]]))
            ->assertNotFound();
    }

    public function test_stampa_pdf_richiede_una_selezione_e_un_ruolo_autorizzato(): void
    {
        $this->get(route('turni.stampa', ['ids' => [1]]))->assertRedirect(route('login'));

        $this->actingAs(User::findOrFail(1))->get(route('turni.stampa'))->assertStatus(422);

        DB::table('tc_admin')->insert([
            'id' => 4, 'user_type' => 3, 'attivo' => 'Y', 'nome' => 'Cliente', 'cognome' => 'Uno', 'id_cliente' => 1,
        ]);

        $this->actingAs(User::findOrFail(4))->get(route('turni.stampa', ['ids' => [1]]))->assertForbidden();
        $this->actingAs(User::findOrFail(2))->get(route('turni.stampa', ['ids' => [1]]))->assertForbidden();
    }

    public function test_operatore_non_puo_eliminare_i_turni_selezionati(): void
    {
        Livewire::actingAs(User::findOrFail(2))->test(Turni::class)
            ->set('selezionati', [1])
            ->call('richiediEliminazioneSelezionati')
            ->assertForbidden();

        $this->assertNotNull(DB::table('tc_turni')->find(1));
    }

    public function test_filtro_stato_replica_la_legenda_di_yii(): void
    {
        $atteso = [
            'programmato' => [3], 'non_timbrato' => [2], 'non_eseguito' => [1],
            'in_corso' => [5], 'non_completato' => [4], 'completato' => [6],
        ];

        foreach ($atteso as $stato => $ids) {
            $this->assertSame($ids, $this->idsLista($this->lista()->set('filtroStato', $stato)), "stato {$stato}");
        }

        $this->assertSame([1, 2, 3, 4, 5, 6], $this->idsLista($this->lista()));
    }

    public function test_filtro_cliente_e_totali_con_filtri_combinati(): void
    {
        $this->assertSame(23.0, $this->lista()->viewData('totaleOreAgenda'));

        $this->assertSame([4, 5, 6], $this->idsLista($this->lista()->set('filtroCliente', 2)));
        $this->assertSame(15.0, $this->lista()->set('filtroCliente', 2)->viewData('totaleOreAgenda'));
        $this->assertSame(0.0, $this->lista()->set('filtroCliente', 2)->set('filtroOperatore', 2)->viewData('totaleOreAgenda'));
        $this->assertSame(4.0, $this->lista()->set('filtroOperatore', 2)->viewData('totaleOreAgenda'));
    }

    public function test_intervallo_date_filtra_calendario_lista_e_totale(): void
    {
        $componente = Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->assertSet('da', '')
            ->assertSet('a', '')
            ->assertSee('Stato turno')
            ->assertSee('filtro-dal')
            ->assertSee('filtro-al')
            ->assertSee('Tutti i clienti')
            ->set('da', '2026-11-06')
            ->set('a', '2026-11-06');

        $this->assertSame([1, 2, 3, 4, 5], $componente->viewData('calendario')['turni']->flatten()->pluck('id')->sort()->values()->all());
        $this->assertSame(15.0, $componente->viewData('totaleOreAgenda'));

        $componente->set('vista', 'lista');
        $this->assertSame([1, 2, 3, 4, 5], $this->idsLista($componente));
        $this->assertSame(15.0, $componente->viewData('totaleOreAgenda'));

        $componente->set('da', '')->set('a', '');
        $this->assertSame([1, 2, 3, 4, 5, 6], $this->idsLista($componente));
        $this->assertNull($componente->viewData('totaleOreAgenda'));
    }

    public function test_apri_e_chiudi_richiedono_nota_data_e_ora_e_registrano_la_timbratura_manuale(): void
    {
        $componente = Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('apriChiudi', 2)
            ->assertSet('ac.stato', 'I')
            ->assertSet('ac.data', '2026-11-06')
            ->assertSet('ac.ora', '11:00')
            ->set('ac.note', '')
            ->call('confermaApriChiudi')
            ->assertHasErrors(['ac.note' => 'required']);

        $componente->set('ac.note', 'Iniziato dal responsabile')->set('ac.ora', '11:10')->call('confermaApriChiudi')->assertHasNoErrors();

        $turno = DB::table('tc_turni')->find(2);
        $this->assertSame('I', $turno->stato);
        $this->assertSame('Iniziato dal responsabile', $turno->note_operatore);
        $gda = DB::table('tc_gda')->where('id_turno', 2)->first();
        $this->assertSame('I', $gda->tipo);
        $this->assertSame('11:10', $gda->orario);
        $this->assertSame(10, (int) $gda->ritardo);
        $this->assertSame(1, (int) $gda->id_operatore);

        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('apriChiudi', 2)
            ->assertSet('ac.stato', 'F')
            ->assertSet('ac.ora', '13:00')
            ->set('ac.note', 'Chiuso')->call('confermaApriChiudi');

        $this->assertSame('F', DB::table('tc_turni')->where('id', 2)->value('stato'));
        $this->assertSame(1.75, (float) DB::table('tc_turni')->where('id', 2)->value('ore_lavorate'));
    }

    public function test_invia_turni_accoda_una_email_per_operatore_con_i_suoi_turni(): void
    {
        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->set('da', '2026-11-06')->set('a', '2026-11-06')
            ->call('nuovoInvio')
            ->set('invio.cliente', 1)
            ->call('inviaTurni')
            ->assertSet('esitoInvio', 'Email turno inviate correttamente. Totale operatori coinvolti 2');

        $coda = DB::table('tc_send_email')->orderBy('id_operatore')->get();
        $this->assertSame(['mario@example.com', 'luca@example.com'], $coda->pluck('email')->all());
        $this->assertSame(['N', 'N'], $coda->pluck('sent')->all());
        $this->assertStringContainsString('Ciao Rossi Mario', $coda[0]->testo);
        $this->assertStringContainsString('06-11-2026 Alle 08:00 presso la sede Sede Uno al seguente indirizzo Via Roma 1 Torino (TO)', $coda[0]->testo);
        $this->assertStringContainsString('Alle 11:00', $coda[0]->testo);
        $this->assertStringNotContainsString('Alle 14:00', $coda[0]->testo);
        $this->assertStringContainsString('Alle 14:00', $coda[1]->testo);
        $this->assertSame($coda[0]->code, $coda[1]->code);
    }

    public function test_invia_turni_senza_turni_corrispondenti_non_accoda_nulla(): void
    {
        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('nuovoInvio')
            ->set('invio.inizio', '2027-01-01')->set('invio.fine', '2027-01-31')
            ->call('inviaTurni')
            ->assertSet('esitoInvio', 'Non sono stati trovati turni a cui inviare comunicazioni per sede, operatore, data indicate.');

        $this->assertSame(0, DB::table('tc_send_email')->count());
    }

    public function test_nuovo_turno_riprende_cliente_e_sede_gia_selezionati(): void
    {
        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->set('filtroSede', 1)
            ->call('nuovo')
            ->assertSet('modal', true)
            ->assertSet('f.cliente_id', 1)
            ->assertSet('f.sede_id', 1);
    }

    public function test_nuovo_turno_con_cliente_che_ha_una_sola_sede_la_compila(): void
    {
        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->set('filtroCliente', 2)
            ->call('nuovo')
            ->assertSet('f.cliente_id', 2)
            ->assertSet('f.sede_id', 2);
    }

    public function test_nuovo_turno_con_piu_sedi_lascia_la_sede_da_scegliere(): void
    {
        DB::table('tc_sedi')->insert([
            'id' => 3, 'id_cliente' => 1, 'citta_id' => 1, 'provincia_id' => 1, 'id_codice' => null,
            'descrizione' => 'Sede Tre', 'indirizzo' => 'Via Tre 3', 'cap' => '10100', 'lat' => '45.0', 'lng' => '7.6',
        ]);

        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->set('filtroCliente', 1)
            ->call('nuovo')
            ->assertSet('f.cliente_id', 1)
            ->assertSet('f.sede_id', '');
    }

    public function test_fine_antecedente_all_inizio_mostra_il_messaggio_e_non_crea_il_turno(): void
    {
        $this->app->setLocale('en');
        $prima = DB::table('tc_turni')->count();

        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('nuovo')
            ->set('f.cliente_id', 1)
            ->set('f.sede_id', 1)
            ->set('f.data_inizio', '2026-11-10')
            ->set('f.ora_inizio', '16:00')
            ->set('f.data_fine', '2026-11-09')
            ->set('f.ora_fine', '08:00')
            ->assertSee('La data e l\'ora di fine devono essere successive a quelle di inizio.', false)
            ->assertSee('Il turno non viene salvato finché l\'orario non è valido.', false)
            ->assertSee('Mario Rossi')
            ->assertSee('Luca Bianchi')
            ->call('salva')
            ->assertHasErrors(['f.data_fine' => 'La data e l\'ora di fine devono essere successive a quelle di inizio.'])
            ->assertSet('modal', true)
            ->assertDontSee('The f.operatore', false);

        $this->assertSame($prima, DB::table('tc_turni')->count());
    }

    public function test_turno_senza_operatore_mostra_il_messaggio_in_italiano(): void
    {
        $this->app->setLocale('en');

        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('nuovo')
            ->set('f.cliente_id', 1)
            ->set('f.sede_id', 1)
            ->set('f.data_inizio', '2026-11-20')
            ->set('f.ora_inizio', '08:00')
            ->set('f.data_fine', '2026-11-20')
            ->set('f.ora_fine', '16:00')
            ->call('salva')
            ->assertHasErrors(['f.operatore_id' => 'Seleziona un operatore.'])
            ->assertSee('Seleziona un operatore.')
            ->assertDontSee('field is required', false);

        $this->assertSame(6, DB::table('tc_turni')->count());
    }

    public function test_nuovo_turno_mostra_le_ore_totali_mentre_si_scelgono_data_e_orario(): void
    {
        Livewire::actingAs(User::findOrFail(1))->test(Turni::class)
            ->call('nuovo')
            ->set('f.data_inizio', '2026-11-20')
            ->set('f.ora_inizio', '08:00')
            ->set('f.data_fine', '2026-11-20')
            ->set('f.ora_fine', '16:00')
            ->assertSeeInOrder(['Ore totali:', '8'])
            ->set('f.ora_fine', '12:30')
            ->assertSeeInOrder(['Ore totali:', '4.5'])
            ->set('f.ora_fine', '12:20')
            ->assertSeeInOrder(['Ore totali:', '4.25'])
            ->set('f.data_fine', '2026-11-21')
            ->set('f.ora_fine', '06:00')
            ->set('f.ora_inizio', '22:00')
            ->assertSeeInOrder(['Ore totali:', '8'])
            ->set('f.data_fine', '2026-11-20')
            ->assertDontSee('Ore totali:');
    }

    public function test_ricerca_e_elenco_mostrano_la_stellina_sugli_operatori_preferiti_del_cliente(): void
    {
        DB::table('tc_operatori_preferiti')->insert(['id_cliente' => 1, 'id_operatore' => 2]);

        $html = $this->lista()->html();

        $this->assertMatchesRegularExpression('/preferiti(?:\\\\u0022)?:\[1\]/', $html);
        $this->assertSame(2, substr_count($html, 'data-operatore-preferito'));
        $this->assertStringContainsString('OP1 (Rossi Mario)', $html);

        $modale = $this->lista()
            ->call('nuovo')
            ->set('f.cliente_id', 1)
            ->set('f.data_inizio', '2026-11-20')
            ->set('f.ora_inizio', '08:00')
            ->set('f.data_fine', '2026-11-20')
            ->set('f.ora_fine', '12:00')
            ->html();

        $this->assertMatchesRegularExpression('/preferito(?:\\\\u0022)?:true/', $modale);
        $this->assertStringNotContainsString('★', $modale);
    }
}
