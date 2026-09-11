import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatearMiles,
    infoCursorMiles,
    posicionDesdeInfoMiles,
    valorParaEnviar,
} from '../../resources/js/miles.js';

test('formatea miles y conserva coma abierta', () => {
    assert.equal(formatearMiles('1000'), '1.000');
    assert.equal(formatearMiles('1000,'), '1.000,');
    assert.equal(formatearMiles('1000,5'), '1.000,5');
    assert.equal(formatearMiles('1000,50'), '1.000,50');
    assert.equal(formatearMiles('1000,501'), '1.000,50');
    assert.equal(formatearMiles('0,05'), '0,05');
    assert.equal(formatearMiles(',5'), '0,5');
});

test('acepta punto decimal del servidor', () => {
    assert.equal(formatearMiles('1500.5'), '1.500,5');
    assert.equal(formatearMiles('99.99'), '99,99');
});

test('punto del teclado con miles ya formateados abre decimales', () => {
    assert.equal(formatearMiles('1000.'), '1.000,');
    assert.equal(formatearMiles('1.000.'), '1.000,');
    assert.equal(formatearMiles('1.000.5'), '1.000,5');
    assert.equal(formatearMiles('1.000.50'), '1.000,50');
});

test('cursor tras tipeo de punto queda en zona decimal', () => {
    const crudo = '1.000.';
    const info = infoCursorMiles(crudo, crudo.length);
    assert.equal(info.zona, 'decimal');
    assert.equal(info.digitosDecimal, 0);
    const formateado = formatearMiles(crudo);
    assert.equal(formateado, '1.000,');
    assert.equal(posicionDesdeInfoMiles(formateado, info), formateado.indexOf(',') + 1);
});

test('simula tipeo 1000.5 (teclado con punto) sin tragar decimal', () => {
    let v = '1.000';
    // Usuario tipea "." al final
    v = `${v}.`;
    const infoPunto = infoCursorMiles(v, v.length);
    v = formatearMiles(v);
    const pos = posicionDesdeInfoMiles(v, infoPunto);
    assert.equal(v, '1.000,');
    assert.equal(pos, v.indexOf(',') + 1);
    // Tipea 5 en pos
    v = `${v.slice(0, pos)}5${v.slice(pos)}`;
    v = formatearMiles(v);
    assert.equal(v, '1.000,5');
    assert.equal(valorParaEnviar(v), '1000.5');
});

test('simula tipeo 1000,5 con coma', () => {
    let v = '';
    for (const ch of '1000') {
        v += ch;
        v = formatearMiles(v);
    }
    assert.equal(v, '1.000');
    v = `${v},`;
    const info = infoCursorMiles(v, v.length);
    v = formatearMiles(v);
    const pos = posicionDesdeInfoMiles(v, info);
    assert.equal(v, '1.000,');
    v = `${v.slice(0, pos)}5${v.slice(pos)}`;
    assert.equal(formatearMiles(v), '1.000,5');
});
