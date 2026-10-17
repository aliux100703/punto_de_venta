# Estado y pendientes de InnovateCode POS

Actualizado: 16 de octubre de 2026.

## Funciones implementadas

- Interfaz en español e importes en MXN.
- Roles Administrador y Vendedor, con permisos en el servidor y la interfaz.
- Productos, clientes, catálogos, inventario y movimientos con responsable.
- Ventas, pagos, cotizaciones y conversión de cotizaciones a venta.
- Caja individual, apertura, entradas, salidas, gastos y cierre con diferencia.
- Panel con indicadores y gráficas de ventas.
- Seeder opcional con 35 productos de demostración.
- Cancelaciones y devoluciones parciales o totales, con historial y reembolso.
- Reembolsos solo para pagos en efectivo, hasta 72 horas inclusive desde la compra original. Las devoluciones parciales no reinician el plazo.
- No se permiten devoluciones ni cancelaciones con reembolso de pagos con tarjeta o transferencia.
- Política de devoluciones y fecha límite para efectivo visibles en el comprobante imprimible.
- Bitácora exclusiva para administradores: accesos, intentos fallidos, bloqueos, cierres de sesión y cambios en datos y operaciones. Incluye filtros, usuario, fecha, IP, dispositivo y valores permitidos antes/después. No guarda contraseñas ni tokens.

## Flujo del vendedor acordado

1. Al iniciar sesión se pregunta si desea abrir caja y se solicita el fondo inicial.
2. Si responde que no, entra en modo consulta: no puede vender, crear, editar ni eliminar datos, incluso mediante solicitudes directas al servidor.
3. Puede abrir caja posteriormente. La caja debe ser propia; la de otro usuario no habilita operaciones.
4. Una caja abierta existente se retoma sin duplicar el turno.
5. Para cerrar caja se utiliza el flujo de finalizar sesión: se pide confirmación y efectivo contado.
6. Confirmar guarda el cierre y la diferencia, y finaliza la sesión. Cancelar mantiene caja y sesión abiertas.
7. Sin caja abierta puede finalizar sesión directamente.
8. Cerrar la pestaña o expirar la sesión no cierra la caja automáticamente; el turno pendiente se retoma al regresar.

## Validación realizada

- Suite completa tras agregar los logs: 103 pruebas exitosas (1207 aserciones).
- Pruebas del flujo de vendedor repetidas tras el ajuste final: 5 exitosas.
- Verificación en Chrome con una base temporal: apertura, modo consulta, bloqueo de acciones, confirmación de salida, conteo, cierre, fin de sesión y siguiente acceso.
- Las pruebas no utilizan la base operativa.

## Pendientes, en orden sugerido

1. **Tickets e impresoras:** diseñar formato térmico de 58/80 mm, incluir los datos del negocio y probar con la impresora y el lector de códigos reales. Existe búsqueda por código/SKU y comprobante imprimible desde el navegador; falta validar y adaptar los equipos reales.
2. **Respaldos automáticos:** respaldar base de datos y archivos necesarios; verificar la recuperación mediante una restauración de prueba.
3. **Reportes:** cortes por vendedor, desglose por método de pago y exportación a Excel/PDF. Ya existen indicadores, consultas y resúmenes básicos.
4. **Compras y proveedores:** gestionar proveedores, registrar compras y costos, y recibir mercancía con movimientos de inventario.
5. **Configuración del negocio:** pantalla para nombre comercial, logo, dirección y datos del ticket.
6. **Puesta en producción:** configurar servidor, HTTPS, correo para recuperar contraseñas y verificar operación simultánea con varios vendedores.

Siguiente paso sugerido: tickets e impresoras; después, respaldos automáticos.

## Facturación

Se plantea como un proyecto separado conectado al POS, con aislamiento de datos por proyecto, agencia o usuario. El POS actual no genera CFDI. Consultar el documento existente PROXIMOS_PASOS_FACTURACION.md para retomar la planificación; el aislamiento y la integración siguen pendientes de implementar.

## Estado de Git al guardar esta nota

- Commit local creado: bb8224f, Completa POS con roles, devoluciones, auditoria y turnos de vendedor.
- El último git pull informó que no había cambios nuevos en el remoto.
- No se ha ejecutado git push en esta conversación.
- Siete archivos vacíos accidentales quedaron fuera del commit y se conservaron localmente.
- Esta nota se guarda después del commit mencionado y queda pendiente de incluir en un próximo commit.

## Actualización: diagnóstico técnico

Se agregaron logs diarios de diagnóstico con referencias por solicitud, captura segura de excepciones, errores HTTP, validaciones, operaciones y solicitudes lentas. Se corrigieron los fallos de inventario que antes no se reportaban. Guía: [Logs y diagnóstico](LOGS_Y_DIAGNOSTICO.md). El README incluye ubicación, referencias, configuración y pasos de soporte. Esta actualización y la nota siguen pendientes de un nuevo commit.
