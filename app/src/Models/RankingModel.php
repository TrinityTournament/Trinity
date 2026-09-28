<?php

namespace Trinity\Models;

use PDO;
use Trinity\Core\Database;

/**
 * No hay una tabla `rankings` propia — la posición se calcula al vuelo
 * sobre usuarios.torneos_ganados / torneos_jugados (contadores que ya
 * existen en el perfil) y, para el filtro por disciplina, sobre
 * deportes_seleccionados / videojuegos_seleccionados con JSON_CONTAINS
 * (mismo patrón que UserModel::findTargetedBySport). Ver la nota en
 * database.sql sobre por qué esto es deliberado y no una tabla aparte.
 */
class RankingModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function top(?string $disciplina, int $limit): array
    {
        $where  = '';
        $params = [];

        if ($disciplina !== null && $disciplina !== '') {
            $where = 'WHERE JSON_CONTAINS(deportes_seleccionados, :disciplina)
                         OR JSON_CONTAINS(videojuegos_seleccionados, :disciplina)';
            $params[':disciplina'] = json_encode($disciplina, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, nombre, usuario, foto_url, torneos_jugados, torneos_ganados
             FROM   usuarios
             {$where}
             ORDER  BY torneos_ganados DESC, torneos_jugados DESC, nombre ASC
             LIMIT :limit"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
