-- ═══════════════════════════════════════════════════════════════════
--  TRINITY — Triggers de auditoría
--  ARCHIVO GENERADO por tools/generar-auditoria.php — no editar a mano:
--  se vuelve a generar si cambia el esquema (php tools/generar-auditoria.php).
--
--  Qué hace: registra en la tabla `auditoria` cada INSERT / UPDATE /
--  DELETE sobre las tablas críticas, con la fila antes y después en
--  JSON, el usuario de la app (@app_user_id) y su IP (@app_ip).
--
--  Tablas auditadas: usuarios, torneos, torneo_participantes, partidos,
--  equipos, equipo_miembros, noticias, solicitudes_organizador.
--
--  Dos detalles importantes (verificados contra MySQL 8.0):
--   1) Los UPDATE sin cambios reales no generan fila.
--   2) MySQL NO dispara los triggers de una tabla hija cuando la fila
--      se borra o se pone en NULL por una FK (ON DELETE CASCADE / SET
--      NULL). Por eso los triggers BEFORE DELETE de `usuarios`,
--      `torneos` y `equipos` registran a mano esas filas, marcadas con
--      "_cascada": "<tabla>:<id>" dentro de datos_antes/datos_despues.
--
--  Requiere que exista la tabla `auditoria` (está en database/01-schema.sql) y
--  privilegio TRIGGER: en Docker lo ejecuta root; en un servidor, root
--  o trinity_migrator (tiene TRIGGER en database/extras/dcl.example.sql).
--      mysql -u root -p trinity < database/03-auditoria.sql
-- ═══════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
USE trinity;

-- ───────────────────────── usuarios ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_usuarios_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_usuarios_ai AFTER INSERT ON usuarios FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'usuarios', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'nombre', NEW.nombre,
            'fecha_nacimiento', NEW.fecha_nacimiento,
            'usuario', NEW.usuario,
            'tipo', NEW.tipo,
            'deportes_seleccionados', NEW.deportes_seleccionados,
            'videojuegos_seleccionados', NEW.videojuegos_seleccionados,
            'futbol_rol', NEW.futbol_rol,
            'futbol_numero', NEW.futbol_numero,
            'futbol_equipo', NEW.futbol_equipo,
            'mc_estilo', NEW.mc_estilo,
            'mc_estrategia', NEW.mc_estrategia,
            'mc_especialidad', NEW.mc_especialidad,
            'mc_modos', NEW.mc_modos,
            'email', NEW.email,
            'telefono', NEW.telefono,
            'pronouns', NEW.pronouns,
            'descripcion', NEW.descripcion,
            'foto_url', IF(NEW.foto_url IS NULL, NULL, IF(CHAR_LENGTH(NEW.foto_url) > 64, CONCAT('md5:', MD5(NEW.foto_url)), NEW.foto_url)),
            'torneos_jugados', NEW.torneos_jugados,
            'torneos_ganados', NEW.torneos_ganados,
            'notif_whatsapp', NEW.notif_whatsapp,
            'rol', NEW.rol,
            'creado_en', NEW.creado_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_usuarios_au;
