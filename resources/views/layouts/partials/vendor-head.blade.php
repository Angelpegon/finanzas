{{-- Misma vía que Ambientes: estáticos en public/assets, no npm. Vite entra después para que el tema gane. --}}
<link rel="stylesheet" href="{{ asset('assets/css/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/fontawesome/css/all.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/bootstrap/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/sweetalert2.min.css') }}">
<script src="{{ asset('assets/js/jquery-4.0.0.min.js') }}"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
