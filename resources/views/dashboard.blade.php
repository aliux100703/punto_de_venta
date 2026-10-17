<x-admin-layout title="Resumen del negocio">
    <div class="page-heading dashboard-heading">
        <div><span class="eyebrow">TODO EN UN SOLO LUGAR</span><h1>El pulso de tu negocio.</h1><p>{{ auth()->user()->isAdministrator() ? 'Una vista clara de lo que pasa en tu negocio.' : 'Tu actividad, tus resultados y tu próximo paso.' }}</p></div>
        <div class="heading-actions"><span class="date-pill"><x-icon name="calendar" size="15" />{{ now()->locale('es')->isoFormat('D MMM, YYYY') }}</span><a href="{{ route('pos.index') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> Nueva venta</a></div>
    </div>
    <section class="welcome-banner">
        <div><span class="eyebrow">CADA DÍA ES UNA NUEVA OPORTUNIDAD</span><h2>Hola, {{ \Illuminate\Support\Str::title(explode(' ', auth()->user()->name)[0]) }}. ¡Vamos por un gran día!</h2><p>Todo listo para seguir creciendo. Consulta tus resultados, cuida tu inventario y concéntrate en tus clientes.</p></div>
        <span class="welcome-art" aria-hidden="true"><x-icon name="trend" size="37" /></span>
        <a class="btn btn-outline-primary" href="{{ route('sales.index') }}">Ver mis ventas <x-icon name="arrow" size="15" /></a>
    </section>
    @php
        $metrics = [
            ['label' => 'Ventas de hoy', 'value' => '$'.number_format($revenue, 2), 'icon' => 'trend', 'color' => '', 'detail' => $revenueChange === null ? 'Importe neto de devoluciones' : ($revenueChange >= 0 ? '+' : '').$revenueChange.'% frente a ayer'],
            ['label' => 'Operaciones', 'value' => number_format($saleCount), 'icon' => 'receipt', 'color' => 'blue', 'detail' => 'Ventas vigentes de hoy'],
            $expenses === null ? ['label' => 'Ticket promedio', 'value' => '$'.number_format($averageTicket, 2), 'icon' => 'wallet', 'color' => 'amber', 'detail' => 'Importe promedio por venta hoy'] : ['label' => 'Gastos de hoy', 'value' => '$'.number_format($expenses, 2), 'icon' => 'expense', 'color' => 'amber', 'detail' => 'Control de tus egresos diarios'],
            ['label' => 'Cotizaciones pendientes', 'value' => number_format($pendingQuotes), 'icon' => 'file', 'color' => 'violet', 'detail' => 'Propuestas vigentes por confirmar'],
        ];
        $chartMax = max(100, ceil($dailySales->max('amount') / 100) * 100);
        $points = $dailySales->values()->map(fn ($day, $index) => ['x' => 54 + $index * 606 / max(1, $dailySales->count() - 1), 'y' => 156 - $day['amount'] / $chartMax * 125, 'label' => $day['label'], 'amount' => $day['amount']]);
        $linePoints = $points->map(fn ($point) => round($point['x'], 2).','.round($point['y'], 2))->implode(' ');
        $shortMoney = fn ($value) => $value >= 1000000 ? '$'.round($value / 1000000, 1).' M' : ($value >= 1000 ? '$'.round($value / 1000, 1).' k' : '$'.number_format($value, 0));
    @endphp
    <section class="metric-grid" aria-label="Indicadores de hoy">
        @foreach($metrics as $metric)
            <article class="metric-card"><div class="metric-top"><span class="metric-label">{{ $metric['label'] }}</span><span class="metric-icon {{ $metric['color'] }}"><x-icon :name="$metric['icon']" size="17" /></span></div><strong class="metric-value">{{ $metric['value'] }}</strong><div class="metric-bottom">@if($loop->first && $revenueChange !== null && $revenueChange >= 0)<span class="metric-positive"><x-icon name="trend" size="12" /></span>@endif{{ $metric['detail'] }}</div></article>
        @endforeach
    </section>
    <div class="dashboard-main-grid">
        <section class="card">
            <div class="panel-heading"><div><h2>Comportamiento de ventas</h2><p>{{ $startDate->locale('es')->isoFormat('D MMM') }} — {{ today()->locale('es')->isoFormat('D MMM YYYY') }}</p></div><form class="range-control" method="GET" action="{{ route('dashboard') }}"><select class="form-select" name="period" aria-label="Período de la gráfica" onchange="this.form.submit()"><option value="week" @selected($period === 'week')>Últimos 7 días</option><option value="month" @selected($period === 'month')>Últimos 30 días</option></select><noscript><button class="btn btn-sm btn-outline-primary">Aplicar</button></noscript></form></div>
            <div class="chart-summary"><strong>${{ number_format($periodRevenue, 2) }}</strong><small>{{ $periodOperations }} operaciones en el período</small><span class="chart-key ms-auto">Ventas netas</span></div>
            <svg class="revenue-chart" viewBox="0 0 680 195" role="img" aria-labelledby="chart-title chart-description">
                <title id="chart-title">Ventas de los últimos {{ $dailySales->count() }} días</title><desc id="chart-description">{{ $dailySales->map(fn ($day) => $day['label'].': $'.number_format($day['amount'], 2))->implode('; ') }}</desc>
                <defs><linearGradient id="sales-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#50a780" stop-opacity=".18"/><stop offset="100%" stop-color="#50a780" stop-opacity=".015"/></linearGradient></defs>
                @foreach([0, 1, 2, 3] as $tick)
                    @php($y = 31 + $tick * 125 / 3)
                    <line x1="54" x2="660" y1="{{ $y }}" y2="{{ $y }}" stroke="#edf2f0" stroke-dasharray="3 4"/><text x="43" y="{{ $y + 3 }}" text-anchor="end">{{ $shortMoney($chartMax * (1 - $tick / 3)) }}</text>
                @endforeach
                <polygon points="54,156 {{ $linePoints }} 660,156" fill="url(#sales-fill)"/>
                <polyline points="{{ $linePoints }}" fill="none" stroke="#4d9c7c" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                @foreach($points as $point)
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ $dailySales->count() <= 7 ? 3 : 2 }}" fill="white" stroke="#4d9c7c" stroke-width="1.7"><title>{{ $point['label'] }}: ${{ number_format($point['amount'], 2) }}</title></circle>
                    @if($dailySales->count() <= 7 || $loop->first || $loop->last || $loop->index % 5 === 0)<text x="{{ $point['x'] }}" y="180" text-anchor="middle">{{ $point['label'] }}</text>@endif
                @endforeach
            </svg>
            @if($periodOperations === 0)<p class="chart-zero-state">Tu gráfica comenzará a tomar forma con las primeras ventas del período.</p>@endif
        </section>
        <section class="card"><div class="panel-heading"><div><h2>Hazlo en un momento</h2><p>Accesos para tu día a día</p></div><x-icon name="spark" size="16" class="text-muted" /></div><div class="quick-actions">
            @foreach([
                ['pos.index', 'Nueva venta', 'Listo para tu próximo cliente', 'cart', 'operate-pos'],
                ['quotes.create', 'Crear cotización', 'Una propuesta que vende', 'file', 'operate-pos'],
                ['customers.create', 'Agregar cliente', 'Haz crecer tu comunidad', 'users', 'operate-pos'],
                ['products.create', 'Nuevo producto', 'Actualiza tu catálogo', 'box', 'manage-products'],
                ['cash.index', 'Revisar mi caja', 'Ten el control de tu turno', 'wallet', 'operate-pos'],
            ] as [$route, $label, $description, $icon, $ability])
                @can($ability)<a class="quick-action" href="{{ route($route) }}"><span class="quick-action-icon"><x-icon :name="$icon" size="18" /></span><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span><x-icon name="chevron" size="13" /></a>@endcan
            @endforeach
        </div></section>
    </div>
    <div class="dashboard-bottom-grid">
        <section class="card"><div class="panel-heading"><div><h2>Ventas recientes</h2><p>{{ auth()->user()->isAdministrator() ? 'Operaciones según su fecha original, netas de devoluciones' : 'Tus últimas operaciones' }}</p></div><a href="{{ route('sales.index') }}">Ver todas <x-icon name="arrow" size="14" /></a></div>
            <div class="table-responsive"><table class="table"><thead><tr><th>Venta / cliente</th><th>Fecha</th><th>Total</th><th>Estado</th><th></th></tr></thead><tbody>
            @forelse($recentSales as $sale)<tr><td><div class="table-identity"><span><x-icon name="receipt" size="15" /></span><div><a href="{{ route('sales.show', $sale) }}">{{ $sale->folio }}</a><small>{{ $sale->customer?->name ?? 'Público general' }}</small></div></div></td><td>{{ $sale->created_at->format('d/m') }}<span class="d-block text-muted" style="font-size:8px">{{ $sale->created_at->format('H:i') }}</span></td><td class="fw-semibold">${{ number_format($sale->net_total, 2) }}</td><td><span class="status-pill {{ $sale->status === 'COMPLETADA' ? '' : 'gray' }}">{{ $sale->status === 'COMPLETADA' ? 'Completada' : $sale->status }}</span></td><td><a aria-label="Ver venta {{ $sale->folio }}" href="{{ route('sales.show', $sale) }}"><x-icon name="chevron" size="14" /></a></td></tr>
            @empty<tr><td colspan="5"><div class="empty-state"><x-icon name="receipt" size="25" /><strong>Tu próxima venta empieza aquí.</strong><p>Las operaciones que registres aparecerán en este espacio.</p><a href="{{ route('pos.index') }}" class="btn btn-sm btn-outline-primary mt-3">Registrar una venta <x-icon name="arrow" size="13" /></a></div></td></tr>@endforelse
            </tbody></table></div>
        </section>
        <section class="card"><div class="panel-heading"><div><h2>Atención al inventario</h2><p>Productos que necesitan reposición</p></div>@if($lowStockCount)<span class="status-pill amber">{{ $lowStockCount }}</span>@endif</div><div class="stock-list">
            @forelse($lowStock as $product)<a class="stock-row" href="{{ route('inventory.show', $product) }}"><span class="stock-product-icon"><x-icon name="box" size="17" /></span><span><strong>{{ \Illuminate\Support\Str::limit($product->name, 26) }}</strong><small>{{ $product->sku ?? 'Sin SKU' }}</small></span><span class="stock-level">{{ number_format($product->current_stock, $product->unit?->allows_decimals ? 2 : 0) }}<small>{{ $product->unit?->abbreviation ?? 'unidades' }}</small></span></a>
            @empty<div class="empty-state"><x-icon name="check" size="24" /><strong>Todo en orden.</strong><p>No hay alertas de existencias bajas.</p></div>@endforelse
        </div><div class="px-4 pb-4"><a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary w-100">Revisar inventario <x-icon name="arrow" size="14" /></a></div></section>
    </div>
</x-admin-layout>