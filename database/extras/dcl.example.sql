SET NAMES utf8mb4;

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

GRANT SELECT, INSERT ON trinity.auditoria TO 'trinity_app'@'localhost';

CREATE USER IF NOT EXISTS 'trinity_readonly'@'localhost' IDENTIFIED BY 'CAMBIAR_READONLY_PASSWORD';
GRANT SELECT ON trinity.* TO 'trinity_readonly'@'localhost';

CREATE USER IF NOT EXISTS 'trinity_backup'@'localhost' IDENTIFIED BY 'CAMBIAR_BACKUP_PASSWORD';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON trinity.* TO 'trinity_backup'@'localhost';

CREATE USER IF NOT EXISTS 'trinity_migrator'@'localhost' IDENTIFIED BY 'CAMBIAR_MIGRATOR_PASSWORD';
GRANT CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE VIEW, TRIGGER, SELECT, INSERT, UPDATE, DELETE
    ON trinity.* TO 'trinity_migrator'@'localhost';

FLUSH PRIVILEGES;