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
}
