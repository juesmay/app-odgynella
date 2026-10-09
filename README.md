# Odgynella Clínica

Sistema odontológico para la Dra. Gynella Medina, pensado para crecer como producto para odontólogos independientes. Hecho en Laravel 13, sin pasos de compilación de JavaScript ni CSS.

## Fase 1 (esta versión)

- **Cotizaciones desde la asesoría virtual**: la doctora crea al prospecto solo con nombre y WhatsApp, va agregando tratamientos del catálogo (con dientes, cantidad, descuento o promoción) y descarga el **PDF** con vigencia de 30 días y formas de pago. Lo que contó la persona queda en notas internas que no salen en el PDF.
- **Etapas de la persona**: en cotización → valoración agendada → en tratamiento → terminado, o “no continuó” con su motivo. Al agendar la valoración presencial el sistema pide el documento y la autorización de datos que faltan y cambia la etapa solo.
- **Embudo** (solo la doctora): de las personas cotizadas en 90 días, cuántas agendaron y cuántas aceptaron, por origen (Facebook, Instagram, Google…).
- Entrada con correo y contraseña, con dos roles: **doctora** y **asistente**.
- Base para varios consultorios: cada registro pertenece a una clínica y nadie ve datos de otra.
- **Sedes** (Envigado, Sabaneta, San Gil, Bucaramanga) y en qué sede atiende la doctora cada día.
- **Servicios y paquetes** con duración y precio (solo la doctora los cambia).
- **Pacientes**: el WhatsApp es la llave única; autorización de datos (Ley 1581); antecedentes médicos que se vuelven alertas en rojo.
- **Agenda** por día y por semana. No deja cruzar citas, ni siquiera entre sedes. Estados: agendada, confirmada, en sala, atendida, no vino, cancelada.
- **Caja** por sede: apertura con base, cierre con conteo y diferencia.
- **Pagos** con recibo de numeración consecutiva y mensaje listo para WhatsApp. Los pagos no se borran: la doctora los anula con motivo mientras la caja siga abierta.

Las fases 2 y 3 están más abajo. Sigue la fase 4 (GoHighLevel, factura electrónica y RIPS).

## Instalar en tu computador (Windows con Laragon)

Requisitos: PHP 8.3 o superior y Composer. Laragon los trae.

Abre la terminal de Laragon (botón **Terminal**) y ejecuta, uno por uno:

```bash
cd C:\laragon\www\odgynella
composer run setup
php artisan serve
```

`composer run setup` descarga las librerías, crea el archivo `.env`, la base de datos SQLite, las tablas y los datos de arranque. Se corre una sola vez.

Abre http://localhost:8000 y entra con:

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Doctora | doctora@odgynella.test | Cambiar.2026 |
| Asistente | asistente@odgynella.test | Cambiar.2026 |

Cambia estas contraseñas antes de usar el sistema con pacientes reales.

### Datos de ejemplo (opcional)

Para recorrer todo con 15 pacientes de ejemplo, dos de ellos niños (citas de hoy, cotizaciones en todos los estados, historia clínica, odontograma, evoluciones, fotos, consentimiento, órdenes de radiografía, pagos, cartera de 0 a 90+ días, gastos de este mes y del anterior, y cierres de caja):

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

El primer comando **borra todo** y deja el sistema como nuevo. Antes de empezar con pacientes reales, corre otra vez solo `php artisan migrate:fresh --seed` para quitar los ejemplos (así los recibos y cotizaciones arrancan en 00001).

### Pruebas automáticas

```bash
php artisan test
```

## Fase 2: historia clínica

- **Antecedentes**: preguntas de sí o no, enfermedades infecciosas (VIH, hepatitis B y C, tuberculosis, sífilis: solo la doctora ve cuál; la asistente ve un aviso genérico), paciente oncológico (actual o previo, fecha de fin y tratamiento recibido), antecedentes familiares y hereditarios.
- **Examen clínico y diagnósticos CIE-10** con buscador.
- **Odontograma por caras**: varios hallazgos por diente, en qué caras (O, M, D, V, L) y una observación por diente.
- **Odontograma pediátrico**: dentición temporal (51 a 85), mixta (permanentes con los temporales en medio) o permanente. Se elige sola por la edad y la doctora la puede cambiar. Hallazgos de niños (mancha blanca, pulpotomía, corona de acero, mantenedor de espacio, movilidad, en erupción, sin erupcionar) e índices COP-D y ceo-d.
- **Evoluciones** con campos separados (valoración, sesión de tratamiento, control). Al cambiar de tipo no se pierde lo escrito y el borrador se guarda solo. La nota firmada no se edita ni se borra. Marca los procedimientos de la cotización como realizados.
- **Consentimientos** con firma en pantalla de la paciente y la doctora, y PDF. Los textos se editan en Configuración.
- **Fotos** antes y después, privadas, con comparación lado a lado.

