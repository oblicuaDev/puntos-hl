#!/usr/bin/env bash
# Ejecuta wp-cli (imagen oficial wordpress:cli) contra el WordPress de
# desarrollo de docker-compose, con los mismos volúmenes/bind mounts que el
# servicio "wordpress" (ver docker-compose.yml). Uso:
#
#   ./docker/wp-cli.sh plugin list
#   ./docker/wp-cli.sh role list
#   ./docker/wp-cli.sh user update admin_xxx --user_pass=nueva-clave
#
set -euo pipefail

# En Git Bash (Windows), docker run reinterpreta las rutas tipo /var/www/html
# como rutas de Windows (MSYS path conversion) y corrompe los bind mounts del
# contenedor — con eso roto, wp-cli no ve ningún plugin/tema aunque sí estén
# en el volumen. No tiene efecto en Linux/macOS.
export MSYS_NO_PATHCONV=1

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

exec docker run --rm --network hl-fidelizacion_hl \
  -v hl-fidelizacion_wp_data:/var/www/html \
  -v "${ROOT_DIR}/wordpress-dev/wp-content/themes/HarkerLloredaTheme:/var/www/html/wp-content/themes/HarkerLloredaTheme" \
  -v "${ROOT_DIR}/wordpress-dev/wp-content/plugins:/var/www/html/wp-content/plugins" \
  -v "${ROOT_DIR}/wordpress-plugin/hl-puntos-sso-bridge:/var/www/html/wp-content/plugins/hl-puntos-sso-bridge" \
  -e WORDPRESS_DB_HOST=mysql-wp \
  -e WORDPRESS_DB_NAME=wordpress \
  -e WORDPRESS_DB_USER=wordpress \
  -e WORDPRESS_DB_PASSWORD=secret \
  -e WORDPRESS_TABLE_PREFIX=VcgIheUb_ \
  wordpress:cli wp --allow-root "$@"
