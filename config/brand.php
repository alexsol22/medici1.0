<?php

return [

    /*
    | Nome mostrato sotto l'icona, in home del telefono. Il nome completo è APP_NAME.
    */
    'short_name' => env('APP_SHORT_NAME', 'Vigilander'),

    'colors' => [
        'navy' => '#071E4A',
        'accent' => '#0094E8',
        'link' => '#0077C2',
        'page' => '#F3F8FC',
    ],

    /*
    | Percorsi relativi a public/. logo.png e le icone quadrate derivano da
    | public/brand/logo-nuovo.png e public/brand/icona-nuova.jpg.
    */
    'logo' => 'brand/logo.png',

    // Versione su sfondo bianco, incorporata nelle email.
    'logo_email' => 'brand/logo-email.png',

    'icons' => [
        '192' => 'brand/icon-192.png',
        '512' => 'brand/icon-512.png',
        'maskable' => 'brand/icon-maskable-512.png',
        'apple' => 'brand/apple-touch-icon.png',
    ],

];
