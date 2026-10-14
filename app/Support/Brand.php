<?php

namespace App\Support;

use App\Models\Clinic;
use Illuminate\Support\Facades\Storage;

/** Identidad de la doctora en los documentos (cotización, orden de radiografía, consentimientos). */
class Brand
{
    public const DEFAULT_COLOR = '#5E0F5C';

    /** Datos iniciales del consultorio de la Dra. Gynella Medina (los que salen en su cotización actual). */
    public static function odgynellaDefaults(int $clinicId): array
    {
        $logo = null;
        $source = database_path('seeders/assets/odgynella-logo.png');
        if (is_file($source)) {
            $logo = 'branding/'.$clinicId.'/logo.png';
            Storage::disk('local')->put($logo, file_get_contents($source));
        }

        return [
            'doctor_title' => 'Odontóloga',
            'phone' => '302 634 3332',
            'email' => 'odgynellamedina@gmail.com',
            'instagram' => '@gynellaodontologa',
            'brand_color' => self::DEFAULT_COLOR,
            'payment_note' => 'Esta cotización incluye facilidades de pago. De acuerdo a cada abono iremos avanzando progresivamente con el tratamiento.',
            'logo_path' => $logo,
            'radiology_centers' => ['Cero70 Centro Radiológico Oral · Número único 448 60 70'],
        ];
    }

    /** @return array{main:string, soft:string, light:string, ink:string} */
    public static function colors(Clinic $clinic): array
    {
        $main = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $clinic->brand_color) ? strtoupper($clinic->brand_color) : self::DEFAULT_COLOR;

