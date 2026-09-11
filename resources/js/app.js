import {
    aplicarFormatoMilesEnInput,
    formatearMiles,
    manejarBeforeInputMiles,
    manejarKeydownMiles,
    valorParaEnviar,
} from './miles.js';

const $ = window.jQuery;

if ($) {
    const normalizarMilesDelForm = (form) => {
        form.querySelectorAll('[data-miles]').forEach((input) => {
            input.value = valorParaEnviar(input.value);
        });
    };

    // keydown: más fiable que beforeinput en algunos WebKit/iOS PWA.
    $(document).on('keydown', '[data-miles]', function (evento) {
        if (manejarKeydownMiles(evento.originalEvent || evento, this)) {
            evento.preventDefault();
        }
    });

    $(document).on('beforeinput', '[data-miles]', function (evento) {
        const nativo = evento.originalEvent;
        if (manejarBeforeInputMiles(nativo, this)) {
            evento.preventDefault();
        }
    });

    $(document).on('input', '[data-miles]', function () {
        aplicarFormatoMilesEnInput(this);
    });

    $('[data-miles]').each(function () {
        this.value = formatearMiles(this.value);
    });

    $(document).on('submit', 'form', function (evento) {
        const form = this;
        if (form.hasAttribute('data-swal-confirm')) {
            // Lo normaliza Swal al confirmar (submit nativo no dispara este handler otra vez).
            return;
        }
        normalizarMilesDelForm(form);

        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') {
            return;
        }
        if (form.dataset.submitting === '1') {
            evento.preventDefault();
            return false;
        }
        form.dataset.submitting = '1';
        $(form).find('button[type="submit"], input[type="submit"]').prop('disabled', true);
    });

    $('[data-mov-tabs]').each(function () {
        const $tabs = $(this);
        const $list = $tabs.parent().find('[data-mov-list]');
        if (! $list.length) {
            return;
        }
        $tabs.on('click', '[data-mov-filter]', function () {
            const $boton = $(this);
            $tabs.find('[data-mov-filter]').removeClass('is-active');
            $boton.addClass('is-active');
            const filtro = $boton.attr('data-mov-filter');
            $list.find('[data-mov-tipo]').each(function () {
                const tipo = this.getAttribute('data-mov-tipo');
                this.hidden = filtro !== 'todos' && tipo !== filtro;
            });
        });
    });

    $('[data-debt-carousel]').each(function () {
        const $root = $(this);
        const $slides = $root.find('[data-debt-slide]');
        const $dots = $root.find('[data-debt-dot]');
        if ($slides.length <= 1) {
            return;
        }
        let indice = 0;
        const intervalo = Number($root.attr('data-interval') || 5000);
        const mostrar = (siguiente) => {
            $slides.eq(indice).prop('hidden', true);
            indice = (siguiente + $slides.length) % $slides.length;
            $slides.eq(indice).prop('hidden', false);
            $dots.removeClass('is-active').eq(indice).addClass('is-active');
        };
        let timer = window.setInterval(() => mostrar(indice + 1), intervalo);
        $dots.on('click', function () {
            const destino = Number(this.getAttribute('data-debt-dot'));
            if (Number.isNaN(destino) || destino === indice) {
                return;
            }
            window.clearInterval(timer);
            mostrar(destino);
            timer = window.setInterval(() => mostrar(indice + 1), intervalo);
        });
    });

    $('[data-recurrente-form], [data-ingreso-form]').each(function () {
        const $form = $(this);
        const $toggle = $form.find('[data-recurrente-toggle], [data-ingreso-recurrente]');
        const $fields = $form.find('[data-recurrente-fields], [data-ingreso-recurrente-fields]');
        const $periodicidad = $form.find('[data-recurrente-periodicidad], [data-ingreso-periodicidad]');
        const sincronizar = () => {
            const activo = $toggle.is(':checked');
            $fields.prop('hidden', !activo);
            $periodicidad.prop('disabled', !activo);
            if (activo && ! $periodicidad.val()) {
                $periodicidad.val('mensual');
            }
        };
        $toggle.on('change', sincronizar);
        sincronizar();
    });

    if (window.Swal) {
        $(document).on('submit', '[data-swal-confirm]', function (evento) {
            evento.preventDefault();
            const form = this;
            if (form.dataset.submitting === '1') {
                return;
            }
            Swal.fire({
                title: form.dataset.swalTitle || '¿Confirmar?',
                text: form.dataset.swalText || '',
                icon: form.dataset.swalIcon || 'question',
                showCancelButton: true,
                confirmButtonText: form.dataset.swalConfirmText || 'Confirmar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ea5455',
                cancelButtonColor: '#252b3b',
                reverseButtons: true,
            }).then((resultado) => {
                if (resultado.isConfirmed) {
                    form.dataset.submitting = '1';
                    normalizarMilesDelForm(form);
                    const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    submitters.forEach((el) => {
                        el.disabled = true;
                    });
                    HTMLFormElement.prototype.submit.call(form);
                }
            });
        });

        const flash = document.getElementById('flash-status');
        if (flash) {
            let mensaje = flash.textContent;
            try {
                mensaje = JSON.parse(flash.textContent);
            } catch (e) {
                // texto plano
            }
            if (mensaje) {
                const esMovilOPwa = window.matchMedia('(max-width: 767.98px)').matches
                    || window.matchMedia('(display-mode: standalone)').matches
                    || window.navigator.standalone === true;
                Swal.fire({
                    toast: true,
                    // En iOS/PWA el top queda bajo el header sticky; abajo siempre se ve.
                    position: esMovilOPwa ? 'bottom' : 'top-end',
                    icon: 'success',
                    title: mensaje,
                    showConfirmButton: false,
                    timer: 2800,
                    timerProgressBar: true,
                });
            }
        }
    }
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const base = (document.querySelector('meta[name="app-base-path"]')?.getAttribute('content') || '').replace(/\/$/, '');
        const swUrl = `${base}/sw.js`;
        const scope = `${base}/`;
        navigator.serviceWorker.register(swUrl, { scope }).then((reg) => {
            // iOS “Añadir a inicio” / PWA: avisar si hay SW nuevo tras deploy Plesk.
            reg.addEventListener('updatefound', () => {
                const neu = reg.installing;
                if (! neu) {
                    return;
                }
                neu.addEventListener('statechange', () => {
                    if (neu.state !== 'installed' || ! navigator.serviceWorker.controller) {
                        return;
                    }
                    const bar = document.querySelector('[data-pwa-update]');
                    if (bar) {
                        bar.hidden = false;
                    }
                });
            });
        }).catch(() => {});
    });

    document.querySelector('[data-pwa-update-reload]')?.addEventListener('click', () => {
        window.location.reload();
    });
}

const banner = document.querySelector('[data-pwa-install]');
const dismissedKey = 'finanzas-pwa-dismissed';
const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
    || (/macintosh/i.test(navigator.userAgent) && navigator.maxTouchPoints > 1 && 'ontouchend' in document);

if (banner && ! isStandalone && localStorage.getItem(dismissedKey) !== '1') {
    const copy = banner.querySelector('[data-pwa-install-copy]');
    const accept = banner.querySelector('[data-pwa-install-accept]');
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        deferredPrompt = evento;
        banner.hidden = false;
        if (accept) {
            accept.hidden = false;
        }
    });

    if (isIos) {
        banner.hidden = false;
        const hintIos = banner.querySelector('[data-pwa-ios-hint]');
        if (hintIos) {
            hintIos.hidden = false;
        }
        if (copy) {
            copy.hidden = true;
        }
        if (accept) {
            accept.hidden = true;
        }
    }

    accept?.addEventListener('click', async () => {
        if (! deferredPrompt) {
            return;
        }
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        banner.hidden = true;
    });

    banner.querySelector('[data-pwa-install-dismiss]')?.addEventListener('click', () => {
        localStorage.setItem(dismissedKey, '1');
        banner.hidden = true;
    });
}
