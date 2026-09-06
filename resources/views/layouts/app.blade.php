<!doctype html>
<html lang="es">

<head>
    @include('layouts.partials.pwa-meta', ['title' => $title ?? 'Finanzas'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.partials.vendor-head')
</head>

<body class="app-shell {{ request()->routeIs('app.situacion') ? 'app-shell--dashboard' : '' }}">
    @php
        $esDashboard = request()->routeIs('app.situacion');
    @endphp
    @auth
        @php
            $navTablet = [
                ['ruta' => 'app.situacion', 'patron' => 'app.situacion', 'etiqueta' => 'Inicio'],
                ['ruta' => 'app.cuentas.index', 'patron' => 'app.cuentas.*', 'etiqueta' => 'Cuentas'],
                ['ruta' => 'app.ingresos.create', 'patron' => 'app.ingresos.*', 'etiqueta' => 'Ingresos'],
                ['ruta' => 'app.gastos.create', 'patron' => 'app.gastos.*', 'etiqueta' => 'Gastos'],
                ['ruta' => 'app.pagos.index', 'patron' => 'app.pagos.*', 'etiqueta' => 'Movimientos'],
                ['ruta' => 'app.deudas.index', 'patron' => 'app.deudas.*', 'etiqueta' => 'Deudas'],
                ['ruta' => 'app.tarjetas.index', 'patron' => 'app.tarjetas.*', 'etiqueta' => 'Tarjetas'],
                ['ruta' => 'app.presupuestos.index', 'patron' => 'app.presupuestos.*', 'etiqueta' => 'Presupuesto'],
                ['ruta' => 'app.metas.index', 'patron' => 'app.metas.*', 'etiqueta' => 'Metas'],
                ['ruta' => 'app.calendario', 'patron' => 'app.calendario', 'etiqueta' => 'Calendario'],
                ['ruta' => 'app.proyecciones', 'patron' => 'app.proyecciones', 'etiqueta' => 'Proyecciones'],
            ];
            $masActivo = request()->routeIs(
                'app.cuentas.*',
                'app.presupuestos.*',
                'app.calendario',
                'app.deudas.*',
                'app.tarjetas.*',
                'app.proyecciones',
            );
            $capturaActiva = request()->routeIs('app.ingresos.*', 'app.gastos.*');
            $alertasLista = $alertas ?? [];
            $alertasCount = count($alertasLista);
        @endphp
        <aside class="desktop-sidebar">
            <a class="sidebar-brand" href="{{ route('app.situacion') }}">
                <span class="brand-mark brand-mark--small">$</span>
                <span><strong>Finanzas</strong><small>Personales</small></span>
            </a>
            <nav class="sidebar-nav">
                <a class="{{ request()->routeIs('app.situacion') ? 'active' : '' }}" href="{{ route('app.situacion') }}">
                    @include('layouts.partials.icon', ['name' => 'chart', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Dashboard</span></a>
                <p>Finanzas</p>
                <a class="{{ request()->routeIs('app.cuentas.*') ? 'active' : '' }}"
                    href="{{ route('app.cuentas.index') }}">
                    @include('layouts.partials.icon', ['name' => 'wallet', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Cuentas</span></a>
                <a class="{{ request()->routeIs('app.ingresos.*') ? 'active' : '' }}"
                    href="{{ route('app.ingresos.create') }}">
                    @include('layouts.partials.icon', ['name' => 'arrow-up', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Ingresos</span></a>
                <a class="{{ request()->routeIs('app.gastos.*') ? 'active' : '' }}"
                    href="{{ route('app.gastos.create') }}">
                    @include('layouts.partials.icon', ['name' => 'arrow-down', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Gastos</span></a>
                <a class="{{ request()->routeIs('app.pagos.*') ? 'active' : '' }}" href="{{ route('app.pagos.index') }}">
                    @include('layouts.partials.icon', ['name' => 'exchange', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Transferencias</span></a>
                <p>Deudas</p>
                <a class="{{ request()->routeIs('app.deudas.*') ? 'active' : '' }}"
                    href="{{ route('app.deudas.index') }}">
                    @include('layouts.partials.icon', ['name' => 'file-invoice', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Créditos y deudas</span></a>
                <a class="{{ request()->routeIs('app.tarjetas.*') ? 'active' : '' }}"
                    href="{{ route('app.tarjetas.index') }}">
                    @include('layouts.partials.icon', ['name' => 'credit-card', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Tarjetas</span></a>
                <p>Planificación</p>
                <a class="{{ request()->routeIs('app.presupuestos.*') ? 'active' : '' }}"
                    href="{{ route('app.presupuestos.index') }}">
                    @include('layouts.partials.icon', ['name' => 'percent', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Presupuesto</span></a>
                <a class="{{ request()->routeIs('app.metas.*') ? 'active' : '' }}" href="{{ route('app.metas.index') }}">
                    @include('layouts.partials.icon', ['name' => 'piggy-bank', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Metas</span></a>
                <a class="{{ request()->routeIs('app.calendario') ? 'active' : '' }}"
                    href="{{ route('app.calendario') }}">
                    @include('layouts.partials.icon', ['name' => 'calendar', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Calendario</span></a>
                <p>Análisis</p>
                <a class="{{ request()->routeIs('app.proyecciones') ? 'active' : '' }}"
                    href="{{ route('app.proyecciones') }}">
                    @include('layouts.partials.icon', ['name' => 'chart-line', 'class' => 'ui-icon ui-icon--sm'])
                    <span>Proyecciones</span></a>
            </nav>
            <div class="sidebar-summary">
                <small>Disponible</small>
                <strong>@cop($situacion['dinero_disponible_real_centavos'] ?? 0)</strong>
                <span>Después de compromisos</span>
            </div>
        </aside>
    @endauth
    <header class="app-header d-flex justify-content-between align-items-center gap-3">
        <div class="app-header__lead min-w-0 flex-grow-1">
            @if (!empty($backUrl))
                <a href="{{ $backUrl }}" class="back-link">
                    @include('layouts.partials.icon', ['name' => 'chevron-left', 'class' => 'ui-icon ui-icon--xs'])
                    {{ $backLabel ?? 'Volver' }}
                </a>
            @endif
            <div class="app-header__titles">
                <h1>{{ $heading ?? $title ?? 'Finanzas' }}</h1>
                @if (!empty($subtitle))
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        <div class="app-header__utils d-flex align-items-center flex-shrink-0 gap-2">
            @yield('header-utils')
            @if (!empty($actionUrl))
                <a class="add-button" href="{{ $actionUrl }}" aria-label="{{ $actionLabel ?? 'Nuevo' }}">
                    @include('layouts.partials.icon', ['name' => 'plus', 'class' => 'ui-icon'])
                </a>
            @endif
            @auth
                @include('layouts.partials.alerts-bell')
                @include('layouts.partials.user-menu')
            @endauth
        </div>
    </header>
    @auth
        <nav class="tablet-nav" aria-label="Navegación tablet">
            @foreach ($navTablet as $item)
                <a class="{{ request()->routeIs($item['patron']) ? 'active' : '' }}"
                    href="{{ route($item['ruta']) }}">{{ $item['etiqueta'] }}</a>
            @endforeach
        </nav>
    @endauth
    @include('layouts.partials.pwa-install')
    @if (session('status'))
        <script type="application/json" id="flash-status">@json(session('status'))</script>
    @endif
    <main class="app-content {{ !empty($esDashboard) ? 'app-content--dashboard' : '' }}">
        @yield('content')
    </main>
    @auth
        <nav class="bottom-nav" aria-label="Navegación móvil">
            <a class="{{ request()->routeIs('app.situacion') ? 'active' : '' }}" href="{{ route('app.situacion') }}">
                <span class="nav-icon">@include('layouts.partials.icon', ['name' => 'house', 'class' => 'ui-icon'])</span>
                <span>Dashboard</span>
            </a>
            <a class="{{ request()->routeIs('app.pagos.*') ? 'active' : '' }}" href="{{ route('app.pagos.index') }}">
                <span class="nav-icon">@include('layouts.partials.icon', ['name' => 'list', 'class' => 'ui-icon'])</span>
                <span>Movimientos</span>
            </a>
            <button type="button" class="bottom-nav__fab {{ $capturaActiva ? 'is-open' : '' }}" data-bs-toggle="offcanvas"
                data-bs-target="#capture-sheet" aria-controls="capture-sheet" aria-label="Registrar ingreso o gasto">
                @include('layouts.partials.icon', ['name' => 'plus', 'class' => 'ui-icon'])
            </button>
            <a class="{{ request()->routeIs('app.metas.*') ? 'active' : '' }}" href="{{ route('app.metas.index') }}">
                <span class="nav-icon">@include('layouts.partials.icon', ['name' => 'bullseye', 'class' => 'ui-icon'])</span>
                <span>Metas</span>
            </a>
            <button type="button" class="{{ $masActivo ? 'active' : '' }}" data-bs-toggle="offcanvas"
                data-bs-target="#more-sheet" aria-controls="more-sheet" aria-label="Más opciones">
                <span class="nav-icon">@include('layouts.partials.icon', ['name' => 'grip', 'class' => 'ui-icon'])</span>
                <span>Más</span>
            </button>
        </nav>
        @include('layouts.partials.alerts-sheet')
        <div class="offcanvas offcanvas-bottom more-sheet" tabindex="-1" id="capture-sheet" aria-labelledby="capture-sheet-title">
            <div class="offcanvas-header">
                <h2 id="capture-sheet-title" class="offcanvas-title h5 mb-0">Registrar</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
            </div>
            <div class="offcanvas-body">
                <a href="{{ route('app.ingresos.create') }}">Registrar ingreso</a>
                <a href="{{ route('app.gastos.create') }}">Registrar gasto</a>
            </div>
        </div>
        <div class="offcanvas offcanvas-bottom more-sheet" tabindex="-1" id="more-sheet" aria-labelledby="more-sheet-title">
            <div class="offcanvas-header">
                <h2 id="more-sheet-title" class="offcanvas-title h5 mb-0">Más opciones</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
            </div>
            <div class="offcanvas-body">
                <p class="eyebrow mb-2">Tesorería</p>
                <a href="{{ route('app.cuentas.index') }}">Cuentas</a>
                <p class="eyebrow mb-2 mt-3">Deudas</p>
                <a href="{{ route('app.deudas.index') }}">Créditos y deudas</a>
                <a href="{{ route('app.tarjetas.index') }}">Tarjetas</a>
                <p class="eyebrow mb-2 mt-3">Planificación</p>
                <a href="{{ route('app.presupuestos.index') }}">Presupuesto</a>
                <a href="{{ route('app.calendario') }}">Calendario</a>
                <a href="{{ route('app.proyecciones') }}">Proyecciones</a>
            </div>
        </div>
    @endauth
    @include('layouts.partials.vendor-scripts')
</body>

</html>