        return [
            'main' => $main,
            'soft' => self::mix($main, 0.45),   // franjas y encabezados de tabla
            'light' => self::mix($main, 0.18),  // curvas suaves
            'wash' => self::mix($main, 0.07),   // fondos de cajas
            'ink' => '#210727',
        ];
    }

    /** Mezcla el color con blanco: $amount = 1 es el color puro, 0 es blanco. */
    public static function mix(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $f = fn (int $c) => (int) round(255 - (255 - $c) * $amount);

        return sprintf('#%02X%02X%02X', $f($r), $f($g), $f($b));
    }

    public static function logoDataUri(Clinic $clinic): ?string
    {
        if (! $clinic->logo_path || ! Storage::disk('local')->exists($clinic->logo_path)) {
            return null;
        }
        $mime = str_ends_with(strtolower($clinic->logo_path), '.jpg') || str_ends_with(strtolower($clinic->logo_path), '.jpeg') ? 'image/jpeg' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($clinic->logo_path));
    }

    /** Curvas de arriba y abajo del documento, como SVG listo para <img src>. */
    public static function waves(Clinic $clinic): array
    {
        $c = self::colors($clinic);
        $top = '<svg xmlns="http://www.w3.org/2000/svg" width="612" height="96" viewBox="0 0 612 96">'
            .'<path d="M0 0H612V34C470 6 300 30 170 62C100 79 40 86 0 88Z" fill="'.$c['soft'].'"/>'
            .'<path d="M330 0H612V22C530 6 430 6 330 0Z" fill="'.$c['light'].'"/>'
            .'</svg>';
        $bottom = '<svg xmlns="http://www.w3.org/2000/svg" width="612" height="90" viewBox="0 0 612 90">'
            .'<path d="M0 22C120 52 260 60 380 42C470 28 550 10 612 0V90H0Z" fill="'.$c['light'].'"/>'
            .'<path d="M0 58C140 74 300 70 420 54C500 44 570 34 612 30V90H0Z" fill="'.$c['soft'].'"/>'
            .'</svg>';

        return [
            'top' => 'data:image/svg+xml;base64,'.base64_encode($top),
            'bottom' => 'data:image/svg+xml;base64,'.base64_encode($bottom),
        ];
    }

    /** Firma dibujada de la doctora (PNG en base64) o null. */
    public static function signature(Clinic $clinic): ?string
    {
        return str_starts_with((string) $clinic->signature, 'data:image/png;base64,') ? $clinic->signature : null;
    }

    /** Una firma válida es una imagen PNG pequeña dibujada en pantalla. */
    public static function isSignature(?string $value): bool
    {
        if (! is_string($value) || ! str_starts_with($value, 'data:image/png;base64,') || strlen($value) > 600_000) {
            return false;
        }
        $bin = base64_decode(substr($value, 22), true);

        return $bin !== false && str_starts_with($bin, "\x89PNG");
    }

    /**
     * Firma desde una foto del papel: quita el fondo (lo deja transparente), deja solo la tinta,
     * recorta los bordes y la reduce. Devuelve un PNG en data URI, o null si no se pudo leer.
     */
    public static function signatureFromPhoto(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! ($src = @imagecreatefromstring((string) file_get_contents($path)))) {
            return null;
        }

        // Trabaja en un tamaño razonable.
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 1800 / max($w, $h));
        $w2 = max(1, (int) round($w * $scale));
        $h2 = max(1, (int) round($h * $scale));
        $img = imagecreatetruecolor($w2, $h2);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w2, $h2, $w, $h);
        imagedestroy($src);

        // Brillo del papel alrededor de cada punto: la foto reducida a un mosaico y vuelta a ampliar.
        // Las sombras y la luz desigual son suaves y quedan en ese fondo; el trazo es fino y no.
        $tile = imagecreatetruecolor(max(2, (int) round($w2 / 40)), max(2, (int) round($h2 / 40)));
        imagecopyresampled($tile, $img, 0, 0, 0, 0, imagesx($tile), imagesy($tile), $w2, $h2);
        $bgImg = imagecreatetruecolor($w2, $h2);
        imagecopyresampled($bgImg, $tile, 0, 0, 0, 0, $w2, $h2, imagesx($tile), imagesy($tile));
        imagedestroy($tile);

        $gray = fn (int $c) => (int) (0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255));
        // $lum = cuánto más oscuro que su papel es cada punto (0 = igual al papel).
        $lum = [];
        for ($y = 0; $y < $h2; $y++) {
            for ($x = 0; $x < $w2; $x++) {
                $lum[$y * $w2 + $x] = max(0, $gray(imagecolorat($bgImg, $x, $y)) - $gray(imagecolorat($img, $x, $y)));
            }
        }
        imagedestroy($bgImg);
        $ink = 32;  // así de más oscuro: tinta sólida
        $edge = 18; // menos que esto: papel

        // Caja de la tinta.
        [$minX, $minY, $maxX, $maxY] = [$w2, $h2, -1, -1];
        foreach ($lum as $i => $l) {
            if ($l > $edge) {
                $x = $i % $w2;
                $y = intdiv($i, $w2);
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < 0 || ($maxX - $minX) < 20) {
            imagedestroy($img);

            return null; // no se encontró una firma
        }
        $pad = 8;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($w2 - 1, $maxX + $pad);
        $maxY = min($h2 - 1, $maxY + $pad);
        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;

        // Tinta oscura sobre transparente.
        $out = imagecreatetruecolor($cw, $ch);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        for ($y = 0; $y < $ch; $y++) {
            for ($x = 0; $x < $cw; $x++) {
                $l = $lum[($y + $minY) * $w2 + ($x + $minX)];
                if ($l <= $edge) {
                    continue;
                }
                $alpha = $l >= $ink ? 0 : (int) round(127 * ($ink - $l) / ($ink - $edge)); // 0 = opaco
                $c = imagecolorat($img, $x + $minX, $y + $minY);
                // Conserva el tono de la tinta (azul o negra) pero más oscuro.
                $r = (int) ((($c >> 16) & 255) * 0.6);
                $g = (int) ((($c >> 8) & 255) * 0.6);
                $b = (int) (($c & 255) * 0.6);
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $alpha));
            }
        }
        imagedestroy($img);

        // Máximo 900 x 400: nítida en el PDF y liviana.
        $s = min(1, 900 / $cw, 400 / $ch);
        if ($s < 1) {
            $fw = max(1, (int) round($cw * $s));
            $fh = max(1, (int) round($ch * $s));
            $small = imagecreatetruecolor($fw, $fh);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
            imagecopyresampled($small, $out, 0, 0, 0, 0, $fw, $fh, $cw, $ch);
            imagedestroy($out);
            $out = $small;
        }

        // Al reducirla, un esfero fino queda tenue: se refuerza la opacidad del trazo.
        $ow = imagesx($out);
        $oh = imagesy($out);
        for ($y = 0; $y < $oh; $y++) {
            for ($x = 0; $x < $ow; $x++) {
                $c = imagecolorat($out, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a < 127) {
                    $na = max(0, (int) round(127 - (127 - $a) * 2.2));
                    imagesetpixel($out, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
                }
            }
        }

        ob_start();
        imagepng($out, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($out);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