DELIMITER $$
CREATE TRIGGER trg_aud_usuarios_au AFTER UPDATE ON usuarios FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'usuarios', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'nombre', OLD.nombre,
            'fecha_nacimiento', OLD.fecha_nacimiento,
            'usuario', OLD.usuario,
            'tipo', OLD.tipo,
            'deportes_seleccionados', OLD.deportes_seleccionados,
            'videojuegos_seleccionados', OLD.videojuegos_seleccionados,
            'futbol_rol', OLD.futbol_rol,
            'futbol_numero', OLD.futbol_numero,
            'futbol_equipo', OLD.futbol_equipo,
            'mc_estilo', OLD.mc_estilo,
            'mc_estrategia', OLD.mc_estrategia,
            'mc_especialidad', OLD.mc_especialidad,
            'mc_modos', OLD.mc_modos,
            'email', OLD.email,
            'telefono', OLD.telefono,
            'pronouns', OLD.pronouns,
            'descripcion', OLD.descripcion,
            'foto_url', IF(OLD.foto_url IS NULL, NULL, IF(CHAR_LENGTH(OLD.foto_url) > 64, CONCAT('md5:', MD5(OLD.foto_url)), OLD.foto_url)),
            'torneos_jugados', OLD.torneos_jugados,
            'torneos_ganados', OLD.torneos_ganados,
            'notif_whatsapp', OLD.notif_whatsapp,
            'rol', OLD.rol,
            'creado_en', OLD.creado_en,
            'password_cambiada', CAST('false' AS JSON)
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'nombre', NEW.nombre,
            'fecha_nacimiento', NEW.fecha_nacimiento,
            'usuario', NEW.usuario,
            'tipo', NEW.tipo,
            'deportes_seleccionados', NEW.deportes_seleccionados,
            'videojuegos_seleccionados', NEW.videojuegos_seleccionados,
            'futbol_rol', NEW.futbol_rol,
            'futbol_numero', NEW.futbol_numero,
            'futbol_equipo', NEW.futbol_equipo,
            'mc_estilo', NEW.mc_estilo,
            'mc_estrategia', NEW.mc_estrategia,
            'mc_especialidad', NEW.mc_especialidad,
            'mc_modos', NEW.mc_modos,
            'email', NEW.email,
            'telefono', NEW.telefono,
            'pronouns', NEW.pronouns,
            'descripcion', NEW.descripcion,
            'foto_url', IF(NEW.foto_url IS NULL, NULL, IF(CHAR_LENGTH(NEW.foto_url) > 64, CONCAT('md5:', MD5(NEW.foto_url)), NEW.foto_url)),
            'torneos_jugados', NEW.torneos_jugados,
            'torneos_ganados', NEW.torneos_ganados,
            'notif_whatsapp', NEW.notif_whatsapp,
            'rol', NEW.rol,
            'creado_en', NEW.creado_en,
            'password_cambiada', IF(OLD.password <> NEW.password, CAST('true' AS JSON), CAST('false' AS JSON))
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_usuarios_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_usuarios_bd BEFORE DELETE ON usuarios FOR EACH ROW
BEGIN
    -- Todo lo que MySQL borra (CASCADE) o desvincula (SET NULL) por culpa de este
    -- usuario, y que NO dispara triggers propios. Se registra acá, antes de que ocurra.

    -- 1) Torneos que organiza + sus inscripciones y partidos
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'torneos', t.id, 'DELETE', JSON_OBJECT(
            'id', t.id,
            'organizador_id', t.organizador_id,
            'titulo', t.titulo,
            'deporte', t.deporte,
            'descripcion', t.descripcion,
            'formato', t.formato,
            'max_participantes', t.max_participantes,
            'fecha_inicio', t.fecha_inicio,
            'visibilidad', t.visibilidad,
            'banner_url', IF(t.banner_url IS NULL, NULL, IF(CHAR_LENGTH(t.banner_url) > 64, CONCAT('md5:', MD5(t.banner_url)), t.banner_url)),
            'estado', t.estado,
            'creado_en', t.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    
    FROM torneos t WHERE t.organizador_id = OLD.id;
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'torneo_participantes', tp.id, 'DELETE', JSON_OBJECT(
            'id', tp.id,
            'torneo_id', tp.torneo_id,
            'usuario_id', tp.usuario_id,
            'inscrito_en', tp.inscrito_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM torneo_participantes tp WHERE tp.torneo_id IN (SELECT id FROM torneos WHERE organizador_id = OLD.id);
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'partidos', pa.id, 'DELETE', JSON_OBJECT(
            'id', pa.id,
            'torneo_id', pa.torneo_id,
            'ronda', pa.ronda,
            'ronda_etiqueta', pa.ronda_etiqueta,
            'orden', pa.orden,
            'participante1_id', pa.participante1_id,
            'participante2_id', pa.participante2_id,
            'resultado1', pa.resultado1,
            'resultado2', pa.resultado2,
            'ganador_id', pa.ganador_id,
            'estado', pa.estado,
            'fecha_programada', pa.fecha_programada,
            'creado_en', pa.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM partidos pa WHERE pa.torneo_id IN (SELECT id FROM torneos WHERE organizador_id = OLD.id);

    -- 2) Sus inscripciones en torneos de otros
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'torneo_participantes', tp2.id, 'DELETE', JSON_OBJECT(
            'id', tp2.id,
            'torneo_id', tp2.torneo_id,
            'usuario_id', tp2.usuario_id,
            'inscrito_en', tp2.inscrito_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM torneo_participantes tp2 WHERE tp2.usuario_id = OLD.id AND tp2.torneo_id NOT IN (SELECT id FROM torneos WHERE organizador_id = OLD.id);

    -- 3) Partidos de torneos ajenos: MySQL deja la fila y pone NULL (SET NULL) => UPDATE
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'partidos', pu.id, 'UPDATE', JSON_OBJECT(
            'id', pu.id,
            'torneo_id', pu.torneo_id,
            'ronda', pu.ronda,
            'ronda_etiqueta', pu.ronda_etiqueta,
            'orden', pu.orden,
            'participante1_id', pu.participante1_id,
            'participante2_id', pu.participante2_id,
            'resultado1', pu.resultado1,
            'resultado2', pu.resultado2,
            'ganador_id', pu.ganador_id,
            'estado', pu.estado,
            'fecha_programada', pu.fecha_programada,
            'creado_en', pu.creado_en
        ),
           JSON_OBJECT(
            'id', pu.id,
            'torneo_id', pu.torneo_id,
            'ronda', pu.ronda,
            'ronda_etiqueta', pu.ronda_etiqueta,
            'orden', pu.orden,
            'participante1_id', IF(pu.participante1_id = OLD.id, NULL, pu.participante1_id),
            'participante2_id', IF(pu.participante2_id = OLD.id, NULL, pu.participante2_id),
            'resultado1', pu.resultado1,
            'resultado2', pu.resultado2,
            'ganador_id', IF(pu.ganador_id = OLD.id, NULL, pu.ganador_id),
            'estado', pu.estado,
            'fecha_programada', pu.fecha_programada,
            'creado_en', pu.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), @app_ip
    FROM partidos pu
    WHERE (pu.participante1_id = OLD.id OR pu.participante2_id = OLD.id OR pu.ganador_id = OLD.id)
      AND pu.torneo_id NOT IN (SELECT id FROM torneos WHERE organizador_id = OLD.id);

    -- 4) Equipos que capitanea + sus miembros
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'equipos', e.id, 'DELETE', JSON_OBJECT(
            'id', e.id,
            'nombre', e.nombre,
            'disciplina', e.disciplina,
            'descripcion', e.descripcion,
            'logo_url', e.logo_url,
            'capitan_id', e.capitan_id,
            'creado_en', e.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM equipos e WHERE e.capitan_id = OLD.id;
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'equipo_miembros', em.id, 'DELETE', JSON_OBJECT(
            'id', em.id,
            'equipo_id', em.equipo_id,
            'usuario_id', em.usuario_id,
            'rol', em.rol,
            'estado', em.estado,
            'creado_en', em.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM equipo_miembros em WHERE em.equipo_id IN (SELECT id FROM equipos WHERE capitan_id = OLD.id);

    -- 5) Su pertenencia a equipos de otros
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'equipo_miembros', em2.id, 'DELETE', JSON_OBJECT(
            'id', em2.id,
            'equipo_id', em2.equipo_id,
            'usuario_id', em2.usuario_id,
            'rol', em2.rol,
            'estado', em2.estado,
            'creado_en', em2.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM equipo_miembros em2 WHERE em2.usuario_id = OLD.id AND em2.equipo_id NOT IN (SELECT id FROM equipos WHERE capitan_id = OLD.id);

    -- 6) Noticias que escribió
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'noticias', n.id, 'DELETE', JSON_OBJECT(
            'id', n.id,
            'autor_id', n.autor_id,
            'titulo', n.titulo,
            'resumen', n.resumen,
            'contenido', n.contenido,
            'categoria', n.categoria,
            'imagen_url', n.imagen_url,
            'estado', n.estado,
            'resuelto_por', n.resuelto_por,
            'resuelto_en', n.resuelto_en,
            'publicado_en', n.publicado_en,
            'creado_en', n.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM noticias n WHERE n.autor_id = OLD.id;

    -- 7) Noticias que moderó como admin (resuelto_por pasa a NULL) => UPDATE
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'noticias', nu.id, 'UPDATE', JSON_OBJECT(
            'id', nu.id,
            'autor_id', nu.autor_id,
            'titulo', nu.titulo,
            'resumen', nu.resumen,
            'contenido', nu.contenido,
            'categoria', nu.categoria,
            'imagen_url', nu.imagen_url,
            'estado', nu.estado,
            'resuelto_por', nu.resuelto_por,
            'resuelto_en', nu.resuelto_en,
            'publicado_en', nu.publicado_en,
            'creado_en', nu.creado_en
        ),
           JSON_OBJECT(
            'id', nu.id,
            'autor_id', nu.autor_id,
            'titulo', nu.titulo,
            'resumen', nu.resumen,
            'contenido', nu.contenido,
            'categoria', nu.categoria,
            'imagen_url', nu.imagen_url,
            'estado', nu.estado,
            'resuelto_por', NULL,
            'resuelto_en', nu.resuelto_en,
            'publicado_en', nu.publicado_en,
            'creado_en', nu.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), @app_ip
    FROM noticias nu WHERE nu.resuelto_por = OLD.id AND nu.autor_id <> OLD.id;

    -- 8) Solicitudes de organizador que hizo
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'solicitudes_organizador', s.id, 'DELETE', JSON_OBJECT(
            'id', s.id,
            'usuario_id', s.usuario_id,
            'estado', s.estado,
            'terminos_aceptados_en', s.terminos_aceptados_en,
            'resuelto_por', s.resuelto_por,
            'resuelto_en', s.resuelto_en,
            'creado_en', s.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), NULL, @app_ip
    FROM solicitudes_organizador s WHERE s.usuario_id = OLD.id;

    -- 9) Solicitudes que resolvió como admin (resuelto_por pasa a NULL) => UPDATE
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'solicitudes_organizador', su.id, 'UPDATE', JSON_OBJECT(
            'id', su.id,
            'usuario_id', su.usuario_id,
            'estado', su.estado,
            'terminos_aceptados_en', su.terminos_aceptados_en,
            'resuelto_por', su.resuelto_por,
            'resuelto_en', su.resuelto_en,
            'creado_en', su.creado_en
        ),
           JSON_OBJECT(
            'id', su.id,
            'usuario_id', su.usuario_id,
            'estado', su.estado,
            'terminos_aceptados_en', su.terminos_aceptados_en,
            'resuelto_por', NULL,
            'resuelto_en', su.resuelto_en,
            'creado_en', su.creado_en,
            '_cascada', CONCAT('usuarios:', OLD.id)
        ), @app_ip
    FROM solicitudes_organizador su WHERE su.resuelto_por = OLD.id AND su.usuario_id <> OLD.id;

    -- 10) Y por último, la fila del usuario en sí
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'usuarios', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'nombre', OLD.nombre,
            'fecha_nacimiento', OLD.fecha_nacimiento,
            'usuario', OLD.usuario,
            'tipo', OLD.tipo,
            'deportes_seleccionados', OLD.deportes_seleccionados,
            'videojuegos_seleccionados', OLD.videojuegos_seleccionados,
            'futbol_rol', OLD.futbol_rol,
            'futbol_numero', OLD.futbol_numero,
            'futbol_equipo', OLD.futbol_equipo,
            'mc_estilo', OLD.mc_estilo,
            'mc_estrategia', OLD.mc_estrategia,
            'mc_especialidad', OLD.mc_especialidad,
            'mc_modos', OLD.mc_modos,
            'email', OLD.email,
            'telefono', OLD.telefono,
            'pronouns', OLD.pronouns,
            'descripcion', OLD.descripcion,
            'foto_url', IF(OLD.foto_url IS NULL, NULL, IF(CHAR_LENGTH(OLD.foto_url) > 64, CONCAT('md5:', MD5(OLD.foto_url)), OLD.foto_url)),
            'torneos_jugados', OLD.torneos_jugados,
            'torneos_ganados', OLD.torneos_ganados,
            'notif_whatsapp', OLD.notif_whatsapp,
            'rol', OLD.rol,
            'creado_en', OLD.creado_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

