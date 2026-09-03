@extends('layouts.guest', ['title' => 'Iniciar sesión'])

@section('content')
<div class="text-center mb-4">
    <div class="brand-mark">$</div>
    <h1 class="h3 mb-1">Bienvenido</h1>
    <p class="text-secondary mb-0">Organiza tus finanzas desde cualquier lugar.</p>
</div>
<form method="POST" action="{{ route('auth.login') }}" class="vstack gap-3">
    @csrf
    <div>
        <label for="email" class="form-label">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div>
        <label for="password" class="form-label">Contraseña</label>
        <input id="password" name="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" autocomplete="current-password" required>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-check">
        <input id="remember" name="remember" type="checkbox" value="1" class="form-check-input">
        <label for="remember" class="form-check-label">Mantener sesión iniciada</label>
    </div>
    <button class="btn btn-primary btn-lg w-100" type="submit">Ingresar</button>
</form>
<p class="text-center mt-4 mb-0">¿Aún no tienes cuenta? <a href="{{ route('register') }}">Crear cuenta</a></p>
@endsection
