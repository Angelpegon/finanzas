{{-- Pintado inmediato: CSS crítico + skip por sesión (evita flash en navegación MPA). --}}
<script>
    (function () {
        try {
            if (sessionStorage.getItem('finanzas-boot-splash')) {
                document.documentElement.classList.add('boot-splash-skip');
            }
        } catch (e) {}
    })();
</script>
<style>
    #boot-splash {
        position: fixed;
        inset: 0;
        z-index: 20000;
        display: grid;
        place-items: center;
        margin: 0;
        padding: max(1.5rem, env(safe-area-inset-top, 0px)) max(1.5rem, env(safe-area-inset-right, 0px)) max(1.5rem, env(safe-area-inset-bottom, 0px)) max(1.5rem, env(safe-area-inset-left, 0px));
        background: #0b0e14;
        color: #e8ecf4;
        transition: opacity .32s ease, visibility .32s ease;
    }
    #boot-splash.is-done {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    html.boot-splash-skip #boot-splash {
        display: none !important;
    }
    .boot-splash__inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .85rem;
        text-align: center;
    }
    .boot-splash__logo {
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 1.15rem;
        object-fit: cover;
        box-shadow: 0 12px 28px rgb(115 103 240 / 28%);
        animation: boot-splash-pulse 1.2s ease-in-out infinite;
    }
    .boot-splash__mark {
        display: grid;
        width: 4.5rem;
        height: 4.5rem;
        place-items: center;
        border-radius: 1.15rem;
        background: linear-gradient(135deg, #4f46e5, #7367f0);
        color: #fff;
        font-size: 2rem;
        font-weight: 800;
        box-shadow: 0 12px 28px rgb(115 103 240 / 28%);
        animation: boot-splash-pulse 1.2s ease-in-out infinite;
    }
    .boot-splash__name {
        margin: 0;
        font-family: "Plus Jakarta Sans", system-ui, sans-serif;
        font-size: 1.15rem;
        font-weight: 750;
        letter-spacing: -.03em;
    }
    .boot-splash__hint {
        margin: 0;
        color: #8b93a7;
        font-family: "Plus Jakarta Sans", system-ui, sans-serif;
        font-size: .78rem;
        font-weight: 600;
    }
    @keyframes boot-splash-pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.04); opacity: .92; }
    }
</style>