-- ───────────────────────── torneos ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_torneos_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_torneos_ai AFTER INSERT ON torneos FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'torneos', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'organizador_id', NEW.organizador_id,
            'titulo', NEW.titulo,
            'deporte', NEW.deporte,
            'descripcion', NEW.descripcion,
            'formato', NEW.formato,
            'max_participantes', NEW.max_participantes,
            'fecha_inicio', NEW.fecha_inicio,
            'visibilidad', NEW.visibilidad,
            'banner_url', IF(NEW.banner_url IS NULL, NULL, IF(CHAR_LENGTH(NEW.banner_url) > 64, CONCAT('md5:', MD5(NEW.banner_url)), NEW.banner_url)),
            'estado', NEW.estado,
            'creado_en', NEW.creado_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_torneos_au;
DELIMITER $$
CREATE TRIGGER trg_aud_torneos_au AFTER UPDATE ON torneos FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'torneos', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'organizador_id', OLD.organizador_id,
            'titulo', OLD.titulo,
            'deporte', OLD.deporte,
            'descripcion', OLD.descripcion,
            'formato', OLD.formato,
            'max_participantes', OLD.max_participantes,
            'fecha_inicio', OLD.fecha_inicio,
            'visibilidad', OLD.visibilidad,
            'banner_url', IF(OLD.banner_url IS NULL, NULL, IF(CHAR_LENGTH(OLD.banner_url) > 64, CONCAT('md5:', MD5(OLD.banner_url)), OLD.banner_url)),
            'estado', OLD.estado,
            'creado_en', OLD.creado_en
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'organizador_id', NEW.organizador_id,
            'titulo', NEW.titulo,
            'deporte', NEW.deporte,
            'descripcion', NEW.descripcion,
            'formato', NEW.formato,
            'max_participantes', NEW.max_participantes,
            'fecha_inicio', NEW.fecha_inicio,
            'visibilidad', NEW.visibilidad,
            'banner_url', IF(NEW.banner_url IS NULL, NULL, IF(CHAR_LENGTH(NEW.banner_url) > 64, CONCAT('md5:', MD5(NEW.banner_url)), NEW.banner_url)),
            'estado', NEW.estado,
            'creado_en', NEW.creado_en
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_torneos_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_torneos_bd BEFORE DELETE ON torneos FOR EACH ROW
BEGIN
    -- Hijas que MySQL borra en cascada SIN disparar sus propios triggers:
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'torneo_participantes', tp.id, 'DELETE', JSON_OBJECT(
            'id', tp.id,
            'torneo_id', tp.torneo_id,
            'usuario_id', tp.usuario_id,
            'inscrito_en', tp.inscrito_en,
            '_cascada', CONCAT('torneos:', OLD.id)
        ), NULL, @app_ip
    FROM torneo_participantes tp WHERE tp.torneo_id = OLD.id;
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'partidos', pa.id, 'DELETE', JSON_OBJECT(
            'id', pa.id,
            'torneo_id', pa.torneo_id,
            'ronda', pa.ronda,
            'ronda_etiqueta', pa.ronda_etiqueta,
            'orden', pa.orden,
            'participante1_id', pa.participante1_id,
            'participante2_id', pa.participante2_id,
            'resultado1', pa.resultado1,
            'resultado2', pa.resultado2,
            'ganador_id', pa.ganador_id,
            'estado', pa.estado,
            'fecha_programada', pa.fecha_programada,
            'creado_en', pa.creado_en,
            '_cascada', CONCAT('torneos:', OLD.id)
        ), NULL, @app_ip
    FROM partidos pa WHERE pa.torneo_id = OLD.id;
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'torneos', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'organizador_id', OLD.organizador_id,
            'titulo', OLD.titulo,
            'deporte', OLD.deporte,
            'descripcion', OLD.descripcion,
            'formato', OLD.formato,
            'max_participantes', OLD.max_participantes,
            'fecha_inicio', OLD.fecha_inicio,
            'visibilidad', OLD.visibilidad,
            'banner_url', IF(OLD.banner_url IS NULL, NULL, IF(CHAR_LENGTH(OLD.banner_url) > 64, CONCAT('md5:', MD5(OLD.banner_url)), OLD.banner_url)),
            'estado', OLD.estado,
            'creado_en', OLD.creado_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

