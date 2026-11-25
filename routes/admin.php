<?php

use App\Http\Controllers\Admin\MovementsReportController;
use App\Http\Controllers\Admin\SsoController;
use Illuminate\Support\Facades\Route;

// Público (sin sesión admin todavía): inicio y callback del handshake SSO.
Route::get('/sso/start', [SsoController::class, 'start'])->name('sso.start');
Route::get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::post('/sso/logout', [SsoController::class, 'logout'])->name('sso.logout');
Route::post('/sso/revoke', [SsoController::class, 'revoke'])->name('sso.revoke');

// Autenticado: cualquier admin_user activo (admin o asesor).
Route::middleware('admin.role:admin,asesor')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');

    // Pacientes y ventas: admin y asesor.
    Route::view('/patients', 'admin.patients.index')->name('patients.index');
    Route::view('/sales', 'admin.sales.index')->name('sales.index');
});

// Solo admin: catálogo, premios, config, banners, T&C.
Route::middleware('admin.role:admin')->group(function () {
    Route::view('/procedures', 'admin.procedures.index')->name('procedures.index');
    Route::view('/settings', 'admin.settings.edit')->name('settings.edit');
    Route::view('/reward-categories', 'admin.reward-categories.index')->name('reward-categories.index');
    Route::view('/rewards', 'admin.rewards.index')->name('rewards.index');
    Route::view('/banners', 'admin.banners.index')->name('banners.index');
    Route::view('/legal', 'admin.legal.edit')->name('legal.edit');

    // Reporte Excel de movimientos de puntos (HL-26).
    Route::get('/reportes/movimientos', MovementsReportController::class)->name('reports.movements');
});
