<?php

namespace App\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;

class CodiceQr extends Model
{
    protected $table = 'codici_qr';

    protected $guarded = [];

    public const LIBERO = 'C';

    public const ATTIVO = 'A';

    public const SEDE = 'S';

    public const POSTAZIONE = 'P';

    public function svg(int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($this->codice);
    }

    /** Stesso formato del vecchio gestionale: ITALPOL* + 6 cifre casuali, univoco. */
    public static function generaCodice(): string
    {
        do {
            $codice = 'ITALPOL*'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('codice', $codice)->exists());

        return $codice;
    }

    public static function nuovo(string $tipo, ?int $sedeId = null, ?int $postazioneId = null): self
    {
        return static::create([
            'codice' => self::generaCodice(),
            'stato' => self::ATTIVO,
            'tipo' => $tipo,
            'sede_id' => $sedeId,
            'postazione_id' => $postazioneId,
        ]);
    }
}
