# InnovateCode POS

Punto de venta con Laravel 12, PHP 8.2+, Bootstrap 5, Alpine y Vite. Interfaz en español e importes en MXN.

## Instalación local

Instala Git, PHP 8.2 o superior con sus extensiones, Composer 2 y Node.js compatible con Vite 7 (20.19+ de la rama 20, o 22.12+). SQLite está configurado por defecto; MySQL/MariaDB requiere un servidor propio.

Consulta [Instalación paso a paso](INSTALACION.md) para los requisitos, configuración de PHP y PATH en Windows, preparación de la base, dependencias y soluciones a errores frecuentes.

Con las dependencias, `.env`, APP_KEY, tablas y recursos preparados, inicia el proyecto:

```powershell
php artisan serve
```

Abre http://127.0.0.1:8000. La primera cuenta será Administrador y no hay contraseñas predeterminadas. Para recarga automática, ejecuta `npm run dev` en otra terminal. Para una instalación existente sigue la sección de actualización de la guía y conserva su APP_KEY.

## Flujo de operación

1. En Catálogos administra categorías, marcas, unidades y categorías de gastos.
2. Crea productos y registra existencias desde Inventario → Movimiento. El stock solo cambia mediante movimientos y ventas.
3. Abre tu caja con el fondo inicial. Cada usuario tiene su propia caja.
4. En Punto de venta busca por código, SKU o nombre; elige cliente, pago y efectivo recibido. El servidor calcula precios, impuestos y cambio, y registra venta, pago, movimiento de inventario y entrada de efectivo en una transacción.
5. Consulta Ventas para filtrar por folio y fechas, ver detalles e imprimir comprobantes.
6. Crea cotizaciones con vigencia. No reservan stock. Al cobrarlas se validan las existencias y se respetan los precios cotizados; cada cotización solo se convierte una vez.
7. Registra Gastos. Los pagos en efectivo requieren caja abierta y saldo suficiente, y generan una salida.
8. Cierra caja indicando el efectivo contado. Se guarda el esperado y la diferencia.

## Verificación

```powershell
php artisan test
npm run build
php vendor/bin/pint --dirty --test
```

Las pruebas usan SQLite en memoria, sin modificar la base operativa. Cubren autenticación, perfil, ventas, rollback, inventario, caja, gastos, clientes y conversión de cotizaciones.

## Alcance

Los permisos se controlan en el servidor y en la interfaz según el rol; la caja es individual. Los catálogos y clientes se desactivan conservando su historial. Los comprobantes imprimibles no son CFDI. No se incluye timbrado fiscal ni compras a proveedores.

Para publicar, configura APP_ENV=production, APP_DEBUG=false, APP_URL, correo SMTP real y HTTPS. El correo usa el registro local de Laravel por defecto. No ejecutes `migrate:fresh` sobre una base con datos.
## Usuarios y permisos

| Función | Administrador | Vendedor |
| --- | --- | --- |
| Punto de venta y caja propia | Sí | Sí |
| Ventas y cotizaciones | Todas | Solo propias |
| Clientes | Gestionar | Gestionar |
| Productos | Gestionar y consultar costo | Consultar sin costo |
| Inventario | Consultar y registrar movimientos | Consultar |
| Gastos y catálogos | Gestionar | Sin acceso |
| Usuarios y asignación de roles | Gestionar | Sin acceso |
| Panel | Actividad del negocio | Actividad propia |

El registro público solo permite crear la primera cuenta de la instalación, con rol Administrador. Después, las cuentas se crean desde **Usuarios**. No se permite eliminar ni degradar al último administrador. El rol no se puede modificar desde el perfil ni enviando un campo extra al registro.

Para actualizar una instalación existente, ejecuta `php artisan migrate --force`. Si solo existe una cuenta, se asigna como Administrador; con varias cuentas, se asigna Vendedor a las cuentas sin rol y el propietario debe elegir explícitamente al administrador mediante `php artisan users:make-admin correo@ejemplo.com`.
## Productos de demostración

