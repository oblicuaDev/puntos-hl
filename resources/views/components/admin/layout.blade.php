<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#335C82">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Plan de Fidelización — Superadmin' }}</title>
    @vite(['resources/css/app.css'])
    {{ Vite::fonts() }}
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.1.6/dist/trix.css">
</head>
<body class="min-h-screen bg-[#F4F5F7] font-sans text-ink">
    @php
        $navItem = function (string $routeName, string $label, string $icon) {
            $active = request()->routeIs($routeName);

            return compact('routeName', 'label', 'icon', 'active');
        };

        $linkClass = fn (bool $active) => 'shrink-0 flex items-center gap-2 rounded-xl px-3 py-2 text-sm transition-colors '
            .($active ? 'bg-white text-azul font-semibold shadow-sm' : 'text-white/80 hover:text-white hover:bg-white/10');

        $admin = auth('admin')->user();
        $initials = collect(explode(' ', (string) $admin?->display_name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    @endphp

    <header class="bg-azul text-white sticky top-0 z-20">
        <div class="flex items-center gap-1 px-5 h-14 overflow-x-auto">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="shrink-0 pr-4 mr-1 border-r border-white/15">
                <x-brand-logo class="h-5 w-auto text-white" />
            </a>

            @foreach ([
                $navItem('admin.dashboard', 'Panel', 'o-squares-2x2'),
                $navItem('admin.patients.index', 'Pacientes', 'o-users'),
                $navItem('admin.sales.index', 'Ventas', 'o-banknotes'),
            ] as $item)
                <a href="{{ route($item['routeName']) }}" wire:navigate class="{{ $linkClass($item['active']) }}">
                    {{ svg('heroicon-'.$item['icon'], 'h-4 w-4') }}
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if ($admin?->isAdmin())
                <span class="mx-2 h-5 w-px bg-white/20 shrink-0"></span>
                @foreach ([
                    $navItem('admin.procedures.index', 'Procedimientos', 'o-clipboard-document-list'),
                    $navItem('admin.reward-categories.index', 'Categorías', 'o-tag'),
                    $navItem('admin.rewards.index', 'Premios', 'o-gift'),
                    $navItem('admin.banners.index', 'Banners', 'o-photo'),
                    $navItem('admin.legal.edit', 'T&C', 'o-scale'),
                    $navItem('admin.settings.edit', 'Configuración', 'o-cog-6-tooth'),
                ] as $item)
                    <a href="{{ route($item['routeName']) }}" wire:navigate class="{{ $linkClass($item['active']) }}">
                        {{ svg('heroicon-'.$item['icon'], 'h-4 w-4') }}
                        {{ $item['label'] }}
                    </a>
                @endforeach
            @endif

            <div class="ml-auto flex items-center gap-3 shrink-0 pl-4">
                <span class="hidden md:flex items-center gap-2">
                    <span class="h-8 w-8 rounded-full bg-dermo text-marino text-xs font-semibold flex items-center justify-center">{{ $initials }}</span>
                    <span class="text-sm leading-tight">
                        <span class="block font-medium">{{ $admin?->display_name }}</span>
                        <span class="block text-xs text-white/70">{{ $admin?->isAdmin() ? 'Superadmin' : 'Operador' }}</span>
                    </span>
                </span>
                <form method="POST" action="{{ route('admin.sso.logout') }}">
                    @csrf
                    <button class="flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm text-white/80 hover:text-white hover:bg-white/10 transition-colors">
                        @svg('heroicon-o-arrow-right-on-rectangle', 'h-4 w-4')
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="px-5 md:px-8 py-8 max-w-7xl mx-auto">
        {{ $slot }}
    </main>

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/trix@2.1.6/dist/trix.umd.min.js"></script>
</body>
</html>
