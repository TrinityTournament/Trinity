# Auditoría de cambios en la base de datos

La letra del proyecto (sección 14) exige que *"deberá mantenerse una historia de todo lo realizado ante la Base de Datos"* y que el administrador general pueda **consultar los registros de auditoría**. Este documento explica qué se audita, cómo funciona y cómo se consulta.

---

## 1. Qué se audita

Cada `INSERT`, `UPDATE` y `DELETE` sobre estas **8 tablas críticas**:

| Tabla | Por qué es crítica |
|---|---|
| `usuarios` | Cuentas, roles, contadores del ranking |
| `torneos` | Creación, cambios de estado, cancelaciones |
| `torneo_participantes` | Inscripciones y bajas |
| `partidos` | Generación del fixture y **carga de resultados** |
| `equipos` | Alta, edición y baja de equipos |
| `equipo_miembros` | Invitaciones, altas, bajas y cambios de rol |
| `noticias` | Publicación y moderación |
| `solicitudes_organizador` | Pedidos de pasar a organizador y su resolución |

No se auditan las tablas auxiliares o transitorias (`notificaciones`, `seguidores`, `password_resets`, `codigos_verificacion`, `cuentas_videojuego`, `torneo_invitaciones`, `mensajes_contacto`): son de alto volumen o de bajo valor de trazabilidad. Si el docente lo pide, agregar una tabla es agregarla a la constante `AUDITADAS` de `tools/generar-auditoria.php` y volver a generar el archivo.

## 2. Cómo funciona

```
 navegador ──► endpoint PHP ──► Database.php ──► MySQL
                                   │               │
                          SET @app_user_id = 12    │  trigger AFTER INSERT/UPDATE
                          SET @app_ip = '…'        │  trigger BEFORE DELETE
                                                   ▼
                                           tabla `auditoria`
```

1. **`app/src/Core/Database.php`** le dice a MySQL quién está operando: en cada conexión fija dos variables de sesión, `@app_user_id` (el usuario logueado, o `NULL` si no hay sesión) y `@app_ip` (`REMOTE_ADDR`). Se vuelven a evaluar en cada llamada a `Database::pdo()` y solo se manda el `SET` si cambiaron, porque algunas clases piden la conexión *antes* de que el login escriba la sesión.
2. **Triggers de MySQL** (`database/03-auditoria.sql`) escriben la fila en `auditoria`. Se eligieron triggers y no código PHP porque **registran aunque alguien toque la base directamente** (consola, otro script, otro sistema), sin pasar por la aplicación.
3. El archivo de triggers está **generado** por `tools/generar-auditoria.php`, que lee las columnas reales de `database/01-schema.sql`: así no hay columnas olvidadas ni errores de copiar y pegar. Si cambia el esquema, se vuelve a generar (`php tools/generar-auditoria.php`).

### La tabla

```sql
auditoria (
  id            BIGINT UNSIGNED  PK,
  usuario_id    INT UNSIGNED     NULL,   -- quién (NULL = sin usuario de la app)
  tabla         VARCHAR(64),
  registro_id   INT UNSIGNED,            -- id de la fila afectada
  accion        ENUM('INSERT','UPDATE','DELETE'),
  datos_antes   JSON NULL,               -- la fila antes (NULL en INSERT)
  datos_despues JSON NULL,               -- la fila después (NULL en DELETE)
  ip            VARCHAR(45) NULL,
  creado_en     TIMESTAMP
)
```

`usuario_id` y `registro_id` **no son claves foráneas a propósito**: la historia tiene que sobrevivir al borrado del usuario o de la fila auditada. Con `ON DELETE SET NULL` habría que hacer `UPDATE` sobre la auditoría, que es justo lo que se quiere impedir.

### Qué significa `usuario_id = NULL`

Que el cambio no vino de un usuario logueado en la app: el seed, una consola SQL, un script de mantenimiento o un registro de cuenta nueva (todavía no hay sesión). Los registros del seed llevan además `ip = 'seed'`.

## 3. Decisiones y trampas que se verificaron

**Los borrados en cascada.** En MySQL, una acción referencial (`ON DELETE CASCADE` / `SET NULL`) **no dispara los triggers de la tabla hija**. Se probó con el motor real: al borrar un padre, los hijos desaparecen sin dejar rastro en la auditoría. Para no perder esa información, los triggers `BEFORE DELETE` de `usuarios`, `torneos` y `equipos` **registran a mano** las filas hijas que están por desaparecer. Esas filas llevan `"_cascada": "<tabla>:<id>"` dentro del JSON para saber de qué borrado vienen.

- Al borrar un **torneo**: sus `torneo_participantes` y sus `partidos`.
- Al borrar un **equipo**: sus `equipo_miembros`.
- Al borrar un **usuario**: sus torneos (con inscripciones y partidos), sus inscripciones en torneos ajenos, los partidos ajenos donde jugó o ganó (que MySQL deja con `NULL`, se registran como `UPDATE`), sus equipos y membresías, sus noticias, las noticias que moderó, sus solicitudes y las que resolvió.

Se comprobó cruzando conteos reales: al borrar una cuenta de organizador vía la API, lo que desapareció de las tablas coincidió exactamente con lo registrado (8 torneos, 54 inscripciones, 57 partidos, 1 equipo, 4 miembros, 4 noticias, 1 solicitud y el usuario), todo atribuido al usuario y a la IP correctos.

**Datos sensibles.** El hash de la contraseña **nunca** se copia. Un cambio de contraseña queda visible como `"password_cambiada": true/false`. Las imágenes en base64 (`foto_url`, `banner_url`) se reemplazan por un resumen `md5:<hash>` si son grandes, para no inflar la tabla.

