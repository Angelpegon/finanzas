# Reglas de negocio financieras

Estas reglas son invariantes del dominio. Los servicios deben validarlas antes
de crear hechos o asientos; las vistas solo muestran el resultado.

## Movimientos y contabilidad

1. Una transferencia entre cuentas propias mueve saldo entre activos y nunca
   se registra como ingreso ni gasto.
2. Una transferencia exige cuentas de origen y destino activas, pertenecientes
   al mismo usuario y diferentes.
3. Todo ingreso o gasto real exige una cuenta activa, una categoría del tipo
   correcto, fecha y monto positivo.
4. Un movimiento real crea un hecho y su asiento dentro de la misma transacción.
5. Todo asiento debe tener débitos y créditos positivos, equilibrados, y todas
   sus cuentas deben pertenecer al usuario.
6. El saldo de una cuenta se deriva de sus movimientos y saldo inicial; no se
   edita manualmente como fuente de verdad.
7. Los asientos posteados son inmutables. Una corrección debe usar reverso y
   nuevo asiento, no modificar el monto original.
8. Un mismo hecho no puede generar más de un asiento para evitar duplicar
   saldos, ingresos, gastos o salidas.
9. Los montos se almacenan como centavos enteros y nunca se aceptan valores
   cero o negativos.

## Proyecciones y pagos

10. Los movimientos proyectados no crean asientos ni alteran saldos reales.
11. Un pago de tarjeta reduce pasivo y liquidez, pero no crea un nuevo gasto:
    la compra ya reconoció el gasto.
12. Los pagos de préstamos y créditos se registran desde su módulo
    especializado para evitar duplicar salidas.
13. Un pago genérico exige referencia única por usuario y cuenta activa.
14. Una deuda pagada completamente no genera cuotas ni compromisos futuros.
15. Los gastos recurrentes, ingresos recurrentes y cuotas futuras se muestran
    como proyección hasta que se registre el hecho real.

## Deudas y financiación

16. Una compra financiada conserva valor original, tasa, intereses, número de
    cuotas, capital pagado, cuotas pendientes y saldo pendiente.
17. Una compra con tarjeta no puede superar el cupo disponible.
18. Una cuota pagada no puede pagarse nuevamente; el pago se vincula a una
    cuota concreta.
19. Capital, intereses, seguros y otros cargos deben conservarse por separado.
20. La cuota final debe ajustar redondeos para que el saldo de capital llegue
    exactamente a cero.

## Presupuestos, metas y alertas

21. El presupuesto consume únicamente gastos reales de su categoría y mes;
    la proyección se presenta separada.
22. Una meta de ahorro no es un gasto ni un ingreso: sus aportes se identifican
    con la meta y no se contabilizan dos veces.
23. Las alertas son reglas de dominio separadas de la interfaz y no modifican
    datos financieros.

## Seguridad y aislamiento

24. Todas las consultas y escrituras de dominio se filtran por `usuario_id`.
25. Un usuario nunca puede consultar, modificar o relacionar cuentas,
    categorías, deudas, tarjetas, pagos o movimientos de otro usuario.
26. Las validaciones de pertenencia se aplican también cuando se desactivan
    los global scopes para operaciones internas.
