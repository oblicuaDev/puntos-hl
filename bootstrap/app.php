<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/portal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.role' => EnsureAdminRole::class,
        ]);

        // El guard "admin" redirige por su cuenta (ver EnsureAdminRole); este
        // fallback de "auth" solo aplica al guard "patient" del portal.
        $middleware->redirectGuestsTo(fn () => route('portal.login'));

        $middleware->web(append: [SecurityHeaders::class]);

        // En producción corre detrás de Apache (WHM) -> Nginx, ambos en
        // infraestructura propia conectados por loopback: confiamos en todo
        // el chain para que X-Forwarded-Proto/Host se respeten (URLs https
        // correctas y cookies "secure" funcionando). Inofensivo en dev: ahí
        // no hay proxy real por delante.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // /admin/sso/revoke y la notificación de Payzen las llaman servicios
        // externos servidor-a-servidor, autenticados con su propia firma
        // HMAC (no con la sesión del navegador).
        $middleware->validateCsrfTokens(except: [
            'admin/sso/revoke',
            'checkout/payzen/notificacion',
            // Krypton hace un POST de navegador real a kr-post-url-success,
            // sin token CSRF de Laravel.
            'checkout/payzen/*/retorno',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
