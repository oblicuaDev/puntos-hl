<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta de /admin/* a uno o más roles de admin_users
 * (reflejo cacheado del rol de WordPress). Uso: ->middleware('admin.role:admin,asesor')
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $adminUser = Auth::guard('admin')->user();

        if (! $adminUser || ! $adminUser->is_active) {
            return redirect()->route('admin.sso.start');
        }

        if ($roles !== [] && ! in_array($adminUser->role, $roles, true)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
