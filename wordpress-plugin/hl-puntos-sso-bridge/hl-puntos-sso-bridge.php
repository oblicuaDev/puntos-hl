<?php
/**
 * Plugin Name: HL Puntos SSO Bridge
 * Description: Permite al staff de Harker Lloreda entrar al superadmin del Plan de Fidelización (puntos.harkerlloreda.com) usando su sesión de WordPress, vía un handshake JWT de corta vida. No expone passwords ni crea usuarios en Laravel directamente.
 * Version: 1.0.0
 * Author: Harker Lloreda
 */
if (! defined('ABSPATH')) {
    exit;
}

define('HLFID_SSO_ROLE', 'asesor_fidelizacion');
define('HLFID_SSO_OPTION_SECRET', 'hlfid_sso_shared_secret');
define('HLFID_SSO_OPTION_APP_URL', 'hlfid_sso_app_url');
define('HLFID_SSO_TOKEN_TTL', 120); // segundos

/**
 * Activación: crea el rol "asesor_fidelizacion" si no existe, y un secreto
 * compartido inicial (se puede rotar después desde Ajustes).
 */
function hlfid_sso_activate(): void
{
    if (! get_role(HLFID_SSO_ROLE)) {
        add_role(HLFID_SSO_ROLE, 'Asesor (Plan de Fidelización)', [
            'read' => true,
        ]);
    }

    if (! get_option(HLFID_SSO_OPTION_SECRET)) {
        update_option(HLFID_SSO_OPTION_SECRET, wp_generate_password(64, true, true));
    }
}
register_activation_hook(__FILE__, 'hlfid_sso_activate');

/**
 * Página de ajustes: URL de la app Laravel y secreto compartido (rotable).
 */
function hlfid_sso_register_settings(): void
{
    register_setting('hlfid_sso', HLFID_SSO_OPTION_APP_URL);
    register_setting('hlfid_sso', HLFID_SSO_OPTION_SECRET);
}
add_action('admin_init', 'hlfid_sso_register_settings');

