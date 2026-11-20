<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Sso\SsoException;
use App\Services\Sso\WordPressSsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SsoController extends Controller
{
    public function __construct(private WordPressSsoService $sso) {}

    /** Pantalla que explica cómo entrar (botón "Entrar con WordPress" -> wp-admin). */
    public function start(): View
    {
        return view('admin.sso.start', [
            'wpAdminUrl' => rtrim((string) config('sso.issuer'), '/').'/wp-admin/',
        ]);
    }

    /** Callback del plugin "HL Puntos SSO Bridge": valida el JWT y loguea al admin_user. */
    public function callback(Request $request): RedirectResponse
    {
        $token = $request->query('token');

        if (! $token) {
            abort(400, 'Falta el token SSO.');
        }

        try {
            $adminUser = $this->sso->handle($token);
        } catch (SsoException $e) {
            report($e);

            return redirect()->route('admin.sso.start')->withErrors(['sso' => $e->getMessage()]);
        }

        Auth::guard('admin')->login($adminUser, remember: false);

        $request->session()->put(
            'admin_session_expires_at',
            now()->addMinutes((int) config('sso.session_ttl_minutes'))
        );

        return redirect()->route('admin.dashboard');
    }

    /**
     * Hook de hardening: el plugin de WP llama aquí cuando el rol de un
     * usuario cambia o se elimina, para revocar su sesión admin de inmediato
     * en vez de esperar al TTL. Autenticado con el mismo secreto compartido
     * del handshake SSO (HMAC sobre el body, no un JWT).
     */
    public function revoke(Request $request): JsonResponse
    {
        $secret = (string) config('sso.shared_secret');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! $secret || ! hash_equals($expected, (string) $request->header('X-Signature'))) {
            abort(401);
        }

        AdminUser::where('wp_user_id', $request->integer('wp_user_id'))
            ->update(['is_active' => false]);

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Esto NO cierra la sesión de WordPress, solo la de Laravel.
        return redirect()->route('admin.sso.start');
    }
}
