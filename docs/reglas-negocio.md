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
    Excepción: consultar el calendario, la proyección o la situación cierra
    los cortes de tarjeta ya vencidos (`ExtractoTarjetaService`). Ese cierre
    sí causa interés, mora y cuota de manejo en `5200` una sola vez. No
    persiste cortes futuros.
12. Un pago de tarjeta reduce el pasivo y la liquidez por el monto pagado.
    El interés, la mora y la cuota de manejo se causan al corte (débito
    `5200`, crédito pasivo de la tarjeta), no en el pago. La compra ya
    reconoció el gasto de consumo por el capital.
13. Los pagos de préstamos y de tarjetas pueden iniciarse desde Movimientos
    (también desde Deudas / Tarjetas); el dominio siempre usa `PrestamoService`
    o `TarjetaService`. En préstamo el mínimo es la próxima cuota y el exceso
    es abono a capital. En tarjeta se puede pagar en cualquier momento hasta
    el saldo del pasivo: si hay extracto abierto se cubre primero (mínimo
    evita mora; total evita rotación); el exceso es abono extraordinario a
    capital. Los gastos de consumo diario se registran en Gastos, no en
    Movimientos. Un envío a un tercero no es transferencia: es un pago
    genérico (`deuda_personal` / `otra_obligacion`) que reduce liquidez y
    reconoce gasto.
14. Un pago genérico exige referencia única por usuario (`unique` en
    `usuario_id + referencia`) y cuenta operativa. La corrección de un pago
    genérico o de una transferencia entre cuentas propias es reverso contable
    (`ContabilizacionService::revertir`).
15. Una deuda liquidada no genera cuotas ni compromisos futuros.
16. Los gastos recurrentes, ingresos recurrentes y cuotas futuras se muestran
    como proyección hasta que se registre el hecho real.
17. El pago de cuota de préstamo debe cubrir al menos el total de la cuota.
    Cualquier exceso es abono extraordinario a capital: reduce plazo y mantiene
    la cuota (`AmortizacionFrancesa::plazosConCuotaFija`) en método francés;
    en lineal o solo interés se recalcula el mismo número de cuotas pendientes
    con el saldo restante. El cronograma futuro se ajusta (no es UPDATE de
    asientos); el pago guarda un snapshot para permitir corrección.
    En tarjeta el tope es el saldo del pasivo. Si hay extracto abierto con
    restante, el pago aplica primero en cascada: mora, interés rotativo,
    interés de diferido, cargos, capital del diferido exigido este ciclo,
    capital rotativo. Lo que supere el mínimo del extracto (sin pasarse del
    total del extracto) reduce capital rotativo. El exceso sobre el extracto
    —o todo el pago si aún no hay extracto— es abono extraordinario: primero
    capital rotativo no cubierto, luego capital de cuotas diferidas por
    fecha de vencimiento (adelanta las próximas). El interés programado de
    esas cuotas adelantadas se condona (no estaba causado en `5200` si no
    habían entrado al extracto). El pago guarda snapshot de `abonado`,
    `pagada` e `interes_centavos` de las cuotas tocadas. La corrección
    restaura ese snapshot y revierte el asiento del pago; no revierte la
    causación del corte y solo aplica al último pago de la tarjeta, mientras
    no exista un corte posterior.

## Deudas y financiación

