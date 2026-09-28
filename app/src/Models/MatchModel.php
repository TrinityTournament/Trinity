<?php

namespace Trinity\Models;

use PDO;
use Trinity\Core\Database;

class MatchModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @param array<int,array<string,mixed>> $filas cada una con claves:
     *   torneo_id, ronda, ronda_etiqueta, orden, participante1_id?,
     *   participante2_id?, estado?, ganador_id?, resultado1?, resultado2?
     */
    public function insertMany(array $filas): void
    {
        if (empty($filas)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO partidos
                (torneo_id, ronda, ronda_etiqueta, orden, participante1_id, participante2_id,
                 resultado1, resultado2, ganador_id, estado)
             VALUES
                (:torneo_id, :ronda, :ronda_etiqueta, :orden, :p1, :p2, :r1, :r2, :ganador_id, :estado)'
        );

        foreach ($filas as $f) {
            $stmt->execute([
                ':torneo_id'      => $f['torneo_id'],
                ':ronda'          => $f['ronda'],
                ':ronda_etiqueta' => $f['ronda_etiqueta'],
                ':orden'          => $f['orden'],
                ':p1'             => $f['participante1_id'] ?? null,
                ':p2'             => $f['participante2_id'] ?? null,
                ':r1'             => $f['resultado1'] ?? null,
                ':r2'             => $f['resultado2'] ?? null,
                ':ganador_id'     => $f['ganador_id'] ?? null,
                ':estado'         => $f['estado'] ?? 'pendiente',
            ]);
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM partidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByTorneoRondaOrden(int $torneoId, int $ronda, int $orden): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM partidos WHERE torneo_id = :tid AND ronda = :ronda AND orden = :orden LIMIT 1'
        );
        $stmt->execute([':tid' => $torneoId, ':ronda' => $ronda, ':orden' => $orden]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Todos los partidos de un torneo, con nombre/usuario de cada
      * participante ya resueltos, ordenados para render de bracket o
      * calendario (ronda asc, orden asc).
      * @return array<int,array<string,mixed>> */
    public function listByTournament(int $torneoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*,
                    u1.nombre AS p1_nombre, u1.usuario AS p1_usuario,
                    u2.nombre AS p2_nombre, u2.usuario AS p2_usuario,
                    ug.nombre AS ganador_nombre
             FROM   partidos p
             LEFT JOIN usuarios u1 ON u1.id = p.participante1_id
             LEFT JOIN usuarios u2 ON u2.id = p.participante2_id
             LEFT JOIN usuarios ug ON ug.id = p.ganador_id
             WHERE  p.torneo_id = :tid
             ORDER  BY p.ronda ASC, p.orden ASC'
        );
        $stmt->execute([':tid' => $torneoId]);
        return $stmt->fetchAll();
    }

    public function updateResult(int $id, ?int $r1, ?int $r2, ?int $ganadorId, string $estado): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE partidos
             SET    resultado1 = :r1, resultado2 = :r2, ganador_id = :ganador_id, estado = :estado
             WHERE  id = :id'
        );
        $stmt->execute([
            ':r1'         => $r1,
            ':r2'         => $r2,
            ':ganador_id' => $ganadorId,
            ':estado'     => $estado,
            ':id'         => $id,
        ]);
    }

    public function setParticipantSlot(int $matchId, int $slot, int $participantId): void
    {
        $columna = $slot === 1 ? 'participante1_id' : 'participante2_id';
        $stmt = $this->pdo->prepare("UPDATE partidos SET {$columna} = :pid WHERE id = :id");
        $stmt->execute([':pid' => $participantId, ':id' => $matchId]);
    }

    public function maxRonda(int $torneoId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(ronda), 0) FROM partidos WHERE torneo_id = :tid');
        $stmt->execute([':tid' => $torneoId]);
        return (int) $stmt->fetchColumn();
    }

    /** Si quedan partidos sin jugar (pendientes) en el torneo, o en una
      * ronda puntual cuando se pasa $ronda. */
    public function hayPendientes(int $torneoId, ?int $ronda = null): bool
    {
        $sql = "SELECT COUNT(*) FROM partidos WHERE torneo_id = :tid AND estado = 'pendiente'";
        $params = [':tid' => $torneoId];
        if ($ronda !== null) {
            $sql .= ' AND ronda = :ronda';
            $params[':ronda'] = $ronda;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** @return array<int,array<string,mixed>> partidos de una ronda puntual */
    public function listByRonda(int $torneoId, int $ronda): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM partidos WHERE torneo_id = :tid AND ronda = :ronda ORDER BY orden ASC'
        );
        $stmt->execute([':tid' => $torneoId, ':ronda' => $ronda]);
        return $stmt->fetchAll();
    }
}
