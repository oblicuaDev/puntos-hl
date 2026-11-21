#!/usr/bin/env bash
# Paso único, antes del primer `./deploy.sh` en un VPS nuevo: emite el
# certificado real de Let's Encrypt para puntos.harkerlloreda.com.
#
# Problema que resuelve: Nginx no arranca sin un certificado en las rutas que
# pide prod.conf, pero Certbot necesita a Nginx corriendo (sirviendo el
# webroot) para validar el dominio. Solución estándar: arrancar con un
# certificado autofirmado "de mentiras" solo para que Nginx levante, pedir el
# real, y recién ahí reiniciar con el de verdad.
#
# Requiere: DNS de puntos.harkerlloreda.com ya apuntando a este VPS, y los
# puertos 80/443 abiertos y libres (no Apache/otro servicio encima).
set -euo pipefail

DOMAIN="puntos.harkerlloreda.com"
EMAIL="soporte@harkerlloreda.com" # cambia esto si corresponde otro contacto
COMPOSE="docker compose -f docker-compose.prod.yml"
LE_PATH="/etc/letsencrypt/live/$DOMAIN"

if [ -d "$LE_PATH" ]; then
    echo "Ya existe un certificado en $LE_PATH — nada que hacer. Usa 'certbot renew' para renovar."
    exit 0
fi

echo "==> generando certificado autofirmado temporal para que Nginx pueda arrancar"
sudo mkdir -p "$LE_PATH"
sudo openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout "$LE_PATH/privkey.pem" \
    -out "$LE_PATH/fullchain.pem" \
    -subj "/CN=$DOMAIN"

echo "==> levantando nginx con el certificado temporal"
$COMPOSE up -d nginx

echo "==> eliminando el certificado temporal"
sudo rm -rf "$LE_PATH"

echo "==> pidiendo el certificado real a Let's Encrypt"
docker run --rm \
    -v /etc/letsencrypt:/etc/letsencrypt \
    -v "$(pwd)/docker/certbot/www:/var/www/certbot" \
    certbot/certbot certonly \
    --webroot -w /var/www/certbot \
    -d "$DOMAIN" \
    --email "$EMAIL" --agree-tos --no-eff-email

echo "==> recargando nginx con el certificado real"
$COMPOSE exec nginx nginx -s reload

echo "==> listo. Para renovar automáticamente, agrega a crontab del VPS:"
echo '0 3 * * * docker run --rm -v /etc/letsencrypt:/etc/letsencrypt -v '"$(pwd)"'/docker/certbot/www:/var/www/certbot certbot/certbot renew --webroot -w /var/www/certbot --quiet && docker compose -f '"$(pwd)"'/docker-compose.prod.yml exec nginx nginx -s reload'
