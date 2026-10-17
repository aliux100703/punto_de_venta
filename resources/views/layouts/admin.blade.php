<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#12232e">
    <title>{{ $title }} · InnovateCode POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $currentUser = auth()->user();
    $initials = collect(explode(' ', trim($currentUser->name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $layoutCash = $currentUser->cashRegisters()->where('status', 'ABIERTA')->latest('opened_at')->first();
    $sellerReadOnly = $currentUser->hasRole('Vendedor') && ! $layoutCash;
    $stockAlerts = \App\Models\Product::where('active', true)->whereColumn('current_stock', '<=', 'minimum_stock')->count();
    $sections = [
        'OPERACIÓN' => [
            'dashboard' => ['Resumen', 'dashboard', 'operate-pos', 'grid'],
            'pos.index' => ['Punto de venta', 'pos.*', 'operate-pos', 'cart'],
            'sales.index' => ['Ventas', 'sales.*', 'operate-pos', 'receipt'],
            'sale-returns.index' => ['Devoluciones', 'sale-returns.*', 'manage-sales', 'expense'],
            'quotes.index' => ['Cotizaciones', 'quotes.*', 'operate-pos', 'file'],
            'cash.index' => ['Mi caja', 'cash.*', 'operate-pos', 'wallet'],
        ],
        'MI NEGOCIO' => [
            'products.index' => ['Productos', 'products.*', 'operate-pos', 'box'],
            'inventory.index' => ['Inventario', 'inventory.*', 'operate-pos', 'layers'],
            'customers.index' => ['Clientes', 'customers.*', 'operate-pos', 'users'],
            'expenses.index' => ['Gastos', 'expenses.*', 'manage-expenses', 'expense'],
        ],
        'ADMINISTRACIÓN' => [
            'catalogs.index' => ['Catálogos', 'catalogs.*', 'manage-catalogs', 'settings'],
            'audit.index' => ['Bitácora', 'audit.*', 'view-audit', 'shield'],
            'users.index' => ['Usuarios y roles', 'users.*', 'manage-users', 'shield'],
        ],
    ];
    $descriptions = ['sale-returns' => 'Cada ajuste con su motivo, reembolso e historial.', 'products' => 'Organiza tu catálogo y mantén tus productos al día.', 'inventory' => 'Cada entrada, cada salida y todas tus existencias en un solo lugar.', 'sales' => $currentUser->isAdministrator() ? 'Consulta las operaciones y los comprobantes de tu negocio.' : 'Consulta tus operaciones y comprobantes de venta.', 'quotes' => 'De una buena propuesta a tu próxima venta.', 'customers' => 'Conoce mejor a quienes confían en tu negocio.', 'cash' => 'Controla tu turno, tus movimientos y cada peso de tu caja.', 'expenses' => 'Lleva un registro claro de los gastos de tu negocio.', 'catalogs' => 'Las bases para mantener tu negocio organizado.', 'users' => 'Tu equipo, con el acceso que necesita.', 'profile' => 'Administra tu información personal y la seguridad de tu cuenta.'];
    $sectionKey = explode('.', request()->route()->getName() ?? '')[0];
@endphp
<body class="pos-app" x-data="{ sidebarOpen: false, isMobile: window.innerWidth <= 800 }" @resize.window="isMobile = window.innerWidth <= 800; if (!isMobile) sidebarOpen = false" :class="{ 'menu-is-open': sidebarOpen }" @keydown.escape.window="if (sidebarOpen) { sidebarOpen = false; $refs.menuToggle.focus(); }">
<a href="#main-content" class="skip-link">Ir al contenido</a>
<button class="sidebar-backdrop d-print-none" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" aria-label="Cerrar menú"></button>
<aside id="app-sidebar" x-ref="sidebarNav" :inert="isMobile && !sidebarOpen" :aria-hidden="isMobile && !sidebarOpen" class="app-sidebar d-print-none" :class="{ 'is-open': sidebarOpen }">
    <a class="app-brand" href="{{ route('dashboard') }}"><span class="brand-mark"><x-icon name="layers" size="24" /></span><span>Innovate<span class="brand-light">Code</span><small>PUNTO DE VENTA</small></span></a>
    <div class="workspace-label"><span class="workspace-dot"></span> Mi espacio de trabajo <span class="workspace-plan">POS</span></div>
    <nav class="sidebar-navigation" aria-label="Menú principal">
        @foreach($sections as $section => $links)
            @if(collect($links)->contains(fn ($link) => $currentUser->can($link[2])))
                <p class="navigation-label">{{ $section }}</p>
                @foreach($links as $route => [$label, $pattern, $ability, $icon])
                    @can($ability)
                        <a href="{{ route($route) }}" class="sidebar-link {{ request()->routeIs($pattern) ? 'is-active' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif><x-icon :name="$icon" /><span>{{ $label }}</span>@if($route === 'pos.index')<span class="nav-shortcut">F2</span>@endif @if(request()->routeIs($pattern))<span class="active-indicator"></span>@endif</a>
                    @endcan
                @endforeach
            @endif
        @endforeach
    </nav>
    @can('operate-pos')
    <a class="sidebar-cash" href="{{ route('cash.index') }}"><span class="cash-state-icon {{ $layoutCash ? 'is-open' : '' }}"><x-icon name="wallet" /></span><span><strong>{{ $layoutCash ? 'Tu caja está abierta' : 'Comienza tu turno' }}</strong><small>{{ $layoutCash ? 'Ver movimientos de caja' : 'Abre tu caja para cobrar en efectivo' }}</small></span><x-icon name="chevron" size="14" /></a>
    @endcan
    <div class="sidebar-bottom"><span class="workspace-dot"></span> InnovateCode POS <span>v1.0</span></div>
</aside>
<div class="app-shell">
    <header class="app-topbar d-print-none">
        <div class="topbar-left"><button type="button" class="icon-button mobile-menu-toggle" x-ref="menuToggle" @click="sidebarOpen = !sidebarOpen; if (sidebarOpen) $nextTick(() => $refs.sidebarNav.querySelector('a').focus())" :aria-expanded="sidebarOpen" aria-controls="app-sidebar" aria-label="Abrir menú"><x-icon name="menu" /></button><span class="breadcrumb-home">Mi negocio</span><x-icon name="chevron" size="13" /><span class="breadcrumb-current">{{ $title }}</span></div>
        <div class="topbar-right">
            @can('operate-pos')
            <form class="topbar-search" action="{{ route('inventory.index') }}" method="GET" role="search"><x-icon name="search" size="17" /><input id="globalSearch" name="search" type="search" aria-label="Buscar en inventario" placeholder="Buscar en inventario…" maxlength="200"><kbd>Ctrl K</kbd></form>
            <div class="dropdown"><button class="icon-button notification-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Avisos de inventario"><x-icon name="bell" />@if($stockAlerts)<span class="notification-dot"></span>@endif</button><div class="dropdown-menu dropdown-menu-end notification-menu"><strong>Tu inventario</strong><p>{{ $stockAlerts ? $stockAlerts.' productos necesitan reposición.' : 'No hay alertas de existencias bajas.' }}</p><a class="btn btn-sm btn-outline-primary" href="{{ route('inventory.index') }}">Revisar inventario <x-icon name="arrow" size="15" /></a></div></div>
            @endcan
            <span class="topbar-divider"></span>
            <div class="dropdown"><button class="profile-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="user-avatar">{{ mb_strtoupper($initials) }}</span><span class="profile-details"><strong>{{ \Illuminate\Support\Str::limit($currentUser->name, 25) }}</strong><small>{{ $currentUser->role?->name ?? 'Sin rol' }}</small></span><x-icon name="down" size="14" /></button><ul class="dropdown-menu dropdown-menu-end"><li class="dropdown-user-label">{{ $currentUser->email }}</li><li><a class="dropdown-item" href="{{ route('profile.edit') }}"><x-icon name="user" size="17" /> Mi perfil</a></li><li><hr class="dropdown-divider"></li><li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><x-icon name="logout" size="17" /> Cerrar sesión</button></form></li></ul></div>
        </div>
    </header>
    <main id="main-content" class="app-content">
        @if($sellerReadOnly)<div class="alert alert-info d-print-none"><strong>Modo consulta.</strong> Abre tu caja para vender o modificar datos. <a href="{{ route('shift.start') }}">Abrir mi caja</a></div>@endif
        @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
        @if(session('success'))<div role="status" class="alert alert-success"><x-icon name="check" size="19" /> {{ session('success') }}</div>@endif
        @if($errors->any())<div role="alert" class="alert alert-danger"><strong>Revisa los siguientes datos</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @unless(request()->routeIs('dashboard', 'pos.index'))
            <div class="page-heading"><div><span class="eyebrow">MI ESPACIO DE TRABAJO</span><h1>{{ $title }}</h1><p>{{ $descriptions[$sectionKey] ?? 'Todos los detalles, en un solo lugar.' }}</p></div><span class="date-pill"><x-icon name="calendar" size="16" />{{ now()->locale('es')->isoFormat('D MMM YYYY') }}</span></div>
        @endunless
        {{ $slot }}
        <footer class="app-footer d-print-none"><span>© {{ now()->year }} InnovateCode<span class="footer-dot">·</span>Hecho para hacer crecer tu negocio.</span><span><span class="connection-dot"></span> Sesión activa <span class="footer-dot">·</span> MXN</span></footer>
    </main>
</div>
@if($sellerReadOnly)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const allowed = @json([route('cash.open'), route('logout'), route('shift.decline')]);
    document.querySelectorAll('form').forEach(form => {
        if (form.method.toLowerCase() === 'get' || allowed.includes(form.action)) return;
        const fieldset = document.createElement('fieldset'); fieldset.disabled = true;
        while (form.firstChild) fieldset.appendChild(form.firstChild);
        form.appendChild(fieldset);
    });
    document.querySelectorAll('a[href]').forEach(link => {
        const url = new URL(link.href);
        if (url.origin === location.origin && (/\/(create|edit)$/.test(url.pathname) || url.pathname === '/pos')) {
            link.removeAttribute('href'); link.setAttribute('aria-disabled', 'true'); link.classList.add('disabled');
        }
    });
});
</script>
@endif
</body>
</html>