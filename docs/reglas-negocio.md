# Reglas de negocio financieras

Estas reglas son invariantes del dominio. Los servicios deben validarlas antes
de crear hechos o asientos; las vistas solo muestran el resultado.

## Movimientos y contabilidad

1. Una transferencia entre cuentas propias mueve saldo entre activos y nunca
   se registra como ingreso ni gasto. Enviar dinero a un tercero no es
   transferencia: es un gasto (en Movimientos, destino «Otra» posteado vía
   `TesoreriaService` como `gasto`).
2. Una transferencia exige cuentas de origen y destino operativas, del mismo
   usuario y diferentes, con saldo suficiente en el origen.
3. Todo ingreso o gasto real exige una cuenta activa, una categoría del tipo
   correcto, fecha y monto positivo.
4. Un movimiento real crea un hecho y su asiento dentro de la misma transacción.
5. Todo asiento debe tener débitos y créditos positivos, equilibrados, y todas
   sus cuentas deben pertenecer al usuario. Las líneas en cero se descartan.
6. El saldo de una cuenta se deriva de sus movimientos y saldo inicial; no se
   edita manualmente como fuente de verdad.
7. Los asientos posteados son inmutables. Una corrección usa `ContabilizacionService::revertir`
   (asiento inverso + log de auditoría), nunca `UPDATE` de montos.
8. Un mismo hecho no puede generar más de un asiento no-reverso
   (`unique(usuario_id, origen_tipo, origen_id)` + validación de servicio).
9. Los montos se almacenan como centavos enteros y nunca se aceptan valores
   cero o negativos en hechos de tesorería.
10. El hash de integridad del asiento debe verificarse ante sospecha de
    manipulación (`verificarIntegridad`).

## Proyecciones y pagos

11. Los movimientos proyectados no crean asientos ni alteran saldos reales.
12. Un pago de tarjeta reduce pasivo (capital) y liquidez; el interés se gasta
    en `5200`. La compra ya reconoció el gasto de consumo.
13. Los pagos de préstamos y de tarjetas pueden iniciarse desde Movimientos
    (también desde Deudas / Tarjetas); el dominio siempre usa `PrestamoService`
    o `TarjetaService`. En ambos, el mínimo es la próxima cuota pendiente y el
    exceso es abono a capital. Los gastos de consumo diario se registran en
    Gastos, no en Movimientos. Un envío a un tercero no es transferencia: es un
    pago genérico (`deuda_personal` / `otra_obligacion`) que reduce liquidez y
    reconoce gasto.
14. Un pago genérico exige referencia única por usuario (`unique` en
    `usuario_id + referencia`) y cuenta operativa. La corrección de un pago
    genérico o de una transferencia entre cuentas propias es reverso contable
    (`ContabilizacionService::revertir`).
15. Una deuda liquidada no genera cuotas ni compromisos futuros.
16. Los gastos recurrentes, ingresos recurrentes y cuotas futuras se muestran
    como proyección hasta que se registre el hecho real.
17. El pago de cuota de préstamo o de tarjeta debe cubrir al menos el total de
    la cuota. Cualquier exceso es abono extraordinario a capital: en préstamo
    reduce plazo y mantiene la cuota (`AmortizacionFrancesa::plazosConCuotaFija`)
    en método francés; en lineal o solo interés se recalcula el mismo número de
    cuotas pendientes con el saldo restante.     En tarjeta el exceso reduce capital de las cuotas futuras desde el final
    (acorta plazo a nivel tarjeta, puede afectar varias compras) y condona el
    interés programado de las cuotas eliminadas; el interés de una cuota
    parcialmente reducida se prorratea. El cronograma futuro se regenera o
    ajusta (no es UPDATE de asientos); el pago guarda un snapshot del
    cronograma previo (con id de cuota) para permitir corrección. La corrección
    solo restaura las cuotas del snapshot; no elimina cuotas de compras
    registradas después del pago.

## Deudas y financiación

18. Una compra financiada conserva valor original, tasa (snapshot al registrar),
    intereses, número de cuotas, capital pagado, cuotas pendientes y saldo
    pendiente. En tarjeta la tasa no se pide en el formulario: compra a 1 cuota
    (corriente) programa interés 0; compra a 2+ cuotas usa `tasa_compras_mensual`;
    avance usa siempre `tasa_avances_mensual` (también a 1 cuota). El revolving
    sobre saldo no pagado al corte queda fuera de fase 1.
