@php
    $bagName = $bag ?? 'default';
    $bagErrors = $errors->getBag($bagName);
@endphp
@if ($bagErrors->any())
    <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
        @if ($bagErrors->has('form'))
            {{ $bagErrors->first('form') }}
        @else
            Revisa los campos marcados en este formulario.
        @endif
    </div>
@endif
