# Reglas de negocio financieras

Estas reglas son invariantes del dominio. Los servicios deben validarlas antes
de crear hechos o asientos; las vistas solo muestran el resultado.

## Movimientos y contabilidad

1. Una transferencia entre cuentas propias mueve saldo entre activos y nunca
   se registra como ingreso ni gasto.
2. Una transferencia exige cuentas de origen y destino activas, pertenecientes
   al mismo usuario y diferentes, con saldo suficiente en el origen.
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
13. Los pagos de préstamos y créditos se registran desde su módulo
    especializado para evitar duplicar salidas.
14. Un pago genérico exige referencia única por usuario y cuenta activa.
15. Una deuda liquidada no genera cuotas ni compromisos futuros.
16. Los gastos recurrentes, ingresos recurrentes y cuotas futuras se muestran
    como proyección hasta que se registre el hecho real.
17. El pago de cuota de préstamo debe cubrir al menos el total de la cuota.
    Cualquier exceso es abono extraordinario: reduce plazo y mantiene la cuota
    (`AmortizacionFrancesa::plazosConCuotaFija`).

## Deudas y financiación

18. Una compra financiada conserva valor original, tasa, intereses, número de
    cuotas, capital pagado, cuotas pendientes y saldo pendiente.
19. Una compra con tarjeta no puede superar el cupo disponible.
20. Una cuota pagada no puede pagarse nuevamente; el pago se vincula a una
    cuota concreta.
21. Capital, intereses, seguros y otros cargos deben conservarse por separado.
22. La cuota final debe ajustar redondeos para que el saldo de capital llegue
    exactamente a cero.
23. Los intereses del dashboard se leen del libro (movimientos a `5200`), no
    del calendario proyectado.

## Presupuestos, metas y alertas

24. El presupuesto consume únicamente gastos reales de su categoría y mes;
    la proyección se presenta separada.
25. Una meta de ahorro no es un gasto ni un ingreso: el avance = suma de hechos
    `aporte_meta` contabilizados (transferencia origen → bolsillo de la meta).
    `monto_actual_centavos` es caché derivada, no fuente de verdad editable.
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
