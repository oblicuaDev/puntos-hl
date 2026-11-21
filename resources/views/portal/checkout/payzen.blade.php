<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago seguro — Plan de Fidelización</title>
    @vite(['resources/css/app.css'])

    {{-- Mismo patrón probado en producción en harker_lloreda/includes/head.php:
         kr-public-key combinado "shopId:publicKey" como atributo del script,
         kr-post-url-success para el regreso del navegador tras pagar. La
         confirmación real siempre llega por el IPN, no por este redirect. --}}
    <script src="https://static.payzen.lat/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js"
        kr-public-key="{{ $krPublicKey }}"
        kr-post-url-success="{{ route('portal.checkout.payzen.return', $payment) }}"
        kr-language="es-ES"></script>
    <link rel="stylesheet" href="https://static.payzen.lat/static/js/krypton-client/V4.0/ext/neon-reset.css">
    <script src="https://static.payzen.lat/static/js/krypton-client/V4.0/ext/neon.min.js"></script>
</head>
<body class="min-h-screen bg-[#FAF8F5] font-sans text-ink flex items-center justify-center p-6">
    <div class="max-w-sm w-full p-6 bg-white rounded-brand shadow space-y-5">
        <div class="text-center space-y-1">
            <span class="inline-flex items-center justify-center h-11 w-11 rounded-full bg-azul/10 text-azul mx-auto">
                @svg('heroicon-o-lock-closed', 'h-5 w-5')
            </span>
            <h1 class="text-lg font-semibold text-marino">Completa tu pago</h1>
            <p class="text-sm text-muted">${{ number_format($payment->amount_cop, 0, ',', '.') }} COP</p>
        </div>

        <div class="kr-embedded" kr-form-token="{{ $formToken }}">
            <div class="kr-pan"></div>
            <div class="kr-expiry"></div>
            <div class="kr-security-code"></div>
            <button class="kr-payment-button"></button>
            <div class="kr-form-error"></div>
        </div>

        <p class="text-[11px] text-center text-muted">Pago procesado de forma segura por Payzen/Izipay. Tu canje se confirma por correo.</p>
    </div>
</body>
</html>