## Fase 3: los números

- **Cuenta del paciente** (pestaña “Cuenta” en la ficha): el pago se liga al tratamiento aceptado y el sistema calcula solo el total del plan, lo realizado (con el descuento repartido), lo pagado, el saldo y si debe algo de lo ya hecho o tiene anticipo. No deja cobrar más que el saldo.
- **Cartera**: quién debe de lo ya realizado, cuánto y desde cuándo (al día, 30, 60 y 90+ días desde el último abono), con recordatorio listo para copiar o abrir en WhatsApp. Lo que falta por hacer del tratamiento no es deuda.
- **Gastos** por categoría y sede, con foto de la factura opcional. Si se pagan con el efectivo de la caja, se descuentan del cuadre. La asistente registra gastos y solo ve los suyos (nunca la nómina). Solo la doctora anula, y lo anulado queda en el historial.
- **Reportes** (solo la doctora): lo que entró, salió y quedó en el mes, comparado con el mes anterior; cartera; producción; % de cotizaciones aceptadas; personas nuevas y de dónde llegaron; inasistencias; resultados por sede; gastos por categoría; medios de pago y cierres de caja.
- **Para el contador**: descarga de un archivo que abre en Excel con todos los recibos (también los anulados, para que la numeración quede completa) y todos los gastos del periodo que elijas.

## Documentos con la marca de la doctora

- **Cotización** con logo, curvas y colores de la marca, título grande, datos de contacto, número, fecha, vigencia y total. Los ítems con **valor según el caso** (ej. endodoncia uniradicular, biradicular o multiradicular) muestran sus opciones y no se suman al total.
- **Orden de radiografía** (pestaña “Órdenes” de la ficha): estudios con diente o zona y cantidad, centro radiológico, indicación clínica, entrega de resultados y firma de la doctora con su registro profesional. Solo la doctora la crea; todo el equipo la descarga.
- **Consentimientos** en PDF con el mismo diseño.
- En **Configuración → Datos de la doctora** se cambian el logo, el color, el contacto, el registro profesional, la firma, el texto de facilidad de pago y la lista de centros radiológicos.
- Fuentes: Poppins y Bebas Neue (licencia libre OFL), en `resources/fonts`.

## Si ya tenías instalada la versión anterior

En la terminal de Laragon, dentro de la carpeta del proyecto:

```bash
composer require dompdf/dompdf
php artisan migrate
```

El primero instala el generador de PDF (si ya lo tienes, no pasa nada) y el segundo agrega las tablas nuevas sin borrar nada.

## Arrancar con la información histórica

Para empezar a usar el sistema desde hoy sin perder lo anterior (libro de control de junio a agosto):

```bash
php artisan migrate:fresh --seed
php artisan historico:importar
```

El primero deja el sistema limpio (borra los datos de ejemplo). El segundo lee `storage/app/private/importacion/historico.json` y crea los pacientes, tratamientos, pagos y gastos marcados como **histórico**:

- Se ven en la ficha y en la cuenta de cada paciente, y los saldos pendientes salen en **Cartera** (se pueden cobrar normalmente desde hoy).
- **No** entran a Reportes, caja ni al archivo del contador, y no gastan números de recibo (RC) ni de cotización (COT): usan su código del libro (HIST-P001, HIST-PAY001).
- Los pacientes quedan sin WhatsApp. Al abrir la ficha aparece el aviso para completarlo.
- Solo se puede importar una vez.

El archivo `historico.json` tiene datos reales de pacientes: está dentro de `storage/app/private`, que no se sube a git.

## Publicar en Hostinger

En `deploy/` están la plantilla de configuración (`env.hostinger`) y los scripts `instalar.sh` (primera vez) y `actualizar.sh` (versiones nuevas, no borra datos). En el servidor, desde la carpeta del proyecto:

```bash
cp deploy/env.hostinger .env   # y llenar los datos entre < >
bash deploy/instalar.sh        # muestra las contraseñas temporales una sola vez
```

El subdominio debe apuntar a la carpeta `public` del proyecto, nunca a la raíz. Cada usuario cambia su contraseña en **Mi cuenta**; la doctora crea usuarios, da contraseñas temporales y desactiva gente desde ahí mismo.

## Antes de usarlo en serio

1. En **Configuración**, revisa precios y duraciones (salen del portafolio de la doctora como punto de partida), pon precio a los paquetes LITE, PRO y GOLD y actívalos, y define qué sede atiende cada día.
2. Cambia las contraseñas de los dos usuarios.
3. En **Configuración → Datos de la doctora**, escribe el registro profesional y dibuja la firma.
4. En **Configuración → Cotizaciones** puedes cambiar la vigencia y las formas de pago que salen en el PDF.
5. Para producción se recomienda MySQL en lugar de SQLite (se cambia en el archivo `.env`).
