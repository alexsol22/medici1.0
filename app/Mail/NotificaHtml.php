<?php

namespace App\Mail;

use App\Models\Legacy\Config;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificaHtml extends Mailable
{
    /**
     * @param  array{etichetta: string, url: string}|null  $azione  pulsante opzionale
     */
    public function __construct(public string $oggettoEmail, public string $corpoHtml, public ?array $azione = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->oggettoEmail);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->corpoRenderizzato());
    }

    public function corpoRenderizzato(): string
    {
        return self::renderizza($this->oggettoEmail, $this->corpoHtml, $this->azione);
    }

    /**
     * @param  array{etichetta: string, url: string}|null  $azione
     */
    public static function renderizza(string $oggetto, string $corpoHtml, ?array $azione = null): string
    {
        $config = Config::first();
        $app = trim((string) config('app.name'));
        $indirizzo = trim(html_entity_decode(implode(' ', array_filter([
            $config?->societa_indirizzo,
            trim($config?->societa_cap.' '.$config?->societa_comune.($config?->societa_provincia ? ' ('.$config->societa_provincia.')' : '')),
        ]))));

        return view('emails.notifica', [
            'oggetto' => $oggetto,
            'corpoHtml' => $corpoHtml,
            'azione' => $azione,
            'app' => $app,
            'logo' => self::logoIncorporato(),
            'colori' => config('brand.colors'),
            'societa' => [
                'ragione_sociale' => $config?->ragione_sociale ?: config('app.name'),
                'indirizzo' => $indirizzo,
                'telefono' => (string) ($config?->societa_telefono ?: $config?->phone),
                'email' => (string) ($config?->societa_email ?: $config?->email),
                'iva' => (string) $config?->societa_iva,
            ],
        ])->render();
    }

    /**
     * URL pubblico: Gmail e Outlook scartano i data URI, quindi il logo sparisce
     * anche quando il file è sul server.
     *
     * @return array{src: string, larghezza: int, altezza: int}
     */
    private static function logoIncorporato(): array
    {
        $percorso = public_path((string) config('brand.logo_email'));
        $misure = is_file($percorso) ? getimagesize($percorso) : false;

        return [
            'src' => asset((string) config('brand.logo_email')),
            'larghezza' => $misure[0] ?? 440,
            'altezza' => $misure[1] ?? 127,
        ];
    }
}
