<?php

namespace App\Console\Commands;

use App\Enums\WhatsappStato;
use App\Services\WhatsApp;
use Illuminate\Console\Command;

class InviaProvaWhatsApp extends Command
{
    protected $signature = 'whatsapp:prova {numeri? : Uno o più numeri internazionali, separati da virgola. Default: WHATSAPP_TEST_TO} {--param=* : Parametri body del template, in ordine}';

    protected $description = 'Invia il template di prova WhatsApp a uno o più numeri';

    public function handle(WhatsApp $whatsapp): int
    {
        $lista = $this->argument('numeri') ?: (string) config('whatsapp.destinatario_prova');
        $template = (string) config('whatsapp.template_prova');
        $mancanti = $whatsapp->requisitiMancanti(is_string($lista) ? $lista : null, $template);

        if ($mancanti !== []) {
            $this->error('WhatsApp non è pronto. Imposta in .env: '.implode(', ', $mancanti).'.');

            return self::FAILURE;
        }

        $parametri = array_values(array_filter(
            $this->option('param'),
            fn (mixed $parametro): bool => is_string($parametro) && $parametro !== '',
        ));

        $falliti = 0;

        foreach ($whatsapp->elenco((string) $lista) as $numero) {
            $inviato = $whatsapp->inviaTemplate(
                $numero,
                $template,
                (string) config('whatsapp.lingua_prova'),
                $parametri,
            );

            if ($inviato === null) {
                $this->error("Invio a {$numero} non eseguito.");
                $falliti++;

                continue;
            }

            if ($inviato->stato === WhatsappStato::Fallito) {
                $this->error("{$numero}: ".($inviato->errore ?: 'Invio rifiutato da Meta.'));
                $falliti++;

                continue;
            }

            $this->info("WhatsApp inviato a {$inviato->destinatario}. Id Meta: {$inviato->wamid}");
        }

        return $falliti === 0 ? self::SUCCESS : self::FAILURE;
    }
}
