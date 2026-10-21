-- ═══════════════════════════════════════════════════════════════════
--  TRINITY — Verificación del seed
--  Uso:  mysql -u root -p trinity < database/extras/seed_verificar.sql
--  Cada fila dice PASS o FAIL. Si todo está bien, la última fila es
--  "RESULTADO: TODO OK".
-- (Usa una tabla auxiliar chk_verificacion que borra al terminar.)
-- ═══════════════════════════════════════════════════════════════════
USE trinity;

DROP TABLE IF EXISTS chk_verificacion;
CREATE TABLE chk_verificacion (orden INT AUTO_INCREMENT PRIMARY KEY, prueba VARCHAR(120), detalle VARCHAR(80), ok TINYINT);

-- 1) Al menos 50 filas en cada tabla de negocio
INSERT INTO chk_verificacion (prueba, detalle, ok) SELECT CONCAT('>= 50 filas en ', t), CONCAT(n, ' filas'), n >= 50 FROM (
    SELECT 'usuarios' t, COUNT(*) n FROM usuarios UNION ALL SELECT 'torneos', COUNT(*) FROM torneos
    UNION ALL SELECT 'torneo_participantes', COUNT(*) FROM torneo_participantes UNION ALL SELECT 'partidos', COUNT(*) FROM partidos
    UNION ALL SELECT 'equipos', COUNT(*) FROM equipos UNION ALL SELECT 'equipo_miembros', COUNT(*) FROM equipo_miembros
    UNION ALL SELECT 'noticias', COUNT(*) FROM noticias UNION ALL SELECT 'notificaciones', COUNT(*) FROM notificaciones
    UNION ALL SELECT 'seguidores', COUNT(*) FROM seguidores UNION ALL SELECT 'cuentas_videojuego', COUNT(*) FROM cuentas_videojuego
    UNION ALL SELECT 'torneo_invitaciones', COUNT(*) FROM torneo_invitaciones UNION ALL SELECT 'solicitudes_organizador', COUNT(*) FROM solicitudes_organizador
    UNION ALL SELECT 'mensajes_contacto', COUNT(*) FROM mensajes_contacto UNION ALL SELECT 'password_resets', COUNT(*) FROM password_resets
    UNION ALL SELECT 'codigos_verificacion', COUNT(*) FROM codigos_verificacion UNION ALL SELECT 'auditoria', COUNT(*) FROM auditoria
) x;

-- 2) Roles y cuentas de demostración
INSERT INTO chk_verificacion (prueba, detalle, ok) VALUES
 ('Existe al menos 1 usuario por rol', (SELECT GROUP_CONCAT(CONCAT(rol,'=',n)) FROM (SELECT rol, COUNT(*) n FROM usuarios GROUP BY rol) r),
   (SELECT COUNT(DISTINCT rol) = 3 FROM usuarios)),
 ('Cuentas demo (admin / organizador / participante)', '3 esperadas',
   (SELECT COUNT(*) = 3 FROM usuarios WHERE (email='admin@trinity.test' AND rol='admin') OR (email='organizador@trinity.test' AND rol='organizador') OR (email='participante@trinity.test' AND rol='participante'))),
 ('Hashes de contraseña son bcrypt ($2y$)', '', (SELECT COUNT(*) = 0 FROM usuarios WHERE password NOT LIKE '$2y$%')),
 ('Nadie tiene WhatsApp activo (no escribirle a números ficticios)', '', (SELECT COUNT(*) = 0 FROM usuarios WHERE notif_whatsapp <> 0));

-- 3) Torneos: todos los estados y formatos están representados
INSERT INTO chk_verificacion (prueba, detalle, ok) VALUES
 ('Hay torneos en los 5 estados', (SELECT GROUP_CONCAT(CONCAT(estado,'=',n)) FROM (SELECT estado, COUNT(*) n FROM torneos GROUP BY estado) e), (SELECT COUNT(DISTINCT estado) = 5 FROM torneos)),
 ('Hay torneos de los 3 formatos', '', (SELECT COUNT(DISTINCT formato) = 3 FROM torneos)),
 ('Hay torneos públicos y privados', '', (SELECT COUNT(DISTINCT visibilidad) = 2 FROM torneos)),
 ('Hay torneos de las 6 disciplinas', '', (SELECT COUNT(DISTINCT deporte) = 6 FROM torneos)),
 ('Ningún torneo supera su cupo', '', (SELECT COUNT(*) = 0 FROM torneos t WHERE t.max_participantes < (SELECT COUNT(*) FROM torneo_participantes p WHERE p.torneo_id = t.id))),
 ('Hay un torneo abierto con cupo completo', '', (SELECT COUNT(*) >= 1 FROM torneos t WHERE estado='abierto' AND max_participantes = (SELECT COUNT(*) FROM torneo_participantes p WHERE p.torneo_id = t.id))),
 ('El organizador nunca está inscripto en su propio torneo', '', (SELECT COUNT(*) = 0 FROM torneos t JOIN torneo_participantes p ON p.torneo_id=t.id AND p.usuario_id=t.organizador_id)),
 ('Los torneos abiertos arrancan en el futuro', '', (SELECT COUNT(*) = 0 FROM torneos WHERE estado='abierto' AND fecha_inicio <= CURDATE()));