-- ───────────────────────── torneo_participantes ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_torneo_participantes_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_torneo_participantes_ai AFTER INSERT ON torneo_participantes FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'torneo_participantes', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'torneo_id', NEW.torneo_id,
            'usuario_id', NEW.usuario_id,
            'inscrito_en', NEW.inscrito_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_torneo_participantes_au;
DELIMITER $$
CREATE TRIGGER trg_aud_torneo_participantes_au AFTER UPDATE ON torneo_participantes FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'torneo_participantes', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'torneo_id', OLD.torneo_id,
            'usuario_id', OLD.usuario_id,
            'inscrito_en', OLD.inscrito_en
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'torneo_id', NEW.torneo_id,
            'usuario_id', NEW.usuario_id,
            'inscrito_en', NEW.inscrito_en
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_torneo_participantes_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_torneo_participantes_bd BEFORE DELETE ON torneo_participantes FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'torneo_participantes', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'torneo_id', OLD.torneo_id,
            'usuario_id', OLD.usuario_id,
            'inscrito_en', OLD.inscrito_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

-- ───────────────────────── partidos ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_partidos_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_partidos_ai AFTER INSERT ON partidos FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'partidos', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'torneo_id', NEW.torneo_id,
            'ronda', NEW.ronda,
            'ronda_etiqueta', NEW.ronda_etiqueta,
            'orden', NEW.orden,
            'participante1_id', NEW.participante1_id,
            'participante2_id', NEW.participante2_id,
            'resultado1', NEW.resultado1,
            'resultado2', NEW.resultado2,
            'ganador_id', NEW.ganador_id,
            'estado', NEW.estado,
            'fecha_programada', NEW.fecha_programada,
            'creado_en', NEW.creado_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_partidos_au;
