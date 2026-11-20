<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsurePatientPasswordChanged;
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
            'patient.password_changed' => EnsurePatientPasswordChanged::class,
        ]);

        // El guard "admin" redirige por su cuenta (ver EnsureAdminRole); este
        // fallback de "auth" solo aplica al guard "patient" del portal.
        $middleware->redirectGuestsTo(fn () => route('portal.login'));

        $middleware->web(append: [SecurityHeaders::class]);

        // /admin/sso/revoke lo llama el plugin de WP servidor-a-servidor,
        // autenticado con firma HMAC propia (no con la sesión del navegador).
        $middleware->validateCsrfTokens(except: [
            'admin/sso/revoke',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