**Sin ruido.** Un `UPDATE` que no cambia ningún valor no genera fila.

## 4. Inmutabilidad (privilegios)

| Usuario | Sobre `auditoria` |
|---|---|
| `trinity_app` (la aplicación) | `SELECT` e `INSERT`. **No** `UPDATE`, `DELETE`, `TRUNCATE`, `DROP`, `ALTER` |
| `trinity_readonly` | `SELECT` |
| `trinity_backup` | `SELECT` (y `TRIGGER` para volcar los triggers) |
| `trinity_migrator` | DDL completo — es un principal de confianza, se usa solo a mano |
| `root` | Todo |

Como MySQL no permite "restarle" `UPDATE/DELETE` a una sola tabla de un permiso dado sobre toda la base, `trinity_app` recibe los permisos **tabla por tabla** (ver `database/extras/dcl.example.sql`). Consecuencia: al agregar una tabla nueva al esquema hay que agregar su `GRANT`; si se olvida, la app falla con `command denied`, que es preferible a dar un acceso que nadie decidió.

Probado con el motor real, conectando como `trinity_app`:

```
UPDATE auditoria …            → ERROR 1142: UPDATE command denied
DELETE FROM auditoria         → ERROR 1142: DELETE command denied
TRUNCATE TABLE auditoria      → ERROR 1142: DROP command denied
DROP TABLE auditoria          → ERROR 1142: DROP command denied
ALTER TABLE auditoria …       → ERROR 1142: ALTER command denied
DROP TRIGGER trg_aud_…        → ERROR 1142: TRIGGER command denied
```

Los triggers se ejecutan con los privilegios de quien los creó (`root` en Docker), por lo que el registro automático no depende de los permisos de la aplicación.

## 5. Cómo consultarla

### Desde el panel de administración

Iniciar sesión con una cuenta **admin** y abrir `pages/admin/` → sección **Auditoría**. Permite filtrar por tabla, acción, id de registro, id de usuario y rango de fechas; pagina de a 25; el botón **Ver** muestra el JSON antes/después de cada cambio. Un usuario que no sea admin recibe `403`, y un anónimo `401`.

### Desde la API (solo lectura)

```
GET /app/admin/auditoria.php
    ?tabla=usuarios            una de las 8 tablas auditadas
    &accion=UPDATE             INSERT | UPDATE | DELETE
    &registro_id=12            id de la fila afectada
    &usuario_id=3              quién hizo el cambio
    &desde=2026-10-01          AAAA-MM-DD
    &hasta=2026-10-31          AAAA-MM-DD
    &pagina=1&por_pagina=25    máximo 100

GET /app/admin/auditoria.php?resumen=1     conteo por tabla y acción
```

No existe ningún endpoint que modifique o borre la auditoría.

### Desde SQL

```sql
-- ¿Quién tocó al usuario 12 y qué cambió?
SELECT a.id, a.creado_en, u.usuario AS quien, a.accion, a.ip,
       a.datos_antes, a.datos_despues
FROM   auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
WHERE  a.tabla = 'usuarios' AND a.registro_id = 12
ORDER  BY a.id;

-- Resultados de partidos cargados en los últimos 7 días
SELECT creado_en, usuario_id, registro_id AS partido,
       JSON_EXTRACT(datos_antes,   '$.resultado1') AS antes_r1,
       JSON_EXTRACT(datos_despues, '$.resultado1') AS despues_r1
FROM   auditoria
WHERE  tabla = 'partidos' AND accion = 'UPDATE'
  AND  creado_en >= NOW() - INTERVAL 7 DAY;

-- Todo lo que desapareció en cascada por el borrado de un usuario
SELECT tabla, COUNT(*) AS filas
FROM   auditoria
WHERE  accion = 'DELETE'
  AND  JSON_UNQUOTE(JSON_EXTRACT(datos_antes, '$._cascada')) = 'usuarios:10'
GROUP  BY tabla;

-- Actividad por usuario
SELECT usuario_id, COUNT(*) AS cambios FROM auditoria GROUP BY usuario_id ORDER BY cambios DESC;
```

Con Docker: `docker compose exec db mysql -utrinity_readonly -p trinity` (la clave es `DB_READONLY_PASS` del `.env`).

## 6. Limitaciones conocidas

- **`root` y `trinity_migrator` pueden alterar la auditoría.** Es inevitable en cualquier base de datos; se mitiga usándolos solo a mano y no desde la aplicación. Para una inmutabilidad real habría que enviar la auditoría a un sistema externo de solo escritura (fuera del alcance).
- **La IP es `REMOTE_ADDR`.** Detrás de un proxy inverso mostraría la IP del proxy; `X-Forwarded-For` no se usa porque el cliente puede falsearlo. En Docker, accediendo desde el mismo equipo, se verá la IP del gateway de Docker (`172.x.x.x`), no `127.0.0.1`.
- **Sin sesión no hay usuario.** El registro de una cuenta nueva y los cambios hechos por consola quedan con `usuario_id = NULL`.
- **La auditoría crece sin límite.** No hay rotación ni archivado; para este alcance no se necesita. Los índices por `(tabla, registro_id)`, `usuario_id`, `creado_en` y `(accion, tabla)` mantienen las consultas rápidas.
- **Cada cambio cuesta una escritura extra.** Para el volumen de una plataforma de torneos estudiantil es despreciable (cargar el seed completo, 2.203 filas con los triggers activos, tarda un segundo).
- **Los triggers se crean como `root`** (en Docker, automáticamente). En un servidor propio hay que ejecutar `database/03-auditoria.sql` con un usuario que tenga el privilegio `TRIGGER` (`root` o `trinity_migrator`).
