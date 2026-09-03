import $ from 'jquery';
import 'bootstrap';

window.$ = window.jQuery = $;

const formatearMiles = (valor) => {
    let limpio = String(valor ?? '').replace(/[^\d,.-]/g, '');
    const negativo = limpio.startsWith('-') ? '-' : '';
    limpio = limpio.replace(/-/g, '').replace(/\./g, '');
    if (!String(valor ?? '').includes(',') && /^\d+\.\d{1,2}$/.test(String(valor ?? '').trim())) {
        const decimal = String(valor).trim().split('.');
        limpio = `${decimal[0]},${decimal[1]}`;
    }
    const partes = limpio.split(',');
    const entero = (partes.shift() || '').replace(/^0+(?=\d)/, '') || '0';
    const decimal = partes.length ? `,${partes.join('').slice(0, 2)}` : '';

    return `${negativo}${entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}${decimal}`;
};

const valorParaEnviar = (valor) => String(valor ?? '').replace(/\./g, '').replace(',', '.');

document.querySelectorAll('[data-miles]').forEach((input) => {
    input.value = formatearMiles(input.value);
    input.addEventListener('input', () => {
        const cursorAlFinal = input.selectionStart === input.value.length;
        input.value = formatearMiles(input.value);
        if (cursorAlFinal) input.setSelectionRange(input.value.length, input.value.length);
    });
    input.form?.addEventListener('submit', () => {
        input.value = valorParaEnviar(input.value);
    });
});
