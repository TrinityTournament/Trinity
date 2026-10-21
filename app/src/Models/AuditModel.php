<?php

namespace Trinity\Models;

use PDO;
use Trinity\Core\Database;

/** Lectura (y solo lectura) de la tabla `auditoria`. La escritura la
  * hacen los triggers de MySQL — ver database/03-auditoria.sql. */
class AuditModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @param array<string,mixed> $f filtros ya validados por AuditService
     * @return array{filas:array<int,array<string,mixed>>,total:int}
     */
    public function search(array $f, int $limit, int $offset): array
    {
        $where  = [];
        $params = [];

        if (!empty($f['tabla']))       { $where[] = 'a.tabla = :tabla';             $params[':tabla']  = $f['tabla']; }
        if (!empty($f['accion']))      { $where[] = 'a.accion = :accion';           $params[':accion'] = $f['accion']; }
        if (!empty($f['registro_id'])) { $where[] = 'a.registro_id = :registro_id'; $params[':registro_id'] = $f['registro_id']; }
        if (!empty($f['usuario_id']))  { $where[] = 'a.usuario_id = :usuario_id';   $params[':usuario_id']  = $f['usuario_id']; }
        if (!empty($f['desde']))       { $where[] = 'a.creado_en >= :desde';        $params[':desde'] = $f['desde'] . ' 00:00:00'; }
        if (!empty($f['hasta']))       { $where[] = 'a.creado_en <= :hasta';        $params[':hasta'] = $f['hasta'] . ' 23:59:59'; }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM auditoria a {$whereSql}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // $limit/$offset son enteros ya validados: se interpolan porque con
        // prepares nativos LIMIT no acepta placeholders de texto.
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.usuario_id, u.usuario AS usuario_nombre, a.tabla, a.registro_id,
                    a.accion, a.datos_antes, a.datos_despues, a.ip, a.creado_en
             FROM   auditoria a
             LEFT   JOIN usuarios u ON u.id = a.usuario_id
             {$whereSql}
             ORDER  BY a.id DESC
             LIMIT  {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['filas' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> cantidad de eventos por tabla y acción */
    public function resumen(): array
    {
        return $this->pdo->query(
            'SELECT tabla, accion, COUNT(*) AS cantidad
             FROM   auditoria
             GROUP  BY tabla, accion
             ORDER  BY tabla, accion'
        )->fetchAll();
    }
}
