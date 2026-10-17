# Instalación de InnovateCode POS

Esta guía describe el proyecto actual: Laravel 12, PHP 8.2 o superior y Vite 7. Los comandos de ejemplo son para PowerShell en Windows y se ejecutan desde la carpeta del proyecto.

## Qué instalar

| Programa | Requisito y uso |
| --- | --- |
| Git | Descargar y actualizar el repositorio. |
| PHP | Versión 8.2 o superior, compatible con las dependencias de composer.lock. Ejecuta Laravel y las herramientas de consola. |
| Composer | Versión 2, para instalar las dependencias de PHP. |
| Node.js y npm | Node 20.19 o superior de la rama 20, o Node 22.12 o superior; requisitos definidos en package.json. npm instala y compila los recursos de la interfaz. |
| Base de datos | SQLite para la configuración local inicial. MySQL/MariaDB es una alternativa que requiere servidor y credenciales propios. |
| Navegador | Para acceder al POS e imprimir los comprobantes actuales. |

Laravel, Bootstrap, Alpine, Tailwind y Vite se instalan con Composer y npm desde las dependencias del repositorio. No necesitas instalar Laravel globalmente. Redis y Docker no son necesarios para la configuración local de ejemplo.

En Windows puedes utilizar el PHP de XAMPP si cumple la versión y las extensiones necesarias. Agrega su carpeta de PHP al PATH junto con Composer, Node.js y Git; abre una terminal nueva tras modificarlo. En este equipo PHP está en `C:/xampp/php`, pero la ruta puede ser distinta en otra instalación. Para `php artisan serve` no necesitas iniciar Apache; inicia MySQL solo si elegiste esa base de datos.

## Extensiones de PHP

Verifica Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer y XML. Para SQLite necesitas `pdo_sqlite` y `sqlite3`; para MySQL/MariaDB, `pdo_mysql`. ZIP facilita la instalación de paquetes con Composer en Windows. Las dependencias de desarrollo pueden requerir extensiones adicionales; `composer check-platform-reqs` comprueba los requisitos de las dependencias instaladas.

Consulta qué PHP y qué configuración utiliza la terminal:

```powershell
where.exe php
php -v
php --ini
php -m
composer --version
node -v
npm -v
git --version
```

Si falta una extensión, habilítala o instálala en el PHP indicado por `php --ini` y reinicia el proceso PHP que sirve el proyecto.

## Primera instalación

Obtén el repositorio con Git y abre PowerShell en su carpeta. No se incluye una URL de clonación porque depende del remoto al que tengas acceso.

Instala las dependencias utilizando las versiones de los archivos de bloqueo:

```powershell
composer install
npm ci
```

Prepara el archivo de configuración sin sobrescribir uno existente:

```powershell
if (-not (Test-Path -LiteralPath .env)) {
    Copy-Item -LiteralPath .env.example -Destination .env
}
```

Para SQLite deja `DB_CONNECTION=sqlite` en `.env`. Si no defines `DB_DATABASE`, la aplicación usa `database/database.sqlite`. Crea el archivo solo si no existe:

```powershell
if (-not (Test-Path -LiteralPath database/database.sqlite)) {
    New-Item -ItemType File -Path database/database.sqlite | Out-Null
}
```

En esta primera instalación, genera la clave, crea las tablas, carga los catálogos y compila la interfaz:

```powershell
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
composer check-platform-reqs
```

Genera la clave únicamente para una instalación nueva con APP_KEY vacía. Si restauras o actualizas una instalación, conserva su APP_KEY; no vuelvas a ejecutar `key:generate`.

El proyecto también ofrece `composer run setup`, que instala dependencias, genera la clave y prepara la base y los recursos. Resérvalo para una primera instalación; los pasos anteriores permiten revisar cada acción y conservar una configuración existente.

## Si eliges MySQL o MariaDB

Instala e inicia el servidor y crea una base de datos y un usuario con permisos para esa base. Antes de las migraciones configura `.env` con tus valores:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=innovatecode_pos
DB_USERNAME=usuario_del_proyecto
DB_PASSWORD=tu_clave
```

Para la conexión específica de MariaDB puedes utilizar `DB_CONNECTION=mariadb`. El usuario y la contraseña del ejemplo son marcadores; no son credenciales creadas por el proyecto. Después de cambiar la configuración local ejecuta `php artisan config:clear` y continúa con las migraciones. Las pruebas automatizadas utilizan SQLite en memoria, por lo que `pdo_sqlite` sigue siendo necesario para ejecutarlas aunque la base operativa sea MySQL.

## Iniciar y acceder

```powershell
php artisan serve
```

Abre http://127.0.0.1:8000. La primera cuenta registrada será Administrador; después se crean los usuarios desde Usuarios y roles. No hay contraseñas predeterminadas.

Para trabajar con recarga automática de la interfaz ejecuta, en otra terminal:

```powershell
npm run dev
```

Mantén ambas terminales abiertas durante el desarrollo. Si utilizas la interfaz compilada con `npm run build`, no necesitas mantener Vite ejecutándose. El script `composer run dev` también inicia procesos adicionales, incluido Pail; para el inicio básico en Windows usa las dos terminales anteriores.

Los 35 productos de demostración son opcionales. Después de crear al Administrador puedes cargarlos con:

```powershell
php artisan db:seed --class=ProductSeeder
```

Son datos ficticios de prueba; sustituye el catálogo y las existencias antes de operar con mercancía real.

## Actualizar una instalación existente

Conserva `.env`, APP_KEY y los datos; respalda la base antes de aplicar migraciones. No ejecutes `migrate:fresh` sobre la base operativa.

```powershell
git pull
composer install
npm ci
php artisan migrate
npm run build
```

No necesitas volver a ejecutar los seeders ni generar otra clave en cada actualización.

## Comprobar y resolver problemas

```powershell
php artisan test
```

Las pruebas usan SQLite en memoria. La última verificación del proyecto completó 103 pruebas correctamente.

| Problema | Qué revisar |
| --- | --- |
| php, composer o npm no se reconoce | Instalación, PATH y terminal nueva. |
| could not find driver | Extensión PDO de la base elegida en el PHP de consola. |
| Error al conectar con MySQL | Servicio iniciado, base creada y valores DB_* de .env. |
| Vite rechaza Node | Versión de Node compatible con package.json. |
| Falta Vite manifest o la interfaz no carga | Ejecuta npm ci y npm run build, o mantén npm run dev activo para desarrollo. |
| No se pueden escribir cachés o logs | Permisos del proceso PHP sobre storage y bootstrap/cache. |
| Error con referencia | Busca el identificador en storage/logs/diagnostics-AAAA-MM-DD.log. |

Guía de soporte: [Logs y diagnóstico](LOGS_Y_DIAGNOSTICO.md). Funciones y operación: [README](README.md).

## Publicación

En el servidor configura `APP_ENV=production`, `APP_DEBUG=false`, URL, HTTPS, correo SMTP y respaldos. El directorio público debe ser `public`. El correo local de ejemplo se escribe en logs; no entrega correos de recuperación reales hasta configurar SMTP. Node.js solo es necesario donde compiles los recursos. `php artisan serve` es el servidor de desarrollo; la publicación requiere configurar el servidor web correspondiente.
