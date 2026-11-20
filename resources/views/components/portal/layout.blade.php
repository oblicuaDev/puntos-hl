<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#335C82">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">
    <title>{{ $title ?? 'Plan de Fidelización Harker Lloreda' }}</title>
    @vite(['resources/css/app.css'])
    {{ Vite::fonts() }}
    @livewireStyles
</head>
<body class="min-h-screen bg-[#FAF8F5] font-sans text-ink pb-16">
    <header class="sticky top-0 z-10 bg-white border-b border-black/5 px-4 py-3 flex items-center justify-between">
        <a href="{{ route('portal.home') }}" class="shrink-0">
            <x-brand-logo class="h-5 w-auto text-azul" />
        </a>

        @auth('patient')
            <a href="{{ route('portal.dashboard') }}" class="text-sm font-semibold bg-dermo/60 text-marino rounded-full px-3 py-1">
                {{ number_format(auth('patient')->user()->pointsBalance()) }} pts
            </a>
        @else
            <a href="{{ route('portal.login') }}" class="text-sm font-medium text-azul">Ingresar</a>
        @endauth
    </header>

    <main class="px-4 py-4 max-w-2xl mx-auto">
        {{ $slot }}
    </main>

    @auth('patient')
        <nav class="fixed bottom-0 inset-x-0 bg-white border-t border-black/5 grid grid-cols-3 text-center text-xs py-2 z-10">
            <a href="{{ route('portal.rewards.index') }}" class="py-1 font-medium text-muted hover:text-azul transition-colors">Premios</a>
            <a href="{{ route('portal.dashboard') }}" class="py-1 font-medium text-muted hover:text-azul transition-colors">Mi cuenta</a>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button class="py-1 font-medium text-muted hover:text-azul transition-colors">Salir</button>
            </form>
        </nav>
    @endauth

    @livewireScripts
</body>
</html>
