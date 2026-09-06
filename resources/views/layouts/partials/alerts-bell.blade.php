<div class="header-alerts" data-alerts>
    <button
        type="button"
        class="header-bell"
        data-bs-toggle="offcanvas"
        data-bs-target="#alerts-sheet"
        aria-controls="alerts-sheet"
        aria-label="Alertas{{ ($alertasCount ?? 0) > 0 ? ' ('.($alertasCount ?? 0).')' : '' }}"
    >
        @include('layouts.partials.icon', ['name' => 'bell', 'class' => 'ui-icon'])
        @if(($alertasCount ?? 0) > 0)
            <span class="header-bell__badge">{{ $alertasCount > 9 ? '9+' : $alertasCount }}</span>
        @endif
    </button>
</div>
