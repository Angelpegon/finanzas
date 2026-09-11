/**
 * Formato de montos COP en UI: miles con punto, decimales con coma (máx. 2).
 *
 * Bug clásico: el teclado inserta "." y el caret se calcula como zona "entero"
 * (porque aún no hay ","), luego el formato pone "1.000," pero el cursor queda
 * ANTES de la coma y el siguiente dígito engorda los miles.
 */

export const yaNormalizadoParaEnvio = (valor) => /^-?\d+(\.\d{1,2})?$/.test(String(valor ?? '').trim());

const ES_SEP_DECIMAL = (ch) => ch === ',' || ch === '.' || ch === '٫' || ch === '，';

/**
 * Normaliza el crudo del input a forma con coma decimal (sin pelear con miles).
 * @param {string} crudo
 * @returns {string}
 */
function normalizarCrudo(crudo) {
    let s = String(crudo ?? '');
    const recortado = s.trim();
    if (recortado === '' || recortado === '-') {
        return recortado === '-' ? '-' : '';
    }

    const neg = /^\s*-/.test(s);
    const cuerpo = recortado.replace(/^\s*-/, '');

    // Ya tiene coma → quitar puntos (miles) y basura.
    if (cuerpo.includes(',')) {
        const i = cuerpo.indexOf(',');
        const ent = cuerpo.slice(0, i).replace(/\D/g, '');
        const dec = cuerpo.slice(i + 1).replace(/\D/g, '').slice(0, 2);
        return `${neg ? '-' : ''}${ent},${dec}`;
    }

    // Servidor / old: 1500.5
    if (/^\d+\.\d{1,2}$/.test(cuerpo)) {
        return `${neg ? '-' : ''}${cuerpo.replace('.', ',')}`;
    }

    // Punto final o punto + hasta 2 decimales que NO es miles (teclado móvil).
    // "1000." / "1000.5" / "1.000." → decimal
    if (/\.\d{0,2}$/.test(cuerpo)) {
        const lastDot = cuerpo.lastIndexOf('.');
        const after = cuerpo.slice(lastDot + 1);
        const before = cuerpo.slice(0, lastDot);
        // Si lo de antes del último punto es solo miles bien formados + dígitos, o dígitos planos:
        const beforeDigits = before.replace(/\./g, '');
        if (/^\d*$/.test(beforeDigits) && after.length <= 2) {
            return `${neg ? '-' : ''}${beforeDigits},${after}`;
        }
    }

    // Solo enteros (posiblemente con puntos de miles): conservar dígitos.
    return `${neg ? '-' : ''}${cuerpo.replace(/\D/g, '')}`;
}

/**
 * @param {unknown} valor
 * @returns {string}
 */
export function formatearMiles(valor) {
    const norm = normalizarCrudo(String(valor ?? ''));
    if (norm === '' || norm === '-') {
        return norm;
    }

    const neg = norm.startsWith('-');
    const cuerpo = neg ? norm.slice(1) : norm;
    const coma = cuerpo.indexOf(',');
    let enteros;
    let decimales = null;
    let comaAbierta = false;

    if (coma === -1) {
        enteros = cuerpo;
    } else {
        comaAbierta = true;
        enteros = cuerpo.slice(0, coma);
        decimales = cuerpo.slice(coma + 1).slice(0, 2);
    }

    enteros = enteros.replace(/\D/g, '');
    if (enteros === '' && ! comaAbierta) {
        return neg ? '-' : '';
    }
    enteros = enteros.replace(/^0+(?=\d)/, '') || '0';

    const conMiles = enteros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const prefijo = neg ? '-' : '';
    if (! comaAbierta) {
        return `${prefijo}${conMiles}`;
    }

    return `${prefijo}${conMiles},${decimales ?? ''}`;
}

/**
 * @param {unknown} valor
 * @returns {string}
 */
export function valorParaEnviar(valor) {
    const crudo = String(valor ?? '').trim();
    if (crudo === '' || crudo === '-') {
        return '';
    }
    if (yaNormalizadoParaEnvio(crudo)) {
        return crudo;
    }

    return formatearMiles(crudo).replace(/\./g, '').replace(',', '.');
}

/**
 * Interpreta la zona del cursor sobre el valor CRUDO (antes de formatear),
 * tratando "." final / decimales con punto como zona decimal.
 *
 * @param {string} valor
 * @param {number} cursor
 */
export function infoCursorMiles(valor, cursor) {
    const antes = String(valor).slice(0, Math.max(0, cursor));
    const normAntes = normalizarCrudo(antes.endsWith('.') && ! antes.includes(',') ? `${antes},` : antes);
    // Si el usuario acaba de tipear "." / ",", normAntes termina en "," o tiene coma.
    const tieneComa = normAntes.includes(',') || /[.,]$/.test(antes.trim());

    if (! tieneComa) {
        const digitos = normalizarCrudo(antes).replace(/^-/, '').replace(/\D/g, '').length;
        return { zona: 'entero', digitos, digitosDecimal: 0 };
    }

    const n = normalizarCrudo(antes.includes(',') || /[.,]$/.test(antes) ? antes.replace(/\.$/, ',') : antes);
    const cuerpo = n.replace(/^-/, '');
    const i = cuerpo.indexOf(',');
    const ent = i === -1 ? cuerpo : cuerpo.slice(0, i);
    const dec = i === -1 ? '' : cuerpo.slice(i + 1);

    return {
        zona: 'decimal',
        digitos: ent.replace(/\D/g, '').length,
        digitosDecimal: dec.replace(/\D/g, '').length,
    };
}

