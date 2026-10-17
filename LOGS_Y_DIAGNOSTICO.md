# Logs y diagnóstico del POS

## Dónde revisar

Los logs técnicos se guardan en storage/logs/diagnostics-AAAA-MM-DD.log. La fecha corresponde al reloj de la aplicación. Los archivos se generan cuando hay una operación relevante, un error o una solicitud lenta; no se incluyen en Git ni se publican en la web.

La bitácora del menú sigue disponible para consultar actividades de usuarios. Los archivos de diagnóstico sirven para investigar fallos del sistema. El archivo Laravel habitual puede seguir recibiendo otros mensajes de componentes; las excepciones reportadas por la aplicación se registran de forma segura en diagnostics.

## Qué se registra

- application.exception: clase de excepción, código, archivo, línea y hasta 30 pasos de la traza, sin argumentos.
- http.request: solicitudes que modifican datos, respuestas HTTP de error y solicitudes lentas. Incluye método, nombre y patrón de ruta, ID de usuario si está disponible, IDs numéricos de modelos enlazados, estado HTTP, duración y nombres de campos con errores de validación.
- Las solicitudes normales de lectura no se registran, salvo error o lentitud.
- Los errores capturados por caja e inventario también se reportan. Las validaciones esperadas se registran como advertencias de solicitud.
- Las excepciones de consola se identifican con source=console y no tienen referencia HTTP.

No se guardan cuerpos de solicitudes, cookies, cabeceras de autenticación, contraseñas, tokens, valores de parámetros de URL, mensajes crudos de excepciones ni consultas SQL con sus valores. El mensaje original de una excepción se omite porque puede contener información sensible; se investiga con su clase, código, archivo, línea y traza.

## Cómo investigar un problema

1. Anotar la operación, hora aproximada y referencia mostrada en el error. Cada respuesta lleva también la cabecera X-Request-ID. Las respuestas JSON de error incluyen request_id. La referencia se genera en el servidor.
2. Buscar esa referencia en los archivos diagnostics. Permite unir el error con el resumen de la misma solicitud.
3. Revisar ruta, usuario, estado, campos de validación y, si existe application.exception, clase, archivo, línea y traza.
4. No repetir un cobro sin comprobar primero si la venta quedó registrada.

Ejemplo en PowerShell, sustituyendo la referencia:

    Select-String -Path storage/logs/diagnostics-*.log -SimpleMatch 'REFERENCIA-DEL-ERROR'

Para seguir el archivo del día, sustituyendo la fecha:

    Get-Content storage/logs/diagnostics-2026-10-16.log -Tail 100 -Wait

## Configuración

Valores por defecto, disponibles en .env.example:

    LOG_DIAGNOSTICS_LEVEL=info
    LOG_DIAGNOSTICS_DAYS=14
    LOG_SLOW_REQUEST_MS=1500

El canal usa archivos diarios con retención de 14 días; la limpieza se realiza al rotar los archivos. El directorio storage/logs debe permitir escritura al proceso PHP. Si se utiliza caché de configuración, reconstruirla después de modificar estas variables.

Las respuestas de error del servidor muestran un mensaje general y la referencia, sin exponer la traza al usuario. El diagnóstico cubre PHP/Laravel; no sustituye los logs del servidor web ni captura automáticamente errores JavaScript del navegador.
