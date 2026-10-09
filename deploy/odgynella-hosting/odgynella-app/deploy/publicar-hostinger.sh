#!/bin/bash
# Hace que el subdominio app-odgynella.mubena.com muestre el sistema (carpeta public del proyecto).
#   bash deploy/publicar-hostinger.sh
set -e
APP="$(cd "$(dirname "$0")/.." && pwd)"
WEB="$HOME/domains/mubena.com/public_html/app-odgynella"

if [ -L "$WEB" ]; then
  echo "El subdominio ya apunta a: $(readlink "$WEB")"; exit 0
fi
if [ -f "$WEB/wp-config.php" ]; then
  echo "ALTO: $WEB tiene un WordPress. No toco nada."; exit 1
fi
if [ -d "$WEB" ]; then
  mv "$WEB" "$WEB.original-$(date +%Y%m%d%H%M)"
fi
ln -s "$APP/public" "$WEB"
echo "Listo: https://app-odgynella.mubena.com ahora muestra el sistema."
