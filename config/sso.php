<?php

return [
    // URL del WordPress headless que emite el JWT (webadmin.harkerlloreda.com en producción).
    'issuer' => env('WP_SSO_ISSUER'),

    // Secreto compartido HS256 entre el plugin de WP y Laravel. Rotable.
    'shared_secret' => env('WP_SSO_SHARED_SECRET'),

    // Vigencia máxima aceptada del JWT emitido por WP (segundos).
    'token_ttl' => 120,

    // Duración de la sesión Laravel del superadmin, independiente de la sesión de WP.
    'session_ttl_minutes' => 240,

    // Mapeo de roles de WordPress -> rol interno de admin_users.
    'role_map' => [
        'administrator' => 'admin',
        'asesor_fidelizacion' => 'asesor',
    ],
];
