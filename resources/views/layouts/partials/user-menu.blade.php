@php
    $inicial = strtoupper(substr(auth()->user()->nombre, 0, 1));
@endphp
<div class="dropdown user-menu" data-user-menu>
    <button type="button" class="user-menu__trigger" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="true">
        <span class="avatar avatar--small">{{ $inicial }}</span>
        <span class="user-menu__label">Hola, {{ auth()->user()->nombre }}</span>
        @include('layouts.partials.icon', ['name' => 'chevron-down', 'class' => 'ui-icon ui-icon--sm user-menu__chevron'])
    </button>
    <div class="dropdown-menu dropdown-menu-end user-menu__panel">
        <form method="POST" action="{{ route('auth.logout') }}" data-swal-confirm data-swal-title="¿Deseas cerrar tu sesión?" data-swal-icon="question" data-swal-confirm-text="Cerrar sesión">
            @csrf
            <button class="user-menu__item dropdown-item" type="submit">
                @include('layouts.partials.icon', ['name' => 'log-out', 'class' => 'ui-icon ui-icon--sm'])
                <span>Cerrar sesión</span>
            </button>
        </form>
    </div>
</div>