DELIMITER $$
CREATE TRIGGER trg_aud_partidos_au AFTER UPDATE ON partidos FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'partidos', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'torneo_id', OLD.torneo_id,
            'ronda', OLD.ronda,
            'ronda_etiqueta', OLD.ronda_etiqueta,
            'orden', OLD.orden,
            'participante1_id', OLD.participante1_id,
            'participante2_id', OLD.participante2_id,
            'resultado1', OLD.resultado1,
            'resultado2', OLD.resultado2,
            'ganador_id', OLD.ganador_id,
            'estado', OLD.estado,
            'fecha_programada', OLD.fecha_programada,
            'creado_en', OLD.creado_en
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'torneo_id', NEW.torneo_id,
            'ronda', NEW.ronda,
            'ronda_etiqueta', NEW.ronda_etiqueta,
            'orden', NEW.orden,
            'participante1_id', NEW.participante1_id,
            'participante2_id', NEW.participante2_id,
            'resultado1', NEW.resultado1,
            'resultado2', NEW.resultado2,
            'ganador_id', NEW.ganador_id,
            'estado', NEW.estado,
            'fecha_programada', NEW.fecha_programada,
            'creado_en', NEW.creado_en
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_partidos_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_partidos_bd BEFORE DELETE ON partidos FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'partidos', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'torneo_id', OLD.torneo_id,
            'ronda', OLD.ronda,
            'ronda_etiqueta', OLD.ronda_etiqueta,
            'orden', OLD.orden,
            'participante1_id', OLD.participante1_id,
            'participante2_id', OLD.participante2_id,
            'resultado1', OLD.resultado1,
            'resultado2', OLD.resultado2,
            'ganador_id', OLD.ganador_id,
            'estado', OLD.estado,
            'fecha_programada', OLD.fecha_programada,
            'creado_en', OLD.creado_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

