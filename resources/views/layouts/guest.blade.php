<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Finanzas' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <main class="container min-vh-100 d-flex align-items-center py-4">
        <div class="auth-card w-100 mx-auto">@yield('content')</div>
    </main>
</body>
</html>
