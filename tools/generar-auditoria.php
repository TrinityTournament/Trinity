<?php
// ═══════════════════════════════════════════════════════════════════
//  TRINITY — Generador de triggers de auditoría
//
//  Uso (desde la raíz del proyecto):
//      php tools/generar-auditoria.php
//
//  Lee las columnas REALES de database/01-schema.sql y escribe
//  database/03-auditoria.sql. Se hace con un generador (y no
//  a mano) porque son 8 tablas × 3 triggers casi idénticos: así no hay
//  columnas olvidadas ni errores de copiar y pegar, y si el esquema
//  cambia basta con volver a correrlo.
//
//  El archivo generado SE ENTREGA (no hace falta PHP para instalarlo).
// ═══════════════════════════════════════════════════════════════════

$raiz   = dirname(__DIR__);
$schema = file_get_contents($raiz . '/database/01-schema.sql');
$salida = $raiz . '/database/03-auditoria.sql';

// Tablas críticas que pide el pliego (4.3).
const AUDITADAS = [
    'usuarios', 'torneos', 'torneo_participantes', 'partidos',
    'equipos', 'equipo_miembros', 'noticias', 'solicitudes_organizador',
];

// Columnas que NUNCA se copian a la auditoría.
const OCULTAS = ['usuarios' => ['password']];

// Columnas que pueden traer una imagen en base64: se guarda su id si es
// corto (ya viene del storage del bot) o un resumen md5 si es grande.
const IMAGENES = ['usuarios' => ['foto_url'], 'torneos' => ['banner_url']];

/** @return string[] columnas de $tabla, en el orden del CREATE TABLE */
function columnas(string $schema, string $tabla): array
{
    if (!preg_match('/CREATE TABLE IF NOT EXISTS ' . preg_quote($tabla, '/') . ' \((.*?)\n\) ENGINE/s', $schema, $m)) {
        fwrite(STDERR, "No encontré la tabla $tabla en database/01-schema.sql\n");
        exit(1);
    }
    $cols = [];
    foreach (explode("\n", $m[1]) as $linea) {
        if (!preg_match('/^\s{4}([a-z_0-9]+)\s+(INT|BIGINT|TINYINT|VARCHAR|TEXT|MEDIUMTEXT|DATE|DATETIME|TIMESTAMP|ENUM|JSON)\b/i', $linea, $c)) {
            continue; // PRIMARY KEY, UNIQUE, INDEX, FOREIGN KEY, comentarios...
        }
        $cols[] = $c[1];
    }
    return $cols;
}

/** Expresión SQL para una columna, con el prefijo de fila (OLD., NEW., p. ...) */
function expr(string $tabla, string $col, string $pre): string
{
    if (in_array($col, IMAGENES[$tabla] ?? [], true)) {
        return "IF({$pre}{$col} IS NULL, NULL, IF(CHAR_LENGTH({$pre}{$col}) > 64, CONCAT('md5:', MD5({$pre}{$col})), {$pre}{$col}))";
    }
    return $pre . $col;
}

/**
 * JSON_OBJECT(...) de una fila.
 * @param array<string,string> $sobrescribir columna => expresión SQL alternativa
 * @param array<string,string> $extra        clave JSON => expresión SQL (se agregan al final)
 */
function obj(string $tabla, string $pre, array $sobrescribir = [], array $extra = []): string
{
    global $schema;
    $pares = [];
    foreach (columnas($schema, $tabla) as $col) {
        if (in_array($col, OCULTAS[$tabla] ?? [], true)) {
            continue;
        }
        $pares[] = "'$col', " . ($sobrescribir[$col] ?? expr($tabla, $col, $pre));
    }
    foreach ($extra as $clave => $valor) {
        $pares[] = "'$clave', $valor";
    }
    return "JSON_OBJECT(\n            " . implode(",\n            ", $pares) . "\n        )";
}

const COLS_AUD = 'usuario_id, tabla, registro_id, accion, datos_antes, datos_despues, ip';

function triggerInsert(string $t): string
{
    $c   = COLS_AUD;
    $obj = obj($t, 'NEW.');
    return <<<SQL
DROP TRIGGER IF EXISTS trg_aud_{$t}_ai;
DELIMITER \$\$
CREATE TRIGGER trg_aud_{$t}_ai AFTER INSERT ON {$t} FOR EACH ROW
BEGIN
    INSERT INTO auditoria ({$c})
    VALUES (@app_user_id, '{$t}', NEW.id, 'INSERT', NULL, {$obj}, @app_ip);
END\$\$
DELIMITER ;

SQL;
}

