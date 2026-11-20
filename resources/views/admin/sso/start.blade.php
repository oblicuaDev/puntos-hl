<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>HL Puntos — Acceso superadmin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="max-w-sm w-full p-8 bg-white rounded-xl shadow text-center space-y-4">
        <h1 class="text-xl font-semibold">Plan de Fidelización</h1>
        <p class="text-gray-600 text-sm">Inicia sesión desde el panel de WordPress de Harker Lloreda para entrar al superadmin.</p>

        @error('sso')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <a href="{{ $wpAdminUrl }}" class="inline-block w-full rounded-lg bg-gray-900 text-white py-2.5 font-medium">
            Entrar con WordPress
        </a>
    </div>
</body>
</html>
