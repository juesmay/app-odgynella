<?php

namespace App\Support;

/**
 * Plantillas de consentimiento con las que arranca un consultorio. Son una base:
 * la doctora reemplaza los textos entre corchetes por los suyos en Configuración.
 * Marcadores disponibles: {paciente}, {documento}, {doctora}, {fecha}.
 */
class DefaultConsents
{
    /** @return list<array{0:string,1:string}> */
    public static function all(): array
    {
        $clinical = fn (string $procedure) => "Yo, {paciente}, identificada con {documento}, declaro que la {doctora} me explicó en un lenguaje claro en qué consiste el procedimiento de {$procedure}, sus beneficios, sus riesgos, las alternativas de tratamiento y los cuidados que debo tener.\n\n"
            ."[Escriba aquí los riesgos y complicaciones propios de este procedimiento.]\n\n"
            ."[Escriba aquí los cuidados y recomendaciones posteriores.]\n\n"
            ."Entiendo que los resultados pueden variar según las condiciones de cada persona y que debo asistir a los controles indicados. Tuve la oportunidad de hacer preguntas y fueron respondidas. Autorizo de manera libre y voluntaria la realización del procedimiento.\n\n"
            .'Fecha: {fecha}.';

        return [
            ['Diseño de sonrisa (carillas)', $clinical('diseño de sonrisa con carillas')],
            ['Aclaramiento dental', $clinical('aclaramiento dental')],
            ['Prótesis', $clinical('rehabilitación con prótesis')],
            ['Cirugía oral', $clinical('cirugía oral')],
            ['Uso de imagen en redes sociales', "Yo, {paciente}, identificada con {documento}, autorizo a la {doctora} a usar las fotografías y videos de mi tratamiento en sus redes sociales, página web y material publicitario, sin que se muestre mi nombre completo.\n\nEntiendo que esta autorización es voluntaria, que no recibo pago por ella y que puedo retirarla en cualquier momento informándolo por escrito.\n\nFecha: {fecha}."],
        ];
    }
}
