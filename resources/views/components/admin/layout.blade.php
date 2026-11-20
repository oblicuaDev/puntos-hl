<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'HL Puntos — Superadmin' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.1.6/dist/trix.css">
</head>
<body class="min-h-screen bg-gray-50">
    <nav class="bg-gray-900 text-white px-6 py-3 flex items-center gap-6 text-sm">
        <span class="font-semibold">HL Puntos</span>
        <a href="{{ route('admin.dashboard') }}" class="opacity-80 hover:opacity-100">Panel</a>
        <a href="{{ route('admin.patients.index') }}" class="opacity-80 hover:opacity-100">Pacientes</a>
        <a href="{{ route('admin.sales.index') }}" class="opacity-80 hover:opacity-100">Ventas</a>
        @if (auth('admin')->user()?->isAdmin())
            <a href="{{ route('admin.procedures.index') }}" class="opacity-80 hover:opacity-100">Procedimientos</a>
            <a href="{{ route('admin.reward-categories.index') }}" class="opacity-80 hover:opacity-100">Categorías</a>
            <a href="{{ route('admin.rewards.index') }}" class="opacity-80 hover:opacity-100">Premios</a>
            <a href="{{ route('admin.banners.index') }}" class="opacity-80 hover:opacity-100">Banners</a>
            <a href="{{ route('admin.legal.edit') }}" class="opacity-80 hover:opacity-100">T&amp;C</a>
            <a href="{{ route('admin.settings.edit') }}" class="opacity-80 hover:opacity-100">Configuración</a>
        @endif
        <form method="POST" action="{{ route('admin.sso.logout') }}" class="ml-auto">
            @csrf
            <button class="opacity-80 hover:opacity-100">Cerrar sesión</button>
        </form>
    </nav>

    <main class="p-6">
        {{ $slot }}
    </main>

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/trix@2.1.6/dist/trix.umd.min.js"></script>
</body>
</html>