-- 4) Partidos coherentes con el estado del torneo
INSERT INTO chk_verificacion (prueba, detalle, ok) VALUES
 ('Torneos finalizado/en_curso tienen partidos; el resto no', '', (SELECT COUNT(*) = 0 FROM torneos t WHERE (t.estado IN ('en_curso','finalizado')) <> EXISTS (SELECT 1 FROM partidos p WHERE p.torneo_id=t.id))),
 ('Torneos finalizados: ningún partido pendiente', '', (SELECT COUNT(*) = 0 FROM partidos p JOIN torneos t ON t.id=p.torneo_id WHERE t.estado='finalizado' AND p.estado='pendiente')),
 ('Torneos en curso: al menos un partido pendiente', '', (SELECT COUNT(*) = 0 FROM torneos t WHERE t.estado='en_curso' AND NOT EXISTS (SELECT 1 FROM partidos p WHERE p.torneo_id=t.id AND p.estado='pendiente'))),
 ('Los jugadores de cada partido están inscriptos en ese torneo', '', (SELECT COUNT(*) = 0 FROM partidos p WHERE
     (p.participante1_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM torneo_participantes x WHERE x.torneo_id=p.torneo_id AND x.usuario_id=p.participante1_id))
  OR (p.participante2_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM torneo_participantes x WHERE x.torneo_id=p.torneo_id AND x.usuario_id=p.participante2_id)))),
 ('El ganador es uno de los dos jugadores del partido', '', (SELECT COUNT(*) = 0 FROM partidos WHERE ganador_id IS NOT NULL AND ganador_id NOT IN (IFNULL(participante1_id,0), IFNULL(participante2_id,0)))),
 ('Partido jugado: tiene resultado, y el ganador coincide con el marcador', '', (SELECT COUNT(*) = 0 FROM partidos WHERE estado='jugado' AND (resultado1 IS NULL OR resultado2 IS NULL
     OR (resultado1 > resultado2 AND ganador_id <> participante1_id) OR (resultado2 > resultado1 AND ganador_id <> participante2_id)
     OR (resultado1 = resultado2 AND ganador_id IS NOT NULL)))),
 ('Partido pendiente: sin ganador ni resultado', '', (SELECT COUNT(*) = 0 FROM partidos WHERE estado='pendiente' AND (ganador_id IS NOT NULL OR resultado1 IS NOT NULL OR resultado2 IS NOT NULL))),
 ('Walkover (bye): un solo jugador, es el ganador, sin marcador', '', (SELECT COUNT(*) = 0 FROM partidos WHERE estado='wo' AND (participante2_id IS NOT NULL OR ganador_id <> participante1_id OR resultado1 IS NOT NULL))),
 ('Eliminación directa: sin empates', '', (SELECT COUNT(*) = 0 FROM partidos p JOIN torneos t ON t.id=p.torneo_id WHERE t.formato='eliminacion' AND p.estado='jugado' AND p.ganador_id IS NULL)),
 ('Liga finalizada: todos contra todos (n*(n-1)/2 partidos)', '', (SELECT COUNT(*) = 0 FROM torneos t WHERE t.formato='liga' AND t.estado='finalizado' AND
     (SELECT COUNT(*) FROM partidos p WHERE p.torneo_id=t.id) <> (SELECT COUNT(*)*(COUNT(*)-1)/2 FROM torneo_participantes x WHERE x.torneo_id=t.id))),
 ('Nadie juega dos veces en la misma ronda', '', (SELECT COUNT(*) = 0 FROM (
     SELECT torneo_id, ronda, j, COUNT(*) c FROM (
        SELECT torneo_id, ronda, participante1_id j FROM partidos WHERE participante1_id IS NOT NULL
        UNION ALL SELECT torneo_id, ronda, participante2_id FROM partidos WHERE participante2_id IS NOT NULL) z GROUP BY torneo_id, ronda, j HAVING c > 1) d));

-- 5) Ranking coherente: jugados/ganados salen de los torneos finalizados sembrados
INSERT INTO chk_verificacion (prueba, detalle, ok) VALUES
 ('torneos_jugados = torneos finalizados en que participó', '', (SELECT COUNT(*) = 0 FROM usuarios u WHERE u.email LIKE '%@trinity.test' AND u.torneos_jugados <>
     (SELECT COUNT(*) FROM torneo_participantes p JOIN torneos t ON t.id=p.torneo_id WHERE p.usuario_id=u.id AND t.estado='finalizado'))),
 ('Suma de torneos_ganados = cantidad de torneos finalizados', '', (SELECT (SELECT SUM(torneos_ganados) FROM usuarios) = (SELECT COUNT(*) FROM torneos WHERE estado='finalizado'))),
 ('Nadie ganó más torneos de los que jugó', '', (SELECT COUNT(*) = 0 FROM usuarios WHERE torneos_ganados > torneos_jugados));

