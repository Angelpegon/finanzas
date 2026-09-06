# Finanzas — guía para agentes

PFM personal en Laravel 10 + Blade + MySQL (COP, `America/Bogota`).

## Principios no negociables

1. **Libro append-only.** Corrección = reverso + (si aplica) nuevo asiento. Nunca `UPDATE` de montos posteados.
2. **Hechos → asientos.** Todo movimiento real pasa por servicios (`TesoreriaService`, `PrestamoService`, `TarjetaService`, `MetaAhorroService`, `PagoService`) y `ContabilizacionService`.
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
npm install && npm run build
php artisan serve
```

Tests: `php vendor/bin/phpunit` (SQLite en memoria).

## Despliegue (Plesk)

Ver `docs/despliegue-plesk.md`. Resumen: Document Root → `public/`, `.env` de producción, `bash scripts/deploy-plesk.sh`. Empaquetado local: `bash scripts/empaquetar-release.sh`.

## Dónde tocar código

| Área | Ubicación |
|------|-----------|
| Contabilidad | `app/Services/ContabilizacionService.php` |
| Tesorería / transferencias / aportes | `app/Services/TesoreriaService.php` |
| Reglas documentadas | `docs/reglas-negocio.md` |
| Plan de producto | `.cursor/plans/finanzas_pfm_8305efb9.plan.md` |

## Fuera de fase 1 (diseñado, no implementar sin pedirlo)

- Revolving fino de tarjeta al corte
- Importación CSV/Excel completa
- Reportes PDF
- Simulador de nueva deuda en UI
- Open Finance
