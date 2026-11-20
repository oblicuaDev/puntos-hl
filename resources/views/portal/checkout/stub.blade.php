<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Simulador de pago (stub)</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="max-w-sm w-full p-8 bg-white rounded-xl shadow text-center space-y-4">
        <p class="text-xs uppercase text-amber-600 font-semibold">Pasarela simulada — solo entorno de desarrollo</p>
        <h1 class="text-xl font-semibold">Confirmar pago de ${{ number_format($payment->amount_cop, 0, ',', '.') }} COP</h1>

        <form method="POST" action="{{ route('portal.checkout.stub.decide', $payment) }}">
            @csrf
            <input type="hidden" name="approve" value="1">
            <button class="w-full rounded-lg bg-emerald-600 text-white py-2.5 font-medium">Aprobar pago</button>
        </form>
        <form method="POST" action="{{ route('portal.checkout.stub.decide', $payment) }}">
            @csrf
            <input type="hidden" name="approve" value="0">
            <button class="w-full rounded-lg bg-red-600 text-white py-2.5 font-medium">Rechazar pago</button>
        </form>
    </div>
</body>
</html>
