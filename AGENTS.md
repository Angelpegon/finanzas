# Finanzas — guía para agentes

PFM personal en Laravel 10 + Blade + MySQL (COP, `America/Bogota`).

## Principios no negociables

1. **Libro append-only.** Corrección = reverso + (si aplica) nuevo asiento. Nunca `UPDATE` de montos posteados.
2. **Hechos → asientos.** Todo movimiento real pasa por servicios (`TesoreriaService`, `PrestamoService`, `TarjetaService`, `ExtractoTarjetaService`, `MetaAhorroService`, `PagoService`) y `ContabilizacionService`.
3. **Saldos derivados.** No editar saldos a mano; metas avanzan solo con `aporte_meta`.
4. **Aislamiento por `usuario_id`.** Global scope + policies + filtros explícitos en servicios.
5. **Centavos enteros.** UI en pesos; persistencia en BIGINT.
6. **Tests de dominio antes de UI cosmética.** Ver `tests/Unit` y `tests/Feature`.

## Arranque local

```bash
composer install
cp .env.example .env   # si falta
php artisan key:generate
# configurar MySQL DB_DATABASE=finanzas
php artisan migrate
npm install && npm run build   # o `npm run dev` para HMR
php artisan serve
```

El esquema sale solo de `database/migrations`; no hay volcados SQL en el repo.

## Tests

| Suite | Comando |
|-------|---------|
| PHP (SQLite en memoria) | `php vendor/bin/phpunit` |
| JS (`node:test`, `tests/js`) | `npm test` |

CI: `.github/workflows/tests.yml` corre build de Vite, `npm test` y PHPUnit. Los tests no dependen de `public/build`.

## Despliegue (Plesk)

Ver `docs/despliegue-plesk.md`. Resumen: Document Root → `public/`, `.env` de producción, `bash scripts/deploy-plesk.sh`. Empaquetado local: `bash scripts/empaquetar-release.sh`.

## Documentación

| Documento | Para qué |
|-----------|----------|
| `docs/estado-proyecto.md` | Estado actual, desviaciones del plan y **backlog vigente** |
| `docs/reglas-negocio.md` | Invariantes de dominio (fuente de verdad funcional) |
| `docs/despliegue-plesk.md` | Operación en Plesk / subcarpeta |
| `docs/auditoria-integral.md` | Auditoría 2026-09-06 (histórica) |
| `.cursor/plans/finanzas_pfm_8305efb9.plan.md` | Plan de producto original |

## Dónde tocar código

| Área | Ubicación |
|------|-----------|
| Contabilidad (asientos, reversos) | `app/Services/ContabilizacionService.php` |
| Ingresos / gastos / transferencias | `app/Services/TesoreriaService.php` |
| Pagos genéricos a terceros | `app/Services/PagoService.php` |
| Préstamos | `app/Services/PrestamoService.php`, `app/Support/AmortizacionFrancesa.php` |
| Tarjetas: alta, compras, cuotas | `app/Services/TarjetaService.php` |
| Tarjetas: extracto, corte, pago, corrección | `app/Services/ExtractoTarjetaService.php` |
| Metas y bolsillos | `app/Services/MetaAhorroService.php` |
| Dashboard / shell (disponible, KPIs) | `app/Services/SituacionFinancieraService.php` |
| Calendario / proyecciones / recurrencias | `CalendarioFinancieroService`, `ProyeccionService`, `app/Support/RecurrenciaMensual.php` |
| Agregados de lectura del libro | `app/Support/AgregadosLibro.php` |
| Subcarpeta Plesk / PWA | `app/Support/UrlPrefix.php`, `StripUrlPrefix`, `PwaAssetController` |

## Fuera de fase (no implementar sin pedirlo)

- Importación CSV/Excel (existe la tabla `importaciones`, sin feature)
- Reportes PDF
- Simulador de nueva deuda en UI
- Escritura offline en la PWA
- Open Finance

El revolving fino de tarjeta **ya está implementado** (`ExtractoTarjetaService`); cambios ahí requieren actualizar `docs/reglas-negocio.md` (reglas 11–12, 17–20).