18. Una compra financiada conserva valor original, tasa (snapshot al registrar),
    intereses, número de cuotas, capital pagado, cuotas pendientes y saldo
    pendiente. En tarjeta la tasa no se pide en el formulario: compra a 1 cuota
    es corriente (interés 0 en el cronograma interno); compra a 2+ cuotas usa
    `tasa_compras_mensual` y solo la cuota del mes entra al extracto; avance
    usa siempre `tasa_avances_mensual` desde el día del retiro, sin periodo de
    gracia. El saldo corriente no pagado en la fecha límite pierde la gracia y
    rota: el siguiente corte causa interés corriente (`round(capital *
    tasa / 100 * días / 30)`, base 30 días) desde la compra o desde el corte
    anterior. La gracia se conserva solo si este ciclo paga el total y el
    ciclo previo también quedó cubierto (o es el primero). Si a la fecha
    límite no se cubrió el mínimo, el siguiente corte suma mora sobre el
    faltante del mínimo, con `tasa_mora_mensual` o, si está en 0, con la tasa
    de compras. El mínimo = 100% de intereses, mora, cargos y capital exigido
    (cuota del diferido y del avance a cuotas de este ciclo, más el avance a
    una cuota) + `porcentaje_abono_capital_minimo` del capital rotativo
    (default 5). El pago total del extracto suma el capital rotativo
    completo, sin el tope porcentual, y deja fuera el saldo diferido de
    meses futuros; ese diferido sí se puede adelantar con un abono
    extraordinario (capital; interés programado condonado).
19. Una compra o avance con tarjeta no puede superar el cupo disponible
    (cupo − saldo del pasivo en el libro), con bloqueo de fila al registrar.
    Se puede editar nombre, entidad, cupo, días de corte/pago, tasas, % del
    mínimo y cuota de manejo. El cupo no puede quedar por debajo del pasivo
    actual. Al cambiar día de corte o de pago: se recalcula la fecha límite
    del extracto abierto (sin mover su fecha de corte ni montos) y los
    vencimientos de cuotas aún no facturadas (`ciclo_facturacion_id` null);
    extractos cerrados y cuotas ya cargadas a un ciclo no se tocan. Cambiar
    tasas solo afecta compras/avances nuevos y causaciones de cortes futuros.
    Una compra o avance queda liquidada cuando todas sus cuotas están `pagada`
    (el cupo se libera al bajar el pasivo con cada pago); no se anula después
    de eso.
20. Una cuota de préstamo pagada no puede pagarse nuevamente; el pago se
    vincula con `pagos.cuota_prestamo_id`. El pago de tarjeta se vincula al
    extracto (`pagos.ciclo_facturacion_id`), no a una cuota suelta.
    La corrección de un pago es reverso contable del asiento + reapertura de
    la cuota o del extracto; solo aplica al último pago de esa obligación.
    Una compra/avance de tarjeta puede anularse solo si ninguna cuota está
    pagada y ninguna entró a un extracto.
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

36. El calendario (`CalendarioFinancieroService`) no inventa movimientos.
    Sí cierra cortes de tarjeta ya vencidos para que el extracto exista
    (regla 11). Muestra: (a) hechos de radar no revertidos (`ingreso`,
    `gasto`, `aporte_meta`, `retiro_meta`); (b) pagos no revertidos
    (genéricos, préstamo y tarjeta); (c) cuotas pendientes de préstamo y el
    extracto abierto de cada tarjeta en su fecha límite; (d) recurrencias
    activas; (e) marca informativa de corte de tarjeta (monto 0). Apertura,
    cierre y transferencia no entran al radar (no son flujo operativo del día).
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
41. El horizonte del mes corriente (Situación / shell) incluye cuotas de
    préstamo impagas con vencimiento ≤ fin de mes (arrastre de vencidas) y
    el extracto de tarjeta abierto aunque su fecha límite sea anterior. En
    el forecast multi-mes, ese arrastre solo va en el mes 0; los meses
    siguientes suman la cuota de diferido aún no facturada con vencimiento
    en ese mes y la cuota de manejo de cortes futuros, asumiendo que el
    extracto abierto se paga (no se rota el saldo hacia adelante).
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

47. En `TarjetaCredito` la relación de cronograma interno se llama
    `cuotasProgramadas` (igual que en `CompraTarjeta`). No debe existir
    columna scalar `cuotas` en `tarjetas_credito`: sombreaba la relación y
    producía 500 al acceder `$tarjeta->cuotas->…`. El compromiso de caja es
    el ciclo con `estado = abierto` (`cicloAbierto`), no la próxima cuota.
