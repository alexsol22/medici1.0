<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrAziendale
{
    public const LATO = 400;

    /** SVG completo: cornice bianca, QR (correzione errori alta per reggere il logo), logo al centro e titolo sotto. */
    public function svg(string $contenuto, string $titolo = '', ?string $logoDataUri = null, string $colore = '#0f172a'): string
    {
        $colore = preg_match('/^#[0-9a-f]{6}$/i', $colore) ? $colore : '#0f172a';
        [$r, $g, $b] = sscanf($colore, '#%02x%02x%02x');

        $renderer = new ImageRenderer(
            new RendererStyle(self::LATO, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb($r, $g, $b))),
            new SvgImageBackEnd
        );
        $qr = (new Writer($renderer))->writeString($contenuto, 'UTF-8', ErrorCorrectionLevel::H());
        $qrUri = 'data:image/svg+xml;base64,'.base64_encode($qr);

        $pad = 30;
        $altezzaTitolo = $titolo !== '' ? 70 : 0;
        $w = self::LATO + $pad * 2;
        $h = $w + $altezzaTitolo;

        $out = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $out .= '<rect width="100%" height="100%" rx="24" fill="#ffffff"/>';
        $out .= '<image x="'.$pad.'" y="'.$pad.'" width="'.self::LATO.'" height="'.self::LATO.'" href="'.$qrUri.'" xlink:href="'.$qrUri.'"/>';

        if ($logoDataUri) {
            $box = 96;
            $x = ($w - $box) / 2;
            $y = $pad + (self::LATO - $box) / 2;
            $out .= '<rect x="'.($x - 8).'" y="'.($y - 8).'" width="'.($box + 16).'" height="'.($box + 16).'" rx="16" fill="#ffffff"/>';
            $out .= '<image x="'.$x.'" y="'.$y.'" width="'.$box.'" height="'.$box.'" preserveAspectRatio="xMidYMid meet" href="'.$logoDataUri.'" xlink:href="'.$logoDataUri.'"/>';
        }

        if ($titolo !== '') {
            $out .= '<text x="'.($w / 2).'" y="'.($w + 30).'" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="26" font-weight="700" fill="'.$colore.'">'.e(mb_substr($titolo, 0, 40)).'</text>';
        }

        return $out.'</svg>';
    }
}
