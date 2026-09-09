# Auditoría integral — Finanzas (post-Plesk)

**Fecha:** 2026-09-06  
**Alcance:** código del repo + PHPUnit + revisión estática + pruebas en vivo en `https://ingeer.co/finanzas`  
**Regla de esta fase:** no se modificó código de la aplicación (solo este informe).  
**Entorno live:** host Plesk público documentado en el repo; sesión autenticada ya presente en el navegador de auditoría (solo lectura de UI; sin crear pagos ni mutar datos).

---

## RESUMEN EJECUTIVO

**Estado general: 🟡 Requiere mejoras**

El núcleo de dominio (libro append-only, cuadratura, aislamiento multi-usuario en tests, flujos de préstamo/tarjeta/meta) está **sólido y bien cubierto por PHPUnit (51/51)**. El despliegue en subcarpeta Plesk **funciona de verdad**: prefijo `/finanzas`, cookies `path=/finanzas; secure`, manifest/SW con scope correcto, assets Vite 200, login y dashboard autenticado operativos.

No está “verde” para producción limpia porque:

1. Hay scripts de diagnóstico en `public/` listos para colarse en el tarball de release (hoy en el host dan 404; el riesgo es de **redeploy**, no de exposición actual verificada).
2. Hay huecos de dominio/UX alrededor de bolsillos de meta en transferencias libres.
3. Hay deuda de rendimiento (N+1 de saldos + composer global de Situación) y superficie de hardening incompleta (`TrustProxies=*`, headers, throttle de registro).

Para un PFM personal single-tenant el sistema es usable. Para “calidad aceptable de producción” sin landmines: **aún no**.

---

## ESTADÍSTICAS

| Métrica | Valor |
|--------|--------|
| Funcionalidades / módulos inventariados | 14 (auth, situación, cuentas, ingresos, gastos, deudas, tarjetas, pagos/transferencias, presupuestos, metas, calendario, proyecciones, PWA, stub API) |
| Casos PHPUnit | 51 |
| Casos PHPUnit exitosos | 51 |
| Casos PHPUnit fallidos | 0 (1 deprecation PHPUnit) |
| Probes live (HTTP/UI) | ~18 |
| Problemas críticos | 1 |
| Problemas altos | 5 |
| Problemas medios | 7 |
| Problemas bajos | 5 |
| Vulnerabilidades / hallazgos de seguridad | 4 (1 crítico potencial, 3 altos/medios) |
| Tests automatizados existentes | 14 archivos (7 unit, 6 feature + helpers) |
| Cobertura aproximada | Buena en dominio contable y PWA/subcarpeta; débil en auth HTTP, concurrencia real, IDOR HTTP amplio, presupuestos/calendario/proyecciones HTTP |

---

## ARQUITECTURA (verificado)

| Capa | Hallazgo |
|------|----------|
| Stack | PHP 8.1+ / Laravel 10 / Blade + Vite / MySQL; Plesk live: PHP 8.3.33 + Apache |
| Auth | Sesión custom (`AuthController`); rate limit login; sin Fortify/Breeze/Sanctum |
| Frontend | Blade MPA + PWA shell; no SPA |
| API | Stub `GET /api/usuario` con `auth` en grupo API **sin** sesión → prácticamente inútil |
| Dominio | Servicios: `Contabilizacion`, `Tesoreria`, `Prestamo`, `Tarjeta`, `Pago`, `MetaAhorro`, `CuentaLiquida`, `Presupuesto`, `Situacion`, etc. |
| Aislamiento | Trait `PerteneceAlUsuario` + `ModeloUsuarioPolicy` (solo 5 modelos) + filtros `usuario_id` en FormRequests/servicios |
| Plesk | `UrlPrefix`, `StripUrlPrefix` global, `TrustProxies=*`, `session.path` desde `APP_URL`, PWA dinámico |

### Inventario funcional (solo lo existente)

