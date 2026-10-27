# Trinity

Plataforma web orientada a comunidades de videojuegos y competencias. Trinity permite gestionar perfiles, equipos, torneos, inscripciones, partidas y resultados, además de ofrecer noticias, rankings, notificaciones y herramientas de administración.

Este repositorio contiene el frontend, la API en PHP, el esquema y los datos iniciales de MySQL, y un bot de WhatsApp opcional. La configuración principal de desarrollo utiliza Docker Compose.

## Contenido

- [Características](#características)
- [Tecnologías](#tecnologías)
- [Arquitectura y funcionamiento](#arquitectura-y-funcionamiento)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Requisitos](#requisitos)
- [Instalación con Docker Compose](#instalación-con-docker-compose)
- [Configuración](#configuración)
- [Ejecutar y comprobar el sistema](#ejecutar-y-comprobar-el-sistema)
- [Desarrollo](#desarrollo)
- [Base de datos](#base-de-datos)
- [Bot de WhatsApp (opcional)](#bot-de-whatsapp-opcional)
- [Solución de problemas](#solución-de-problemas)
- [Seguridad](#seguridad)

## Características

- Registro, inicio y cierre de sesión, verificación y recuperación de cuentas.
- Perfiles de usuario, cuentas de videojuegos, seguimiento entre usuarios y equipos.
- Creación, búsqueda, inscripción y administración de torneos.
- Gestión de participantes, rondas, partidas y resultados.
- Soporte para formatos de torneo como eliminación, liga y sistema suizo.
- Noticias y moderación de publicaciones.
- Rankings, notificaciones y auditoría de acciones.
- Paneles diferenciados para administración y organización.
- Integraciones externas opcionales para correo, estadísticas de videojuegos y mensajería.
- Bot de WhatsApp opcional para notificaciones y flujos internos.

La disponibilidad de algunas integraciones depende de que se configuren sus credenciales y servicios externos.

## Tecnologías

| Área | Tecnologías |
| --- | --- |
| Frontend | HTML, CSS y JavaScript |
| Backend | PHP 8.3, API HTTP y clases organizadas por capas |
| Base de datos | MySQL 8.0 |
| Dependencias PHP | Composer y `vlucas/phpdotenv` |
| Servidor web | Apache con `mod_rewrite` y `mod_headers` |
| Entorno recomendado | Docker y Docker Compose |
| Bot opcional | Node.js, Express y `zapo-js` |

## Arquitectura y funcionamiento

### Frontend

El frontend se encuentra en `Trinity-page/`. La página principal y las páginas de cada funcionalidad utilizan HTML, CSS y JavaScript. Los scripts de `components/` centralizan elementos compartidos, como navegación, pie de página, estilos de interfaz y comunicación con la API.

Las páginas del sitio realizan solicitudes HTTP al backend para consultar o modificar información. La interfaz incluye secciones de autenticación, perfil, noticias, rankings, equipos, torneos y paneles de administración u organización.

### Backend y API

La API está en `app/`. Los endpoints reciben solicitudes del frontend, validan la entrada y delegan la lógica de negocio en servicios y modelos.

- `app/*.php` y los subdirectorios de `app/` exponen endpoints agrupados por funcionalidad.
- `app/src/Core/` contiene componentes transversales, como acceso a base de datos, autenticación, sesiones, solicitudes, respuestas, correo e integraciones.
- `app/src/Services/` concentra la lógica de negocio.
- `app/src/Models/` implementa el acceso y las operaciones sobre los datos.
- `app/config.php` registra el autoloader de las clases `Trinity\...` y carga la configuración de entorno.

De forma simplificada, el flujo es:

1. El usuario interactúa con una página del frontend.
2. El JavaScript envía una solicitud HTTP a un endpoint de `app/`.
3. El endpoint utiliza los servicios correspondientes.
4. Los servicios aplican las reglas de negocio y consultan o modifican datos mediante los modelos.
5. La API devuelve una respuesta al frontend, que actualiza la interfaz.

### Base de datos

El esquema relacional se define en `database/01-schema.sql`. La inicialización Docker también crea usuarios de base de datos con distintos privilegios, configura triggers de auditoría y, de forma predeterminada, carga datos de prueba.

Los archivos de inicialización se ejecutan automáticamente **solo cuando MySQL crea un volumen de datos nuevo y vacío**. Cambiar los archivos SQL después de la primera ejecución no vuelve a ejecutar la inicialización automáticamente.

### Bot de WhatsApp

El bot de `WhatsApp/` es un servicio independiente y opcional. El backend PHP puede comunicarse con él mediante una API HTTP interna. El sitio y sus funciones principales pueden iniciarse sin activar el bot.

## Estructura del proyecto

```text
.
├── Trinity-page/          # Frontend: páginas, estilos y JavaScript
│   ├── components/        # Componentes compartidos
│   └── pages/             # Secciones y funcionalidades del sitio
├── app/                   # API y backend PHP
│   ├── auth/              # Autenticación y sesiones
│   ├── users/             # Operaciones de usuarios y perfiles
│   ├── teams/             # Equipos
│   ├── tournaments/       # Torneos, participantes y resultados
│   ├── news/              # Noticias
│   └── src/
│       ├── Core/          # Infraestructura común
│       ├── Models/        # Acceso a datos
│       └── Services/      # Lógica de negocio
├── database/              # Esquema, auditoría, inicialización y datos
│   └── extras/            # Plantillas y datos de inicialización
├── docker/                # Configuración de Apache, PHP y MySQL
├── scripts/               # Scripts de administración y mantenimiento
├── tools/                 # Herramientas auxiliares
├── WhatsApp/              # Servicio opcional de WhatsApp
├── Dockerfile             # Imagen web PHP + Apache
├── docker-compose.yml     # Entorno principal
├── docker-compose.dev.yml # Ajustes opcionales para desarrollo
├── composer.json          # Dependencias PHP
└── composer.lock          # Versiones fijadas de dependencias
```

## Requisitos

Para la instalación recomendada necesitás:

- Docker Engine o Docker Desktop.
- Docker Compose v2 (`docker compose`).
- Git, si vas a clonar el repositorio.

No es necesario instalar PHP, Composer, Apache, MySQL ni Node.js en el host para levantar el sitio con Docker Compose: esos componentes se ejecutan dentro de contenedores. Node.js solo será necesario en el host si decidís ejecutar el bot manualmente fuera de Docker.

## Instalación con Docker Compose

### 1. Obtener el proyecto

Si tenés el repositorio Git:

```bash
git clone <URL_DEL_REPOSITORIO>
cd <CARPETA_DEL_REPOSITORIO>
```

Si recibiste un archivo ZIP, descomprimilo y abrí una terminal en la carpeta que contiene `docker-compose.yml`, `Dockerfile`, `app/`, `Trinity-page/` y `database/`.

### 2. Crear el archivo de entorno

La configuración Compose requiere un archivo `.env` en la raíz del proyecto. En el paquete revisado no se encuentra un `.env.example` en la raíz (solo hay uno dentro de `WhatsApp/`), por lo que podés crear `.env` manualmente con esta plantilla:

```dotenv
# Aplicación
APP_PORT=8080
APP_URL=http://localhost:8080
DB_NAME=trinity
DB_USER=trinity_app

# Contraseñas: reemplazá cada valor por uno propio, largo y único.
DB_ROOT_PASS=CAMBIAR_POR_UNA_CLAVE_ROOT_SEGURA
DB_PASS=CAMBIAR_POR_UNA_CLAVE_APP_SEGURA
DB_READONLY_PASS=CAMBIAR_POR_UNA_CLAVE_READONLY_SEGURA
DB_BACKUP_PASS=CAMBIAR_POR_UNA_CLAVE_BACKUP_SEGURA
DB_MIGRATOR_PASS=CAMBIAR_POR_UNA_CLAVE_MIGRATOR_SEGURA

# Opcionales: dejalos vacíos si no utilizás esas integraciones.
BREVO_KEY=
ADMIN_KEY=
FNKEY=
FF_API_URL=
FF_API_KEY=
WA_SECRET=
LOAD_SEED=1
```

Generá contraseñas seguras y reemplazá **todos** los valores `CAMBIAR_...` antes de iniciar los contenedores. No compartas ni subas el archivo `.env` al repositorio.

En Linux/macOS podés generar una cadena aleatoria, por ejemplo, con:

```bash
openssl rand -hex 24
```

Repetí el comando para obtener valores distintos. Guardá las contraseñas en un lugar seguro.

### 3. Construir e iniciar los servicios

Desde la raíz del proyecto:

```bash
docker compose up -d --build
```

La primera ejecución puede tardar porque descarga imágenes, instala dependencias y prepara la base de datos. El servicio web espera a que MySQL esté saludable antes de arrancar.

### 4. Abrir la aplicación

Abrí en el navegador:

```text
http://localhost:8080
```

Si modificaste `APP_PORT` en `.env`, utilizá ese puerto en la dirección.

### 5. Verificar el estado

```bash
docker compose ps
docker compose logs -f web
docker compose logs -f db
```

Para salir del seguimiento de logs, presioná `Ctrl+C`; esto no detiene los contenedores.

## Configuración

Las variables de entorno principales son:

| Variable | Propósito | Valor predeterminado / requisito |
| --- | --- | --- |
| `APP_PORT` | Puerto del host para el sitio web | `8080` |
| `APP_URL` | URL pública de la aplicación | `http://localhost:8080` |
| `DB_NAME` | Nombre de la base | `trinity` |
| `DB_USER` | Usuario de la aplicación | `trinity_app` |
| `DB_ROOT_PASS` | Contraseña de root de MySQL | Obligatoria |
| `DB_PASS` | Contraseña del usuario de la aplicación | Obligatoria |
| `DB_READONLY_PASS` | Contraseña del usuario de solo lectura | Obligatoria |
| `DB_BACKUP_PASS` | Contraseña del usuario para backups | Obligatoria |
| `DB_MIGRATOR_PASS` | Contraseña del usuario de migraciones | Obligatoria |
| `LOAD_SEED` | Cargar datos de prueba durante la inicialización | `1`; usar `0` para omitirlos |
| `BREVO_KEY` | Clave de integración de correo Brevo | Opcional |
| `ADMIN_KEY` | Clave para una integración administrativa | Opcional |
| `FNKEY` | Clave de una integración externa | Opcional |
| `FF_API_URL` | URL de API externa de Free Fire | Opcional |
| `FF_API_KEY` | Clave de API externa de Free Fire | Opcional |
| `WA_SECRET` | Secreto compartido entre PHP y el bot | Opcional para el sitio; obligatorio para activar el bot |

El backend PHP carga variables del entorno y también admite un archivo `.env` en la raíz. En Docker, las variables se transmiten desde Compose al contenedor web.

## Ejecutar y comprobar el sistema

### Comandos habituales

```bash
# Iniciar servicios en segundo plano
docker compose up -d

# Detener y conservar los datos
docker compose down

# Ver estado
docker compose ps

# Seguir logs de todos los servicios
docker compose logs -f

# Reconstruir la imagen web después de cambios que deban incluirse en ella
docker compose up -d --build web
```

`docker compose down` no elimina el volumen de MySQL. Los datos permanecen disponibles al volver a iniciar el proyecto.

### Cliente web para la base de datos (desarrollo)

El archivo Compose incluye Adminer como servicio opcional:

```bash
docker compose --profile dev up -d
```

Luego abrí `http://localhost:8081` (o el puerto que hayas configurado con `ADMINER_PORT`). Usá `db` como servidor y las credenciales correspondientes. Este servicio está pensado para desarrollo local.

## Desarrollo

Para trabajar sobre el código PHP y frontend sin reconstruir la imagen por cada cambio, existe un Compose de desarrollo que monta las carpetas de código como volúmenes:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

Revisá `docker-compose.dev.yml` para ver qué carpetas y configuraciones se montan. Para volver al modo normal, detené ese conjunto de servicios y ejecutá `docker compose up -d --build`.

Las dependencias PHP se declaran en `composer.json` y se fijan mediante `composer.lock`. La imagen Docker instala esas dependencias durante la construcción.

## Base de datos

### Inicialización

En un volumen nuevo, el contenedor MySQL procesa los archivos en orden:

1. `database/01-schema.sql`: crea el esquema.
2. `database/02-dcl.sh`: configura usuarios y permisos.
3. `database/03-auditoria.sql`: crea triggers de auditoría.
4. `database/04-seed.sh`: carga datos de prueba si `LOAD_SEED=1`.

Los datos de prueba se encuentran en `database/extras/seed.sql`. Si necesitás una base sin esos registros, configurá `LOAD_SEED=0` **antes de la primera inicialización**.

### Reiniciar la base desde cero

**Advertencia:** el siguiente comando elimina permanentemente el volumen de datos de Trinity, incluidos los registros que hayas creado.

```bash
docker compose down -v
docker compose up -d --build
```

Usalo solo en un entorno de desarrollo o cuando realmente quieras borrar la base y recrearla. No ejecutes `down -v` si necesitás conservar datos.

## Bot de WhatsApp (opcional)

El bot no se inicia en el arranque normal. Para activarlo:

1. Configurá `WA_SECRET` en el `.env` de la raíz con una cadena aleatoria segura.
2. Asegurate de que `WA_SECRET` tenga el mismo valor para el backend y el servicio del bot; Compose lo transmite a ambos.
3. Iniciá el perfil `bot`:

```bash
docker compose --profile bot up -d --build
```

4. Consultá los logs para seguir el proceso de conexión:

```bash
docker compose logs -f whatsapp
```

El bot requiere vincular una cuenta/número real de WhatsApp. Seguí las instrucciones que aparezcan en los logs para completar el proceso. Si no vas a usar el bot, no hace falta activar este perfil ni configurar el secreto para ejecutar el sitio principal.

## Solución de problemas

### Compose informa que faltan variables de entorno

Comprobá que el archivo `.env` esté en la misma carpeta que `docker-compose.yml` y que contenga todas las variables obligatorias de contraseñas. No dejes los valores de ejemplo sin reemplazar.

### El sitio no abre en `localhost:8080`

- Ejecutá `docker compose ps` y verificá que `web` esté en ejecución.
- Consultá `docker compose logs -f web` y `docker compose logs -f db`.
- Confirmá que el puerto elegido en `APP_PORT` no esté ocupado por otra aplicación.
- Esperá a que termine la inicialización inicial de MySQL.

### Cambié los archivos SQL, pero la base no se actualizó

Los scripts de `/docker-entrypoint-initdb.d` se ejecutan automáticamente solo cuando el directorio de datos de MySQL está vacío. Para aplicar cambios a una base existente, prepará y ejecutá una migración apropiada o recreá el volumen únicamente si podés perder sus datos.

### La API no puede conectarse a MySQL

Verificá `DB_NAME`, `DB_USER` y `DB_PASS` en `.env`. En Docker, el host de la base es `db` dentro de la red Compose; no es `localhost`.

### El bot no inicia o no se conecta

Comprobá que el perfil `bot` esté activo, que `WA_SECRET` esté definido y coincida para ambos servicios, y que el proceso pueda completar la vinculación de WhatsApp. El resto del sitio puede funcionar sin el bot.

## Seguridad

- No subas `.env`, claves de API, contraseñas ni datos de sesión al control de versiones.
- Cambiá todas las contraseñas de ejemplo antes de ejecutar el proyecto.
- No expongas el puerto de MySQL a redes públicas. La configuración Compose predeterminada lo mantiene dentro de la red interna de Docker.
- Adminer es una herramienta de desarrollo; no lo publiques en un servidor de producción.
- Antes de desplegar en producción, revisá HTTPS, copias de seguridad, credenciales, permisos, registros y configuración de servicios externos.
- Los datos de prueba no deben considerarse cuentas ni contraseñas de producción. Consultá los scripts y datos de `database/extras/` para conocer el contenido cargado en tu entorno.
