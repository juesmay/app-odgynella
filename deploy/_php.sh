# Busca un PHP 8.3 o más. En Hostinger la terminal a veces usa otra versión que la web.
ok() { "$1" -r 'exit(version_compare(PHP_VERSION, "8.3.0") < 0 ? 1 : 0);' 2>/dev/null; }
PHP=""
for c in php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /usr/bin/php8.4 /usr/bin/php8.3; do
  if command -v "$c" >/dev/null 2>&1 && ok "$c"; then PHP="$c"; break; fi
done
if [ -z "$PHP" ]; then
  echo "No encontré PHP 8.3 o superior en la terminal."
  echo "En hPanel > Avanzado > Configuración de PHP (sitio mubena.com) elige 8.3, cierra y abre la Terminal y vuelve a correr esto."
  exit 1
fi
COMPOSER_BIN="$(command -v composer2 || command -v composer)"
if [ -z "$COMPOSER_BIN" ]; then echo "No encontré composer en el servidor."; exit 1; fi
COMPOSER="$PHP $COMPOSER_BIN"
echo "Usando $($PHP -r 'echo "PHP ".PHP_VERSION;')"
