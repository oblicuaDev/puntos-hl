@php
    $admin = auth('admin')->user();
    $salesThisMonth = \App\Models\Sale::whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year);

    $stats = [
        ['icon' => 'o-users', 'label' => 'Pacientes', 'value' => \App\Models\Patient::count(), 'hint' => 'registrados'],
        ['icon' => 'o-banknotes', 'label' => 'Ventas este mes', 'value' => (clone $salesThisMonth)->count(), 'hint' => now()->format('m/Y')],
        ['icon' => 'o-sparkles', 'label' => 'Puntos otorgados', 'value' => number_format((int) (clone $salesThisMonth)->sum('points_awarded')), 'hint' => 'este mes'],
        ['icon' => 'o-gift', 'label' => 'Premios activos', 'value' => \App\Models\Reward::where('is_active', true)->count(), 'hint' => 'disponibles en el catálogo'],
    ];

    $shortcuts = [
        ['route' => 'admin.patients.index', 'label' => 'Gestionar pacientes', 'icon' => 'o-users', 'desc' => 'Buscar, editar o bloquear cuentas'],
        ['route' => 'admin.sales.index', 'label' => 'Registrar venta', 'icon' => 'o-banknotes', 'desc' => 'Cargar ventas y asignar puntos'],
    ];

    // Últimas 8 semanas (lunes como inicio) para comparar puntos otorgados vs canjeados.
    $start = now()->startOfWeek()->subWeeks(7);
    $grantedByWeek = \App\Models\Sale::where('sale_date', '>=', $start)->get()
        ->groupBy(fn ($sale) => $sale->sale_date->copy()->startOfWeek()->format('Y-m-d'))
        ->map(fn ($group) => (int) $group->sum('points_awarded'));
    $redeemedByWeek = \App\Models\Redemption::whereIn('status', ['approved', 'fulfilled'])->where('created_at', '>=', $start)->get()
        ->groupBy(fn ($redemption) => $redemption->created_at->copy()->startOfWeek()->format('Y-m-d'))
        ->map(fn ($group) => (int) $group->sum('points_used'));

    $series = collect(range(0, 7))->map(function ($i) use ($start, $grantedByWeek, $redeemedByWeek) {
        $week = $start->copy()->addWeeks($i);
        $key = $week->format('Y-m-d');

        return [
            'label' => $week->format('d/m'),
            'granted' => $grantedByWeek[$key] ?? 0,
            'redeemed' => $redeemedByWeek[$key] ?? 0,
        ];
    });
    $maxValue = max(1, $series->max('granted'), $series->max('redeemed'));
    $pct = fn (int $value) => $value > 0 ? max(3, (int) round($value / $maxValue * 100)) : 0;

    // Requiere atención: datos reales de stock, vigencia y canjes pendientes.
    $tones = [
        'amber' => ['box' => 'bg-amber-50', 'bar' => 'bg-amber-500', 'text' => 'text-amber-800'],
        'red' => ['box' => 'bg-red-50', 'bar' => 'bg-red-500', 'text' => 'text-red-800'],
        'slate' => ['box' => 'bg-[#F4F5F7]', 'bar' => 'bg-steel', 'text' => 'text-marino'],
    ];

    $alerts = collect();
    foreach (\App\Models\Reward::where('is_active', true)->whereNotNull('stock')->where('stock', '<=', 5)->orderBy('stock')->limit(3)->get() as $reward) {
        $alerts->push(['tone' => 'amber', 'title' => 'Stock bajo: '.$reward->title, 'detail' => $reward->stock.' unidades restantes', 'href' => $admin->isAdmin() ? route('admin.rewards.index') : null]);
    }
    foreach (\App\Models\Reward::where('is_active', true)->whereBetween('valid_until', [now(), now()->addDays(14)])->orderBy('valid_until')->limit(3)->get() as $reward) {
        $alerts->push(['tone' => 'red', 'title' => 'Vence el '.$reward->valid_until->format('d/m').': '.$reward->title, 'detail' => 'Revisa la vigencia del premio', 'href' => $admin->isAdmin() ? route('admin.rewards.index') : null]);
    }
    $pendingPayments = \App\Models\Redemption::where('status', 'pending_payment')->count();
    if ($pendingPayments > 0) {
        $alerts->push(['tone' => 'slate', 'title' => $pendingPayments.' canje(s) pendiente(s) de pago', 'detail' => 'Aún no confirmados', 'href' => null]);
    }
