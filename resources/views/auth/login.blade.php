@extends('layouts.guest', ['title' => 'Iniciar sesión'])

@section('content')
<div class="auth-card__intro">
    <p class="auth-card__kicker">Iniciar sesión</p>
    <h2 class="auth-card__title">Bienvenido de nuevo</h2>
    <p class="auth-card__lead">Entra para ver tu situación y registrar movimientos.</p>
</div>
<form method="POST" action="{{ route('auth.login') }}" class="auth-form" novalidate>
    @csrf
    <div class="auth-field">
        <label for="email" class="form-label">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="auth-field">
        <label for="password" class="form-label">Contraseña</label>
        <input id="password" name="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" autocomplete="current-password" required>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="auth-field auth-field--inline">
        <div class="form-check">
            <input id="remember" name="remember" type="checkbox" value="1" class="form-check-input">
            <label for="remember" class="form-check-label">Mantener sesión iniciada</label>
        </div>
    </div>
    <div class="auth-actions">
        <button class="btn btn-primary btn-lg w-100" type="submit">Ingresar</button>
    </div>
</form>
<p class="auth-card__footer">¿Aún no tienes cuenta? <a href="{{ route('register') }}">Crear cuenta</a></p>
@endsection
