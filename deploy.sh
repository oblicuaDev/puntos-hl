#!/usr/bin/env bash
# Deploy de "Plan de Fidelización" en el VPS. Correr desde la raíz del
# proyecto ya clonado (ver docs/08-despliegue para el checklist completo).
set -euo pipefail

COMPOSE="docker compose -f docker-compose.prod.yml"

if [ ! -f .env ]; then
    echo "Falta .env — copia .env.production.example a .env y complétalo antes de desplegar." >&2
    exit 1
fi

echo "==> git pull"
git pull --ff-only

echo "==> build de imágenes"
$COMPOSE build

echo "==> levantando contenedores"
$COMPOSE up -d

echo "==> instalando dependencias"
$COMPOSE exec -T app composer install --no-dev --no-interaction --optimize-autoloader
$COMPOSE exec -T app npm ci
$COMPOSE exec -T app npm run build

echo "==> migraciones"
$COMPOSE exec -T app php artisan migrate --force

echo "==> cacheando config/rutas/vistas/eventos"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache
$COMPOSE exec -T app php artisan event:cache

echo "==> storage:link"
$COMPOSE exec -T app php artisan storage:link || true

echo "==> reiniciando app/queue/scheduler (refresca opcache y recarga el código)"
$COMPOSE restart app queue scheduler

echo "==> listo. Verificando salud básica:"
$COMPOSE exec -T app php artisan about --only=environment
