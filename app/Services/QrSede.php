<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QrSede
{
    public function percorso(string $codice): string
    {
        return trim(config('filesystems.private_paths.documenti'), '/').'/QrcodeText/'.str_replace(['/', '\\'], '_', $codice).'.png';
    }

    public function genera(string $codice, string $colore = '#000000', ?string $logo = null, string $titolo = ''): void
    {
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $colore)) {
            throw new RuntimeException('Il colore del QR non è valido.');
        }

        $disco = Storage::disk(config('filesystems.files_disk'));
        $percorso = $this->percorso($codice);

        if ($disco->exists($percorso)) {
            throw new RuntimeException("Il file QR esiste già: {$percorso}");
        }

        $this->scriviPng($codice, $percorso, $colore, $logo, trim($titolo));
    }

    public function generaPersonalizzato(string $codice, string $colore, ?string $logo = null, string $titolo = ''): void
    {
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $colore)) {
            throw new RuntimeException('Il colore del QR non è valido.');
        }

        $percorso = $this->percorso($codice);
        $this->scriviPng($codice, $percorso, $colore, $logo, trim($titolo));
    }

    private function scriviPng(string $codice, string $percorso, string $colore = '#000000', ?string $logo = null, string $titolo = ''): void
    {
        $matrice = Encoder::encode($codice, ErrorCorrectionLevel::H())->getMatrix();
        $scala = 16;
        $margine = 4;
        $lato = ($matrice->getWidth() + 2 * $margine) * $scala;
        $immagine = imagecreatetruecolor($lato, $lato + $this->altezzaDidascalia($titolo));
        $bianco = imagecolorallocate($immagine, 255, 255, 255);
        [$rosso, $verde, $blu] = sscanf($colore, '#%02x%02x%02x');
        $coloreQr = imagecolorallocate($immagine, $rosso, $verde, $blu);
        imagefill($immagine, 0, 0, $bianco);

        for ($y = 0; $y < $matrice->getHeight(); $y++) {
            for ($x = 0; $x < $matrice->getWidth(); $x++) {
                if ($matrice->get($x, $y) === 1) {
                    $sinistra = ($x + $margine) * $scala;
                    $alto = ($y + $margine) * $scala;
                    imagefilledrectangle($immagine, $sinistra, $alto, $sinistra + $scala - 1, $alto + $scala - 1, $coloreQr);
                }
            }
        }

        if ($logo !== null) {
            $immagineLogo = imagecreatefromstring($logo);

            if ($immagineLogo === false) {
                imagedestroy($immagine);
                throw new RuntimeException('Impossibile leggere il logo caricato.');
            }

            $latoLogo = (int) floor($lato * 0.2);
            $logoX = intdiv($lato - $latoLogo, 2);
            $logoY = intdiv($lato - $latoLogo, 2);
            $margineLogo = 10;
            imagefilledrectangle(
                $immagine,
                $logoX - $margineLogo,
                $logoY - $margineLogo,
                $logoX + $latoLogo + $margineLogo,
                $logoY + $latoLogo + $margineLogo,
                $bianco,
            );
            imagecopyresampled(
                $immagine,
                $immagineLogo,
                $logoX,
                $logoY,
                0,
                0,
                $latoLogo,
                $latoLogo,
                imagesx($immagineLogo),
                imagesy($immagineLogo),
            );
            imagedestroy($immagineLogo);
        }

        $this->disegnaDidascalia($immagine, $lato, $coloreQr, $codice, $titolo);

        ob_start();
        try {
            if (! imagepng($immagine)) {
                throw new RuntimeException("Impossibile generare l'immagine QR: {$codice}");
            }
            $png = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($immagine);
        }

        $disco = Storage::disk(config('filesystems.files_disk'));

        if ($png === false || ! $disco->put($percorso, $png)) {
            throw new RuntimeException("Impossibile salvare l'immagine QR: {$percorso}");
        }
    }

    public function generaMancante(string $codice): bool
    {
        $disco = Storage::disk(config('filesystems.files_disk'));
        $percorso = $this->percorso($codice);

        if ($disco->exists($percorso)) {
            return false;
        }

        $this->scriviAziendale($codice, $percorso);

        return true;
    }

    private function scriviAziendale(string $codice, string $percorso): void
    {
        $matrice = Encoder::encode($codice, ErrorCorrectionLevel::H())->getMatrix();
        $moduli = $matrice->getWidth();
        $scala = 40;
        $latoQr = $moduli * $scala;
        $tela = 1260;
        $origineX = intdiv($tela - $latoQr, 2);
        $origineY = 110;
        $immagine = imagecreatetruecolor($tela, $tela);
        $bianco = imagecolorallocate($immagine, 255, 255, 255);
        $navy = imagecolorallocate($immagine, 16, 22, 38);
        $ciano = imagecolorallocate($immagine, 39, 154, 201);
        $nero = imagecolorallocate($immagine, 0, 0, 0);
        imagefill($immagine, 0, 0, $bianco);

        $coloriRiga = [];

        for ($y = 0; $y < $moduli; $y++) {
            [$rosso, $verde, $blu] = $this->coloreModulo($y, $moduli);
            $coloriRiga[$y] = imagecolorallocate($immagine, $rosso, $verde, $blu);
        }

        $diametro = 34;

        for ($y = 0; $y < $moduli; $y++) {
            for ($x = 0; $x < $moduli; $x++) {
                if ($matrice->get($x, $y) !== 1 || $this->eFinder($x, $y, $moduli)) {
                    continue;
                }

                $centroX = $origineX + $x * $scala + intdiv($scala, 2);
                $centroY = $origineY + $y * $scala + intdiv($scala, 2);
                imagefilledellipse($immagine, $centroX, $centroY, $diametro, $diametro, $coloriRiga[$y]);
            }
        }

        foreach ([[0, 0], [$moduli - 7, 0], [0, $moduli - 7]] as [$fx, $fy]) {
            $this->disegnaFinder(
                $immagine,
                $origineX + $fx * $scala,
                $origineY + $fy * $scala,
                $scala,
                $navy,
                $ciano,
                $bianco,
            );
        }

        $this->disegnaLogo($immagine, $origineX, $origineY, $latoQr);
        $this->disegnaCodice($immagine, $nero, $codice, $origineY + $latoQr + 100);

        ob_start();
        try {
            if (! imagejpeg($immagine, null, 100)) {
                throw new RuntimeException("Impossibile generare l'immagine QR: {$codice}");
            }
            $jpeg = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($immagine);
        }

        $disco = Storage::disk(config('filesystems.files_disk'));

        if ($jpeg === false || ! $disco->put($percorso, $jpeg)) {
            throw new RuntimeException("Impossibile salvare l'immagine QR: {$percorso}");
        }
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function coloreModulo(int $riga, int $moduli): array
    {
        $t = $moduli <= 1 ? 0 : $riga / ($moduli - 1);
        $alto = [16, 22, 38];
        $basso = [49, 156, 201];

        return [
            (int) round($alto[0] + ($basso[0] - $alto[0]) * $t),
            (int) round($alto[1] + ($basso[1] - $alto[1]) * $t),
            (int) round($alto[2] + ($basso[2] - $alto[2]) * $t),
        ];
    }

    private function eFinder(int $x, int $y, int $moduli): bool
    {
        $dentro = fn (int $coordinata, int $origine): bool => $coordinata >= $origine && $coordinata < $origine + 7;

        return ($dentro($x, 0) && $dentro($y, 0))
            || ($dentro($x, $moduli - 7) && $dentro($y, 0))
            || ($dentro($x, 0) && $dentro($y, $moduli - 7));
    }

    private function disegnaFinder(\GdImage $immagine, int $x, int $y, int $scala, int $navy, int $ciano, int $bianco): void
    {
        $lato = 7 * $scala;
        $this->rettangoloArrotondato($immagine, $x, $y, $lato, $lato, (int) round($scala * 1.15), $navy);
        $this->rettangoloArrotondato($immagine, $x + $scala, $y + $scala, $lato - 2 * $scala, $lato - 2 * $scala, (int) round($scala * 0.85), $bianco);
        $this->rettangoloArrotondato($immagine, $x + 2 * $scala, $y + 2 * $scala, 3 * $scala, 3 * $scala, (int) round($scala * 0.55), $ciano);
    }

    private function rettangoloArrotondato(\GdImage $immagine, int $x, int $y, int $w, int $h, int $r, int $colore): void
    {
        $r = max(1, min($r, intdiv(min($w, $h), 2)));
        imagefilledrectangle($immagine, $x + $r, $y, $x + $w - $r, $y + $h, $colore);
        imagefilledrectangle($immagine, $x, $y + $r, $x + $w, $y + $h - $r, $colore);
        imagefilledellipse($immagine, $x + $r, $y + $r, $r * 2, $r * 2, $colore);
        imagefilledellipse($immagine, $x + $w - $r, $y + $r, $r * 2, $r * 2, $colore);
        imagefilledellipse($immagine, $x + $r, $y + $h - $r, $r * 2, $r * 2, $colore);
        imagefilledellipse($immagine, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $colore);
    }

    private function disegnaLogo(\GdImage $immagine, int $origineX, int $origineY, int $latoQr): void
    {
        $percorso = resource_path('brand/logo-qr-italpol.png');
        $logo = is_file($percorso) ? imagecreatefrompng($percorso) : false;

        if ($logo === false) {
            imagedestroy($immagine);
            throw new RuntimeException('Manca il logo del QR aziendale.');
        }

        imagealphablending($immagine, true);
        $x = $origineX + intdiv($latoQr - imagesx($logo), 2);
        $y = $origineY + intdiv($latoQr - imagesy($logo), 2) - (int) round($latoQr * 0.02);

        for ($py = 0; $py < imagesy($logo); $py++) {
            for ($px = 0; $px < imagesx($logo); $px++) {
                $colore = imagecolorat($logo, $px, $py);

                if ((($colore >> 24) & 127) > 16) {
                    continue;
                }

                imagesetpixel(
                    $immagine,
                    $x + $px,
                    $y + $py,
                    imagecolorallocate($immagine, ($colore >> 16) & 255, ($colore >> 8) & 255, $colore & 255),
                );
            }
        }

        imagedestroy($logo);
    }

    private function disegnaCodice(\GdImage $immagine, int $colore, string $codice, int $baseline): void
    {
        $font = resource_path('fonts/DejaVuSans.ttf');

        if (! is_file($font)) {
            imagestring($immagine, 5, 8, $baseline - 16, $codice, $colore);

            return;
        }

        $size = 28;
        $box = imagettfbbox($size, 0, $font, $codice);
        $larghezza = $box === false ? 0 : abs($box[2] - $box[0]);
        $x = (int) max(0, (imagesx($immagine) - $larghezza) / 2);
        imagettftext($immagine, $size, 0, $x, $baseline, $colore, $font, $codice);
    }

    private function altezzaDidascalia(string $titolo): int
    {
        return $titolo === '' ? 56 : 96;
    }

    private function disegnaDidascalia(\GdImage $immagine, int $lato, int $colore, string $codice, string $titolo): void
    {
        $font = resource_path('fonts/DejaVuSans.ttf');
        $righe = $titolo === ''
            ? [['testo' => $codice, 'size' => 18]]
            : [['testo' => $titolo, 'size' => 22], ['testo' => $codice, 'size' => 16]];

        if (! is_file($font)) {
            imagestring($immagine, 5, 8, $lato + 12, $codice, $colore);

            return;
        }

        $y = $lato + 4;

        foreach ($righe as $riga) {
            $box = imagettfbbox($riga['size'], 0, $font, $riga['testo']);
            $larghezza = $box === false ? 0 : abs($box[2] - $box[0]);
            $x = (int) max(0, ($lato - $larghezza) / 2);
            $y += $riga['size'] + 10;
            imagettftext($immagine, $riga['size'], 0, $x, $y, $colore, $font, $riga['testo']);
        }
    }
}