-- ───────────────────────── equipos ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_equipos_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_equipos_ai AFTER INSERT ON equipos FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'equipos', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'nombre', NEW.nombre,
            'disciplina', NEW.disciplina,
            'descripcion', NEW.descripcion,
            'logo_url', NEW.logo_url,
            'capitan_id', NEW.capitan_id,
            'creado_en', NEW.creado_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_equipos_au;
DELIMITER $$
CREATE TRIGGER trg_aud_equipos_au AFTER UPDATE ON equipos FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'equipos', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'nombre', OLD.nombre,
            'disciplina', OLD.disciplina,
            'descripcion', OLD.descripcion,
            'logo_url', OLD.logo_url,
            'capitan_id', OLD.capitan_id,
            'creado_en', OLD.creado_en
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'nombre', NEW.nombre,
            'disciplina', NEW.disciplina,
            'descripcion', NEW.descripcion,
            'logo_url', NEW.logo_url,
            'capitan_id', NEW.capitan_id,
            'creado_en', NEW.creado_en
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_equipos_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_equipos_bd BEFORE DELETE ON equipos FOR EACH ROW
BEGIN
    -- Hijas que MySQL borra en cascada SIN disparar sus propios triggers:
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'equipo_miembros', em.id, 'DELETE', JSON_OBJECT(
            'id', em.id,
            'equipo_id', em.equipo_id,
            'usuario_id', em.usuario_id,
            'rol', em.rol,
            'estado', em.estado,
            'creado_en', em.creado_en,
            '_cascada', CONCAT('equipos:', OLD.id)
        ), NULL, @app_ip
    FROM equipo_miembros em WHERE em.equipo_id = OLD.id;
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'equipos', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'nombre', OLD.nombre,
            'disciplina', OLD.disciplina,
            'descripcion', OLD.descripcion,
            'logo_url', OLD.logo_url,
            'capitan_id', OLD.capitan_id,
            'creado_en', OLD.creado_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