function triggerUpdate(string $t): string
{
    // Solo se registra si algo cambió de verdad (evita ruido de UPDATEs vacíos).
    $extraAntes = $extraDespues = [];
    if ($t === 'usuarios') {
        // El hash nunca se copia, pero un cambio de contraseña tiene que quedar a la vista.
        $extraAntes   = ['password_cambiada' => "CAST('false' AS JSON)"];
        $extraDespues = ['password_cambiada' => "IF(OLD.password <> NEW.password, CAST('true' AS JSON), CAST('false' AS JSON))"];
    }
    $c       = COLS_AUD;
    $antes   = obj($t, 'OLD.', [], $extraAntes);
    $despues = obj($t, 'NEW.', [], $extraDespues);
    return <<<SQL
DROP TRIGGER IF EXISTS trg_aud_{$t}_au;
DELIMITER \$\$
CREATE TRIGGER trg_aud_{$t}_au AFTER UPDATE ON {$t} FOR EACH ROW
BEGIN
    INSERT INTO auditoria ({$c})
    SELECT @app_user_id, '{$t}', NEW.id, 'UPDATE', q.antes, q.despues, @app_ip
    FROM (SELECT
        {$antes} AS antes,
        {$despues} AS despues
    ) AS q
    WHERE NOT (q.antes <=> q.despues);
END\$\$
DELIMITER ;

SQL;
}

/** INSERT ... SELECT que registra como DELETE las filas hijas que se van a borrar en cascada. */
function borradoHijas(string $tabla, string $alias, string $where, string $origen): string
{
    $obj = obj($tabla, "$alias.", [], ['_cascada' => $origen]);
    return "    INSERT INTO auditoria (" . COLS_AUD . ")\n"
         . "    SELECT @app_user_id, '$tabla', $alias.id, 'DELETE', $obj, NULL, @app_ip\n"
         . "    FROM $tabla $alias WHERE $where;\n";
}

