<?php

namespace App\Support;

use App\Models\Clinic;

/**
 * Convierte el HTML de los documentos en PDF con Dompdf (composer require dompdf/dompdf).
 * Usa las fuentes de la marca (Poppins y Bebas Neue, en resources/fonts) y DejaVu Sans de respaldo.
 */
class QuotePdf
{
    private const FONTS = [
        ['Poppins', 'normal', 'Poppins-Regular.ttf'],
        ['Poppins', 'bold', 'Poppins-Bold.ttf'],
        ['Poppins SemiBold', 'normal', 'Poppins-SemiBold.ttf'],
        ['Bebas Neue', 'normal', 'BebasNeue-Regular.ttf'],
    ];

    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public static function render(string $html): string
    {
        $cache = storage_path('fonts');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        $dompdf = new \Dompdf\Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'fontDir' => $cache,
            'fontCache' => $cache,
            'chroot' => [base_path()],
        ]);

        $metrics = $dompdf->getFontMetrics();
        foreach (self::FONTS as [$family, $weight, $file]) {
            $path = resource_path('fonts/'.$file);
            if (is_file($path)) {
                $metrics->registerFont(['family' => $family, 'weight' => $weight, 'style' => 'normal'], $path);
            }
        }

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return $dompdf->output();
    }

    /** Variables de marca para las vistas PDF. */
    public static function brand(Clinic $clinic): array
    {
        return [
            'brand' => Brand::colors($clinic),
            'logo' => Brand::logoDataUri($clinic),
            'waves' => Brand::waves($clinic),
            'signature' => Brand::signature($clinic),
        ];
    }
}
