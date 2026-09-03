@extends('layouts.guest', ['title' => 'Crear cuenta'])

@section('content')
<div class="text-center mb-4">
    <div class="brand-mark">$</div>
    <h1 class="h3 mb-1">Crea tu cuenta</h1>
    <p class="text-secondary mb-0">Tu información financiera queda aislada y protegida.</p>
</div>
<form method="POST" action="{{ route('auth.register') }}" class="vstack gap-3">
    @csrf
    <div>
        <label for="nombre" class="form-label">Nombre</label>
        <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" class="form-control form-control-lg @error('nombre') is-invalid @enderror" autocomplete="name" required autofocus>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div>
        <label for="email" class="form-label">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" autocomplete="email" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div>
        <label for="password" class="form-label">Contraseña</label>
        <input id="password" name="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" autocomplete="new-password" required>
        <small class="text-secondary">Mínimo 8 caracteres.</small>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div>
        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control form-control-lg" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary btn-lg w-100" type="submit">Crear cuenta</button>
</form>
<p class="text-center mt-4 mb-0">¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
@endsection