Para cargar un catálogo de ejemplo de ferretería, con una cuenta Administrador ya creada:

```powershell
php artisan db:seed --class=ProductSeeder
```

Se crean 35 productos con SKU `DEMO-FER-001` a `DEMO-FER-035`, marcas de demostración, categorías y unidades. Incluyen costos y precios ficticios, IVA de ejemplo del 16%, existencias iniciales y movimientos de entrada atribuidos al primer administrador. Hay productos por pieza, bolsa, rollo, metro, kilogramo y litro, además de algunos con existencias bajas para probar las alertas.

El seeder es opcional y no se ejecuta dentro de `DatabaseSeeder`. Repetirlo conserva los productos existentes por SKU, sus cambios de precio, estado y existencias; no duplica movimientos ni repone automáticamente el inventario. Un conflicto de código de barras cancela toda la carga.

Los códigos `200000…` tienen dígito de control EAN-13 para pruebas internas. No son códigos oficiales de fabricantes y los importes no representan precios comerciales. Sustituye los datos de demostración antes de operar con mercancía real.
## Cancelaciones y devoluciones

Solo el Administrador puede registrar ajustes. Desde **Ventas → Detalle de venta** se puede:

- Cancelar una venta completa sin devoluciones previas. Se reintegran todos los productos y se registra el reembolso completo.
- Devolver uno o varios productos, en cantidades parciales o totales, hasta el límite vendido. La venta queda en DEVOLUCION_PARCIAL o DEVUELTA.
- Desmarcar **Reintegrar** para mercancía que no vuelve al inventario disponible. La cantidad se cuenta como devuelta, pero no aumenta las existencias.
- Indicar un motivo obligatorio y consultar el folio, responsable, conceptos, reembolso y comprobante de cada ajuste.

En efectivo, el importe sale de la caja abierta del administrador que procesa la operación, con saldo suficiente. Puede ser una caja distinta de la original o de un turno posterior; no se cambian movimientos ni cierres anteriores. Si el importe calculado es cero no se genera una salida de efectivo.

Los pagos con tarjeta o transferencia no admiten devoluciones ni cancelaciones con reembolso. Se conserva el método de pago original aunque después se desactive o renombre. Los pagos mixtos o importes inconsistentes requieren conciliación.

Los pagos y totales originales permanecen intactos. Las devoluciones se calculan usando los importes originales de cada concepto y una asignación acumulada de centavos para evitar errores en devoluciones sucesivas. Los reportes y gráficas muestran ventas netas de devoluciones según la fecha original de la venta, mientras que Caja refleja la salida en la fecha del reembolso.

Cada solicitud tiene una clave única: repetir la misma operación con la misma clave no duplica el reembolso ni las existencias. Reutilizar la clave con otros datos se rechaza. Los cambios de venta, historial, inventario y caja se guardan dentro de una transacción con bloqueo de registros.

El menú **Devoluciones** permite buscar cancelaciones o devoluciones por su folio o el de la venta. El vendedor puede consultar el historial y los comprobantes de sus propias ventas, pero no registrar ajustes ni acceder al historial global.

Una cotización convertida permanece vinculada a la venta aunque esta se cancele; no se habilita un segundo cobro de la misma cotización. Este módulo registra ajustes internos del POS y no cancela CFDI.
### Política de reembolsos

Solo se permiten devoluciones y cancelaciones con reembolso de ventas pagadas en efectivo, hasta 72 horas inclusive desde la compra original. Las devoluciones parciales no reinician el plazo. No se permiten para tarjeta o transferencia, incluso con referencia bancaria. La política y la fecha límite para efectivo se imprimen en el comprobante de venta. Los registros históricos se conservan.

### Bitácora de auditoría