| Módulo | Funcionalidad | Entrada | Resultado esperado | Riesgos |
|--------|---------------|---------|-------------------|---------|
| Auth | Login / registro / logout | email/password | Sesión + redirect situación | Registro sin throttle; sin reset password |
| Situación | Dashboard | GET auth | Widgets, alertas, calendario mini | Coste SQL alto |
| Cuentas | CRUD ciclo vida | forms | Crear/archivar/restaurar/cancelar | Cancelación con saldo |
| Ingresos/Gastos | Alta + posteo | monto COP | Hecho + asiento | Doble submit |
| Deudas | Alta préstamo + pago | datos amortización | Calendario + pagos | Abonos / concurrencia |
| Tarjetas | Alta, compra, pago | cupo/cuotas | Pasivo sin liquidez | Revolving fino fuera de fase |
| Pagos | Pago genérico + transferencia | referencia única | Posteo libro | Bolsillos en transferencia |
| Presupuestos | Guardar líneas mes | montos | Umbrales alerta | Poca cobertura HTTP |
| Metas | Crear + aporte | bolsillo | Avance solo con aporte | Transferencia libre al bolsillo |
| Calendario / Proyecciones | Lectura | mes/horizonte | Agenda / forecast | Perf |
| PWA | manifest/sw/offline | GET público | Scope con prefijo | Cache Vite |
| API | `/api/usuario` | — | Usuario JSON | Mal cableado |

---

## PROBLEMAS CRÍTICOS

### [C1] Scripts `public/diag-*.php` empaquetables en release

**Severidad:** CRÍTICO  

**Ubicación:**  
- `public/diag-finanzas.php` (lee `.env`: `APP_ENV`, `APP_DEBUG`, presencia/longitud de `APP_KEY`, `APP_URL`) — **sin token**  
- `public/diag-log.php` — token hardcoded `finanzas-diag-2026`; vuelca cola de `storage/logs/laravel.log`  
- `public/diag-register.php` — mismo token; bootea Laravel, intenta `User::create`, imprime traza  
- `scripts/empaquetar-release.sh` incluye el árbol `public/` **sin** excluir `diag-*`

**Descripción:** Herramientas temporales de diagnóstico de Plesk. En el host live (2026-09-06) `diag-finanzas.php`, `diag-log.php` y `diag-register.php` responden **404** (no expuestos ahora). En el workspace local existen como archivos sin trackear / presentes en disco: un `empaquetar-release.sh` desde esta máquina los metería en el tarball.

**Cómo reproducirlo:**  
1. Confirmar archivos en `public/diag-*.php`.  
2. Revisar que el tar de release no los excluye.  
3. En live: `GET /finanzas/diag-finanzas.php` → 404 (estado actual seguro).

**Resultado actual:** No expuestos en prod hoy; listos para reaparecer en el próximo empaquetado local.

**Resultado esperado:** Cero scripts de diag en Document Root; exclusión explícita en el empaquetador; tokens nunca en el repo.

**Impacto:** Exfiltración de config/logs, fingerprinting de `APP_KEY`, superficie de abuso de registro/diagnóstico.

**Recomendación:** Borrar los tres archivos del workspace, añadir exclusión `public/diag-*` en `empaquetar-release.sh`, y verificar en cada deploy que no existan.

---

## PROBLEMAS DE SEGURIDAD

| ID | Severidad | Hallazgo | Evidencia |
|----|-----------|----------|-----------|
| C1 | CRÍTICO | Diag scripts empaquetables | Código + live 404 |
| S1 | ALTO | `TrustProxies::$proxies = '*'` | `app/Http/Middleware/TrustProxies.php` — necesario detrás de Plesk, pero confía cualquier `X-Forwarded-*` si el vhost no filtra |
| S2 | MEDIO | Registro sin `throttle` (login sí tiene) | `routes/web.php` vs `throttle:login` |
| S3 | MEDIO | Headers de seguridad incompletos | Live: `X-Frame-Options: SAMEORIGIN` sí; no HSTS / CSP / `X-Content-Type-Options` visibles; `X-Powered-By: PHP/8.3.33` y `PleskLin` |
| S4 | BAJO/INFO | Stub `/api/usuario` con `auth` sin sesión en grupo API | `routes/api.php` + Kernel `api` |
| — | Verificado OK | CSRF en forms Blade (`@csrf`) | Vistas |
| — | Verificado OK | Sin `{!!` en views (menor XSS reflejado) | Grep views |
| — | Verificado OK | Passwords hashed cast | `User` model |
| — | Verificado OK | Cookies sesión `path=/finanzas; secure; httponly; samesite=lax` | Headers live |
| — | Verificado OK | Guest → `/finanzas/situacion` redirige a login | Live 302 |
| — | Verificado OK | IDOR destroy cuenta ajena → 404 | `AislamientoMultiUsuarioTest` |
| — | Parcial | Policies solo en 5 modelos; resto depende de scope + exists-by-usuario en requests | `AuthServiceProvider` |

