const $ = window.jQuery;

const yaNormalizadoParaEnvio = (valor) => /^-?\d+(\.\d{1,2})?$/.test(String(valor ?? '').trim());

const formatearMiles = (valor) => {
    const crudo = String(valor ?? '').trim();
    if (crudo === '' || crudo === '-') {
        return '';
    }

    let limpio = crudo.replace(/[^\d,.-]/g, '');
    const negativo = limpio.startsWith('-') ? '-' : '';
    limpio = limpio.replace(/-/g, '').replace(/\./g, '');
    if (!crudo.includes(',') && /^\d+\.\d{1,2}$/.test(crudo)) {
        const decimal = crudo.split('.');
        limpio = `${decimal[0]},${decimal[1]}`;
    }
    const partes = limpio.split(',');
    const soloDigitos = (partes.shift() || '').replace(/\D/g, '');
    if (soloDigitos === '' && partes.length === 0) {
        return negativo === '-' ? '-' : '';
    }
    const entero = soloDigitos.replace(/^0+(?=\d)/, '') || '0';
    const decimal = partes.length ? `,${partes.join('').slice(0, 2)}` : '';

    return `${negativo}${entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}${decimal}`;
};

const valorParaEnviar = (valor) => {
    const crudo = String(valor ?? '').trim();
    if (crudo === '' || crudo === '-') {
        return '';
    }
    if (yaNormalizadoParaEnvio(crudo)) {
        return crudo;
    }

    return crudo.replace(/\./g, '').replace(',', '.');
};

const digitosAntesDeCursor = (valor, cursor) => String(valor).slice(0, cursor).replace(/\D/g, '').length;

const posicionTrasDigitos = (valor, digitos) => {
    if (digitos <= 0) {
        return 0;
    }
    let vistos = 0;
    for (let i = 0; i < valor.length; i += 1) {
        if (/\d/.test(valor[i])) {
            vistos += 1;
            if (vistos === digitos) {
                return i + 1;
            }
        }
    }

    return valor.length;
};

if ($) {
    $('[data-miles]').each(function () {
        const $input = $(this);
        $input.val(formatearMiles($input.val()));
        $input.on('input', function () {
            const el = this;
            const digitos = digitosAntesDeCursor(el.value, el.selectionStart ?? el.value.length);
            el.value = formatearMiles(el.value);
            const pos = posicionTrasDigitos(el.value, digitos);
            el.setSelectionRange(pos, pos);
        });
        $input.closest('form').on('submit', function () {
            $input.val(valorParaEnviar($input.val()));
        });
    });

    $(document).on('submit', 'form', function (evento) {
        const form = this;
        if (form.hasAttribute('data-swal-confirm')) {
            return;
        }
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
                    // No deshabilitar botones del diálogo Swal: solo el submit del form.
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
        navigator.serviceWorker.register(swUrl, { scope }).catch(() => {});
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
