# Plan de Fidelización — Harker Lloreda

Plataforma de puntos/fidelización para pacientes de Harker Lloreda. Monolito Laravel con dos superficies:

- **Superadmin** (`/admin`) — catálogo de procedimientos y premios, pacientes, ventas, configuración general, banners, T&C. Login vía SSO con el WordPress headless de Harker Lloreda (`webadmin.harkerlloreda.com`).
- **Portal de pacientes** (`/`) — catálogo de premios, saldo de puntos, historial, checkout (100% puntos o mixto puntos+dinero). Cuentas propias, independientes de WordPress y de Oreka.

Ver el plan de implementación completo en `.claude/` (histórico de la sesión) para el contexto de arquitectura y las decisiones tomadas.

## Requisitos

- Docker + Docker Compose (todo el desarrollo corre en contenedores, no se necesita PHP/MySQL instalados en el host).

## Puesta en marcha (desarrollo)

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app npm run build        # o: docker compose exec app npm run dev
docker compose exec app php artisan key:generate   # solo si .env no trae ya una APP_KEY
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

Servicios expuestos:

| Servicio | URL |
|---|---|
| App (Laravel vía Nginx) | http://localhost:8080 |
| Portal de pacientes | http://localhost:8080/ |
| Superadmin | http://localhost:8080/admin |
| WordPress de prueba (para el plugin SSO) | http://localhost:8081 |
| Mailpit (correos capturados en dev) | http://localhost:8025 |
| MySQL | localhost:33061 |

## Tests

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint        # formateo de código
```

La suite usa SQLite en memoria (ver `phpunit.xml`), así que corre rápido y no toca la base de datos de desarrollo en MySQL.

## WordPress de desarrollo: copia real de producción

El WordPress del docker-compose (`http://localhost:8081`) **no es un WordPress genérico**: se restaura desde un backup real de `webadmin.harkerlloreda.com` (tema, plugins y base de datos reales), para desarrollar/probar el plugin SSO contra roles y datos reales en vez de un sitio vacío. Esto nunca toca producción.

### Restaurar un backup (primera vez, o para refrescar con datos más recientes)

Dado un backup como el de `D:\NAS_DATA\Code\Clientes\HyL\BU-YYYYMMDD\` (tema `.zip`, `plugins.zip`, dump `.sql`):

```bash
# 1. Extraer tema y plugins dentro del proyecto (gitignored, ver wordpress-dev/)
mkdir -p wordpress-dev/wp-content/themes wordpress-dev/wp-content/plugins wordpress-dev/db
unzip -q ".../HarkerLloredaTheme.zip" -d wordpress-dev/wp-content/themes
unzip -q ".../plugins.zip" -d wordpress-dev/wp-content
cp ".../carlos_hl_sbvo0.sql" wordpress-dev/db/

# 2. Reset completo de los contenedores de WP (son desechables)
docker compose stop wordpress mysql-wp
docker compose rm -f wordpress mysql-wp
docker volume rm hl-fidelizacion_wp_data hl-fidelizacion_mysql_wp_data

# 3. Levantar la base de datos e importar el dump real
docker compose up -d mysql-wp
docker compose exec -T mysql-wp mysql -uroot -psecret wordpress < wordpress-dev/db/carlos_hl_sbvo0.sql

# 4. Levantar WordPress (usa WORDPRESS_TABLE_PREFIX del dump — ver docker-compose.yml)
docker compose up -d wordpress

# 5. Apuntar el sitio a localhost en vez del dominio real
./docker/wp-cli.sh search-replace "https://webadmin.harkerlloreda.com" "http://localhost:8081" --all-tables --report-changed-only

# 6. Resetear la contraseña de un usuario real para poder entrar en local
#    (el dump trae los hashes reales de producción, inútiles en local)
./docker/wp-cli.sh user list --fields=ID,user_login,user_email,roles
./docker/wp-cli.sh user update <user_login> --user_pass=dev-local-only-2026