**IDOR:** El patrón dominante es seguro en HTTP tipificado (global scope + `Rule::exists(... usuario_id)` + `authorize` en recursos principales). El riesgo residual es código futuro que use `withoutGlobalScopes()` sin filtrar `usuario_id` (ya es el estilo de los servicios; hoy lo hacen bien).

**Recuperación de contraseña:** no implementada (migración `password_reset_tokens` huérfana de rutas). INFO de producto, no bug de seguridad por sí solo.

---

## PROBLEMAS FUNCIONALES / DOMINIO

### [A1] Transferencias permiten bolsillos de meta sin sincronizar avance

**Severidad:** ALTO  

**Ubicación:** `TransferenciaRequest`, `PagoController@transferir`, `TesoreriaService` (tipo `Transferencia`); UI live `/pagos` lista `Cuenta Nu_bolsillo1`.

**Descripción:** El avance de meta solo se recalcula con hechos `aporte_meta` (y reverso). Una transferencia “libre” desde/hacia el bolsillo mueve liquidez y **no** ajusta el progreso de la meta. En metas, el formulario de aporte sí filtra cuentas operativas; en transferencias, no.

**Impacto:** Estados imposibles / contradictorios entre saldo del bolsillo y `avance` de la meta.

**Recomendación:** Excluir IDs de bolsillo activo de origen/destino en transferencias (o forzar tipo aporte/retiro de meta con sync).

### [A2] Sin protección anti doble-submit en UI

**Severidad:** ALTO (mitigado parcialmente en backend)

**Ubicación:** `resources/js/app.js` (no deshabilita botón al enviar).

**Mitigaciones existentes:**  
- Pagos: unique `(usuario_id, referencia)` + check en servicio.  
- Asientos: unique origen + reject doble posteo.  
- Pagos préstamo/tarjeta: `lockForUpdate` en transacciones.

**Hueco:** Transferencias / ingresos / gastos sin clave de idempotencia → doble clic puede postear dos hechos.

### [A3] Stub API no usable

**Severidad:** BAJO  

`GET /api/usuario` con middleware `auth` en stack sin cookies de sesión → 302 a login en live. Confusión de frontera auth.

---

## PROBLEMAS DE BASE DE DATOS

| ID | Severidad | Hallazgo |
|----|-----------|----------|
| D1 | INFO/OK | FKs amplias, uniques de integridad (`asientos_origen_unico`, `pagos.usuario_id+referencia`, presupuesto mes) | Migraciones 2026_09_* |
| D2 | MEDIO | `meta_ahorro_id` en hechos sin FK en migración core (columna suelta) | `create_finanzas_core` |
| D3 | OK | Cascade on delete usuario → limpia tenant | Migraciones |
| D4 | BAJO | Tabla `personal_access_tokens` sin Sanctum en uso | Migración legacy |

No se ejecutó auditoría destructiva sobre MySQL de producción (sin SSH / sin credenciales DB).

---

## PROBLEMAS DE RENDIMIENTO

### [P1] N+1 / sumas por cuenta vía `saldoCentavos()`

**Severidad:** ALTO  

**Ubicación:** `CuentaLiquida::saldoCentavos()` → `CuentaContable::saldoCentavos()` hace **2 `SUM`** sobre `movimientos_libro` por cuenta.

**Dónde duele:** `SituacionFinancieraService::responder()` suma saldos en colección; además el View composer de `layouts.app` llama Situación en **casi toda página autenticada**.

**Impacto:** Con muchas cuentas/movimientos, cada navegación MPA se vuelve cara. Escalabilidad pobre.

### [P2] Composer global recalcula dashboard

**Severidad:** MEDIO  

**Ubicación:** `AppServiceProvider` View::composer `layouts.app`.

Cache estático por request/usuario mitiga repetición *dentro* del mismo request, no entre páginas.

---

## PROBLEMAS DE UX/UI

| ID | Severidad | Hallazgo | Evidencia |
|----|-----------|----------|-----------|
| U1 | BAJO | Boot splash hasta `load` o 2.8s | `boot-splash.blade.php`; screenshot inicial “Cargando…” |
| U2 | INFO | Nav móvil coherente (Dashboard / Movimientos / + / Metas / Más) | Live situacion/pagos |
| U3 | INFO | Formularios con labels claros y referencia única explícita | Live pagos |
| U4 | NO VERIFICADO | Responsive exhaustivo 6 viewports | Solo muestra mobile browser + desktop headers |
| U5 | BAJO | Banner “Instala Finanzas…” persistente | Live |

---

## PLESK / SUBCARPETA / PWA (verificado live)