-- ───────────────────────── equipo_miembros ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_equipo_miembros_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_equipo_miembros_ai AFTER INSERT ON equipo_miembros FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'equipo_miembros', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'equipo_id', NEW.equipo_id,
            'usuario_id', NEW.usuario_id,
            'rol', NEW.rol,
            'estado', NEW.estado,
            'creado_en', NEW.creado_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_equipo_miembros_au;
DELIMITER $$
CREATE TRIGGER trg_aud_equipo_miembros_au AFTER UPDATE ON equipo_miembros FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'equipo_miembros', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'equipo_id', OLD.equipo_id,
            'usuario_id', OLD.usuario_id,
            'rol', OLD.rol,
            'estado', OLD.estado,
            'creado_en', OLD.creado_en
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'equipo_id', NEW.equipo_id,
            'usuario_id', NEW.usuario_id,
            'rol', NEW.rol,
            'estado', NEW.estado,
            'creado_en', NEW.creado_en
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_equipo_miembros_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_equipo_miembros_bd BEFORE DELETE ON equipo_miembros FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'equipo_miembros', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'equipo_id', OLD.equipo_id,
            'usuario_id', OLD.usuario_id,
            'rol', OLD.rol,
            'estado', OLD.estado,
            'creado_en', OLD.creado_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

-- ───────────────────────── noticias ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_noticias_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_noticias_ai AFTER INSERT ON noticias FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'noticias', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'autor_id', NEW.autor_id,
            'titulo', NEW.titulo,
            'resumen', NEW.resumen,
            'contenido', NEW.contenido,
            'categoria', NEW.categoria,
            'imagen_url', NEW.imagen_url,
            'estado', NEW.estado,
            'resuelto_por', NEW.resuelto_por,
            'resuelto_en', NEW.resuelto_en,
            'publicado_en', NEW.publicado_en,
            'creado_en', NEW.creado_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_noticias_au;
