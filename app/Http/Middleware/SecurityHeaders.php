<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad base para todo el sitio (admin + portal). No
 * sustituye un firewall/WAF, pero cubre el mínimo razonable de hardening
 * a nivel de aplicación.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->buildCsp($request));

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * La pantalla de pago embebido de Payzen es la excepción: Krypton puede
     * necesitar abrir un iframe de verificación 3D Secure del banco emisor
     * de la tarjeta, cuyo dominio no se puede predecir de antemano. Ahí se
     * relajan frame-src/connect-src en vez de arriesgar romper un pago real;
     * el resto del sitio queda con la política estricta.
     */
    private function buildCsp(Request $request): string
    {
        $isPayzenCheckout = $request->routeIs('portal.checkout.payzen.show');

        $directives = [
            "default-src 'self'",
            // 'unsafe-inline' es por Cloudflare: el proxy de harkerlloreda.com
            // inyecta su propio script de verificación en cada respuesta con
            // un token distinto por request (no se puede permitir por hash
            // fijo) — no es algo que controlemos desde la app.
            "script-src 'self' 'unsafe-eval' 'unsafe-inline' https://cdn.jsdelivr.net https://static.payzen.lat https://static.cloudflareinsights.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://static.payzen.lat",
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://cdn.jsdelivr.net",
            "connect-src 'self'".($isPayzenCheckout ? ' https://*.payzen.lat https://*.lyra.com' : ''),
            "frame-src ".($isPayzenCheckout ? "'self' https:" : "'self'"),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }
}