# 7. Activar nuestro plugin SSO y sincronizar su secreto/URL con el .env de Laravel
./docker/wp-cli.sh plugin activate hl-puntos-sso-bridge
./docker/wp-cli.sh option update hlfid_sso_shared_secret "<mismo valor que WP_SSO_SHARED_SECRET en .env>"
./docker/wp-cli.sh option update hlfid_sso_app_url "http://localhost:8080"
```

`./docker/wp-cli.sh` corre wp-cli (imagen oficial `wordpress:cli`) contra los mismos volúmenes que el servicio `wordpress` — más cómodo que repetir `docker run` a mano.

Si el `.sql` usa un prefijo de tabla distinto a `VcgIheUb_` (busca `CREATE TABLE` en el dump), actualiza `WORDPRESS_TABLE_PREFIX` en `docker-compose.yml` y en `docker/wp-cli.sh` antes del paso 4.

**Nota de seguridad**: nunca pegues contraseñas/hashes reales del dump en commits, chats o este README — solo IDs/roles.

### Login al superadmin (SSO con WordPress)

1. El plugin `wordpress-plugin/hl-puntos-sso-bridge/` ya está montado y activo (ver arriba).
2. En **Ajustes → HL Puntos SSO Bridge** del wp-admin, confirma que la URL de la app y el secreto compartido coinciden con el `.env` de Laravel.
3. Usa un usuario con rol `administrator` (acceso total al superadmin) o `asesor_fidelizacion` (rol que el plugin crea solo; acceso a pacientes/ventas).
4. Desde el menú **Panel de Fidelización** del wp-admin, entra al superadmin — el plugin genera un JWT de corta vida y redirige a `/admin/sso/callback`.

En producción, este mismo plugin se instala en `webadmin.harkerlloreda.com` (pendiente de acceso — ver sección de pendientes).

## Pacientes de prueba

No hay seeders de pacientes/premios de ejemplo todavía. Para probar el portal manualmente:

```bash
docker compose exec app php artisan tinker
```

```php
$patient = \App\Models\Patient::create([
    'document_number' => '123', 'first_name' => 'Test', 'last_name' => 'Patient',
    'email' => 'test@example.com', 'password' => '123', 'must_change_password' => false,
]);
\App\Models\PointBatch::create([
    'patient_id' => $patient->id, 'points_original' => 1000, 'points_remaining' => 1000,
    'earned_at' => now(), 'expires_at' => now()->addMonths(12), 'status' => 'active',
]);
```

O, más realista: entra al superadmin y usa **Pacientes → Registrar paciente** y **Ventas → Registrar venta** (los puntos se acreditan automáticamente a la tasa configurada en **Configuración**).

## Pagos: Payzen (actualmente en modo "stub")

`PAYMENTS_DRIVER=stub` en `.env` activa `App\Services\Payments\StubPaymentGateway`, una pantalla local de "Aprobar/Rechazar" que simula la respuesta de la pasarela — permite construir y probar todo el checkout mixto sin credenciales reales.

Cuando Katherine entregue las credenciales reales de Payzen/Izipay:

1. Completar en `.env`: `PAYZEN_SHOP_ID`, `PAYZEN_PUBLIC_KEY`, `PAYZEN_API_PASSWORD`, `PAYZEN_HMAC_KEY_TEST`, `PAYZEN_HMAC_KEY_PROD`.
2. Cambiar `PAYMENTS_DRIVER=payzen`.
3. Revisar `App\Services\Payments\PayzenGateway` — el diseño ya está hecho (Krypton embebido + validación de firma HMAC-SHA256 en el callback, igual al patrón que corre hoy en producción en `harker_lloreda/`), pero no se ha podido probar contra la API real de Lyra sin credenciales.

No se requiere ningún otro cambio: `RedemptionService` y la UI de checkout no conocen el proveedor de pago, solo el contrato `PaymentGatewayContract`.

## Tareas programadas

Ya corren vía el contenedor `scheduler` (`routes/console.php`):

- `points:expire-batches` (diario, 2am) — vence lotes de puntos cumplido el año.
- `checkout:cancel-abandoned` (cada 5 min) — cancela canjes mixtos abandonados y libera los puntos reservados.

## Pendientes externos (no bloquean el desarrollo, bloquean producción)

- **VPS de producción**: acceso SSH/root pendiente de Katherine/Daniela. Provisionar PHP 8.4, MySQL, Redis, Nginx, Supervisor, Composer, TLS.
- **Acceso a wp-admin de `webadmin.harkerlloreda.com`**: para instalar el plugin `hl-puntos-sso-bridge` en producción.
- **Credenciales de Payzen**: pendientes de Katherine.
- **Brand book / identidad visual**: la UI actual usa un tema neutro (Tailwind, sin colores de marca) a propósito, fácil de reemplazar una vez llegue el material de diseño.
- **Servicio SMTP transaccional** para los correos reales de producción (en dev se usa Mailpit).
- **Iconos para el manifest PWA** (`public/manifest.json` está vacío de iconos hasta tener assets de marca).
- CSP (Content-Security-Policy) no se activó todavía: requiere afinar nonces/dominios permitidos (Trix, jsDelivr) sin romper la app — queda como tarea de hardening previa al lanzamiento.
