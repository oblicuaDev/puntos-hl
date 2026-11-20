<?php

use App\Models\AdminUser;
use App\Models\Patient;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Dos superficies, dos guards:
    | - "admin": superadmin, autenticado vía SSO con WordPress (sin password propio).
    | - "patient": portal de pacientes, con cuentas propias.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'patient'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'patients'),
    ],

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'admin_users',
        ],

        'patient' => [
            'driver' => 'session',
            'provider' => 'patients',
        ],
    ],

    'providers' => [
        'admin_users' => [
            'driver' => 'eloquent',
            'model' => AdminUser::class,
        ],

        'patients' => [
            'driver' => 'eloquent',
            'model' => Patient::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Solo los pacientes tienen password propio (admin_users es SSO-only).
    |
    */

    'passwords' => [
        'patients' => [
            'provider' => 'patients',
            'table' => 'patient_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
