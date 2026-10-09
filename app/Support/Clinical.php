<?php

namespace App\Support;

/** Catálogos clínicos: antecedentes, odontograma, evoluciones y CIE-10 odontológico. */
class Clinical
{
    public const INFECTIOUS = ['VIH/SIDA', 'Hepatitis B', 'Hepatitis C', 'Tuberculosis', 'Sífilis'];

    public const ONCOLOGY_STATUS = [
        'no' => 'No',
        'actual' => 'Sí, en tratamiento actualmente',
        'previo' => 'Lo fue (ya terminó el tratamiento)',
    ];

    public const ONCOLOGY_TREATMENTS = [
        'quimioterapia' => 'Quimioterapia',
        'radio_cabeza_cuello' => 'Radioterapia en cabeza o cuello',
        'radio_otra' => 'Radioterapia en otra zona',
        'bifosfonatos' => 'Bifosfonatos u otros medicamentos para los huesos',
        'cirugia' => 'Cirugía',
    ];

    public const FAMILY_CONDITIONS = ['Diabetes', 'Hipertensión', 'Cáncer', 'Enfermedades del corazón'];

    public const EXAM_FIELDS = [
        'blandos' => 'Tejidos blandos',
        'periodontal' => 'Estado periodontal',
        'oclusion' => 'Oclusión',
        'atm' => 'ATM',
        'higiene' => 'Higiene oral',
        'otros' => 'Otros hallazgos',
    ];

    /** Caras del diente. */
    public const SURFACES = [
        'O' => 'Oclusal / incisal',
        'M' => 'Mesial',
        'D' => 'Distal',
        'V' => 'Vestibular',
        'L' => 'Lingual / palatino',
    ];

    /**
     * Hallazgos del odontograma. 'faces' = se marca por caras; si no, aplica al diente completo.
     * 'abbr' es la sigla que se dibuja bajo el diente.
     */
    public const FINDINGS = [
        'caries' => ['name' => 'Caries', 'faces' => true, 'abbr' => 'C'],
        'obturacion' => ['name' => 'Obturación / resina', 'faces' => true, 'abbr' => 'O'],
        'fractura' => ['name' => 'Fractura', 'faces' => true, 'abbr' => 'F'],
        'sellante' => ['name' => 'Sellante', 'faces' => true, 'abbr' => 'S'],
        'endodoncia' => ['name' => 'Endodoncia', 'faces' => false, 'abbr' => 'E'],
        'corona' => ['name' => 'Corona', 'faces' => false, 'abbr' => 'Co'],
        'carilla' => ['name' => 'Carilla', 'faces' => false, 'abbr' => 'Ca'],
        'implante' => ['name' => 'Implante', 'faces' => false, 'abbr' => 'I'],
        'protesis' => ['name' => 'Prótesis', 'faces' => false, 'abbr' => 'P'],
        'extraccion' => ['name' => 'Extracción indicada', 'faces' => false, 'abbr' => 'Ex'],
        'ausente' => ['name' => 'Ausente', 'faces' => false, 'abbr' => '×'],
        // Odontopediatría (se muestran en dentición temporal o mixta).
        'mancha_blanca' => ['name' => 'Mancha blanca (caries inicial)', 'faces' => true, 'abbr' => 'Mb', 'kids' => true],
        'pulpotomia' => ['name' => 'Pulpotomía / pulpectomía', 'faces' => false, 'abbr' => 'Pt', 'kids' => true],
        'corona_acero' => ['name' => 'Corona de acero', 'faces' => false, 'abbr' => 'CA', 'kids' => true],
        'mantenedor' => ['name' => 'Mantenedor de espacio', 'faces' => false, 'abbr' => 'Me', 'kids' => true],
        'movilidad' => ['name' => 'Movilidad / próximo a exfoliar', 'faces' => false, 'abbr' => 'Mv', 'kids' => true],
        'erupcion' => ['name' => 'En erupción', 'faces' => false, 'abbr' => 'Er', 'kids' => true],
        'sin_erupcionar' => ['name' => 'Sin erupcionar', 'faces' => false, 'abbr' => 'NE', 'kids' => true],
    ];

