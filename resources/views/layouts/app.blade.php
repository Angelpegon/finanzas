<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Finanzas' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell">
    @auth
    <aside class="desktop-sidebar">
        <a class="sidebar-brand" href="{{ route('app.situacion') }}"><span class="brand-mark brand-mark--small">▥</span><span><strong>Finanzas</strong><small>Personales</small></span></a>
        <nav class="sidebar-nav">
            <a class="{{ request()->routeIs('app.situacion') ? 'active' : '' }}" href="{{ route('app.situacion') }}">▦ <span>Dashboard</span></a>
            <p>Finanzas</p>
            <a href="{{ route('app.cuentas.index') }}">▤ <span>Cuentas</span></a>
            <a href="{{ route('app.ingresos.create') }}">↗ <span>Ingresos</span></a>
            <a href="{{ route('app.gastos.create') }}">↘ <span>Gastos</span></a>
            <a href="{{ route('app.pagos.index') }}">⇄ <span>Transferencias y pagos</span></a>
            <p>Deudas</p>
            <a href="{{ route('app.deudas.index') }}">▣ <span>Créditos y deudas</span></a>
            <a href="{{ route('app.tarjetas.index') }}">▤ <span>Tarjetas</span></a>
            <p>Planificación</p>
            <a href="{{ route('app.presupuestos.index') }}">▥ <span>Presupuesto</span></a>
            <a href="{{ route('app.metas.index') }}">★ <span>Metas</span></a>
            <a href="{{ route('app.calendario') }}">▦ <span>Calendario</span></a>
            <p>Análisis</p>
            <a href="{{ route('app.proyecciones') }}">≈ <span>Proyecciones</span></a>
        </nav>
        <div class="sidebar-summary"><small>Resumen rápido</small><strong>@cop($situacion['dinero_disponible_real_centavos'] ?? 0)</strong><span>Disponible</span></div>
    </aside>
    @endauth
    <header class="app-header px-3 py-3 d-flex justify-content-between align-items-center">
        <a class="app-brand" href="{{ route('app.situacion') }}">
            <span class="brand-mark brand-mark--small">$</span>
            <span>Finanzas</span>
        </a>
        @auth
            <div class="header-user"><span class="header-bell">♧</span><span class="avatar avatar--small">{{ strtoupper(substr(auth()->user()->nombre, 0, 1)) }}</span><span class="header-name">Hola, {{ auth()->user()->nombre }}</span><form method="POST" action="{{ route('auth.logout') }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Salir</button></form></div>
        @endauth
    </header>
    <main class="container app-content py-4">@yield('content')</main>
    @auth
        <nav class="bottom-nav">
            <a class="{{ request()->routeIs('app.situacion') ? 'active' : '' }}" href="{{ route('app.situacion') }}"><span class="nav-icon">⌂</span><span>Inicio</span></a>
            <a class="{{ request()->routeIs('app.cuentas.*') ? 'active' : '' }}" href="{{ route('app.cuentas.index') }}"><span class="nav-icon">▤</span><span>Cuentas</span></a>
            <a class="{{ request()->routeIs('app.pagos.*') ? 'active' : '' }}" href="{{ route('app.pagos.index') }}"><span class="nav-icon">⇄</span><span>Movimientos</span></a>
            <a class="{{ request()->routeIs('app.proyecciones') ? 'active' : '' }}" href="{{ route('app.proyecciones') }}"><span class="nav-icon">▥</span><span>Reportes</span></a>
            <a class="{{ request()->routeIs('app.metas.*', 'app.presupuestos.*', 'app.calendario', 'app.deudas.*', 'app.tarjetas.*') ? 'active' : '' }}" href="{{ route('app.metas.index') }}"><span class="nav-icon">•••</span><span>Más</span></a>
        </nav>
    @endauth
</body>
</html>
