<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_aziendali', function (Blueprint $t) {
            $t->id();
            $t->string('titolo')->nullable();
            $t->text('contenuto');
            $t->string('colore', 7)->default('#0369a1');
            $t->string('logo_path')->nullable();
            $t->foreignId('cliente_id')->nullable()->constrained('clienti')->nullOnDelete();
            $t->foreignId('sede_id')->nullable()->constrained('sedi')->nullOnDelete();
            $t->foreignId('postazione_id')->nullable()->constrained('postazioni')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_aziendali');
    }
};
