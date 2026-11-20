<?php

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

    return redirect()->route('portal.home');
})->middleware('auth:patient')->name('portal.logout');

// Autenticado, pero accesible aunque deba cambiar la contraseña.
Route::middleware('auth:patient')->group(function () {
    Route::view('/cambiar-password', 'portal.change-password')->name('portal.password.edit');
});

// Autenticado y con contraseña ya cambiada.
Route::middleware(['auth:patient', 'patient.password_changed'])->group(function () {
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
});