function hlfid_sso_settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>HL Puntos — SSO Bridge</h1>
        <form method="post" action="options.php">
            <?php settings_fields('hlfid_sso'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="hlfid_app_url">URL de la app de fidelización</label></th>
                    <td>
                        <input type="url" id="hlfid_app_url" name="<?php echo esc_attr(HLFID_SSO_OPTION_APP_URL); ?>"
                               value="<?php echo esc_attr(get_option(HLFID_SSO_OPTION_APP_URL, '')); ?>"
                               class="regular-text" placeholder="https://puntos.harkerlloreda.com" />
                    </td>
                </tr>
                <tr>
                    <th><label for="hlfid_secret">Secreto compartido (HS256)</label></th>
                    <td>
                        <input type="text" id="hlfid_secret" name="<?php echo esc_attr(HLFID_SSO_OPTION_SECRET); ?>"
                               value="<?php echo esc_attr(get_option(HLFID_SSO_OPTION_SECRET, '')); ?>"
                               class="regular-text" />
                        <p class="description">Debe coincidir exactamente con WP_SSO_SHARED_SECRET en el .env de Laravel. Rotar aquí invalida sesiones nuevas hasta actualizar ambos lados.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function hlfid_sso_admin_menu(): void
{
    // Item visible para administradores y para el rol asesor_fidelizacion.
    add_menu_page(
        'Panel de Fidelización',
        'Panel de Fidelización',
        'read',
        'hlfid-sso-launch',
        'hlfid_sso_launch_page',
        'dashicons-tickets-alt',
        58
    );

    if (current_user_can('manage_options')) {
        add_submenu_page(
            'hlfid-sso-launch',
            'Ajustes SSO',
            'Ajustes',
            'manage_options',
            'hlfid-sso-settings',
            'hlfid_sso_settings_page'
        );
    }
}
add_action('admin_menu', 'hlfid_sso_admin_menu');

/**
 * El redirect real ocurre aquí, en admin_init — no en el callback de
 * add_menu_page. WordPress ya envía el HTML completo del wp-admin (DOCTYPE,
 * <head>, barra de administración) ANTES de invocar el callback de una
 * página de menú, así que un wp_redirect() disparado desde ahí llega con
 * headers ya enviados y falla en silencio (la página se corta a medias en
 * vez de redirigir). admin_init corre antes de cualquier salida.
 */
function hlfid_sso_maybe_redirect(): void
{
    if (($_GET['page'] ?? '') !== 'hlfid-sso-launch') {
        return;
    }

    $user = wp_get_current_user();
    $role = hlfid_sso_map_role($user);

    if ($role === null) {
        wp_die('Tu usuario de WordPress no tiene permiso para acceder al Plan de Fidelización.');
    }

    $appUrl = rtrim((string) get_option(HLFID_SSO_OPTION_APP_URL, ''), '/');

    if ($appUrl === '') {
        wp_die('Falta configurar la URL de la app en Ajustes del plugin.');
    }

    $token = hlfid_sso_build_jwt($user, $role);
    $callbackUrl = $appUrl.'/admin/sso/callback?token='.rawurlencode($token);

    wp_redirect($callbackUrl);
    exit;
}
add_action('admin_init', 'hlfid_sso_maybe_redirect');

/**
 * Callback de la página de menú: en operación normal, admin_init ya
 * redirigió antes de llegar aquí. Esto solo se ve si, por lo que sea,
 * admin_init no pudo ejecutar el redirect (no debería pasar).
 */
function hlfid_sso_launch_page(): void
{
    echo '<p>Redirigiendo al Plan de Fidelización…</p>';
}

/** Mapea el rol de WP al rol interno esperado por Laravel, o null si no aplica. */
function hlfid_sso_map_role(WP_User $user): ?string
{
    if (in_array('administrator', $user->roles, true)) {
        return 'administrator';
    }

    if (in_array(HLFID_SSO_ROLE, $user->roles, true)) {
        return HLFID_SSO_ROLE;
    }

    return null;
}

/**
 * Construye el JWT (HS256) que Laravel validará en /admin/sso/callback.
 * No depende de ninguna librería externa: HS256 es solo HMAC-SHA256 sobre
 * base64url(header).base64url(payload), lo que WordPress puede hacer con
 * hash_hmac() nativo sin instalar nada.
 */
function hlfid_sso_build_jwt(WP_User $user, string $wpRole): string
{
    $secret = (string) get_option(HLFID_SSO_OPTION_SECRET, '');
    $now = time();

    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $payload = [
        'iss' => home_url(),
        'aud' => (string) get_option(HLFID_SSO_OPTION_APP_URL, ''),
        'iat' => $now,
        'exp' => $now + HLFID_SSO_TOKEN_TTL,
        'jti' => wp_generate_uuid4(),
        'wp_user_id' => $user->ID,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'role' => $wpRole,
    ];

    $segments = [
        hlfid_sso_base64url_encode(wp_json_encode($header)),
        hlfid_sso_base64url_encode(wp_json_encode($payload)),
    ];

    $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
    $segments[] = hlfid_sso_base64url_encode($signature);

    return implode('.', $segments);
}

function hlfid_sso_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Hardening opcional: si el rol de un usuario cambia o se elimina, notificar
 * a Laravel para que pueda revocar su sesión admin de inmediato (en vez de
 * esperar a que expire el TTL de la sesión). Requiere el mismo secreto
 * compartido para autenticar la llamada.
 */
function hlfid_sso_notify_revocation(int $userId): void
{
    $appUrl = rtrim((string) get_option(HLFID_SSO_OPTION_APP_URL, ''), '/');
    $secret = (string) get_option(HLFID_SSO_OPTION_SECRET, '');

    if ($appUrl === '' || $secret === '') {
        return;
    }

    $body = wp_json_encode(['wp_user_id' => $userId]);
    $signature = hash_hmac('sha256', $body, $secret);

    wp_remote_post($appUrl.'/admin/sso/revoke', [
        'timeout' => 3,
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json', 'X-Signature' => $signature],
        'body' => $body,
    ]);
}
add_action('set_user_role', fn (int $userId) => hlfid_sso_notify_revocation($userId));
add_action('delete_user', fn (int $userId) => hlfid_sso_notify_revocation($userId));