@endphp

<x-admin.layout title="HL Puntos — Panel">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-2 mb-8">
        <div>
            <p class="text-sm text-muted">Hola, {{ $admin->display_name }}</p>
            <h1 class="text-3xl font-semibold text-marino tracking-tight">Panel</h1>
        </div>
        <p class="text-xs text-muted uppercase tracking-wider">{{ $admin->role }}</p>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($stats as $stat)
            <div class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-5 flex flex-col gap-4">
                <span class="inline-flex items-center justify-center h-10 w-10 rounded-xl bg-azul/10 text-azul">
                    {{ svg('heroicon-'.$stat['icon'], 'h-5 w-5') }}
                </span>
                <div>
                    <p class="text-3xl font-semibold text-marino leading-none">{{ $stat['value'] }}</p>
                    <p class="text-sm text-ink mt-2">{{ $stat['label'] }}</p>
                    <p class="text-xs text-muted">{{ $stat['hint'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid xl:grid-cols-3 gap-4 mt-6">
        <section class="xl:col-span-2 bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 flex flex-col gap-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-marino">Puntos otorgados vs. canjeados</h2>
                    <p class="text-xs text-muted">Últimas 8 semanas</p>
                </div>
                <div class="flex gap-4 text-xs text-muted">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-azul"></span>Otorgados</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-dermo"></span>Canjeados</span>
                </div>
            </div>

            <div class="h-56 flex items-end gap-3 border-b border-black/5 pb-px" role="img" aria-label="Gráfica de puntos otorgados y canjeados por semana">
                @foreach ($series as $week)
                    <div class="flex-1 flex flex-col items-center gap-2 h-full justify-end min-w-0">
                        <div class="w-full flex items-end justify-center gap-1 h-full">
                            <div class="flex-1 max-w-[24px] rounded-t-md bg-azul" style="height: {{ $pct($week['granted']) }}%" title="{{ number_format($week['granted']) }} pts otorgados"></div>
                            <div class="flex-1 max-w-[24px] rounded-t-md bg-dermo" style="height: {{ $pct($week['redeemed']) }}%" title="{{ number_format($week['redeemed']) }} pts canjeados"></div>
                        </div>
                        <span class="text-[11px] text-muted tabular-nums">{{ $week['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 flex flex-col gap-4">
            <h2 class="font-semibold text-marino">Requiere atención</h2>

            @forelse ($alerts as $alert)
                @php($tone = $tones[$alert['tone']])
                <div class="flex items-center gap-3 rounded-xl p-3 {{ $tone['box'] }}">
                    <span class="h-9 w-1 rounded-full {{ $tone['bar'] }} shrink-0"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-marino truncate">{{ $alert['title'] }}</p>
                        <p class="text-xs {{ $tone['text'] }}">{{ $alert['detail'] }}</p>
                    </div>
                    @if ($alert['href'])
                        <a href="{{ $alert['href'] }}" wire:navigate class="text-xs font-semibold text-azul hover:underline shrink-0">Revisar</a>
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center gap-2 py-8 text-muted">
                    @svg('heroicon-o-check-circle', 'h-8 w-8 text-emerald-500')
                    <p class="text-sm">Todo en orden por ahora.</p>
                </div>
            @endforelse
        </section>
    </div>

    <div class="grid md:grid-cols-2 gap-4 mt-6">
        @foreach ($shortcuts as $shortcut)
            <a href="{{ route($shortcut['route']) }}" wire:navigate class="group bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-5 flex items-center gap-4 transition-shadow hover:shadow-md">
                <span class="inline-flex items-center justify-center h-11 w-11 rounded-xl bg-dermo/50 text-marino shrink-0">
                    {{ svg('heroicon-'.$shortcut['icon'], 'h-5 w-5') }}
                </span>
                <div class="flex-1">
                    <p class="font-semibold text-marino">{{ $shortcut['label'] }}</p>
                    <p class="text-sm text-muted">{{ $shortcut['desc'] }}</p>
                </div>
                <span class="text-muted group-hover:text-azul transition-colors">{{ svg('heroicon-o-chevron-right', 'h-5 w-5') }}</span>
            </a>
        @endforeach
    </div>
</x-admin.layout>
