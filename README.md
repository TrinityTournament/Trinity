# Trinity

Plataforma web para **organizar y participar en torneos** de fútbol y videojuegos (Clash Royale, Brawl Stars, Fortnite, Free Fire y Minecraft). Permite crear torneos en tres formatos (**liga**, **eliminación directa** y **sistema suizo**), inscribirse, invitar jugadores, cargar resultados, ver llaves y tablas de posiciones, armar equipos, publicar noticias y seguir un ranking. Incluye un panel de administración con moderación y **auditoría de todos los cambios en la base de datos**.

Tercera entrega de **Programación Full Stack** — 3° año de BT Tecnologías de la Información, ITS Arias-Balparda (UTU), Uruguay.

- **Front-end:** HTML, CSS y JavaScript vanilla (Mobile First, Flexbox/Grid)
- **Back-end:** PHP 8.3 con arquitectura MVC por capas (Core → Models → Services → endpoints), PDO con consultas preparadas
- **Base de datos:** MySQL 8.0+ (el esquema también carga en MariaDB, pero los triggers de auditoría usan `CAST(… AS JSON)` y requieren MySQL 8)
- **Bot de WhatsApp (opcional):** Node 22, envía códigos de verificación y guarda imágenes subidas
- **Despliegue:** Docker Compose

---

## 1. Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows / macOS) o Docker Engine + el plugin Compose (Linux). Verificá con `docker compose version`.
- ~1,5 GB libres en disco y conexión a internet **solo la primera vez** (para bajar las imágenes de Docker).
- Libre el puerto **8080** (se cambia con `APP_PORT` en el `.env`).

No hace falta tener PHP, MySQL ni Node instalados.

## 2. Levantar el sistema

```bash
cp .env.example .env          # Windows (cmd): copy .env.example .env
docker compose up -d --build
```

Esperá ~1 minuto la primera vez (se construye la imagen y MySQL carga esquema, usuarios, triggers y datos de prueba). Después abrí **<http://localhost:8080>**.

Para ver el avance: `docker compose logs -f db` (termina con *"ready for connections"*) y `docker compose ps` (los servicios deben figurar como `healthy`).

> El `.env.example` trae contraseñas **de desarrollo**, suficientes para correrlo en tu máquina. No uses esos valores si exponés el sistema en una red.

### Comandos útiles

| Qué | Comando |
|---|---|
| Ver estado | `docker compose ps` &nbsp;·&nbsp; `./scripts/docker/estado.sh` |
| Ver logs del sitio / de la base | `docker compose logs -f web` &nbsp;·&nbsp; `docker compose logs -f db` |
| Detener (conserva los datos) | `docker compose stop` |
| Volver a arrancar | `docker compose up -d` |
| Desarrollar sin reconstruir la imagen | `docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d` |
| **Reiniciar la base de datos desde cero** | `docker compose down -v && docker compose up -d --build` |
| Reconstruir tras cambiar código | `docker compose up -d --build web` |
| Entrar a MySQL como root | `docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" trinity'` |
| Cliente web de MySQL (solo desarrollo) | `docker compose --profile dev up -d` → <http://localhost:8081> (servidor `db`) |
| Con el bot de WhatsApp | `docker compose --profile bot up -d --build` (ver §8) |

> **`down -v` borra el volumen de la base** y vuelve a cargar todo desde cero, con los datos de prueba. Sin `-v` los datos se conservan. La carga inicial solo ocurre cuando el volumen está vacío.

Para arrancar **sin datos de prueba** (solo estructura), poné `LOAD_SEED=0` en el `.env` y reiniciá de cero.

## 3. Cuentas de prueba (defensa)

Se inicia sesión con **email y contraseña**:

| Rol | Email | Contraseña |
|---|---|---|
| **Administrador** | `admin@trinity.test` | `Admin2026!` |
| **Organizador** | `organizador@trinity.test` | `Organizador2026!` |
| **Participante** | `participante@trinity.test` | `Participante2026!` |

Los otros 61 usuarios del seed (2 admins más, 7 organizadores y 52 participantes) comparten la contraseña `Trinity2026!`; su email está en la tabla `usuarios` (todos terminan en `@trinity.test`). Algunos solo tienen teléfono (formato `+598 90 xxx xxx`) y entran con él.

> Son credenciales de **demostración** para el seed. Los teléfonos y emails son ficticios (el dominio `.test` está reservado y no existe) y todos tienen `notif_whatsapp = 0`, así que aunque conectes el bot **nunca se le escribe a un número real**.

