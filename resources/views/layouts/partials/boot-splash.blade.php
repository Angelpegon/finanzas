<div id="boot-splash" role="status" aria-live="polite" aria-label="Cargando Finanzas">
    <div class="boot-splash__inner">
        <img
            class="boot-splash__logo"
            src="{{ asset('icons/icon-192.png') }}"
            width="72"
            height="72"
            alt=""
            decoding="async"
            onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'boot-splash__mark',textContent:'$'}))"
        >
        <p class="boot-splash__name">Finanzas</p>
        <p class="boot-splash__hint">Cargando…</p>
    </div>
</div>
<script>
    (function () {
        var el = document.getElementById('boot-splash');
        if (! el || document.documentElement.classList.contains('boot-splash-skip')) {
            if (el) el.remove();
            return;
        }
        var KEY = 'finanzas-boot-splash';
        var MIN_MS = 480;
        var MAX_MS = 2800;
        var start = Date.now();
        var done = false;
        function hide() {
            if (done) return;
            done = true;
            try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
            var wait = Math.max(0, MIN_MS - (Date.now() - start));
            setTimeout(function () {
                el.classList.add('is-done');
                setTimeout(function () { el.remove(); }, 340);
            }, wait);
        }
        if (document.readyState === 'complete') {
            hide();
        } else {
            window.addEventListener('load', hide);
        }
        setTimeout(hide, MAX_MS);
    })();
</script>
