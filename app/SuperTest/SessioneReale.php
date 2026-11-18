<?php

namespace App\SuperTest;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/** Copia temporanea del database applicativo. Il file vive 30 minuti. */
class SessioneReale
{
    public const MINUTI = 30;

    public static function validaId(string $id): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', $id) === 1;
    }

    public static function nuova(): string
    {
        self::pulisciScadute();
        $corrente = self::corrente();

        if ($corrente !== null) {
            self::elimina($corrente);
        }

        $id = bin2hex(random_bytes(16));
        File::ensureDirectoryExists(self::cartella());
        self::scrivi($id, [
            'id' => $id,
            'stato' => 'copia',
            'creata' => now()->toIso8601String(),
            'scade' => null,
            'tabelle' => [],
            'errore' => null,
            'annullata' => false,
        ]);
        file_put_contents(self::puntatore(), $id);

        return $id;
    }

    public static function lanciaCopia(string $id): void
    {
        if (! self::validaId($id)) {
            return;
        }

        $comando = 'nohup '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan'))
            .' supertest:copia '.escapeshellarg($id)
            .' >> '.escapeshellarg(self::file($id, 'output.log'))
            .' 2>&1 < /dev/null &';

        pclose(popen($comando, 'r'));
    }

    public static function programmaEliminazione(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $comando = '(sleep '.(self::MINUTI * 60).'; '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan'))
            .' supertest:pulisci) >/dev/null 2>&1 < /dev/null &';

        pclose(popen($comando, 'r'));
    }

    /**
     * @param  array{nome: string, totale: int, copiate: int}  $tabella
     */
    public static function aggiornaTabella(string $id, array $tabella): void
    {
        $meta = self::leggi($id);

        if ($meta === null || self::fermata($id)) {
            return;
        }

        $tabelle = $meta['tabelle'];
        $indice = array_search($tabella['nome'], array_column($tabelle, 'nome'), true);

        if ($indice === false) {
            $tabelle[] = $tabella;
        } else {
            $tabelle[$indice] = $tabella;
        }

        $meta['tabelle'] = array_values($tabelle);
        self::scrivi($id, $meta);
    }

    public static function segnaPronta(string $id): void
    {
        $meta = self::leggi($id);

        if ($meta === null || self::fermata($id)) {
            return;
        }

        $meta['stato'] = 'pronta';
        $meta['scade'] = now()->addMinutes(self::MINUTI)->toIso8601String();
        $meta['errore'] = null;
        self::scrivi($id, $meta);
        self::programmaEliminazione();
    }

    public static function segnaErrore(string $id, string $messaggio): void
    {
        if (self::fermata($id)) {
            return;
        }

        $meta = self::leggi($id) ?? [
            'id' => $id,
            'stato' => 'errore',
            'creata' => now()->toIso8601String(),
            'scade' => now()->addMinutes(self::MINUTI)->toIso8601String(),
            'tabelle' => [],
            'errore' => null,
            'annullata' => false,
        ];
        $meta['stato'] = 'errore';
        $meta['errore'] = mb_substr($messaggio, 0, 400);
        self::scrivi($id, $meta);
    }

    public static function pronta(string $id): bool
    {
        $meta = self::leggi($id);

        return $meta !== null
            && $meta['stato'] === 'pronta'
            && ! self::fermata($id)
            && is_file(self::database($id))
            && ($meta['scade'] === null || now()->lt(Carbon::parse($meta['scade'])));
    }

    public static function fermata(string $id): bool
    {
        return is_file(self::file($id, 'stop'));
    }

    public static function database(string $id): string
    {
        return self::file($id, 'sqlite');
    }

    public static function diario(string $id): string
    {
        return self::file($id, 'diario.log');
    }

    public static function elimina(?string $id = null): void
    {
        $id ??= self::corrente();

        if ($id === null || ! self::validaId($id)) {
            return;
        }

        File::ensureDirectoryExists(self::cartella());
        file_put_contents(self::file($id, 'stop'), '1');

        foreach (['sqlite', 'sqlite-wal', 'sqlite-shm', 'json', 'diario.log', 'output.log'] as $suffisso) {
            $file = self::file($id, $suffisso);

            if (is_file($file)) {
                unlink($file);
            }
        }

        if (self::corrente() === $id) {
            @unlink(self::puntatore());
        }
    }

    public static function pulisciScadute(): int
    {
        if (! is_dir(self::cartella())) {
            return 0;
        }

        $rimosse = 0;

        foreach (File::files(self::cartella()) as $file) {
            $nome = $file->getFilename();

            if (! str_ends_with($nome, '.json')) {
                continue;
            }

            $id = substr($nome, 0, -5);

            if (! self::validaId($id) || ! self::scaduta($id)) {
                continue;
            }

            self::elimina($id);
            @unlink(self::file($id, 'stop'));
            $rimosse++;
        }

        foreach (File::files(self::cartella()) as $file) {
            if (str_ends_with($file->getFilename(), '.stop') && $file->getMTime() < time() - 3600) {
                unlink($file->getPathname());
            }
        }

        return $rimosse;
    }

    /**
     * @return array{id: ?string, stato: string, creata: ?string, scade: ?string, tabelle: list<array{nome: string, totale: int, copiate: int}>, errore: ?string, diario: list<array{t: string, caso: string, testo: string}>, prossimo: int}
     */
    public static function stato(int $da = 0): array
    {
        self::pulisciScadute();
        $id = self::corrente();
        $meta = $id !== null ? self::leggi($id) : null;

        if ($meta === null || self::fermata($id ?? '')) {
            return [
                'id' => null,
                'stato' => 'assente',
                'creata' => null,
                'scade' => null,
                'tabelle' => [],
                'errore' => null,
                'diario' => [],
                'prossimo' => 0,
            ];
        }

        $righe = is_file(self::diario($id)) ? file(self::diario($id), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $righe = $righe === false ? [] : $righe;
        $diario = [];

        foreach (array_slice($righe, max(0, $da)) as $riga) {
            $decodificata = json_decode($riga, true);

            if (is_array($decodificata) && isset($decodificata['caso'], $decodificata['testo'])) {
                $diario[] = [
                    't' => (string) ($decodificata['t'] ?? ''),
                    'caso' => (string) $decodificata['caso'],
                    'testo' => (string) $decodificata['testo'],
                ];
            }
        }

        return [
            'id' => $id,
            'stato' => $meta['stato'],
            'creata' => $meta['creata'],
            'scade' => $meta['scade'],
            'tabelle' => $meta['tabelle'],
            'errore' => $meta['errore'],
            'diario' => $diario,
            'prossimo' => max(0, $da) + count($diario),
        ];
    }

    public static function corrente(): ?string
    {
        if (! is_file(self::puntatore())) {
            return null;
        }

        $id = trim((string) file_get_contents(self::puntatore()));

        return self::validaId($id) ? $id : null;
    }

    /**
     * @return array{id: string, stato: string, creata: ?string, scade: ?string, tabelle: list<array{nome: string, totale: int, copiate: int}>, errore: ?string, annullata: bool}|null
     */
    public static function leggi(string $id): ?array
    {
        if (! self::validaId($id) || ! is_file(self::file($id, 'json'))) {
            return null;
        }

        $meta = json_decode((string) file_get_contents(self::file($id, 'json')), true);

        if (! is_array($meta)) {
            return null;
        }

        return [
            'id' => $id,
            'stato' => (string) ($meta['stato'] ?? 'errore'),
            'creata' => isset($meta['creata']) ? (string) $meta['creata'] : null,
            'scade' => isset($meta['scade']) ? (string) $meta['scade'] : null,
            'tabelle' => array_values(array_filter(
                is_array($meta['tabelle'] ?? null) ? $meta['tabelle'] : [],
                fn ($tabella): bool => is_array($tabella) && isset($tabella['nome'], $tabella['totale'], $tabella['copiate']),
            )),
            'errore' => isset($meta['errore']) ? (string) $meta['errore'] : null,
            'annullata' => (bool) ($meta['annullata'] ?? false),
        ];
    }

    public static function cartella(): string
    {
        return storage_path('framework/supertest/sessioni');
    }

    private static function scaduta(string $id): bool
    {
        $meta = self::leggi($id);

        if ($meta === null) {
            return false;
        }

        if ($meta['scade'] !== null && now()->gte(Carbon::parse($meta['scade']))) {
            return true;
        }

        return $meta['stato'] === 'copia'
            && $meta['creata'] !== null
            && now()->gte(Carbon::parse($meta['creata'])->addMinutes(self::MINUTI + 15));
    }

    /**
     * @param  array{id: string, stato: string, creata: ?string, scade: ?string, tabelle: list<array{nome: string, totale: int, copiate: int}>, errore: ?string, annullata: bool}  $meta
     */
    private static function scrivi(string $id, array $meta): void
    {
        if (! self::validaId($id) || self::fermata($id)) {
            return;
        }

        File::ensureDirectoryExists(self::cartella());
        $destinazione = self::file($id, 'json');
        $temporaneo = $destinazione.'.tmp';
        file_put_contents($temporaneo, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        rename($temporaneo, $destinazione);
    }

    private static function file(string $id, string $suffisso): string
    {
        return self::cartella().DIRECTORY_SEPARATOR.$id.'.'.$suffisso;
    }

    private static function puntatore(): string
    {
        return storage_path('framework/supertest/corrente');
    }
}