DELIMITER $$
CREATE TRIGGER trg_aud_noticias_au AFTER UPDATE ON noticias FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'noticias', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'autor_id', OLD.autor_id,
            'titulo', OLD.titulo,
            'resumen', OLD.resumen,
            'contenido', OLD.contenido,
            'categoria', OLD.categoria,
            'imagen_url', OLD.imagen_url,
            'estado', OLD.estado,
            'resuelto_por', OLD.resuelto_por,
            'resuelto_en', OLD.resuelto_en,
            'publicado_en', OLD.publicado_en,
            'creado_en', OLD.creado_en
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'autor_id', NEW.autor_id,
            'titulo', NEW.titulo,
            'resumen', NEW.resumen,
            'contenido', NEW.contenido,
            'categoria', NEW.categoria,
            'imagen_url', NEW.imagen_url,
            'estado', NEW.estado,
            'resuelto_por', NEW.resuelto_por,
            'resuelto_en', NEW.resuelto_en,
            'publicado_en', NEW.publicado_en,
            'creado_en', NEW.creado_en
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_noticias_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_noticias_bd BEFORE DELETE ON noticias FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'noticias', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'autor_id', OLD.autor_id,
            'titulo', OLD.titulo,
            'resumen', OLD.resumen,
            'contenido', OLD.contenido,
            'categoria', OLD.categoria,
            'imagen_url', OLD.imagen_url,
            'estado', OLD.estado,
            'resuelto_por', OLD.resuelto_por,
            'resuelto_en', OLD.resuelto_en,
            'publicado_en', OLD.publicado_en,
            'creado_en', OLD.creado_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

-- ───────────────────────── solicitudes_organizador ─────────────────────────
DROP TRIGGER IF EXISTS trg_aud_solicitudes_organizador_ai;
DELIMITER $$
CREATE TRIGGER trg_aud_solicitudes_organizador_ai AFTER INSERT ON solicitudes_organizador FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'solicitudes_organizador', NEW.id, 'INSERT', NULL, JSON_OBJECT(
            'id', NEW.id,
            'usuario_id', NEW.usuario_id,
            'estado', NEW.estado,
            'terminos_aceptados_en', NEW.terminos_aceptados_en,
            'resuelto_por', NEW.resuelto_por,
            'resuelto_en', NEW.resuelto_en,
            'creado_en', NEW.creado_en
        ), @app_ip);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_solicitudes_organizador_au;
DELIMITER $$
CREATE TRIGGER trg_aud_solicitudes_organizador_au AFTER UPDATE ON solicitudes_organizador FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    SELECT @app_user_id, 'solicitudes_organizador', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        JSON_OBJECT(
            'id', OLD.id,
            'usuario_id', OLD.usuario_id,
            'estado', OLD.estado,
            'terminos_aceptados_en', OLD.terminos_aceptados_en,
            'resuelto_por', OLD.resuelto_por,
            'resuelto_en', OLD.resuelto_en,
            'creado_en', OLD.creado_en
        ) AS antes,
        JSON_OBJECT(
            'id', NEW.id,
            'usuario_id', NEW.usuario_id,
            'estado', NEW.estado,
            'terminos_aceptados_en', NEW.terminos_aceptados_en,
            'resuelto_por', NEW.resuelto_por,
            'resuelto_en', NEW.resuelto_en,
            'creado_en', NEW.creado_en
        ) AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_aud_solicitudes_organizador_bd;
DELIMITER $$
CREATE TRIGGER trg_aud_solicitudes_organizador_bd BEFORE DELETE ON solicitudes_organizador FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip)
    VALUES (@app_user_id, 'solicitudes_organizador', OLD.id, 'DELETE', JSON_OBJECT(
            'id', OLD.id,
            'usuario_id', OLD.usuario_id,
            'estado', OLD.estado,
            'terminos_aceptados_en', OLD.terminos_aceptados_en,
            'resuelto_por', OLD.resuelto_por,
            'resuelto_en', OLD.resuelto_en,
            'creado_en', OLD.creado_en
        ), NULL, @app_ip);
END$$
DELIMITER ;