19. Una compra o avance con tarjeta no puede superar el cupo disponible
    (cupo − saldo del pasivo en el libro), con bloqueo de fila al registrar.
20. Una cuota pagada no puede pagarse nuevamente; el pago se vincula a una
    cuota concreta (`pagos.cuota_prestamo_id` o `pagos.cuota_tarjeta_id`).
    La corrección de un pago es reverso contable del asiento + reapertura de
    la cuota; solo aplica al último pago de esa obligación. Una compra/avance
    de tarjeta sin cuotas pagadas puede anularse (reverso + borrar cuotas).
20b. Un avance en efectivo acredita liquidez operativa y el pasivo de la
    tarjeta; no consume categoría de gasto. La cuenta destino no puede ser
    bolsillo de meta.
21. Capital, intereses, seguros y otros cargos deben conservarse por separado.
22. La cuota final debe ajustar redondeos para que el saldo de capital llegue
    exactamente a cero.
23. Los intereses del dashboard se leen del libro (movimientos a `5200`), no
    del calendario proyectado.

## Presupuestos, metas y alertas

24. El presupuesto consume únicamente gastos reales de su categoría y mes
    (`AgregadosLibro`: hechos `gasto`, pagos genéricos
    `deuda_personal`/`otra_obligacion`, y compras con tarjeta con categoría).
    El % de avance usa solo ese real. La “proyección” del módulo es la
    recurrencia bruta de la categoría (no se suma al %) y se muestra aparte
    como pendiente estimado (`max(0, recurrente − real)`). Una compra con
    tarjeta compromete el **monto total** de la compra en el mes de la
    compra (no el capital de cada cuota). Los reversos se excluyen del consumo.
    Guardar el presupuesto de un mes reemplaza las líneas de ese mes
    (planificación, no libro append-only).
25. Una meta de ahorro no es un gasto ni un ingreso: el avance neto = suma de
    hechos `aporte_meta` menos `retiro_meta` (ambos contabilizados; reversos
    excluidos). `monto_actual_centavos` es caché derivada. Al crear la meta se
    elige una cuenta operativa de referencia y el sistema crea siempre un
    bolsillo dedicado `{nombre}_bolsilloN`. El bolsillo **nunca** se convierte
    en cuenta operativa (cualquier estado de la meta). Los aportes solo salen
    de cuentas operativas. El retiro exige destino operativo y confirmación en
    UI; reduce avance y saldo del bolsillo. Se puede editar objetivo y fecha
    objetivo; el bolsillo no se cancela ni elimina desde Metas.
26. Las alertas son reglas de dominio separadas de la interfaz y no modifican
    datos financieros.

## Seguridad y aislamiento

27. Todas las consultas y escrituras de dominio se filtran por `usuario_id`.
28. Un usuario nunca puede consultar, modificar o relacionar cuentas,
    categorías, deudas, tarjetas, pagos o movimientos de otro usuario.
29. Las validaciones de pertenencia se aplican también cuando se desactivan
    los global scopes para operaciones internas.
30. Login, logout, registro y correcciones de asiento se registran en
    `seguridad_logs` vía `SeguridadService`.
31. Las escrituras sensibles autorizan con policies (`ModeloUsuarioPolicy`)
    además del global scope.

## Cuentas líquidas

32. Archivar oculta la cuenta de la tesorería operativa (`activa=false`,
    `estado=inactiva`) sin borrar el libro; se puede restaurar. Si la cuenta
    tiene saldo, antes debe transferirse íntegramente a otra cuenta operativa.
33. Cancelar es permanente en la UI (`estado=cancelada`): la cuenta deja de
    aparecer en listados y selects. Si hay saldo positivo, hay que transferirlo
    a otra cuenta operativa o registrarlo como cierre (inverso de apertura).
    No se usa `UPDATE` de montos ni se borra el historial del libro.
34. No se cancela ni se archiva un bolsillo de meta (cualquier estado; se
    gestionan en Metas con aportes/retiros), ni la cuenta de desembolso/pago
    de un préstamo con cuotas pendientes.
