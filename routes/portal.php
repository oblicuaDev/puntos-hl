<?php

use App\Http\Controllers\Portal\PayzenCheckoutController;
use App\Http\Controllers\Portal\StubPaymentController;
use App\Models\Banner;
use App\Models\LegalPage;
use App\Models\Redemption;
use App\Models\Reward;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Portal de pacientes (puntos.harkerlloreda.com). Guard "patient" propio,
// sin relación con el guard "admin" ni con WordPress/Oreka.
Route::get('/', function () {
    return view('portal.home', [
        'banners' => Banner::currentlyVisible()->get(),
    ]);
})->name('portal.home');

Route::get('/terminos-y-condiciones', function () {
    return view('portal.legal', [
        'page' => LegalPage::where('slug', 'terminos-y-condiciones')->first(),
    ]);
})->name('portal.legal');

// Invitados únicamente.
Route::middleware('guest:patient')->group(function () {
    Route::view('/login', 'portal.login')->name('portal.login')->middleware('throttle:20,1');
    Route::view('/registro', 'portal.register')->name('portal.register')->middleware('throttle:20,1');
});

Route::post('/logout', function () {
    Auth::guard('patient')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    // Al salir del portal de puntos, el paciente vuelve al sitio principal
    // de la clínica (harkerlloreda.com), no se queda dando vueltas aquí.
    return redirect()->away(config('services.harker.site_url'));
})->middleware('auth:patient')->name('portal.logout');

// Autenticado (ingreso por código SMS; los pacientes no tienen contraseña).
Route::middleware('auth:patient')->group(function () {
    Route::view('/mi-cuenta', 'portal.dashboard')->name('portal.dashboard');
    Route::view('/premios', 'portal.rewards.index')->name('portal.rewards.index');

    Route::get('/premios/{reward:slug}', function (Reward $reward) {
        return view('portal.rewards.show', ['reward' => $reward]);
    })->name('portal.rewards.show');

    Route::get('/canje/{code}', function (string $code) {
        return view('portal.checkout.success', [
            'redemption' => Redemption::where('code', $code)->firstOrFail(),
        ]);
    })->name('portal.checkout.success');

    // Pantalla de pago simulada (solo mientras PAYMENTS_DRIVER=stub).
    Route::get('/checkout/stub/{payment}', [StubPaymentController::class, 'show'])->name('portal.checkout.stub.show');
    Route::post('/checkout/stub/{payment}', [StubPaymentController::class, 'decide'])->name('portal.checkout.stub.decide');

    // Checkout embebido real con Payzen (PAYMENTS_DRIVER=payzen).
    Route::get('/checkout/payzen/{payment}', [PayzenCheckoutController::class, 'show'])->name('portal.checkout.payzen.show');
    // Krypton hace un POST de navegador real a kr-post-url-success (no fetch/XHR),
    // sin token CSRF de Laravel — ver la excepción en bootstrap/app.php. GET
    // también, porque la propia pantalla "procesando" recarga por GET.
    Route::match(['get', 'post'], '/checkout/payzen/{payment}/retorno', [PayzenCheckoutController::class, 'return'])->name('portal.checkout.payzen.return');
});

// Notificación servidor-a-servidor de Payzen: pública (la llama Payzen, no un
// paciente logueado) y sin CSRF (ver bootstrap/app.php). Autenticada por
// firma HMAC dentro de PayzenGateway::handleCallback(), no por sesión.
Route::post('/checkout/payzen/notificacion', [PayzenCheckoutController::class, 'ipn'])
    ->name('portal.checkout.payzen.ipn')
    ->middleware('throttle:60,1');
