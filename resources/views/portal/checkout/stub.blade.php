<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Simulador de pago (stub)</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="max-w-sm w-full p-8 bg-white rounded-xl shadow text-center space-y-4">
        <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 text-amber-600 mx-auto">
            @svg('heroicon-o-credit-card', 'h-6 w-6')
        </span>
        <p class="text-xs uppercase text-amber-600 font-semibold flex items-center justify-center gap-1.5">
            @svg('heroicon-o-exclamation-triangle', 'h-3.5 w-3.5')
            Pasarela simulada — solo entorno de desarrollo
        </p>
        <h1 class="text-xl font-semibold">Confirmar pago de ${{ number_format($payment->amount_cop, 0, ',', '.') }} COP</h1>

        <form method="POST" action="{{ route('portal.checkout.stub.decide', $payment) }}">
            @csrf
            <input type="hidden" name="approve" value="1">
            <button class="w-full rounded-lg bg-emerald-600 text-white py-2.5 font-medium flex items-center justify-center gap-2">
                @svg('heroicon-o-check-circle', 'h-4 w-4')
                Aprobar pago
            </button>
        </form>
        <form method="POST" action="{{ route('portal.checkout.stub.decide', $payment) }}">
            @csrf
            <input type="hidden" name="approve" value="0">
            <button class="w-full rounded-lg bg-red-600 text-white py-2.5 font-medium flex items-center justify-center gap-2">
                @svg('heroicon-o-x-circle', 'h-4 w-4')
                Rechazar pago
            </button>
        </form>
    </div>
</body>
</html>
