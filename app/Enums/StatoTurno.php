<?php

namespace App\Enums;

enum StatoTurno: string
{
    case Programmato = 'P';
    case Iniziato = 'I';
    case Completato = 'F';

    public function label(): string
    {
        return match ($this) {
            self::Programmato => 'Programmato',
            self::Iniziato => 'In corso',
            self::Completato => 'Completato',
        };
    }
}
