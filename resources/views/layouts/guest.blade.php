<!doctype html>
<html lang="es">

<head>
    @include('layouts.partials.pwa-meta', ['title' => $title ?? 'Finanzas'])
    @include('layouts.partials.boot-splash-head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.partials.vendor-head')
</head>

<body class="auth-page">
    @include('layouts.partials.boot-splash')
    @include('layouts.partials.pwa-install')
    @if (session('status'))
        <script type="application/json" id="flash-status">@json(session('status'))</script>
    @endif
    <main class="auth-shell">
        <section class="auth-stage" aria-label="Finanzas">
            <div class="auth-stage__glow" aria-hidden="true"></div>
            <div class="auth-stage__copy">
                <img class="auth-stage__logo" src="{{ asset('icons/icon-192.png') }}" width="56" height="56" alt="" decoding="async"
                     onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'brand-mark brand-mark--small',textContent:'$'}))">
                <p class="auth-stage__eyebrow">Finanzas personales</p>
                <h1 class="auth-stage__title">Tu dinero, con claridad.</h1>
                <p class="auth-stage__text">Saldos reales, deudas y metas en un solo lugar. Sin hojas de cálculo ni sorpresas.</p>
                <ul class="auth-stage__points">
                    <li>Libro contable que cuadra</li>
                    <li>Disponible después de compromisos</li>
                    <li>Pensado para usar en el celular</li>
                </ul>
            </div>
        </section>
        <section class="auth-panel">
            <div class="auth-card">@yield('content')</div>
        </section>
    </main>
    @include('layouts.partials.vendor-scripts')
</body>

</html>