35. El disponible parte de saldos de cuentas no canceladas (incluye archivadas
    con saldo residual, aunque el archivo normal deja saldo en cero)
    **excluyendo todos los bolsillos de meta**: ese dinero está etiquetado y no
    es cash libre. Sigue restando cuotas, aportes planificados a metas activas
    netos de aportes ya hechos en el mes, y gastos proyectados pendientes del
    horizonte. `tengo` / saldo de cuentas sí incluye los bolsillos.

## Calendario (proyección de lectura)

36. El calendario (`CalendarioFinancieroService`) no escribe libro. Muestra:
    (a) hechos de radar no revertidos (`ingreso`, `gasto`, `aporte_meta`,
    `retiro_meta`); (b) pagos no revertidos (genéricos, préstamo y tarjeta);
    (c) cuotas pendientes de préstamo/tarjeta; (d) recurrencias activas;
    (e) marca informativa de corte de tarjeta (monto 0). No inventa un
    “límite de pago” aparte de las cuotas. Apertura, cierre y transferencia
    no entran al radar (no son flujo operativo del día).
37. Tras pagar una cuota, el compromiso proyectado desaparece y el pago real
    aparece como evento `pago`. Tras corregir (reverso), el origen deja de
    contar como real y la cuota puede volver a proyectarse.
38. Una recurrencia `unico` es una sola fecha (día del mes anclado al mes de
    creación). `anual` usa el mes de creación como aniversario. Diario /
    semanal / quincenal se proyectan por ocurrencia y se omiten el día en que
    ya hay un real de la misma categoría por monto ≥ proyectado. Mensual /
    anual / unico se consideran cubiertos en el periodo si la suma real
    (excluyendo reversos) alcanza el monto de la recurrencia.
39. El resumen del mes separa ingresos/salidas **reales** de **compromisos**
    (cuotas y gastos/ingresos recurrentes proyectados o vencidos). Las metas
    aparecen en la agenda pero no suman a ingresos/salidas (regla 25).

## Proyecciones (forecast agregado)

40. `ProyeccionService` no escribe libro. Comparte la semántica de recurrencias
    con el calendario vía `RecurrenciaMensual` (`unico` en el mes de creación,
    `anual` monto completo en mes aniversario, cobertura por monto de categoría
    excluyendo reversos). Presupuesto usa el mismo bruto mensual.
41. El horizonte del mes corriente (Situación / shell) incluye cuotas impagas
    con vencimiento ≤ fin de mes (arrastre de vencidas). En el forecast
    multi-mes, ese arrastre solo va en el mes 0; los meses siguientes solo
    suman cuotas con vencimiento en ese mes.
42. El residual de `/proyecciones` es flujo del mes (ingresos − gastos −
    cuotas − metas) **sin** saldo inicial. El disponible de Situación es
    liquidez libre − cuotas/metas − gastos recurrentes pendientes. No son el
    mismo KPI.
43. Los aportes a metas en el horizonte se limitan al faltante de objetivos
    activos (no se proyecta plan infinito tras cumplir la meta).

## Shell / PWA

44. El chrome (sidebar/header) muestra el disponible del **mes corriente**
    (“hoy”), aunque Situación navegue otro mes. No se cachea HTML financiero
    en el service worker: sin red solo `offline.html` (sin postear libro).
45. El View composer no usa `static` de proceso PHP: disponible y alertas
    vienen de `resumenShell` (Cache Laravel TTL corto + `olvidarResumenShell`
    al contabilizar). Mentir saldos en el chrome tras un movimiento es un bug.
46. PWA = atajo instalable (web + “Añadir a inicio” en iOS Safari). Escritura
    offline queda fuera de fase. Vendor `/assets/` y Vite `/build/` usan
    network-first; bumpear `finanzas-pwa-vN` tras cambios de SW. No cachear
    HTML autenticado.

47. En `TarjetaCredito` la relación de cronograma se llama `cuotasProgramadas`
    (igual que en `CompraTarjeta`). No debe existir columna scalar `cuotas` en
    `tarjetas_credito`: sombreaba la relación y producía 500 al acceder
    `$tarjeta->cuotas->…`.
