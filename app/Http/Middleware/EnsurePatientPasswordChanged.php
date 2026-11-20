<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pacientes creados manualmente por el admin empiezan con password = cédula
 * (must_change_password = true). Los forzamos a cambiarla antes de usar el
 * resto del portal.
 */
class EnsurePatientPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $patient = Auth::guard('patient')->user();

        if ($patient && $patient->must_change_password && ! $request->routeIs('portal.password.*', 'portal.logout')) {
            return redirect()->route('portal.password.edit');
        }

        return $next($request);
    }
}
