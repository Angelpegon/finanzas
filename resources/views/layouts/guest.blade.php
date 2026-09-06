<!doctype html>
<html lang="es">

<head>
    @include('layouts.partials.pwa-meta', ['title' => $title ?? 'Finanzas'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.partials.vendor-head')
</head>

<body class="auth-page">
    @include('layouts.partials.pwa-install')
    @if (session('status'))
        <script type="application/json" id="flash-status">@json(session('status'))</script>
    @endif
    <main class="container min-vh-100 d-flex align-items-center py-4">
        <div class="auth-card w-100 mx-auto">@yield('content')</div>
    </main>
    @include('layouts.partials.vendor-scripts')
</body>

</html>
