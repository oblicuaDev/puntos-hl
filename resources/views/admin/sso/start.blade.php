<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="theme-color" content="#335C82">
    <title>Plan de Fidelización — Acceso superadmin</title>
    @vite(['resources/css/app.css'])
    {{ Vite::fonts() }}
</head>
<body class="min-h-screen bg-[#FAF8F5] font-sans text-ink flex items-center justify-center p-6">
    <div class="max-w-sm w-full p-8 bg-white rounded-brand shadow text-center space-y-5">
        <x-brand-logo class="h-6 w-auto text-azul mx-auto" />
        <p class="text-muted text-sm">Inicia sesión desde el panel de WordPress de Harker Lloreda para entrar al superadmin.</p>

        @error('sso')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <a href="{{ $wpAdminUrl }}" class="inline-block w-full rounded-brand bg-azul text-white py-2.5 font-medium text-center border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
            Entrar con WordPress
        </a>
    </div>
</body>
</html>