/**
 * @param {string} valor
 * @param {{ zona: 'entero'|'decimal', digitos: number, digitosDecimal: number }} info
 */
export function posicionDesdeInfoMiles(valor, info) {
    const texto = String(valor);
    if (info.zona === 'entero') {
        return posicionTrasDigitosEn(texto.split(',')[0] ?? texto, info.digitos);
    }

    const coma = texto.indexOf(',');
    if (coma === -1) {
        return texto.length;
    }
    if (info.digitosDecimal <= 0) {
        return coma + 1;
    }

    let vistos = 0;
    for (let i = coma + 1; i < texto.length; i += 1) {
        if (/\d/.test(texto[i])) {
            vistos += 1;
            if (vistos === info.digitosDecimal) {
                return i + 1;
            }
        }
    }

    return texto.length;
}

function posicionTrasDigitosEn(valor, digitos) {
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
}

function colocarCursor(el, pos) {
    const aplicar = () => {
        try {
            el.setSelectionRange(pos, pos);
        } catch (e) {
            // ignore
        }
    };
    aplicar();
    // iOS a menudo descarta el caret síncrono tras mutar value.
    setTimeout(aplicar, 0);
}

/**
 * @param {HTMLInputElement} el
 */
export function insertarSeparadorDecimal(el) {
    el.dataset.milesIgnoreInput = '1';
    const valor = String(el.value ?? '');
    const start = el.selectionStart ?? valor.length;
    const end = el.selectionEnd ?? start;

    if (valor.includes(',')) {
        const coma = valor.indexOf(',');
        colocarCursor(el, coma + 1);
        setTimeout(() => {
            delete el.dataset.milesIgnoreInput;
        }, 0);
        return;
    }

    const mezclado = `${valor.slice(0, start)},${valor.slice(end)}`;
    el.value = formatearMiles(mezclado);
    const coma = el.value.indexOf(',');
    const pos = coma === -1 ? el.value.length : coma + 1;
    colocarCursor(el, pos);
    setTimeout(() => {
        delete el.dataset.milesIgnoreInput;
        colocarCursor(el, pos);
    }, 0);
}

/**
 * @param {HTMLInputElement} el
 */
export function aplicarFormatoMilesEnInput(el) {
    if (el.dataset.milesIgnoreInput === '1') {
        return;
    }

    const cursor = el.selectionStart ?? el.value.length;
    const info = infoCursorMiles(el.value, cursor);
    el.value = formatearMiles(el.value);
    colocarCursor(el, posicionDesdeInfoMiles(el.value, info));
}

/**
 * @param {KeyboardEvent} ev
 * @param {HTMLInputElement} el
 */
export function manejarKeydownMiles(ev, el) {
    if (ev.isComposing) {
        return false;
    }

    if (ev.key === ',' || ev.key === '.' || ev.key === 'Decimal' || ev.code === 'NumpadDecimal') {
        ev.preventDefault();
        insertarSeparadorDecimal(el);
        return true;
    }

    // Backspace sobre punto de miles: borrar el dígito anterior, no el separador.
    if (ev.key === 'Backspace') {
        const start = el.selectionStart ?? 0;
        const end = el.selectionEnd ?? start;
        if (start !== end || start <= 0) {
            return false;
        }
        if (el.value[start - 1] === '.') {
            ev.preventDefault();
            const sinSep = `${el.value.slice(0, start - 1)}${el.value.slice(start)}`;
            // Borrar también el dígito previo al separador.
            let i = start - 2;
            while (i >= 0 && ! /\d/.test(sinSep[i])) {
                i -= 1;
            }
            const borrado = i >= 0
                ? `${sinSep.slice(0, i)}${sinSep.slice(i + 1)}`
                : sinSep;
            const info = infoCursorMiles(borrado, Math.max(0, i));
            el.value = formatearMiles(borrado);
            colocarCursor(el, posicionDesdeInfoMiles(el.value, info));
            return true;
        }
    }

    return false;
}

/**
 * @param {InputEvent} ev
 * @param {HTMLInputElement} el
 */
export function manejarBeforeInputMiles(ev, el) {
    if (! ev || ev.isComposing) {
        return false;
    }

    if (ev.inputType === 'insertText' && ev.data && ES_SEP_DECIMAL(ev.data)) {
        ev.preventDefault();
        insertarSeparadorDecimal(el);
        return true;
    }

    if (ev.inputType === 'insertText' && ev.data && /[^\d]/.test(ev.data)) {
        ev.preventDefault();
        return true;
    }

    return false;
}
