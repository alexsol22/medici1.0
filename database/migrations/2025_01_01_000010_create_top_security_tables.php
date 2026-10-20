<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clienti', function (Blueprint $t) {
            $t->id();
            $t->string('ragione_sociale');
            $t->string('codice_fiscale')->nullable();
            $t->string('piva', 11)->nullable();
            $t->string('indirizzo')->nullable();
            $t->string('cap', 10)->nullable();
            $t->string('citta')->nullable();
            $t->string('provincia', 2)->nullable();
            $t->string('referente_nome')->nullable();
            $t->string('referente_cognome')->nullable();
            $t->string('telefono')->nullable();
            $t->string('email')->nullable();
            $t->string('pec')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->date('inizio_contratto')->nullable();
            $t->date('fine_contratto')->nullable();
            $t->boolean('is_test')->default(false);
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('cognome')->nullable();
            $t->string('cellulare')->nullable();
            $t->string('ruolo')->default('operatore');
            $t->foreignId('cliente_id')->nullable()->constrained('clienti')->nullOnDelete();
            $t->string('codice')->nullable();
            $t->decimal('costo_orario', 8, 2)->nullable();
            $t->boolean('attivo')->default(true);
        });

        Schema::create('sedi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clienti')->cascadeOnDelete();
            $t->string('descrizione');
            $t->string('indirizzo')->nullable();
            $t->string('cap', 10)->nullable();
            $t->string('citta')->nullable();
            $t->string('provincia', 2)->nullable();
            $t->string('telefono')->nullable();
            $t->string('email')->nullable();
            $t->text('note')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('distanza_max')->default(100);
            $t->decimal('tariffa', 8, 2)->nullable();
            $t->json('ore_richieste')->nullable();
            $t->timestamps();
        });

        Schema::create('codici_qr', function (Blueprint $t) {
            $t->id();
            $t->string('codice')->unique();
            $t->string('stato', 1)->default('C');
            $t->string('tipo', 1)->nullable();
            $t->unsignedBigInteger('sede_id')->nullable();
            $t->unsignedBigInteger('postazione_id')->nullable();
            $t->timestamps();
        });

        Schema::create('postazioni', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clienti')->cascadeOnDelete();
            $t->string('titolo');
            $t->foreignId('codice_qr_id')->nullable()->constrained('codici_qr')->nullOnDelete();
            $t->unsignedInteger('minuti_passaggio')->nullable();
            $t->timestamps();
        });

        Schema::table('sedi', function (Blueprint $t) {
            $t->foreignId('codice_qr_id')->nullable()->constrained('codici_qr')->nullOnDelete();
        });

        Schema::create('turni', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operatore_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clienti')->cascadeOnDelete();
            $t->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $t->dateTime('inizio');
            $t->dateTime('fine');
            $t->decimal('ore', 6, 2)->default(0);
            $t->decimal('ore_lavorate', 6, 2)->nullable();
            $t->string('stato', 1)->default('P');
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['operatore_id', 'inizio']);
            $t->index(['sede_id', 'inizio']);
        });

        Schema::create('presenze', function (Blueprint $t) {
            $t->id();
            $t->foreignId('turno_id')->constrained('turni')->cascadeOnDelete();
            $t->foreignId('operatore_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clienti')->cascadeOnDelete();
            $t->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $t->foreignId('postazione_id')->nullable()->constrained('postazioni')->nullOnDelete();
            $t->foreignId('codice_qr_id')->nullable()->constrained('codici_qr')->nullOnDelete();
            $t->string('tipo', 1); // I inizio, F fine, P passaggio
            $t->dateTime('rilevata_at');
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->decimal('distanza_km', 8, 3)->nullable();
            $t->integer('ritardo_minuti')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
        });

        Schema::create('cliente_operatore', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clienti')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('tipo', 10); // preferito | escluso
            $t->unique(['cliente_id', 'user_id', 'tipo']);
        });

        Schema::create('documenti', function (Blueprint $t) {
            $t->id();
            $t->string('titolo');
            $t->string('tipo', 10); // cliente | operatore | sede
            $t->foreignId('cliente_id')->nullable()->constrained('clienti')->cascadeOnDelete();
            $t->foreignId('sede_id')->nullable()->constrained('sedi')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $t->string('file');
            $t->date('scadenza')->nullable();
            $t->boolean('formazione')->default(false);
            $t->timestamp('visto_at')->nullable();
            $t->timestamps();
        });

        Schema::create('avvisi', function (Blueprint $t) {
            $t->id();
            $t->string('titolo');
            $t->text('testo');
            $t->boolean('per_tutti')->default(true);
            $t->foreignId('autore_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('avviso_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('avviso_id')->constrained('avvisi')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('letto_at')->nullable();
            $t->unique(['avviso_id', 'user_id']);
        });

        Schema::create('modelli_messaggio', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->unsignedInteger('giorni_prima')->default(0);
            $t->boolean('invia_email')->default(true);
            $t->boolean('invia_sms')->default(false);
            $t->string('oggetto')->nullable();
            $t->text('testo_email')->nullable();
            $t->text('testo_sms')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['modelli_messaggio', 'avviso_user', 'avvisi', 'documenti', 'cliente_operatore', 'presenze', 'turni'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('sedi', fn (Blueprint $t) => $t->dropConstrainedForeignId('codice_qr_id'));
        foreach (['postazioni', 'codici_qr', 'sedi'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cliente_id');
            $t->dropColumn(['cognome', 'cellulare', 'ruolo', 'codice', 'costo_orario', 'attivo']);
        });
        Schema::dropIfExists('clienti');
    }
};