| Chequeo | Resultado |
|---------|-----------|
| `GET /finanzas/login` | 200, HTML con `app-base-path` y `/finanzas` |
| Guest `GET /finanzas/situacion` | 302 → `https://ingeer.co/finanzas/login` |
| Cookies | `path=/finanzas; secure; httponly; samesite=lax` |
| `manifest.json` | `start_url`/`scope` `/finanzas/`; icons prefijados |
| `sw.js` | 200; `Service-Worker-Allowed: /finanzas/` |
| `offline.html` | `href="/finanzas/"` |
| Vite `build/manifest.json` + CSS | 200 |
| Icons / fonts | 200 |
| `diag-*` | 404 |
| Dashboard autenticado | Carga datos reales (ingresos/gastos/disponible/alertas) |
| PHPUnit subcarpeta | `SubdirectorioAppTest` + `UrlPrefixTest` OK |

**Nota:** `.htaccess` raíz fija `Service-Worker-Allowed /` para archivo estático; en despliegue correcto el rewrite de `public/.htaccess` manda `sw.js` a Laravel (header dinámico). Riesgo solo si se sirve el estático sin pasar por la ruta.

---

## TRANSACCIONES Y CONCURRENCIA

| Flujo | `DB::transaction` | Locks | Idempotencia |
|-------|-------------------|-------|--------------|
| Contabilización post/reverso | Sí | — | Unique origen + checks |
| Tesorería | Sí | `lockForUpdate` cuenta | No clave externa |
| Pago genérico | Sí | lock cuenta | Unique referencia |
| Préstamo/tarjeta pago | Sí | lock préstamo/tarjeta/cuenta | Marca cuota pagada |
| Meta aporte | Sí | — | Sync progreso |
| Registro usuario | Sí | — | Email unique |

**No verificado:** carrera real de dos requests paralelos (solo diseño + tests unitarios).  
**Hallazgo:** doble clic en ingreso/gasto/transferencia es el escenario más débil.

---

## COBERTURA DE TESTS

### Cubierto bien
- Contabilización (cuadratura, doble posteo, reverso)
- Préstamos (amortización francesa, abono extra)
- Tarjetas compra/pago
- Metas / bolsillo / disponible
- Ciclo cuentas líquidas
- Aislamiento multi-usuario (scope + destroy 404)
- Validación forms español + normalización COP
- Dashboard widgets
- PWA shell + subdirectorio APP_URL

### Gaps importantes (no escribir tests aún; backlog)
1. HTTP IDOR completo (pagos, tarjetas, metas ajenas por ID en POST)
2. Doble submit / race transferencias e ingresos
3. Transferencia desde bolsillo vs integridad de meta
4. Auth HTTP (throttle login, registro, logout session invalidate)
5. Feature tests presupuestos / calendario / proyecciones
6. Smoke deploy: diag ausente, headers, cookie path
7. Concurrencia `lockForUpdate` bajo paralelismo real

---

## MATRIZ DE RIESGOS (resumen)

| ID | Severidad | Área |
|----|-----------|------|
| C1 | CRÍTICO | Seguridad / deploy |
| A1 | ALTO | Dominio metas |
| A2 | ALTO | Concurrencia UX |
| P1 | ALTO | Rendimiento |
| S1 | ALTO | Seguridad proxy |
| S2 | MEDIO | Seguridad auth |
| S3 | MEDIO | Hardening headers |
| P2 | MEDIO | Rendimiento |
| D2 | MEDIO | DB integridad |
| U1 | BAJO | UX |
| A3 | BAJO | API |
| D4 | BAJO | Código muerto |

---

## PLAN DE CORRECCIÓN

| Prioridad | Problema | Severidad | Acción | Esfuerzo |
|-----------|----------|-----------|--------|----------|
| 1 | C1 diag en public/release | CRÍTICO | Borrar diag; excluir en empaquetar; checklist post-deploy | S |
| 2 | A1 bolsillo en transferencias | ALTO | Filtrar bolsillos o canalizar como aporte/retiro con sync | M |
| 3 | A2 doble submit | ALTO | Disable botón + idempotency key en hechos tesorería | M |
| 4 | P1/P2 saldos N+1 + composer | ALTO | Agregar saldos por usuario en una query; no recalcular Situación en todo layout | L |
| 5 | S1 TrustProxies | ALTO | Restringir a IPs del proxy Plesk o red local del nodo | S |
| 6 | S2 throttle registro | MEDIO | `throttle` en POST registro | S |
| 7 | S3 headers | MEDIO | HSTS (en Plesk/Apache), quitar `X-Powered-By`, opcional CSP | S |
| 8 | D2 FK meta_ahorro | MEDIO | Migración FK nullable | S |
| 9 | Gaps de tests | — | Ver sección cobertura | M–L |
| 10 | A3 API stub | BAJO | Quitar o cablear Sanctum/session conscientemente | S |

