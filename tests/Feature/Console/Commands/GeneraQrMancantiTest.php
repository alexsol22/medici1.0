<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneraQrMancantiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = ':memory:';
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => $sqlite,
        ]);
        DB::purge('mysql');
        Storage::fake(config('filesystems.files_disk'));

        Schema::create('tc_codici', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('codice')->nullable();
            $table->string('stato')->nullable();
            $table->string('tipo')->nullable();
        });
    }

    public function test_creates_only_the_qr_images_that_are_missing(): void
    {
        DB::table('tc_codici')->insert([
            ['codice' => 'ITALPOL*000001', 'stato' => 'A', 'tipo' => 'S'],
            ['codice' => 'ITALPOL*000002', 'stato' => 'A', 'tipo' => 'S'],
        ]);
        $disco = Storage::disk(config('filesystems.files_disk'));
        $disco->put('protected/documenti/QrcodeText/ITALPOL*000001.png', 'gia-presente');

        $this->artisan('app:genera-qr-mancanti')
            ->expectsOutputToContain('ITALPOL*000002')
            ->expectsOutputToContain('Creati 1. Già presenti 1.')
            ->assertSuccessful();

        $this->assertSame('gia-presente', $disco->get('protected/documenti/QrcodeText/ITALPOL*000001.png'));
        $this->assertSame('image/jpeg', getimagesizefromstring($disco->get('protected/documenti/QrcodeText/ITALPOL*000002.png'))['mime']);
    }
}
