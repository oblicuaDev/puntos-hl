@php
    $admin = auth('admin')->user();
    $salesThisMonth = \App\Models\Sale::whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year);
    // Puntos otorgados = movimientos de acumulación (ventas + saldos iniciales importados + ajustes a favor).
    $earnedMovements = \App\Models\PointMovement::whereIn('type', ['earned', 'adjustment_credit']);
    $pointsInCirculation = (int) \App\Models\PointBatch::where('status', 'active')->where('expires_at', '>', now())->sum('points_remaining');
    $copPerRedeemedPoint = \App\Services\Points\PointRates::copValuePerRedeemedPoint();
    $redemptionRate = \App\Services\Points\PointRates::redemptionPointsPer100Cop();

    // HL-29: vencimientos del mes y lo que vence pronto.
    $expiredThisMonth = (int) -\App\Models\PointMovement::where('type', 'expired')
        ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('points');
    $expiringSoon = (int) \App\Models\PointBatch::where('status', 'active')
        ->whereBetween('expires_at', [now(), now()->addDays(30)])->sum('points_remaining');

    // HL-27: compras registradas vs. puntos que generaron vs. lo que esos puntos valen al redimir.
    $purchaseSummary = function ($query) use ($copPerRedeemedPoint) {
        $points = (int) (clone $query)->sum('points_awarded');

        return [
            'count' => (clone $query)->count(),
            'amount' => (int) (clone $query)->sum('amount_cop'),
            'points' => $points,
            'redemption_value' => (int) round($points * $copPerRedeemedPoint),
        ];
    };
    $purchases = [
        'Este mes' => $purchaseSummary($salesThisMonth),
        'Total' => $purchaseSummary(\App\Models\Sale::query()),
    ];

    $stats = [
        ['icon' => 'o-users', 'label' => 'Pacientes', 'value' => \App\Models\Patient::count(), 'hint' => 'registrados'],
        ['icon' => 'o-sparkles', 'label' => 'Puntos otorgados', 'value' => number_format((int) (clone $earnedMovements)->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('points')), 'hint' => 'este mes · '.number_format($pointsInCirculation).' vigentes en total'],
        ['icon' => 'o-clock', 'label' => 'Puntos vencidos', 'value' => number_format($expiredThisMonth), 'hint' => 'este mes · '.number_format($expiringSoon).' vencen en los próximos 30 días'],
        ['icon' => 'o-gift', 'label' => 'Premios activos', 'value' => \App\Models\Reward::where('is_active', true)->count(), 'hint' => 'disponibles en el catálogo'],
    ];

    $shortcuts = [
        ['route' => 'admin.patients.index', 'label' => 'Gestionar pacientes', 'icon' => 'o-users', 'desc' => 'Buscar, editar o bloquear cuentas'],
        ['route' => 'admin.sales.index', 'label' => 'Registrar venta', 'icon' => 'o-banknotes', 'desc' => 'Cargar ventas y asignar puntos'],
    ];

    // Últimas 8 semanas (lunes como inicio) para comparar puntos otorgados vs canjeados.
    $start = now()->startOfWeek()->subWeeks(7);
    $grantedByWeek = (clone $earnedMovements)->where('occurred_at', '>=', $start)->get()
        ->groupBy(fn ($movement) => $movement->occurred_at->copy()->startOfWeek()->format('Y-m-d'))
        ->map(fn ($group) => (int) $group->sum('points'));
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
        <div class="flex flex-col items-start md:items-end gap-2">
            <p class="text-xs text-muted uppercase tracking-wider">{{ $admin->role }}</p>
            @if ($admin->isAdmin())
                <div x-data="{ open: {{ $errors->has('desde') || $errors->has('hasta') ? 'true' : 'false' }} }" class="relative">
                    <button type="button" @click="open = !open" :aria-expanded="open" class="inline-flex items-center gap-2 rounded-xl bg-azul text-white px-4 py-2.5 text-sm font-semibold uppercase tracking-wide transition-colors hover:bg-azul-dark">
                        @svg('heroicon-o-arrow-down-tray', 'h-4 w-4')
                        Descargar reporte de movimientos
                    </button>
                    <form x-show="open" x-cloak @click.outside="open = false" method="GET" action="{{ route('admin.reports.movements') }}" class="absolute right-0 z-20 mt-2 w-72 bg-white rounded-brand shadow-lg ring-1 ring-black/10 p-4 space-y-3">
                        <p class="text-sm font-semibold text-marino">Rango de fechas</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-muted">Desde
                                <input type="date" name="desde" required value="{{ old('desde', now()->startOfMonth()->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg px-2 py-1.5 text-sm text-ink border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                            </label>
                            <label class="text-xs text-muted">Hasta
                                <input type="date" name="hasta" required value="{{ old('hasta', now()->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg px-2 py-1.5 text-sm text-ink border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                            </label>
                        </div>
                        @error('desde') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('hasta') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted">Incluye acumulaciones, redenciones, vencimientos y ajustes.</p>
                        <button type="submit" @click="setTimeout(() => open = false, 300)" class="w-full rounded-xl bg-azul text-white py-2 text-sm font-semibold hover:bg-azul-dark">Descargar Excel</button>
                    </form>
                </div>
            @endif
        </div>
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

    <section class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 mt-6" aria-labelledby="purchases-title">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
            <div>
                <h2 id="purchases-title" class="font-semibold text-marino">Compras registradas</h2>
                <p class="text-xs text-muted">Dinero facturado, puntos que generó y lo que esos puntos valen al redimirlos.</p>
            </div>
            <a href="{{ route('admin.sales.index') }}" wire:navigate class="text-xs font-semibold text-azul hover:underline">Ver ventas</a>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase tracking-wider text-muted">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Periodo</th>
                        <th class="py-2 px-4 font-semibold text-right">Compras (dinero)</th>
                        <th class="py-2 px-4 font-semibold text-right">Puntos acumulados</th>
                        <th class="py-2 pl-4 font-semibold text-right">Valor de esos puntos al redimir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @foreach ($purchases as $period => $row)
                        <tr>
                            <td class="py-3 pr-4">
                                <p class="font-medium text-ink">{{ $period }}</p>
                                <p class="text-xs text-muted">{{ number_format($row['count']) }} compra(s)</p>
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums font-semibold text-marino">${{ number_format($row['amount'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ number_format($row['points'], 0, ',', '.') }} pts</td>
                            <td class="py-3 pl-4 text-right tabular-nums text-azul font-semibold">${{ number_format($row['redemption_value'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-muted flex gap-1.5">
            @svg('heroicon-o-information-circle', 'h-4 w-4 shrink-0')
            <span>El valor al redimir usa la tasa de redención vigente ({{ rtrim(rtrim(number_format($redemptionRate, 2, ',', '.'), '0'), ',') }} pts por cada $100, es decir ${{ number_format($copPerRedeemedPoint, 2, ',', '.') }} por punto). Es lo que los pacientes pueden pagar en procedimientos con esos puntos: no es dinero recibido ni equivale al valor de las compras.</span>
        </p>
    </section>

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
