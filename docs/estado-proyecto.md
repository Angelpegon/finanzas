# Estado del proyecto

Documento vivo: qué existe hoy, en qué difiere del plan original y qué está
pendiente. Las auditorías (`auditoria-integral.md`) son instantáneas
históricas; este archivo es la fuente de verdad del backlog.

**Última revisión:** 2026-10-08 (sobre `main` @ `dca0e0f` + limpieza de repo).

## Calidad verificable

| Suite | Comando | Resultado |
|-------|---------|-----------|
| PHPUnit (SQLite memoria) | `php vendor/bin/phpunit` | 144 tests, 946 aserciones, verde |
| JS (`node:test`) | `npm test` | 6 tests, verde |
| CI | `.github/workflows/tests.yml` | build Vite + JS + PHPUnit en cada push/PR a `main` |

Límite conocido: `lockForUpdate` no hace nada en SQLite, así que la
concurrencia no está cubierta por la suite.

Verificación manual en MySQL 8 local (2026-10-08): 19 migraciones aplicadas,
`DemoDatosSeeder`, 25 páginas GET en 200, flujos de ingreso / gasto /
transferencia / pago y corrección de tarjeta en navegador, libro cuadrado y
hashes válidos, y 3 POST con la misma `idempotency_key` (2 en paralelo) → 1
solo gasto.

## Módulos implementados

Auth (sesión, throttle login/registro), Situación (dashboard), Cuentas
líquidas (ciclo de vida), Ingresos, Gastos, Movimientos (transferencias y
pagos a terceros), Deudas (francés / lineal / solo interés + abono a
capital), Tarjetas (compras, cuotas, avances, **extracto con gracia,
rotación, mora y cargos**), Presupuestos, Metas con bolsillo, Recurrencias,
Calendario, Proyecciones, Alertas, PWA (shell instalable, sin escritura
offline).

## Desviaciones respecto al plan original

Plan: `.cursor/plans/finanzas_pfm_8305efb9.plan.md`.

| Plan | Realidad |
|------|----------|
| Revolving fino de tarjeta fuera de fase 1 | Implementado en `ExtractoTarjetaService` (reglas 11–12, 17–18 de `reglas-negocio.md`) |
| Importación CSV/Excel en fase 1 | **No implementada**: la tabla `importaciones` existe (migración core) pero no hay servicio, rutas ni UI |
| Alertas solo diseñadas | Implementadas en `AlertaService` (sin scheduler: se evalúan al pedir la página) |
| JS por pantalla en `public/assets/js` | JS de la app en `resources/js` vía Vite; `public/assets` solo vendor (Bootstrap, FA, jQuery, SweetAlert) |
| Simulador de nueva deuda, PDF, Open Finance | Sin implementar (sigue fuera de fase) |

## Resuelto (2026-10-08)

- **Dependencias:** `guzzlehttp/guzzle` 7.15.2, `league/commonmark` 2.10.3, `league/flysystem` 3.36.0 (19 → 4 advisories).
- **Email con CRLF en registro:** `RegisterRequest` añade `not_regex:/[\r\n]/` porque la regla `email` de Laravel 10 acepta `"ana\r\n x"@example.com`. Test en `FormValidationTest`.
- **`TRUSTED_PROXIES`:** ahora vive en `config('app.trusted_proxies')`, compatible con `config:cache`. Test en `TrustProxiesTest`.
- **Flujo de caja con pagos corregidos:** `AgregadosLibro::pagosDeudaReales` excluye reversos; lo usan Situación, shell y comparación con el mes anterior. Test en `DomainCorreccionesTest`.

## Backlog priorizado

| # | Severidad | Problema | Dónde |
|---|-----------|----------|-------|
| 1 | Alta | Quedan 4 advisories de `laravel/framework` sin parche en **ninguna** 10.x (Laravel 10 sin soporte de seguridad desde feb-2025): XSS en página de debug, path confusion en URLs firmadas temporales, CRLF en regla `email` (mitigado en registro). Requiere migrar a Laravel ≥ 12.61 | `composer.json` |
| 2 | Alta | Compras de tarjeta anteriores al inicio de la tarjeta nunca se facturan en un corte pasado: `primerCorte` parte de `fecha_inicio ?? created_at`, la UI no permite fijar `fecha_inicio` y `CompraTarjetaRequest` acepta cualquier fecha. Una compra de agosto en una tarjeta creada en octubre no genera mora ni aparece vencida (visto con `DemoDatosSeeder`) | `ExtractoTarjetaService::primerCorte`, `TarjetaRequest`, `CompraTarjetaRequest` |
| 3 | Alta | Cierre de cortes en cada request autenticado: `resumenShell` llama `cerrarExtractosVencidos` antes de la caché; recorre todos los meses desde el primer corte por tarjeta con transacción + `lockForUpdate` | `SituacionFinancieraService::resumenShell`, `ExtractoTarjetaService::cerrarTarjeta` |
| 4 | Media | Sin `unique (tarjeta_credito_id, fecha_corte)` en `ciclos_facturacion`; la idempotencia del cierre depende solo del lock | migraciones |
| 5 | Media | Cálculo de “disponible” duplicado (`responder`, `calcularResumenShell`, `disponibleEn`): riesgo de que shell y dashboard diverjan (regla 45) | `SituacionFinancieraService` |
| 6 | Media | `nivel_endeudamiento = deuda / liquidez` no mide capacidad de pago y se dispara con liquidez ≈ 0 | `SituacionFinancieraService`, `AlertaService` |
| 7 | Baja | `ExtractoTarjetaService` (~1000 líneas) concentra extracto, pagos, corrección y proyección | `app/Services` |
| 8 | Baja | Tablas sin uso: `personal_access_tokens` (sin Sanctum) e `importaciones` (sin feature). No borrar migraciones ya corridas en prod; si se descartan, migración nueva de `drop` | `database/migrations` |
| 9 | Baja | `empaquetar-release.sh` corre `composer install --no-dev` sobre el `vendor/` local: después de empaquetar, PHPUnit desaparece hasta otro `composer install` | `scripts/empaquetar-release.sh` |
| 10 | Baja | `Archivo.zip` (36 MB) sigue en el historial de git; el pack pesa ~38 MB. Limpiarlo exige reescribir historia + force push | git |

## Decisiones abiertas (del dueño del producto)

- Licencia del repo (`composer.json` dice `MIT`, heredado del skeleton de Laravel).
- ¿Se mantiene la simulación fina del extracto o se registra el extracto real del banco y se usa la simulación solo como estimación?
- ¿Usuario único o multiusuario real? Si es multiusuario, faltan reset de contraseña y batería IDOR por HTTP.
- ¿Reescribir historial para sacar `Archivo.zip`?
