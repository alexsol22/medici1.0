<?php

namespace App\SuperTest;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/** Lancia un solo caso PHPUnit in un processo con database in memoria. */
class Esecutore
{
    /**
     * @return array{id: string, sezione: string, titolo: string, esito: string, durata_ms: int, messaggio: string}
     */
    public function esegui(string $id, ?string $sessione = null): array
    {
        $caso = Catalogo::caso($id);

        if ($caso === null || preg_match('/^test_[a-z]+_\d{2}$/', $caso['metodo']) !== 1) {
            return $this->esito($id, 'sconosciuto', 'Caso non previsto', 'error', 0, 'Caso non presente nel catalogo.');
        }

        $database = null;
        $diario = null;
        $classe = 'CasiCollaudoTest';

        if ($sessione !== null) {
            if (! SessioneReale::validaId($sessione) || ! SessioneReale::pronta($sessione)) {
                return $this->esito($id, $caso['sezione'], $caso['titolo'], 'error', 0, 'La sessione con i dati reali non è disponibile.');
            }

            $database = SessioneReale::database($sessione);
            $diario = SessioneReale::diario($sessione);
            $classe = 'CasiDatiRealiTest';
        }

        $junit = storage_path('framework/supertest/'.bin2hex(random_bytes(8)).'.xml');

        if (! is_dir(dirname($junit))) {
            mkdir(dirname($junit), 0755, true);
        }

        $processo = new Process(
            [
                PHP_BINARY,
                base_path('vendor/bin/phpunit'),
                '--do-not-cache-result',
                '--filter',
                $classe.'::'.$caso['metodo'].'$',
                '--log-junit',
                $junit,
            ],
            base_path(),
            $this->ambiente($database, $diario),
            null,
            120,
        );

        $inizio = hrtime(true);

        try {
            $processo->run();
        } catch (ProcessTimedOutException) {
            @unlink($junit);

            return $this->esito($caso['id'], $caso['sezione'], $caso['titolo'], 'error', 120000, 'Il caso ha superato il tempo massimo.');
        }

        $durata = (int) ((hrtime(true) - $inizio) / 1_000_000);
        $esito = $this->leggiJunit($junit, $caso['metodo']);
        @unlink($junit);

        if ($esito === null) {
            $dettaglio = trim($processo->getErrorOutput()."\n".$processo->getOutput());

            return $this->esito(
                $caso['id'],
                $caso['sezione'],
                $caso['titolo'],
                'error',
                $durata,
                $dettaglio !== '' ? $this->accorcia($dettaglio) : 'PHPUnit non ha restituito un risultato.',
            );
        }

        return $this->esito($caso['id'], $caso['sezione'], $caso['titolo'], $esito['esito'], $esito['durata_ms'] ?: $durata, $esito['messaggio']);
    }

    /** @return array<string, string> */
    private function ambiente(?string $database = null, ?string $diario = null): array
    {
        $path = getenv('PATH');
        $ambiente = [
            'PATH' => is_string($path) && $path !== '' ? $path : '/usr/bin:/bin',
            'HOME' => is_string(getenv('HOME')) ? (string) getenv('HOME') : sys_get_temp_dir(),
            'TMPDIR' => sys_get_temp_dir(),
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database ?? ':memory:',
            'DB_URL' => '',
            'DB_HOST' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'SMS_ENABLED' => 'false',
            'WHATSAPP_ENABLED' => 'false',
            'WHATSAPP_CRON' => 'false',
            'NOTIFICHE_PROVA' => 'false',
            'BCRYPT_ROUNDS' => '4',
            'LOG_CHANNEL' => 'stderr',
        ];

        if ($database !== null) {
            $ambiente['SUPERTTEST_DATABASE'] = $database;
            $ambiente['SUPERTTEST_DIARIO'] = $diario ?? '';

            foreach (['APP_URL', 'APP_LOCALE', 'FILESYSTEM_DISK', 'FILES_DISK', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET', 'AWS_URL', 'AWS_ENDPOINT', 'AWS_USE_PATH_STYLE_ENDPOINT'] as $chiave) {
                $valore = $_SERVER[$chiave] ?? $_ENV[$chiave] ?? getenv($chiave);

                if (is_string($valore) && $valore !== '') {
                    $ambiente[$chiave] = $valore;
                }
            }
        }

        return $ambiente;
    }

    /**
     * @return array{esito: string, durata_ms: int, messaggio: string}|null
     */
    private function leggiJunit(string $percorso, string $metodo): ?array
    {
        if (! is_file($percorso)) {
            return null;
        }

        $xml = @simplexml_load_file($percorso);

        if ($xml === false) {
            return null;
        }

        foreach ($xml->xpath('//testcase') ?: [] as $caso) {
            $nome = (string) $caso['name'];

            if ($nome !== $metodo) {
                continue;
            }

            $durata = (int) round(((float) $caso['time']) * 1000);

            if (isset($caso->skipped)) {
                $motivo = trim((string) $caso->skipped);

                return ['esito' => 'skip', 'durata_ms' => $durata, 'messaggio' => $motivo !== '' ? $this->accorcia($motivo) : 'Dato assente.'];
            }

            $fallimento = $caso->failure[0] ?? $caso->error[0] ?? null;

            if ($fallimento === null) {
                return ['esito' => 'pass', 'durata_ms' => $durata, 'messaggio' => 'Ok'];
            }

            $testo = trim((string) ($fallimento['message'] ?: $fallimento));

            return ['esito' => 'fail', 'durata_ms' => $durata, 'messaggio' => $this->accorcia($testo !== '' ? $testo : 'Asserzione non riuscita.')];
        }

        return null;
    }

    private function accorcia(string $testo): string
    {
        $testo = preg_replace("/\n{3,}/", "\n\n", $testo) ?? $testo;

        return mb_strlen($testo) > 1500 ? mb_substr($testo, 0, 1500).'…' : $testo;
    }

    /**
     * @return array{id: string, sezione: string, titolo: string, esito: string, durata_ms: int, messaggio: string}
     */
    private function esito(string $id, string $sezione, string $titolo, string $esito, int $durata, string $messaggio): array
    {
        return [
            'id' => $id,
            'sezione' => $sezione,
            'titolo' => $titolo,
            'esito' => $esito,
            'durata_ms' => $durata,
            'messaggio' => $messaggio,
        ];
    }
}
