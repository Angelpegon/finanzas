@extends('layouts.guest', ['title' => 'Crear cuenta'])

@section('content')
<div class="auth-card__intro">
    <p class="auth-card__kicker">Registro</p>
    <h2 class="auth-card__title">Crea tu espacio</h2>
    <p class="auth-card__lead">Tu información queda aislada por usuario desde el primer día.</p>
</div>
<form method="POST" action="{{ route('auth.register') }}" class="auth-form" novalidate>
    @csrf
    <div class="auth-field">
        <label for="nombre" class="form-label">Nombre</label>
        <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" class="form-control form-control-lg @error('nombre') is-invalid @enderror" autocomplete="name" required autofocus>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="auth-field">
        <label for="email" class="form-label">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" autocomplete="email" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="auth-field">
        <label for="password" class="form-label">Contraseña</label>
        <input id="password" name="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" autocomplete="new-password" required>
        <small class="text-secondary">Mínimo 8 caracteres.</small>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="auth-field">
        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror" autocomplete="new-password" required>
        @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="auth-actions">
        <button class="btn btn-primary btn-lg w-100" type="submit">Crear cuenta</button>
    </div>
</form>
<p class="auth-card__footer">¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
@endsection
