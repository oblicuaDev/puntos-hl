<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="theme-color" content="#335C82">
    <title>{{ $title ?? 'Plan de Fidelización — Superadmin' }}</title>
    @vite(['resources/css/app.css'])
    {{ Vite::fonts() }}
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.1.6/dist/trix.css">
</head>
<body class="min-h-screen bg-[#FAF8F5] font-sans text-ink">
    <nav class="bg-azul text-white px-6 py-3 flex items-center gap-6 text-sm">
        <a href="{{ route('admin.dashboard') }}" class="shrink-0">
            <x-brand-logo class="h-5 w-auto text-white" />
        </a>
        <a href="{{ route('admin.dashboard') }}" class="opacity-80 hover:opacity-100 transition-opacity">Panel</a>
        <a href="{{ route('admin.patients.index') }}" class="opacity-80 hover:opacity-100 transition-opacity">Pacientes</a>
        <a href="{{ route('admin.sales.index') }}" class="opacity-80 hover:opacity-100 transition-opacity">Ventas</a>
        @if (auth('admin')->user()?->isAdmin())
            <a href="{{ route('admin.procedures.index') }}" class="opacity-80 hover:opacity-100 transition-opacity">Procedimientos</a>
            <a href="{{ route('admin.reward-categories.index') }}" class="opacity-80 hover:opacity-100 transition-opacity">Categorías</a>
            <a href="{{ route('admin.rewards.index') }}" class="opacity-80 hover:opacity-100 transition-opacity">Premios</a>
            <a href="{{ route('admin.banners.index') }}" class="opacity-80 hover:opacity-100 transition-opacity">Banners</a>
            <a href="{{ route('admin.legal.edit') }}" class="opacity-80 hover:opacity-100 transition-opacity">T&amp;C</a>
            <a href="{{ route('admin.settings.edit') }}" class="opacity-80 hover:opacity-100 transition-opacity">Configuración</a>
        @endif
        <form method="POST" action="{{ route('admin.sso.logout') }}" class="ml-auto">
            @csrf
            <button class="opacity-80 hover:opacity-100 transition-opacity">Cerrar sesión</button>
        </form>
    </nav>

    <main class="p-6">
        {{ $slot }}
    </main>

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/trix@2.1.6/dist/trix.umd.min.js"></script>
</body>
</html>