---

## VERIFICADO VS NO VERIFICADO

### Verificado
- Arquitectura e inventario desde código
- PHPUnit 51/51
- Live: login guest, redirects, cookies, PWA, assets, dashboard/pagos autenticados (sesión existente), diag 404
- Revisión estática seguridad/dominio/migraciones/deploy scripts

### No verificado
- SSH / permisos `storage` / `.env` real de prod / `APP_DEBUG` real (solo indicios: login sin stack traces)
- Login con credenciales nuevas / registro en prod
- Escritura de movimientos en prod (a propósito no se hizo)
- Race conditions paralelas reales
- Responsive 6 breakpoints formales
- Load test / volumen grande de movimientos
- Contenido de logs de producción
- Que el próximo tarball no reintroduzca diag (depende de higiene local)

---

## RESPUESTAS FINALES

1. **¿El software funciona correctamente?**  
   Sí en el núcleo contable (tests) y en el shell Plesk/subcarpeta (live). No sin reservas: bolsillos en transferencias y riesgo de redeploy de diag.

2. **¿Es seguro?**  
   Aceptable para uso personal con sesión web, CSRF y aislamiento básico. **No** endurecido para exposición pública agresiva (proxies `*`, diag empaquetables, headers incompletos, registro sin throttle).

3. **¿Los datos están protegidos y son consistentes?**  
   El libro está bien diseñado (append-only, unique de origen, transacciones). La consistencia meta↔bolsillo puede romperse con transferencias libres. Aislamiento multi-usuario verificado en tests, no en batería HTTP completa.

4. **¿Las reglas de negocio están correctamente implementadas?**  
   En gran medida sí (cubierto por unit tests de dominio). Excepción clara: transferencia vs bolsillo/meta.

5. **¿Qué funcionalidades tienen errores?**  
   No se encontró fallo bloqueante en flujos happy-path live. Defecto de producto: transferencias vs metas. API stub rota por diseño.

6. **¿Qué problemas de rendimiento existen?**  
   Saldos por cuenta con doble SUM + Situación en layout global.

7. **¿Qué tan buena es la cobertura de pruebas?**  
   Fuerte en dominio y PWA/subcarpeta; débil en auth HTTP, concurrencia, IDOR amplio y módulos de solo lectura.

8. **¿Está preparado para producción?**  
   **Condicional:** usable en Plesk ahora, pero no “listo” hasta eliminar diag del pipeline de release y cerrar A1/A2 y lo básico de hardening.

9. **¿Cuáles son los 10 problemas más importantes?**  
   C1, A1, A2, P1, S1, P2, S2, S3, D2, gaps de tests de concurrencia/IDOR.

10. **¿Qué pruebas automatizadas implementar después?**  
    (1) Feature: transferencia rechaza bolsillo. (2) Feature: doble POST ingreso no duplica / o documenta comportamiento. (3) Smoke: `diag-*` ausentes en `public`. (4) Auth throttle registro. (5) IDOR POST pago/meta/tarjeta ajena. (6) Assert cookie path bajo `APP_URL` con subcarpeta (ya parcial en SubdirectorioAppTest).

---

## APÉNDICE — PHPUnit

```
Tests: 51, Assertions: 319, PHPUnit Deprecations: 1
OK (exit 0)
Runtime: PHP 8.2.31 local / suite en SQLite memoria
```

## APÉNDICE — Correcciones aplicadas (2026-09-06)

Tras la auditoría se corrigió en código (sin redeploy automático a Plesk):

- C1: eliminados `public/diag-*.php`; exclusión en `empaquetar-release.sh` + `.gitignore`
- A1: bolsillos de meta activa bloqueados en transferencias/ingresos/gastos (servicio + FormRequest + UI)
- A2: anti doble-submit en POST (JS)
- S1: `TRUSTED_PROXIES` configurable
- S2: throttle `register`
- S3: middleware `SecurityHeaders`
- D2: FK `hechos_tesoreria.meta_ahorro_id`
- A3: stub API vaciado
- P1/P2: `saldosCentavosMap` + `resumenShell` en layout
