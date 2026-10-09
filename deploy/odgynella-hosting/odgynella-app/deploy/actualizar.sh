#!/bin/bash
# Para subir una versión NUEVA del sistema (después de reemplazar los archivos). No borra datos.
#   bash deploy/actualizar.sh
set -e
cd "$(dirname "$0")/.."
source deploy/_php.sh
$PHP artisan down --retry=30 || true
$COMPOSER install --no-dev --optimize-autoloader --no-interaction
$PHP artisan migrate --force
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan up
echo "Actualizado."
