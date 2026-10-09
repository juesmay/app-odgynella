#!/bin/bash
# Instalación por PRIMERA VEZ en el hosting. En la Terminal (SSH), desde la carpeta del proyecto:
#   bash deploy/instalar.sh
set -e
cd "$(dirname "$0")/.."
source deploy/_php.sh

if [ ! -f .env ]; then echo "Falta el archivo .env. Copia deploy/env.hostinger como .env y llena los datos."; exit 1; fi
if grep -vE "^\s*#" .env | grep -qE "<[^>]+>"; then echo "El .env todavía tiene datos por llenar (los que están entre < >)."; exit 1; fi

echo "1/6 Instalando librerías (puede tardar un par de minutos)..."
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "2/6 Llave de la aplicación..."
grep -q "^APP_KEY=base64" .env || $PHP artisan key:generate --force

echo "3/6 Creando las tablas y los usuarios..."
$PHP artisan migrate --force
$PHP artisan db:seed --force

echo "4/6 Cargando el histórico..."
if [ -f storage/app/private/importacion/historico.json ]; then $PHP artisan historico:importar --no-interaction; fi

echo "5/6 Optimizando..."
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "6/6 Permisos..."
mkdir -p storage/fonts
chmod -R ug+rw storage bootstrap/cache

echo ""
echo "Listo. COPIA las dos contraseñas temporales que salieron arriba: no se vuelven a mostrar."