function triggerDelete(string $t): string
{
    $cuerpo = '';

    if ($t === 'torneos') {
        $o = "CONCAT('torneos:', OLD.id)";
        $cuerpo .= "    -- Hijas que MySQL borra en cascada SIN disparar sus propios triggers:\n";
        $cuerpo .= borradoHijas('torneo_participantes', 'tp', 'tp.torneo_id = OLD.id', $o);
        $cuerpo .= borradoHijas('partidos', 'pa', 'pa.torneo_id = OLD.id', $o);
    }

    if ($t === 'equipos') {
        $cuerpo .= "    -- Hijas que MySQL borra en cascada SIN disparar sus propios triggers:\n";
        $cuerpo .= borradoHijas('equipo_miembros', 'em', 'em.equipo_id = OLD.id', "CONCAT('equipos:', OLD.id)");
    }

    if ($t === 'usuarios') {
        $o = "CONCAT('usuarios:', OLD.id)";
        $propios = '(SELECT id FROM torneos WHERE organizador_id = OLD.id)';
        $capitaneados = '(SELECT id FROM equipos WHERE capitan_id = OLD.id)';

        $cuerpo .= "    -- Todo lo que MySQL borra (CASCADE) o desvincula (SET NULL) por culpa de este\n";
        $cuerpo .= "    -- usuario, y que NO dispara triggers propios. Se registra acá, antes de que ocurra.\n\n";
        $cuerpo .= "    -- 1) Torneos que organiza + sus inscripciones y partidos\n";
        $cuerpo .= borradoHijas('torneos', 't', 't.organizador_id = OLD.id', $o);
        $cuerpo .= borradoHijas('torneo_participantes', 'tp', "tp.torneo_id IN $propios", $o);
        $cuerpo .= borradoHijas('partidos', 'pa', "pa.torneo_id IN $propios", $o);
        $cuerpo .= "\n    -- 2) Sus inscripciones en torneos de otros\n";
        $cuerpo .= borradoHijas('torneo_participantes', 'tp2', "tp2.usuario_id = OLD.id AND tp2.torneo_id NOT IN $propios", $o);

        // 3) partidos de torneos ajenos donde jugó/ganó: SET NULL => UPDATE
        $sobre = [
            'participante1_id' => 'IF(pu.participante1_id = OLD.id, NULL, pu.participante1_id)',
            'participante2_id' => 'IF(pu.participante2_id = OLD.id, NULL, pu.participante2_id)',
            'ganador_id'       => 'IF(pu.ganador_id = OLD.id, NULL, pu.ganador_id)',
        ];
        $cuerpo .= "\n    -- 3) Partidos de torneos ajenos: MySQL deja la fila y pone NULL (SET NULL) => UPDATE\n";
        $cuerpo .= "    INSERT INTO auditoria (" . COLS_AUD . ")\n";
        $cuerpo .= "    SELECT @app_user_id, 'partidos', pu.id, 'UPDATE', " . obj('partidos', 'pu.') . ",\n";
        $cuerpo .= "           " . obj('partidos', 'pu.', $sobre, ['_cascada' => $o]) . ", @app_ip\n";
        $cuerpo .= "    FROM partidos pu\n";
        $cuerpo .= "    WHERE (pu.participante1_id = OLD.id OR pu.participante2_id = OLD.id OR pu.ganador_id = OLD.id)\n";
        $cuerpo .= "      AND pu.torneo_id NOT IN $propios;\n";

        $cuerpo .= "\n    -- 4) Equipos que capitanea + sus miembros\n";
        $cuerpo .= borradoHijas('equipos', 'e', 'e.capitan_id = OLD.id', $o);
        $cuerpo .= borradoHijas('equipo_miembros', 'em', "em.equipo_id IN $capitaneados", $o);
        $cuerpo .= "\n    -- 5) Su pertenencia a equipos de otros\n";
        $cuerpo .= borradoHijas('equipo_miembros', 'em2', "em2.usuario_id = OLD.id AND em2.equipo_id NOT IN $capitaneados", $o);

        $cuerpo .= "\n    -- 6) Noticias que escribió\n";
        $cuerpo .= borradoHijas('noticias', 'n', 'n.autor_id = OLD.id', $o);
        // noticias moderadas por él: SET NULL => UPDATE
        $cuerpo .= "\n    -- 7) Noticias que moderó como admin (resuelto_por pasa a NULL) => UPDATE\n";
        $cuerpo .= "    INSERT INTO auditoria (" . COLS_AUD . ")\n";
        $cuerpo .= "    SELECT @app_user_id, 'noticias', nu.id, 'UPDATE', " . obj('noticias', 'nu.') . ",\n";
        $cuerpo .= "           " . obj('noticias', 'nu.', ['resuelto_por' => 'NULL'], ['_cascada' => $o]) . ", @app_ip\n";
        $cuerpo .= "    FROM noticias nu WHERE nu.resuelto_por = OLD.id AND nu.autor_id <> OLD.id;\n";

        $cuerpo .= "\n    -- 8) Solicitudes de organizador que hizo\n";
        $cuerpo .= borradoHijas('solicitudes_organizador', 's', 's.usuario_id = OLD.id', $o);
        $cuerpo .= "\n    -- 9) Solicitudes que resolvió como admin (resuelto_por pasa a NULL) => UPDATE\n";
        $cuerpo .= "    INSERT INTO auditoria (" . COLS_AUD . ")\n";
        $cuerpo .= "    SELECT @app_user_id, 'solicitudes_organizador', su.id, 'UPDATE', " . obj('solicitudes_organizador', 'su.') . ",\n";
        $cuerpo .= "           " . obj('solicitudes_organizador', 'su.', ['resuelto_por' => 'NULL'], ['_cascada' => $o]) . ", @app_ip\n";
        $cuerpo .= "    FROM solicitudes_organizador su WHERE su.resuelto_por = OLD.id AND su.usuario_id <> OLD.id;\n";

        $cuerpo .= "\n    -- 10) Y por último, la fila del usuario en sí\n";
    }

    $obj = obj($t, 'OLD.');
    $cuerpo .= "    INSERT INTO auditoria (" . COLS_AUD . ")\n";
    $cuerpo .= "    VALUES (@app_user_id, '$t', OLD.id, 'DELETE', $obj, NULL, @app_ip);\n";

    return "DROP TRIGGER IF EXISTS trg_aud_{$t}_bd;\nDELIMITER \$\$\n"
         . "CREATE TRIGGER trg_aud_{$t}_bd BEFORE DELETE ON {$t} FOR EACH ROW\nBEGIN\n"
         . $cuerpo
         . "END\$\$\nDELIMITER ;\n";
}

// ───────────────────────── Armado del archivo ─────────────────────────
$out = <<<'HDR'
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


HDR;

foreach (AUDITADAS as $t) {
    $out .= "-- ───────────────────────── {$t} ─────────────────────────\n";
    $out .= triggerInsert($t) . "\n";
    $out .= triggerUpdate($t) . "\n";
    $out .= triggerDelete($t) . "\n";
}

file_put_contents($salida, $out);
printf("Escribí %s (%d triggers, %d líneas)\n", $salida, count(AUDITADAS) * 3, substr_count($out, "\n"));