Los administradores pueden consultar `/audit` desde el menú Bitácora y filtrar por fechas, evento, usuario, responsable, entidad o IP. Registra accesos exitosos, fallidos, bloqueos por intentos y cierres explícitos de sesión; también altas, modificaciones y bajas realizadas con una sesión autenticada en productos, usuarios, roles, clientes, catálogos, ventas, devoluciones, cotizaciones, gastos, caja e inventario. Conserva usuario, fecha, IP, dispositivo y campos permitidos antes/después. No guarda contraseñas, hashes, tokens ni cuerpos completos de solicitudes. Los cambios de contraseña solo generan una marca. No reconstruye eventos anteriores ni registra modificaciones SQL externas o tareas sin usuario autenticado. El cierre automático por expiración de sesión no genera un evento de salida. No ofrece rutas para editar o borrar registros.

### Turno del vendedor

Al iniciar sesión, el vendedor pasa por `/shift/start`: puede abrir caja con un fondo inicial o entrar en modo consulta. Sin caja propia abierta no puede cobrar (incluidos tarjeta y transferencia), crear, editar ni eliminar datos; solo consulta pantallas, listas y comprobantes. Puede abrir caja posteriormente. Una caja existente se conserva y no se duplica. Los permisos administrativos mantienen su comportamiento.

El vendedor cierra caja desde Finalizar sesión o Cerrar caja y finalizar sesión. Se muestra el efectivo esperado y se solicita efectivo contado; confirmar guarda el cierre y la diferencia, registra la salida y termina la sesión. Cancelar conserva caja y sesión. Sin caja abierta puede salir directamente. El cierre compara el ID del turno para evitar cerrar otra caja desde una pantalla anterior. Las operaciones del vendedor usan el mismo bloqueo de usuario para coordinar cobros y cierre. Cerrar la pestaña o expirar la sesión no cierra automáticamente la caja: al regresar se retoma el turno pendiente.

## Logs y soporte técnico

Los logs se generan en `storage/logs/diagnostics-AAAA-MM-DD.log`. Cada solicitud recibe una referencia `X-Request-ID`; los errores JSON incluyen `request_id` y los avisos de error muestran la referencia para localizar el fallo.

Se registran excepciones con archivo, línea y traza sin argumentos, operaciones que modifican datos, respuestas de error, campos rechazados por validación y solicitudes lentas. Incluyen ruta, usuario cuando está disponible, estado HTTP y duración. Los fallos capturados por caja e inventario también quedan registrados.

### Investigar un fallo

1. Anota la referencia del error, la hora y la operación realizada.
2. Busca la referencia en los archivos de diagnóstico:

    ```powershell
    Select-String -Path storage/logs/diagnostics-*.log -SimpleMatch 'REFERENCIA-DEL-ERROR'
    ```

3. Revisa los eventos `http.request` y `application.exception` asociados a la referencia.
4. Ante un fallo al cobrar, comprueba si la venta quedó registrada antes de repetir el cobro.

### Configuración de logs

```dotenv
LOG_DIAGNOSTICS_LEVEL=info
LOG_DIAGNOSTICS_DAYS=14
LOG_SLOW_REQUEST_MS=1500
```

Los archivos rotan diariamente, con retención predeterminada de 14 días. El proceso PHP necesita permiso de escritura en `storage/logs`. Las solicitudes normales de lectura no generan entradas, salvo error o lentitud. Si utilizas caché de configuración, reconstrúyela tras cambiar estas variables.

Estos logs no guardan cuerpos de solicitudes, contraseñas, tokens, cookies, mensajes crudos de excepciones ni consultas SQL con sus valores. La bitácora administrativa conserva su función de historial de actividad. Los logs del navegador y del servidor web se revisan por separado.

Guía completa: [Logs y diagnóstico](LOGS_Y_DIAGNOSTICO.md). Estado y pendientes: [Estado del POS](ESTADO_Y_PENDIENTES_POS.md).
