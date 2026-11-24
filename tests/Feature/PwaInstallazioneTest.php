<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaInstallazioneTest extends TestCase
{
    public function test_la_pagina_di_accesso_propone_linstallazione_sul_telefono(): void
    {
        $risposta = $this->get('/login');

        $risposta->assertOk();
        $risposta->assertSee('rel="manifest"', false);
        $risposta->assertSee(route('pwa.manifest'), false);
        $risposta->assertSee('apple-mobile-web-app-capable', false);
        $risposta->assertSee('apple-touch-icon', false);
        $risposta->assertSee('serviceWorker.register', false);
    }

    public function test_il_manifest_descrive_un_app_a_schermo_intero(): void
    {
        $risposta = $this->get(route('pwa.manifest'));

        $risposta->assertOk();
        $risposta->assertHeader('Content-Type', 'application/manifest+json');
        $risposta->assertJsonPath('name', config('app.name'));
        $risposta->assertJsonPath('short_name', config('brand.short_name'));
        $risposta->assertJsonPath('display', 'standalone');
        $risposta->assertJsonPath('start_url', '/');
        $risposta->assertJsonPath('icons.0.src', '/'.config('brand.icons.192'));
        $risposta->assertJsonPath('icons.1.src', '/'.config('brand.icons.512'));
        $risposta->assertJsonPath('icons.2.purpose', 'maskable');

        foreach ([
            config('brand.icons.192') => 192,
            config('brand.icons.512') => 512,
            config('brand.icons.apple') => 180,
            config('brand.icons.maskable') => 512,
        ] as $file => $lato) {
            $percorso = public_path($file);
            $this->assertFileExists($percorso);
            $misure = getimagesize($percorso);
            $this->assertSame($lato, $misure[0]);
            $this->assertSame($lato, $misure[1]);
        }

        $this->assertStringContainsString("addEventListener('fetch'", (string) file_get_contents(public_path('sw.js')));
    }
}
