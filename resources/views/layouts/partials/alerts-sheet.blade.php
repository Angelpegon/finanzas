<div class="offcanvas offcanvas-bottom alerts-sheet" tabindex="-1" id="alerts-sheet" aria-labelledby="alerts-sheet-title">
    <div class="offcanvas-header">
        <h2 id="alerts-sheet-title" class="offcanvas-title h5 mb-0">Alertas</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar alertas"></button>
    </div>
    <div class="offcanvas-body">
        @forelse(($alertasLista ?? []) as $alerta)
            <div class="header-alerts__item header-alerts__item--{{ $alerta['nivel'] }}">
                <strong>{{ $alerta['titulo'] }}</strong>
                <span>{{ $alerta['mensaje'] }}</span>
            </div>
        @empty
            <p class="header-alerts__empty">Sin alertas por ahora.</p>
        @endforelse
    </div>
</div>
