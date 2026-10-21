-- ═══════════════════════════════════════════════════════════════════
--  TRINITY — DCL (Data Control Language) — PLANTILLA
--  Usuarios de MySQL con privilegios mínimos, separados por rol.
--
--  Este archivo SÍ se entrega (son solo placeholders, sin secretos).
--
--  ┌─ Docker ─────────────────────────────────────────────────────────┐
--  │ No hace falta tocarlo: database/02-dcl.sh lo lee, reemplaza los  │
--  │ CAMBIAR_* por las contraseñas del .env y 'localhost' por '%'     │
--  │ (los contenedores se conectan por red, no por socket local).     │
--  └──────────────────────────────────────────────────────────────────┘
--  ┌─ Servidor real ──────────────────────────────────────────────────┐
--  │ Copiar a `database/extras/dcl.sql` (ignorado por git), reemplazar│
--  │ los CAMBIAR_* por contraseñas fuertes y ejecutarlo como root:    │
--  │     mysql -u root -p < database/01-schema.sql                    │
--  │     mysql -u root -p < database/extras/dcl.sql                   │
--  │     mysql -u root -p < database/03-auditoria.sql                 │
--  │ Cargar la contraseña de trinity_app en el .env (DB_USER/DB_PASS).│
--  │ NO lo guardes como database/dcl.sql: Docker ejecuta todo .sql que│
--  │ esté suelto en database/ (por eso los extras van en extras/).    │
--  └──────────────────────────────────────────────────────────────────┘
--
--  Por qué separar usuarios en vez de usar `root` para todo:
--  · La aplicación PHP (app/) NUNCA debería poder ejecutar DROP TABLE,
--    ALTER TABLE ni crear/borrar otros usuarios — solo necesita leer
--    y escribir filas. Si un endpoint tuviera una falla de inyección
--    SQL (mitigada acá con PDO + prepared statements en todos los
--    Models, pero como defensa en profundidad), el daño posible queda
--    acotado a INSERT/UPDATE/DELETE/SELECT sobre la base `trinity`,
--    nunca a comandos DDL ni a otras bases del servidor.
--  · Un usuario de solo lectura permite dar acceso de reportes/lectura
--    sin riesgo de modificar datos.
--  · Un usuario de backup solo necesita LOCK TABLES + SELECT, no
--    privilegios de escritura.
--  · Un usuario de migraciones (DDL) se usa solo manualmente al
--    desplegar/actualizar el esquema, nunca desde el código PHP.
--
--  POR QUÉ LOS GRANTS DE trinity_app SON POR TABLA (y no ON trinity.*):
--  la auditoría tiene que ser inmutable para la app (solo SELECT e
--  INSERT sobre `auditoria`). MySQL no permite "restarle" UPDATE/DELETE
--  a UNA tabla de un grant dado a nivel base de datos (sin activar
--  partial_revokes), así que se otorga tabla por tabla. Consecuencia:
--  si se agrega una tabla nueva al esquema, hay que agregar su GRANT
--  acá (si no, la app va a fallar con "command denied": es lo que
--  queremos, mejor eso que un acceso que nadie decidió dar).
-- ═══════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── 1) USUARIO DE APLICACIÓN (el que usa app/config.php vía PDO) ───
-- Solo DML sobre las tablas de negocio. Sin CREATE/ALTER/DROP/GRANT.
-- Se recrea siempre: si ya existía con el esquema viejo (GRANT ... ON
-- trinity.*) no puede sobrevivir un UPDATE/DELETE sobre `auditoria`.
-- (Un REVOKE no sirve acá: falla con error 1141 si el usuario es nuevo.)
DROP USER IF EXISTS 'trinity_app'@'localhost';
CREATE USER 'trinity_app'@'localhost' IDENTIFIED BY 'CAMBIAR_APP_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.usuarios                TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.password_resets         TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.seguidores              TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.codigos_verificacion    TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.cuentas_videojuego      TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.notificaciones          TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.torneos                 TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.torneo_participantes    TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.torneo_invitaciones     TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.solicitudes_organizador TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.partidos                TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.noticias                TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.mensajes_contacto       TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.equipos                 TO 'trinity_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON trinity.equipo_miembros         TO 'trinity_app'@'localhost';

-- AUDITORÍA: la app puede escribir y consultar el historial, pero nunca
-- modificarlo ni borrarlo (no tiene UPDATE ni DELETE ni DROP/TRUNCATE).
-- Nota: los triggers corren con los privilegios de quien los creó (root),
-- así que el registro automático no depende de este INSERT; se deja
-- porque la letra pide que la app pueda insertar.
GRANT SELECT, INSERT ON trinity.auditoria TO 'trinity_app'@'localhost';

-- NOTA sobre contraseñas: los usuarios 2, 3 y 4 se crean con IF NOT EXISTS, así que
-- volver a ejecutar este archivo NO les cambia la contraseña si ya existen (a
-- trinity_app sí, porque se recrea). Para rotar una contraseña:
--     ALTER USER 'trinity_readonly'@'localhost' IDENTIFIED BY 'nueva_clave';

-- ── 2) USUARIO DE SOLO LECTURA (dashboards / reportes) ──────────────
-- Lee todo, incluida la auditoría. No escribe nada.
CREATE USER IF NOT EXISTS 'trinity_readonly'@'localhost' IDENTIFIED BY 'CAMBIAR_READONLY_PASSWORD';
GRANT SELECT ON trinity.* TO 'trinity_readonly'@'localhost';

-- ── 3) USUARIO DE BACKUP (mysqldump) ────────────────────────────────
-- TRIGGER es necesario para que mysqldump --triggers pueda volcar los
-- triggers de auditoría. backup.sh usa --no-tablespaces, así que no
-- hace falta el privilegio global PROCESS.
CREATE USER IF NOT EXISTS 'trinity_backup'@'localhost' IDENTIFIED BY 'CAMBIAR_BACKUP_PASSWORD';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON trinity.* TO 'trinity_backup'@'localhost';

-- ── 4) USUARIO DE MIGRACIONES (DDL manual, nunca desde PHP) ─────────
-- Se usa a mano para correr database/01-schema.sql / database/03-auditoria.sql /
-- futuras migraciones. TRIGGER es necesario para (re)crear los triggers
-- de auditoría. No se referencia desde ningún .env de la aplicación.
-- Es un principal de confianza igual que root: con DROP podría borrar
-- la tabla de auditoría; por eso se usa solo a mano y se guarda aparte.
CREATE USER IF NOT EXISTS 'trinity_migrator'@'localhost' IDENTIFIED BY 'CAMBIAR_MIGRATOR_PASSWORD';
GRANT CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE VIEW, TRIGGER, SELECT, INSERT, UPDATE, DELETE
    ON trinity.* TO 'trinity_migrator'@'localhost';

-- ── APLICAR CAMBIOS ──────────────────────────────────────────────────
FLUSH PRIVILEGES;

-- ── VERIFICACIÓN (opcional, ejecutar a mano para auditar) ───────────
-- SHOW GRANTS FOR 'trinity_app'@'localhost';
-- SHOW GRANTS FOR 'trinity_readonly'@'localhost';
-- SHOW GRANTS FOR 'trinity_backup'@'localhost';
-- SHOW GRANTS FOR 'trinity_migrator'@'localhost';
--
-- Prueba de inmutabilidad (debe fallar con "command denied"):
--   mysql -u trinity_app -p trinity -e "UPDATE auditoria SET tabla='x' LIMIT 1"
--   mysql -u trinity_app -p trinity -e "DELETE FROM auditoria LIMIT 1"