-- 6) Equipos, invitaciones, noticias, solicitudes
INSERT INTO chk_verificacion (prueba, detalle, ok) VALUES
 ('Cada equipo tiene su capitán como miembro con rol capitan', '', (SELECT COUNT(*) = 0 FROM equipos e WHERE NOT EXISTS (SELECT 1 FROM equipo_miembros m WHERE m.equipo_id=e.id AND m.usuario_id=e.capitan_id AND m.rol='capitan' AND m.estado='activo'))),
 ('Un solo capitán por equipo', '', (SELECT COUNT(*) = 0 FROM (SELECT equipo_id FROM equipo_miembros WHERE rol='capitan' GROUP BY equipo_id HAVING COUNT(*) > 1) d)),
 ('Invitación aceptada => el invitado está inscripto', '', (SELECT COUNT(*) = 0 FROM torneo_invitaciones i WHERE i.estado='aceptada' AND NOT EXISTS (SELECT 1 FROM torneo_participantes p WHERE p.torneo_id=i.torneo_id AND p.usuario_id=i.invitado_id))),
 ('Nadie se invita a su propio torneo', '', (SELECT COUNT(*) = 0 FROM torneo_invitaciones WHERE organizador_id = invitado_id)),
 ('Noticias: pendiente sin moderar / moderada con admin y fecha', '', (SELECT COUNT(*) = 0 FROM noticias WHERE (estado='pendiente' AND (resuelto_por IS NOT NULL OR publicado_en IS NOT NULL)) OR (estado='rechazada' AND (resuelto_por IS NULL OR publicado_en IS NOT NULL)) OR (estado='publicada' AND publicado_en IS NULL))),
 ('Noticias: hay publicadas, pendientes y rechazadas', '', (SELECT COUNT(DISTINCT estado) = 3 FROM noticias)),
 ('Solicitudes: hay aprobadas, rechazadas y pendientes', '', (SELECT COUNT(DISTINCT estado) = 3 FROM solicitudes_organizador)),
 ('Solicitudes: máximo una pendiente por usuario', '', (SELECT COUNT(*) = 0 FROM (SELECT usuario_id FROM solicitudes_organizador WHERE estado='pendiente' GROUP BY usuario_id HAVING COUNT(*) > 1) d)),
 ('Solicitud aprobada => el usuario es organizador', '', (SELECT COUNT(*) = 0 FROM solicitudes_organizador s JOIN usuarios u ON u.id=s.usuario_id WHERE s.estado='aprobado' AND u.rol='participante')),
 ('Nadie se sigue a sí mismo', '', (SELECT COUNT(*) = 0 FROM seguidores WHERE seguidor_id = seguido_id)),
 ('Ningún token de reset sigue vigente', '', (SELECT COUNT(*) = 0 FROM password_resets WHERE usado = 0 AND expira_en > NOW())),
 ('Ningún código de verificación sigue vigente', '', (SELECT COUNT(*) = 0 FROM codigos_verificacion WHERE usado = 0 AND expira_en > NOW()));

-- 7) Auditoría: el seed pasó por los triggers y el hash nunca se copia
INSERT INTO chk_verificacion (prueba, detalle, ok) VALUES
 ('Hay filas de auditoría de las 8 tablas auditadas', '', (SELECT COUNT(DISTINCT tabla) = 8 FROM auditoria)),
 ('Ninguna fila de auditoría contiene hashes de contraseña', '', (SELECT COUNT(*) = 0 FROM auditoria WHERE CAST(datos_antes AS CHAR) LIKE '%$2y$%' OR CAST(datos_despues AS CHAR) LIKE '%$2y$%')),
 ('Triggers instalados (8 tablas x 3)', '', (SELECT COUNT(*) = 24 FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'trg_aud_%'));

SELECT orden, IF(ok = 1, 'PASS', 'FAIL') AS resultado, prueba, detalle FROM chk_verificacion
UNION ALL
SELECT 9999, IF(SUM(ok = 1) = COUNT(*), 'OK', 'FALLAS'), CONCAT('RESULTADO: ', IF(SUM(ok = 1) = COUNT(*), 'TODO OK', CONCAT(SUM(ok <> 1), ' prueba(s) fallaron')), ' (', SUM(ok = 1), '/', COUNT(*), ')'), '' FROM chk_verificacion
ORDER BY orden;

DROP TABLE chk_verificacion;