    /** Dientes permanentes en numeración FDI, como se ven de frente: [superior, inferior]. */
    public const ARCHES = [
        [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28]],
        [[48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]],
    ];

    /** Dientes temporales (de leche), numeración FDI 51 a 85. */
    public const PRIMARY_ARCHES = [
        [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65]],
        [[85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
    ];

    public const DENTITIONS = [
        'permanente' => 'Permanente',
        'mixta' => 'Mixta',
        'temporal' => 'Temporal',
    ];

    /** Hallazgos que no aplican a un diente temporal. */
    public const ADULT_ONLY = ['implante', 'protesis', 'carilla'];

    public const EVOLUTION_FIELDS = [
        'motivo' => 'Motivo de consulta',
        'hallazgos' => 'Hallazgos',
        'diagnostico' => 'Diagnóstico',
        'plan' => 'Plan propuesto',
        'procedimiento' => 'Procedimiento realizado',
        'anestesia' => 'Anestesia',
        'evolucion' => 'Evolución',
        'indicaciones' => 'Indicaciones',
        'proxima' => 'Próxima cita',
    ];

    /** Qué campos lleva cada tipo de nota. Los campos que se repiten conservan lo escrito al cambiar de tipo. */
    public const EVOLUTION_TYPES = [
        'valoracion' => ['name' => 'Valoración', 'fields' => ['motivo', 'hallazgos', 'diagnostico', 'plan']],
        'sesion' => ['name' => 'Sesión de tratamiento', 'fields' => ['procedimiento', 'anestesia', 'evolucion', 'indicaciones', 'proxima']],
        'control' => ['name' => 'Control', 'fields' => ['evolucion', 'indicaciones', 'proxima']],
    ];

    public const PHOTO_STAGES = [
        'antes' => 'Antes',
        'durante' => 'Durante',
        'despues' => 'Después',
        'control' => 'Control',
    ];

    /** Diagnósticos CIE-10 frecuentes en odontología. Se puede escribir uno distinto a mano. */
    public const CIE10 = [
        'K00.0' => 'Anodoncia',
        'K00.1' => 'Dientes supernumerarios',
        'K00.4' => 'Alteraciones en la formación dentaria',
        'K00.6' => 'Alteraciones en la erupción dentaria',
        'K01.0' => 'Dientes incluidos',
        'K01.1' => 'Dientes impactados',
        'K02.0' => 'Caries limitada al esmalte',
        'K02.1' => 'Caries de la dentina',
        'K02.2' => 'Caries del cemento',
        'K02.3' => 'Caries dentaria detenida',
        'K02.9' => 'Caries dental, no especificada',
        'K03.0' => 'Atrición excesiva de los dientes',
        'K03.1' => 'Abrasión de los dientes',
        'K03.2' => 'Erosión de los dientes',
        'K03.6' => 'Depósitos [acreciones] en los dientes',
        'K03.7' => 'Cambios posteruptivos del color de los tejidos dentales duros',
        'K04.0' => 'Pulpitis',
        'K04.1' => 'Necrosis de la pulpa',
        'K04.4' => 'Periodontitis apical aguda originada en la pulpa',
        'K04.5' => 'Periodontitis apical crónica',
        'K04.6' => 'Absceso periapical con fístula',
        'K04.7' => 'Absceso periapical sin fístula',
        'K05.0' => 'Gingivitis aguda',
        'K05.1' => 'Gingivitis crónica',
        'K05.2' => 'Periodontitis aguda',
        'K05.3' => 'Periodontitis crónica',
        'K06.0' => 'Retracción gingival',
        'K06.1' => 'Hiperplasia gingival',
        'K07.2' => 'Anomalías de la relación entre los arcos dentarios',
        'K07.3' => 'Anomalías de la posición del diente',
        'K07.4' => 'Maloclusión de tipo no especificado',
        'K07.6' => 'Trastornos de la articulación temporomaxilar',
        'K08.1' => 'Pérdida de dientes debida a accidente, extracción o enfermedad periodontal local',
        'K08.3' => 'Raíz dental retenida',
        'K08.8' => 'Otras afecciones especificadas de los dientes y de sus estructuras de sostén',
        'K12.0' => 'Estomatitis aftosa recurrente',
        'K13.0' => 'Enfermedades de los labios',
        'S02.5' => 'Fractura de los dientes',
        'Z01.2' => 'Examen odontológico',
        'Z46.3' => 'Prueba y ajuste de prótesis dental',
        'Z96.5' => 'Presencia de implantes de raíz de diente y de mandíbula',
    ];

    /** Cuadrantes 1, 4, 5 y 8 se dibujan a la izquierda: su cara mesial queda hacia el centro (derecha). */
    public static function mesialOnRight(int $tooth): bool
    {
        return in_array(intdiv($tooth, 10), [1, 4, 5, 8], true);
    }

    public static function isUpper(int $tooth): bool
    {
        return in_array(intdiv($tooth, 10), [1, 2, 5, 6], true);
    }

    public static function isPrimary(int $tooth): bool
    {
        return intdiv($tooth, 10) >= 5;
    }

    /** @return list<int> */
    public static function allTeeth(): array
    {
        return array_merge(...array_merge(...self::ARCHES), ...array_merge(...self::PRIMARY_ARCHES));
    }

    /** Dentición sugerida por edad: temporal hasta los 5 años, mixta de 6 a 12, permanente desde los 13. */
    public static function dentitionForAge(?int $age): string
    {
        return match (true) {
            $age === null => 'permanente',
            $age <= 5 => 'temporal',
            $age <= 12 => 'mixta',
            default => 'permanente',
        };
    }

    /**
     * Filas del dibujo según la dentición. En mixta, los temporales van entre los permanentes,
     * como en las fichas impresas.
     *
     * @return list<array{label: ?string, upper: bool, primary: bool, quads: array}>
     */
    public static function chart(string $dentition): array
    {
        $perm = fn (int $i) => ['label' => $dentition === 'mixta' ? 'Permanentes' : null, 'upper' => $i === 0, 'primary' => false, 'quads' => self::ARCHES[$i]];
        $prim = fn (int $i) => ['label' => $dentition === 'mixta' ? 'Temporales' : null, 'upper' => $i === 0, 'primary' => true, 'quads' => self::PRIMARY_ARCHES[$i]];

        return match ($dentition) {
            'temporal' => [$prim(0), $prim(1)],
            'mixta' => [$perm(0), $prim(0), $prim(1), $perm(1)],
            default => [$perm(0), $perm(1)],
        };
    }

    /**
     * Índices de caries: COP-D (permanentes: cariados, obturados, perdidos) y
     * ceo-d (temporales: cariados, extracción indicada, obturados). Cuenta dientes, no caras.
     *
     * @param  iterable<\App\Models\PatientTooth>  $teeth
     * @return array{cop: array{c:int,o:int,p:int,total:int}, ceo: array{c:int,e:int,o:int,total:int}}
     */
    public static function cariesIndex(iterable $teeth): array
    {
        $cop = ['c' => 0, 'o' => 0, 'p' => 0];
        $ceo = ['c' => 0, 'e' => 0, 'o' => 0];

        foreach ($teeth as $t) {
            $has = fn (string ...$types) => collect($t->findings ?? [])->contains(fn ($f) => in_array($f['type'], $types, true));
            if (self::isPrimary($t->tooth)) {
                if ($has('caries')) {
                    $ceo['c']++;
                } elseif ($has('extraccion')) {
                    $ceo['e']++;
                } elseif ($has('obturacion', 'corona_acero', 'pulpotomia')) {
                    $ceo['o']++;
                }
            } else {
                if ($has('caries')) {
                    $cop['c']++;
                } elseif ($has('ausente', 'extraccion')) {
                    $cop['p']++;
                } elseif ($has('obturacion', 'corona', 'endodoncia')) {
                    $cop['o']++;
                }
            }
        }

        return [
            'cop' => $cop + ['total' => array_sum($cop)],
            'ceo' => $ceo + ['total' => array_sum($ceo)],
        ];
    }

    /** "caries O-D" a partir de un hallazgo guardado. */
    public static function describeFinding(array $f): string
    {
        $name = self::FINDINGS[$f['type']]['name'] ?? $f['type'];
        $faces = $f['surfaces'] ?? [];

        return $faces ? $name.' '.implode('-', $faces) : $name;
    }
}
