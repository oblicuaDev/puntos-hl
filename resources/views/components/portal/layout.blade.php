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
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="canonical" href="{{ url()->current() }}">
    <title>{{ $title ?? 'Plan de Fidelización Harker Lloreda' }}</title>
    <meta name="description" content="{{ $description ?? 'Acumula puntos en cada procedimiento con Harker Lloreda y cámbialos por tratamientos, bonos y productos.' }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Plan de Fidelización Harker Lloreda">
    <meta property="og:title" content="{{ $title ?? 'Plan de Fidelización Harker Lloreda' }}">
    <meta property="og:description" content="{{ $description ?? 'Acumula puntos en cada procedimiento con Harker Lloreda y cámbialos por tratamientos, bonos y productos.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @vite(['resources/css/app.css'])
    {{ Vite::fonts() }}
    @livewireStyles
</head>
<body class="min-h-screen bg-[#FAF8F5] font-sans text-ink pb-28">
    <header class="sticky top-0 z-10 bg-white/95 backdrop-blur border-b border-black/5 px-5 h-14 flex items-center justify-between">
        <a href="{{ route('portal.home') }}" wire:navigate class="shrink-0">
            <x-brand-logo class="h-5 w-auto text-azul" />
        </a>

        @auth('patient')
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold bg-dermo text-marino rounded-full pl-2.5 pr-3.5 py-1.5">
                @svg('heroicon-s-star', 'h-3.5 w-3.5')
                {{ number_format(auth('patient')->user()->pointsBalance()) }} pts
            </a>
        @else
            <a href="{{ route('portal.login') }}" wire:navigate class="inline-flex items-center rounded-full border-2 border-azul text-azul text-sm font-semibold px-4 py-1.5 transition-colors hover:bg-azul hover:text-white">Ingresar</a>
        @endauth
    </header>

    <main class="px-5 pt-5 pb-4 max-w-2xl mx-auto">
        {{ $slot }}
    </main>

    @auth('patient')
        <nav class="fixed bottom-3 inset-x-3 mx-auto max-w-md bg-white rounded-2xl shadow-[0_8px_24px_rgba(43,60,76,0.14)] grid grid-cols-3 gap-1 p-1.5 text-xs z-10">
            @php
                $tab = fn (string $routeName) => request()->routeIs($routeName)
                    ? 'bg-azul/10 text-azul font-semibold'
                    : 'text-muted hover:text-azul font-medium';
            @endphp
            <a href="{{ route('portal.rewards.index') }}" wire:navigate class="flex flex-col items-center gap-0.5 rounded-xl py-2 transition-colors {{ $tab('portal.rewards.*') }}">
                @svg('heroicon-o-gift', 'h-5 w-5')
                Premios
            </a>
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="flex flex-col items-center gap-0.5 rounded-xl py-2 transition-colors {{ $tab('portal.dashboard') }}">
                @svg('heroicon-o-user-circle', 'h-5 w-5')
                Mi cuenta
            </a>
            <form method="POST" action="{{ route('portal.logout') }}" class="contents">
                @csrf
                <button class="flex flex-col items-center gap-0.5 rounded-xl py-2 font-medium text-muted hover:text-azul transition-colors w-full">
                    @svg('heroicon-o-arrow-right-on-rectangle', 'h-5 w-5')
                    Salir
                </button>
            </form>
        </nav>
    @endauth

    @livewireScripts
</body>
</html>
