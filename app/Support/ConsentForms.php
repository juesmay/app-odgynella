<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\Patient;

/**
 * Consentimientos de la doctora con campos para llenar en pantalla (nada a mano en papel).
 *
 * Cada formulario tiene:
 *  - title / subtitle: como en su documento original.
 *  - fields: lo que la doctora llena de ese procedimiento (dientes, diagnóstico, modalidad…).
 *  - decision: las dos opciones (acepta / no acepta) con el texto de su documento.
 *  - note: la constancia final del documento.
 * El texto (riesgos, cuidados, declaración) está en resources/consents/{clave}.md y se puede editar en Configuración.
 *
 * Todos comparten los datos de arriba (paciente, documento, historia clínica, fecha y lugar, profesional y registro)
 * y el bloque del representante (obligatorio en menores).
 *
 * Tipos de campo: text, textarea, number, radio (una opción; 'other' agrega "Otro: ___"), checks (varias).
 */
class ConsentForms
{
    private const ACCEPT = ['ACEPTO el tratamiento descrito', 'NO ACEPTO el tratamiento descrito'];

    private const AUTHORIZE = ['AUTORIZO el tratamiento descrito', 'NO AUTORIZO el tratamiento descrito'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $dx = fn (string $label = 'Diagnóstico y motivo del tratamiento') => ['key' => 'diagnostico', 'label' => $label, 'type' => 'textarea'];

        return [
            'endodoncia' => [
                'title' => 'Tratamiento de conducto (endodoncia)',
                'subtitle' => 'Tratamiento endodóntico no quirúrgico en dientes permanentes',
                'fields' => [
                    ['key' => 'dientes', 'label' => 'Diente(s) a tratar (nomenclatura FDI)', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: 36'],
                    $dx('Diagnóstico y motivo de la endodoncia'),
                ],
                'decision' => self::ACCEPT,
                'note' => 'Constancia: las condiciones particulares del paciente, hallazgos, medicamentos, incidentes, técnica y evolución se consignarán en la historia clínica. Este formulario no sustituye esas anotaciones.',
            ],
            'extraccion' => [
                'title' => 'Extracción dental',
                'subtitle' => 'Exodoncia simple o quirúrgica bajo anestesia local',
                'fields' => [
                    ['key' => 'dientes', 'label' => 'Diente(s) por extraer (nomenclatura FDI)', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: 38, 48'],
                    ['key' => 'arcada', 'label' => 'Arcada / lado', 'type' => 'text', 'placeholder' => 'Ej: inferior derecha'],
                    $dx('Diagnóstico e indicación de la extracción'),
                    ['key' => 'modalidad', 'label' => 'Modalidad prevista', 'type' => 'radio', 'required' => true,
                        'options' => ['Exodoncia simple', 'Exodoncia quirúrgica (con incisión, colgajo, osteotomía o sección dental, según necesidad clínica)']],
                ],
                'decision' => ['AUTORIZO la extracción indicada', 'NO AUTORIZO la extracción indicada'],
                'note' => null,
            ],
            'implantes' => [
                'title' => 'Colocación quirúrgica de implantes dentales',
                'subtitle' => 'Documento de información y decisión del paciente',
                'fields' => [
                    ['key' => 'zona', 'label' => 'Zona(s) / diente(s) a reemplazar', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: 36 y 46'],
                    ['key' => 'numero', 'label' => 'Número de implantes', 'type' => 'number', 'required' => true],
                    $dx('Diagnóstico e indicación según historia clínica y plan de tratamiento'),
                ],
                'decision' => ['ACEPTO voluntariamente el procedimiento descrito', 'NO ACEPTO el procedimiento descrito'],
                'note' => 'En caso de rechazo o retiro: se dejará constancia adicional en la historia clínica de las explicaciones brindadas y la decisión.',
            ],
            'periodoncia' => [
                'title' => 'Tratamiento periodontal no quirúrgico',
                'subtitle' => 'Documento para atención odontológica en Colombia',
                'fields' => [
                    ['key' => 'diagnostico', 'label' => 'Diagnóstico periodontal', 'type' => 'textarea', 'required' => true],
                    ['key' => 'zonas', 'label' => 'Zonas o dientes', 'type' => 'text', 'placeholder' => 'Ej: boca completa, por cuadrantes'],
                ],
                'decision' => ['AUTORIZO el tratamiento periodontal no quirúrgico descrito', 'NO AUTORIZO el tratamiento descrito'],
                'note' => 'En caso de negativa, se dejará constancia de la información y de la decisión en la historia clínica.',
            ],
            'protesis_completa' => [
                'title' => 'Prótesis completa removible convencional',
                'subtitle' => null,
                'fields' => [
                    ['key' => 'arcada', 'label' => 'Prótesis completa removible', 'type' => 'radio', 'required' => true, 'options' => ['Superior', 'Inferior', 'Ambas arcadas']],
                    ['key' => 'material', 'label' => 'Material protésico previsto', 'type' => 'text', 'placeholder' => 'Ej: acrílico de alto impacto'],
                    ['key' => 'tipo', 'label' => 'Tipo', 'type' => 'radio', 'required' => true, 'options' => ['Convencional', 'Inmediata (requiere información específica adicional)']],
                ],
                'decision' => self::AUTHORIZE,
                'note' => 'Nota clínica: las circunstancias y complicaciones particulares de cada persona deben informarse y registrarse en la historia clínica. Este formato no sustituye la valoración individual ni exonera al profesional de sus deberes.',
            ],
            'protesis_fija' => [
                'title' => 'Prótesis fija dentosoportada',
                'subtitle' => 'Coronas individuales y puentes fijos sobre dientes naturales',
                'fields' => [
                    ['key' => 'pilares', 'label' => 'Dientes pilares / piezas a tratar', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: 14 a 16'],
                    ['key' => 'zona', 'label' => 'Zona / arcada', 'type' => 'text'],
                    ['key' => 'tratamiento', 'label' => 'Tratamiento previsto', 'type' => 'radio', 'required' => true, 'other' => true, 'options' => ['Corona individual', 'Puente fijo']],
                    ['key' => 'material', 'label' => 'Material informado / seleccionado', 'type' => 'radio', 'required' => true, 'other' => true, 'options' => ['Metal-cerámica', 'Cerámica', 'Zirconia']],
                    $dx('Diagnóstico / motivo del tratamiento'),
                ],
                'decision' => ['ACEPTO de forma libre y voluntaria el tratamiento descrito', 'NO ACEPTO el tratamiento descrito'],
                'note' => null,
            ],
            'protesis_parcial' => [
                'title' => 'Prótesis parcial removible',
                'subtitle' => null,
                'fields' => [
                    $dx('Diagnóstico'),
                    ['key' => 'arcada', 'label' => 'Arcada', 'type' => 'radio', 'required' => true, 'options' => ['Superior', 'Inferior', 'Ambas']],
                    ['key' => 'material', 'label' => 'Material', 'type' => 'radio', 'required' => true, 'other' => true, 'options' => ['Acrílico', 'Metálico', 'Flexible']],
                    ['key' => 'dientes', 'label' => 'Dientes a reemplazar / referencia al plan de tratamiento', 'type' => 'text'],
                ],
                'decision' => ['AUTORIZO voluntariamente la prótesis parcial removible descrita', 'NO AUTORIZO el procedimiento'],
                'note' => null,
            ],
            'odontopediatria' => [
                'title' => 'Atención odontológica de niños, niñas y adolescentes',
                'subtitle' => 'Odontopediatría',
                'minor' => true,
                'fields' => [
                    ['key' => 'procedimientos', 'label' => 'Procedimientos autorizados', 'type' => 'checks', 'required' => true, 'options' => [
                        'Examen clínico y valoración', 'Profilaxis / higiene profesional', 'Detartraje supragingival', 'Aplicación tópica de flúor',
                        'Sellantes de fosas y fisuras', 'Restauración dental en resina / ionómero', 'Radiografías odontológicas clínicamente indicadas',
                        'Anestesia local asociada a los procedimientos marcados',
                    ]],
                    ['key' => 'dientes', 'label' => 'Diente(s)/zona(s) y tratamiento autorizado', 'type' => 'textarea'],
                ],
                'decision' => ['AUTORIZO los procedimientos marcados', 'NO AUTORIZO (se registra la decisión y la orientación en la historia clínica)'],
                'assent' => ['Estoy de acuerdo', 'No estoy de acuerdo', 'No puedo expresarlo / no aplica'],
                'note' => 'Constancia de explicación y participación según la historia clínica.',
            ],
            'aclaramiento' => [
                'title' => 'Aclaramiento dental',
                'subtitle' => 'Documento para dientes vitales · Aclaramiento en consultorio y/o domiciliario supervisado, según indicación profesional',
                'witness' => true,
                'fields' => [
                    ['key' => 'dientes', 'label' => 'Dientes / arcadas previstos', 'type' => 'text', 'placeholder' => 'Ej: 15 a 25 y 35 a 45'],
                    ['key' => 'modalidad', 'label' => 'Modalidad', 'type' => 'radio', 'required' => true, 'options' => ['Consultorio', 'Domiciliario supervisado', 'Combinado']],
                    ['key' => 'agente', 'label' => 'Agente y concentración indicados', 'type' => 'text', 'placeholder' => 'Ej: peróxido de hidrógeno 35%'],
                    ['key' => 'sesiones', 'label' => 'Sesiones / pauta propuesta', 'type' => 'text', 'placeholder' => 'Ej: 2 sesiones de 45 minutos'],
                ],
                'decision' => self::AUTHORIZE,
                'note' => 'En caso de retiro posterior del consentimiento, registrar fecha, alcance y explicación en la historia clínica.',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function get(?string $key): ?array
    {
        return $key ? (self::all()[$key] ?? null) : null;
    }

    /** Texto original del documento, listo para guardar como plantilla. */
    public static function body(string $key): string
    {
        return trim((string) file_get_contents(resource_path('consents/'.$key.'.md')));
    }

    /** Valores iniciales de los datos de arriba. @return array<string, string> */
    public static function defaults(Patient $patient, Clinic $clinic, ?string $place): array
    {
        $age = $patient->age();

        return [
            'hc' => (string) ($patient->doc_number ?? ''),
            'fecha_lugar' => trim(($place ? $place.', ' : '').now()->translatedFormat('j \d\e F \d\e Y')),
            'profesional' => (string) $clinic->doctor_name,
            'registro' => (string) ($clinic->doctor_license ?? ''),
            'edad' => $age !== null ? $age.' años' : '',
            'menor' => (string) $patient->name,
        ];
    }

    /**
     * Texto con formato simple a bloques: "## " título, "- " viñeta, línea en blanco separa párrafos.
     * "{{datos}}" marca dónde van los datos llenados (si no está, van arriba).
     *
     * @return list<array{0: string, 1: string|list<string>}>
     */
    public static function blocks(string $text): array
    {
        $blocks = [];
        foreach (preg_split('/\R{2,}/', trim($text)) as $chunk) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $chunk)), fn ($l) => $l !== ''));
            if (! $lines) {
                continue;
            }
            if ($lines[0] === '{{datos}}') {
                $blocks[] = ['datos', ''];
            } elseif (str_starts_with($lines[0], '## ')) {
                $blocks[] = ['h', substr($lines[0], 3)];
                if (count($lines) > 1) {
                    $blocks[] = ['p', implode(' ', array_slice($lines, 1))];
                }
            } elseif (str_starts_with($lines[0], '- ')) {
                $blocks[] = ['ul', array_map(fn ($l) => ltrim(substr($l, 1)), $lines)];
            } else {
                $blocks[] = ['p', implode("\n", $lines)];
            }
        }

        // Viñetas separadas por líneas en blanco quedan en una sola lista.
        $merged = [];
        foreach ($blocks as $b) {
            $last = count($merged) - 1;
            if ($b[0] === 'ul' && $last >= 0 && $merged[$last][0] === 'ul') {
                $merged[$last][1] = array_merge($merged[$last][1], $b[1]);
            } else {
                $merged[] = $b;
            }
        }

        return $merged;
    }
}