### Qué mirar en la defensa

| Quiero ver… | Cómo |
|---|---|
| Los **tres formatos con datos reales** | Torneos finalizados: **#1** *Copa Trinity de Fútbol 5* (eliminación), **#2** *Copa Estrellas del Sur* (liga), **#4** *Copa Rambla Futsal* (suizo). En curso: **#28** (eliminación), **#25** (liga), **#20** (suizo) |
| **Cargar un resultado / avanzar un torneo** | Entrar como **organizador** → su torneo en curso **#31** *Liga Brawlers Montevideo* (eliminación, privado) tiene un partido jugable; terminarlo dispara la siguiente ronda y, al final, el campeón y el ranking |
| **Inscribirse** | Como **participante**, a un torneo abierto con cupo (ej. **#33** *Fortnite Trios Primavera*). El **#30** (privado) tiene el cupo lleno, 8 de 8 |
| **Moderar noticias** | Como **admin**: `pages/admin/` → **Noticias**, 6 pendientes de aprobar |
| **Aprobar organizadores** | Como **admin**: `pages/admin/` → **Solicitudes** (24 pendientes; la insignia del menú muestra cuántas). *Aprobar* y *Rechazar* hacen lo mismo que `/aprobar N` y `/rechazar N` por WhatsApp: quien se aprueba pasa a *organizador* y nunca se degrada a un admin |
| **Administrar usuarios y torneos** | Como **admin**: **Usuarios** (búsqueda, filtro por rol, cambio de rol con confirmación; no se puede cambiar el propio) y **Torneos** (filtro por estado y búsqueda, con inscriptos / cupo reales) |
| **Notificaciones** | Con sesión iniciada, la **campana** de la barra: contador de no leídas, lista, *marcar todas* y ir al torneo o equipo. Ej.: `marina.fernandez@trinity.test` / `Trinity2026!` |
| **Eliminar cuenta** | *Editar perfil* → **Zona de peligro**. Pide la contraseña, borra todo lo asociado (cascada, queda en la auditoría) y no deja que el único administrador se elimine. Probalo con una cuenta de prueba |
| **Pedir ser organizador** | La cuenta *Participante Demo* no tiene solicitudes: se puede hacer en vivo |
| **Ranking** | `Participante Demo` (3 de 3), *Sebastián Rocha* (2 de 7) y *Abril Rocha* (2 de 3) encabezan |
| **Auditoría** | Como **admin**: `pages/admin/` → sección **Auditoría**. Hacé un cambio como otro usuario (editar perfil, inscribirse, cargar un resultado) y aparece con su usuario y su IP |

## 4. Verificar que quedó bien

```bash
# ¿hay 50+ filas en cada tabla y los datos son coherentes? (debe terminar en "TODO OK (57/57)")
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" trinity' < database/extras/seed_verificar.sql

# conteos rápidos
docker compose exec db sh -c 'mysql -utrinity_readonly -p"$DB_READONLY_PASS" trinity -e "
  SELECT (SELECT COUNT(*) FROM usuarios) usuarios, (SELECT COUNT(*) FROM torneos) torneos,
         (SELECT COUNT(*) FROM partidos) partidos, (SELECT COUNT(*) FROM auditoria) auditoria"'

# prueba de inmutabilidad: debe responder "UPDATE command denied"
docker compose exec db sh -c 'mysql -utrinity_app -p"$DB_PASS" trinity -e "UPDATE auditoria SET tabla=\"x\""'
```

### Datos de prueba sembrados

| Tabla | Filas | | Tabla | Filas |
|---|---:|---|---|---:|
| usuarios | 64 | | seguidores | 140 |
| torneos | 56 | | cuentas_videojuego | 67 |
| torneo_participantes | 288 | | torneo_invitaciones | 167 |
| partidos | 286 | | solicitudes_organizador | 54 |
| equipos | 52 | | mensajes_contacto | 56 |
| equipo_miembros | 305 | | password_resets | 52 |
| noticias | 54 | | codigos_verificacion | 52 |
| notificaciones | 510 | | **auditoria** (generada por los triggers) | 1.159 |

- Los **56 torneos** cubren los 5 estados (18 finalizados, 12 en curso, 14 abiertos, 6 en creación, 6 cancelados), los 3 formatos, públicos y privados, y las 6 disciplinas.
- Los **partidos de los torneos en curso y finalizados** se generaron con los mismos algoritmos que `MatchService` (llaves con byes, método del círculo para la liga, emparejamiento suizo ronda a ronda). Se comprobó con el código real que **los 12 torneos en curso se pueden seguir jugando hasta terminarlos**, y que el ranking (`torneos_jugados` / `torneos_ganados`) coincide con los torneos finalizados sembrados.
- `password_resets` y `codigos_verificacion` son tablas transitorias; se sembraron igual (52 filas) pero **todas ya vencidas o usadas**: ningún token sembrado sirve para recuperar una cuenta.
- El seed es **idempotente**: se puede volver a cargar (`mysql … < database/extras/seed.sql`) y primero borra solo lo que sembró antes. Y es **reproducible**: `python3 tools/generar-seed.py` regenera exactamente el mismo archivo. Las fechas son relativas al día de la carga.

## 5. Estructura del proyecto

```
.
├── Dockerfile                  Imagen web (PHP 8.3 + Apache): sitio + API
├── docker-compose.yml          Servicios: db, web, whatsapp (opcional), adminer (dev)
├── docker-compose.dev.yml      Override OPCIONAL de desarrollo: monta el código, sin rebuild
├── .env.example                Variables de TODO el proyecto, Docker y sin Docker (copiar a .env)
├── .gitignore  .dockerignore   (el .dockerignore es una lista blanca: solo entra lo que la imagen usa)
├── composer.json composer.lock Dependencias PHP; vendor/ se genera en la raíz (junto a app/)
│
├── docker/                     SOLO configuración de contenedores
│   ├── apache.conf security.conf php.ini      Apache y PHP de la imagen web
│   └── mysql.cnf                              Charset y zona horaria de MySQL
├── database/                   TODO lo de la base de datos; se monta entera en el contenedor MySQL
│   ├── 01-schema.sql           Esquema (15 tablas + auditoria), comentado
│   ├── 02-dcl.sh               Crea los usuarios de BD con las contraseñas del .env
│   ├── 03-auditoria.sql        24 triggers de auditoría (generado)
│   ├── 04-seed.sh              Carga los datos de prueba (si LOAD_SEED=1)
│   └── extras/                 Lo que MySQL ignora y leen los scripts o se usa a mano
│       dcl.example.sql  seed.sql  seed_verificar.sql
├── scripts/
│   ├── docker/                 backup.sh restore.sh estado.sh      Operación del stack Docker
│   └── server/                 Servidor Linux SIN Docker: backup.sh monitor.sh usuarios.sh
│                               cron-install.sh wacheck.sh trinity.conf (vhost) router.php (php -S)
├── tools/                      Generadores (opcionales): generar-seed.py, generar-auditoria.php
├── docs/                       auditoria.md, logs.md
│
├── Trinity-page/               FRONT-END solamente (DocumentRoot): index.html style.css components/ pages/
├── app/                        BACK-END PHP (MVC por capas), carpeta HERMANA de Trinity-page/
│   ├── auth/ users/ tournaments/ teams/ news/ notifications/ rankings/ admin/ contact/ verification/ assets/
│   └── src/  Core/ Models/ Services/
└── WhatsApp/                   Bot de WhatsApp (Node): Dockerfile, .env.example propio y assets/ (imágenes del sitio)
```

**Regla de oro:** `docker/` y `database/` no contienen código de la aplicación; `Trinity-page/` y `app/` no contienen infraestructura. Las imágenes del sitio viven en `WhatsApp/assets/` a propósito: las sirve el bot por HTTP (corre fuera de Docker, por ngrok) y no se copian al front.

## 6. Decisiones técnicas

- **Una imagen web autocontenida (sellada).** El `Dockerfile` de la raíz mete dentro de la imagen `Trinity-page/` y `app/` como carpetas hermanas (igual que en un servidor real) y `vendor/` en la raíz, que se instala siempre con `composer install` desde `composer.lock` (versiones exactas; hace falta internet al construir). El código queda de solo lectura para Apache, así que ver un cambio exige `docker compose up -d --build web`; para desarrollar existe `docker-compose.dev.yml`, que monta `app/` y `Trinity-page/` encima de la imagen. Las imágenes del sitio (90 MB) no van en la imagen: se montan de solo lectura.
- **Un solo montaje para inicializar la base.** `database/` entera se monta en `/docker-entrypoint-initdb.d`: MySQL ejecuta `01 → 02 → 03 → 04` en ese orden y solo con el volumen vacío; la subcarpeta `extras/` la ignora y la leen los scripts. Por eso un `.sql` suelto en `database/` se ejecutaría en el arranque: lo que no deba correr automáticamente va en `extras/`.
- **Usuarios de BD con privilegios mínimos.** La app se conecta como `trinity_app` (solo DML), nunca como root. Hay otros tres usuarios: `trinity_readonly`, `trinity_backup` y `trinity_migrator` (DDL, solo a mano). En Docker se crean a partir de la **misma plantilla** que se usa en un servidor real (`database/extras/dcl.example.sql`): el script `database/02-dcl.sh` reemplaza `localhost` por `%` (los contenedores se conectan por red) y las contraseñas por las del `.env`. **No hay contraseñas escritas en ningún archivo del proyecto.** El `%` es seguro porque MySQL no publica su puerto: solo es alcanzable desde la red interna de Compose.
- **Auditoría con triggers de MySQL**, que registran aunque se toque la base directamente. Detalle en [`docs/auditoria.md`](docs/auditoria.md). `trinity_app` solo puede `SELECT`/`INSERT` en esa tabla, por eso sus permisos se otorgan tabla por tabla.
- **El sistema funciona sin el bot de WhatsApp ni Brevo.** Las imágenes del sitio se leen del disco (`ASSETS_DIR`) y, si el bot no responde, `WhatsAppClient` falla rápido y devuelve un mensaje claro. Ver §8.
- **Seguridad del contenedor web:** el vhost bloquea `app/src/`, `app/config.php`, `*.sql`, `*.md`, `*.sh`, `.env`, los `.js`/`.json` sueltos de `app/videogames/` (scripts de consola y cachés de estadísticas que antes se podían descargar) y el listado de directorios; PHP no muestra errores al cliente (van al log); el código es de solo lectura para Apache; cabeceras `nosniff`/`X-Frame-Options`; el contenedor no publica el puerto de MySQL.
- **Se corrigió un bug del proyecto** descubierto al ejecutar `MatchService` con distintas cantidades de inscriptos: en **eliminación directa con 5, 6, 9 a 13, 17… jugadores** (siempre que sobran 2 o más "byes") el torneo **quedaba trabado para siempre** en `en_curso`, porque los byes se apilaban al final del cuadro y algún partido de la ronda siguiente nunca recibía rival. Ahora los byes se reparten por el cuadro. Probado de 2 a 33 jugadores: siempre termina con *n−1* partidos jugados. Liga y suizo no estaban afectados.

## 7. Correr sin Docker (servidor tradicional)

1. Instalar PHP 8.3 (con `pdo_mysql`, `curl`, `mbstring`) y MySQL 8.
2. `mysql -u root -p < database/01-schema.sql`
3. Copiar `database/extras/dcl.example.sql` a `database/extras/dcl.sql`, cambiar los `CAMBIAR_*_PASSWORD` y ejecutarlo.
4. `mysql -u root -p < database/03-auditoria.sql` y, si se quieren datos de prueba, `database/extras/seed.sql`.
5. Copiar `.env.example` a `.env` (en la **raíz**, ya no dentro de `Trinity-page/`) y completarlo (`DB_USER=trinity_app`).
6. Desarrollo rápido: `php -S localhost:8000 -t Trinity-page scripts/server/router.php` (desde la raíz). En producción: Apache con `scripts/server/trinity.conf`.

### Scripts de `scripts/server/` (servidor Linux)

| Script | Qué hace |
|---|---|
| `backup.sh` | `mysqldump` comprimido con rotación; usa `trinity_backup` y se llama a `wacheck.sh` al final |
| `monitor.sh` | Panel de estado del servidor: servicios (`systemctl`), disco, conexión a MySQL y consultas lentas |
| `cron-install.sh` | Instala la tarea de cron que ejecuta `backup.sh` automáticamente |
| `usuarios.sh` | Gestión de usuarios del **sistema Linux** (no de la base de datos); se ejecuta como root |
| `wacheck.sh` | Pregunta al bot si está conectado y avisa por correo si se cayó |

Asumen un servidor con MySQL local y `systemd`, por eso **no se tocaron para Docker**: para el stack de Compose están las variantes `scripts/docker/backup.sh`, `scripts/docker/restore.sh` y `scripts/docker/estado.sh` (`./scripts/docker/backup.sh` genera `backups/trinity_AAAA-MM-DD_HH-MM.sql.gz` y conserva los últimos 7; `./scripts/docker/restore.sh <archivo>` restaura). En Windows se corren con Git Bash o WSL.

## 8. El bot de WhatsApp y las integraciones (todas opcionales)

**El sitio arranca y funciona sin ellas.** Con la configuración por defecto:

| Función | Sin bot / sin Brevo |
|---|---|
| Login, torneos, equipos, noticias, ranking, auditoría, imágenes del sitio | ✅ funciona |
| **Registrar una cuenta nueva** | ❌ necesita enviar un código: por **email** (clave `BREVO_KEY`) o por **teléfono** (bot). Sin ninguno, la app responde con un mensaje de error claro. Para la defensa usá las cuentas de prueba |
| Recuperar contraseña | ❌ mismo motivo |
| Subir foto de perfil / banner / logo | ❌ se guardan en el bot (`BotStorageClient`); sin él la API responde `502` con *"No se pudo guardar la foto ahora mismo"* y el resto del perfil sigue funcionando |
| Estadísticas de Fortnite en el perfil | ❌ necesita `FNKEY` |
| Perfil de Free Fire (UID, nivel, rango, clan) | ❌ necesita `FF_API_URL` (+ `FF_API_KEY` si tu API la pide). Para verlo funcionando sin la API real: `tools/mock-freefire-api.php` (instrucciones en su cabecera) |

Para activar el bot: poné un `WA_SECRET` en el `.env` (`openssl rand -hex 24`), levantá `docker compose --profile bot up -d --build`, mirá el QR con `docker compose logs -f whatsapp` y vinculá un número. La sesión queda guardada en un volumen. Para correos: poné `BREVO_KEY`.

> La API del bot **queda abierta a cualquiera de la red si `WA_SECRET` está vacío**; por eso el compose lo exige para el perfil `bot`.

## 9. Qué se probó y qué no

**Probado** con PHP 8.3, Apache 2.4 + mod_php y MySQL 8.0.46 reales:

- Esquema, triggers y seed cargan sin errores en modo SQL estricto; la verificación del seed pasa (57/57) y **detecta datos rotos** (prueba negativa). Cargar el seed dos veces no duplica nada. Es reproducible.
- La cadena de arranque de la base tal como la ejecuta Docker (`01 → 02 → 03 → 04`), con el script de usuarios ejecutado y con `source`, y contraseñas con símbolos `' " \ & $`.
- Con el vhost real: qué archivos se sirven y cuáles se bloquean (SQL, `.env.example`, `vendor`, `database`, `scripts`, `app/src`, `app/config.php`, path traversal, listado de directorios).
- Login de los tres roles (email y teléfono), contraseña incorrecta, y control de acceso a la auditoría (anónimo `401`, participante `403`).
- Auditoría a través de la app: editar perfil, crear torneo, inscribirse, cambiar rol y **borrar una cuenta con todo lo que cuelga** (conteos de tablas = conteos de auditoría). Lo mismo para torneos y equipos.
- `trinity_app` no puede modificar ni borrar la auditoría (6 intentos, todos denegados).
- La pantalla de auditoría del admin, ejecutada contra la API real (carga, filtros, detalle, paginación, error de validación).
- Servicios de torneos: los 12 torneos en curso se terminan de jugar con `MatchService`; eliminación directa de 2 a 33 jugadores; liga y suizo con 2 a 9 y 12 jugadores.
- Sin bot ni Brevo: ningún error fatal; todas las funciones afectadas responden con mensajes JSON.
- **Panel de admin, solicitudes, notificaciones y eliminar cuenta** (octubre): el JavaScript real de cada pantalla se ejecutó con jsdom contra el backend PHP y MySQL 8 reales. Panel: control de acceso (invitado y participante se redirigen), datos reales de usuarios, torneos y solicitudes, búsqueda sin tildes, filtros, cambio de rol, aprobar y rechazar (el rol cambia o no según corresponda). Solicitudes: la vía web y la del bot (`resolve.php`) dan los mismos resultados y mensajes; aprobar no degrada a un admin. Campana: contador, apertura, marcar una o todas, navegación, y que un título con `<img onerror>` o un link `javascript:` / `//` no se ejecute ni se enlace. Eliminar cuenta: sin contraseña, con contraseña incorrecta, correcta (cascada + auditoría + sesión cerrada) y el único admin. Barrido de las 25 páginas con sesión: 0 errores de JavaScript, antes y después. Los endpoints que modifican datos desde estas pantallas exigen token CSRF. jsdom ejecuta el JS pero **no dibuja**: la revisión visual sigue pendiente.
- Backup, rotación y restauración (los datos y los 24 triggers quedan idénticos), `wacheck.sh` contra un bot simulado, el bot de Node (instala, arranca, API con secreto y bloqueo de traversal), y el modo `php -S` con `scripts/server/router.php`.

**Reorganización de carpetas (octubre):** se verificó fuera de Docker con las mismas versiones: `php -l` en los 119 PHP y `bash -n` en los 10 scripts; `composer install` desde el lock; la cadena `01→02→03→04` sobre MySQL 8.0 (con scripts ejecutables y con `source`, y con `LOAD_SEED=0`) → seed `57/57`, 24 triggers, 4 usuarios, auditoría inmutable; la app con Apache y el vhost de `docker/` sobre el layout de la imagen (login de organizador y admin, auditoría, y los archivos que se sirven y los que se bloquean); `php -S` con el nuevo `router.php`; `scripts/server/backup.sh` con el `.env` en la raíz; `tools/generar-seed.py` reproduce `seed.sql` byte a byte; el compose y el override son YAML válido y todas sus rutas existen; el contexto de build (lista blanca) no incluye `.env`, SQL ni scripts.

**NO se pudo probar** (en el entorno donde se preparó no hay Docker):

- ❌ **`docker compose up` / `docker build` no se ejecutaron nunca.** `docker-compose.yml`, `docker-compose.dev.yml`, los dos Dockerfile y el `.env.example` están revisados (YAML válido, todas las variables cruzadas con el `.env.example`) y cada pieza se probó por separado, pero **el ensamblado no**. Si algo falla al primer arranque, es lo primero a revisar. Pasos para comprobarlo:
  1. `docker compose up -d --build` → `docker compose ps` debe mostrar `db` y `web` en `healthy`.
  2. <http://localhost:8080> carga con imágenes; iniciar sesión con `admin@trinity.test`.
  3. Comando de verificación de §4 → `TODO OK (57/57)`.
  4. `docker compose down -v && docker compose up -d` → vuelve a quedar igual.
  5. `docker compose --profile bot up -d --build` (con `WA_SECRET`) → el contenedor `whatsapp` figura `healthy`.
- ❌ **Nunca se vio una página en un navegador.** El CSS Mobile First se validó con un analizador (sintaxis, solo `min-width`, ningún selector perdido) y los cambios en `news.css` con una simulación de la cascada en 1.400 anchos, pero **la prueba visual a 360 px y 1280 px queda pendiente**: conviene abrir las páginas de equipos, mis torneos, panel de organizador y 404 con las herramientas de desarrollo del navegador.
- ❌ Vincular un número real de WhatsApp, enviar un correo real con Brevo, y las estadísticas de Fortnite/Clash Royale/Brawl Stars contra sus APIs (las cuentas sembradas tienen identificadores ficticios).

### Mobile First: alcance

Los cuatro CSS que no tenían ningún `@media` (`teams.css`, `mis-torneos.css`, `organizador.css`, `404.css`) ahora tienen base móvil y mejoras con `min-width` (480 / 640 / 768 / 1024 px). `news.css` también se invirtió a mobile first. **Quedan 17 bloques `max-width`** en `style.css`, `nav.css`, `footer.css`, `admin.css` y las páginas de login, contacto, ranking, detalle de torneo, perfil y configuración. Son bloques grandes (`style.css` tiene uno de 78 declaraciones, `nav.css` de 56) o con propiedades abreviadas, y no se invirtieron porque sin ver la página a 360 y 1280 px el riesgo de romper el diseño es mayor que el beneficio. Quedan señalados como mejora futura.

## 10. Seguridad: lo que hay que hacer vos

- **Rotar las claves.** El paquete original traía un `.env` con claves reales (`BREVO_KEY`, `WA_SECRET`, `ADMIN_KEY`, `FNKEY`, contraseñas de BD), un `database/extras/dcl.sql` con contraseñas de los usuarios de base de datos, y `scripts/server/wacheck.sh` tenía el `WA_SECRET` **escrito en el código**, junto a una URL de ngrok, un teléfono y un correo personales. Todo eso se sacó del paquete de entrega, pero **si alguna vez se compartió o subió a un repositorio, hay que considerarlo comprometido**: regenerá la clave de Brevo, el `WA_SECRET`, la `ADMIN_KEY`, la de Fortnite, la de Free Fire (`FF.js` la traía escrita en el código; ese archivo se retiró) y las contraseñas de MySQL, y si estuvo en git, limpiá el historial.
- El `.gitignore` excluye `.env`, `database/extras/dcl.sql`, `vendor/`, `node_modules/`, la sesión del bot y las subidas.
