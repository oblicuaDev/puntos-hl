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

## Login al superadmin (SSO con WordPress)

1. En el WordPress de prueba (http://localhost:8081/wp-admin), instala/activa el plugin ubicado en `wordpress-plugin/hl-puntos-sso-bridge/` (ya está montado como volumen en `docker-compose.yml`, solo falta activarlo desde Plugins).
2. En **Ajustes → HL Puntos SSO Bridge**, configura:
   - URL de la app: `http://localhost:8080`
   - Secreto compartido: debe ser **idéntico** al `WP_SSO_SHARED_SECRET` del `.env` de Laravel.
3. Crea o usa un usuario de WordPress con rol `administrator` (acceso total al superadmin) o `asesor_fidelizacion` (rol que el plugin crea solo; acceso a pacientes/ventas).
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
