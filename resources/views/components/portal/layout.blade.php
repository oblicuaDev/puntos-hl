<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">
    <title>{{ $title ?? 'Plan de Fidelización Harker Lloreda' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 pb-16">
    <header class="sticky top-0 z-10 bg-white border-b px-4 py-3 flex items-center justify-between">
        <a href="{{ route('portal.home') }}" class="font-semibold">HL Puntos</a>

        @auth('patient')
            <a href="{{ route('portal.dashboard') }}" class="text-sm font-medium bg-gray-100 rounded-full px-3 py-1">
                {{ number_format(auth('patient')->user()->pointsBalance()) }} pts
            </a>
        @else
            <a href="{{ route('portal.login') }}" class="text-sm font-medium">Ingresar</a>
        @endauth
    </header>

    <main class="px-4 py-4 max-w-2xl mx-auto">
        {{ $slot }}
    </main>

    @auth('patient')
        <nav class="fixed bottom-0 inset-x-0 bg-white border-t grid grid-cols-3 text-center text-xs py-2 z-10">
            <a href="{{ route('portal.rewards.index') }}" class="py-1">Premios</a>
            <a href="{{ route('portal.dashboard') }}" class="py-1">Mi cuenta</a>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button class="py-1">Salir</button>
            </form>
        </nav>
    @endauth

    @livewireScripts
</body>
</html>
