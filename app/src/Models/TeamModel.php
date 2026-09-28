<?php

namespace Trinity\Models;

use PDO;
use Trinity\Core\Database;

class TeamModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function create(string $nombre, string $disciplina, ?string $descripcion, ?string $logoUrl, int $capitanId): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO equipos (nombre, disciplina, descripcion, logo_url, capitan_id)
                 VALUES (:nombre, :disciplina, :descripcion, :logo_url, :capitan_id)'
            );
            $stmt->execute([
                ':nombre'      => $nombre,
                ':disciplina'  => $disciplina,
                ':descripcion' => $descripcion,
                ':logo_url'    => $logoUrl,
                ':capitan_id'  => $capitanId,
            ]);
            $equipoId = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare(
                "INSERT INTO equipo_miembros (equipo_id, usuario_id, rol, estado)
                 VALUES (:equipo_id, :usuario_id, 'capitan', 'activo')"
            );
            $stmt->execute([':equipo_id' => $equipoId, ':usuario_id' => $capitanId]);

            $this->pdo->commit();
            return $equipoId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.nombre AS capitan_nombre, u.usuario AS capitan_usuario
             FROM   equipos e
             JOIN   usuarios u ON u.id = e.capitan_id
             WHERE  e.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, string $nombre, string $disciplina, ?string $descripcion, ?string $logoUrl): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE equipos
             SET    nombre = :nombre, disciplina = :disciplina, descripcion = :descripcion, logo_url = :logo_url
             WHERE  id = :id'
        );
        $stmt->execute([
            ':nombre'      => $nombre,
            ':disciplina'  => $disciplina,
            ':descripcion' => $descripcion,
            ':logo_url'    => $logoUrl,
            ':id'          => $id,
        ]);
    }

    /** Equipos donde el usuario es miembro activo o tiene una invitación
      * pendiente (mis-equipos.html muestra ambos, separados por tab).
      * @return array<int,array<string,mixed>> */
    public function listForUser(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.nombre, e.disciplina, e.logo_url, e.capitan_id,
                    u.nombre AS capitan_nombre,
                    em.rol, em.estado,
                    (SELECT COUNT(*) FROM equipo_miembros em2 WHERE em2.equipo_id = e.id AND em2.estado = \'activo\') AS miembros
             FROM   equipo_miembros em
             JOIN   equipos  e ON e.id = em.equipo_id
             JOIN   usuarios u ON u.id = e.capitan_id
             WHERE  em.usuario_id = :usuario_id
             ORDER  BY em.creado_en DESC'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listMembers(int $equipoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.nombre, u.usuario, u.foto_url, em.rol, em.estado, em.creado_en
             FROM   equipo_miembros em
             JOIN   usuarios u ON u.id = em.usuario_id
             WHERE  em.equipo_id = :equipo_id
             ORDER  BY FIELD(em.rol, \'capitan\', \'jugador\', \'suplente\'), em.creado_en ASC'
        );
        $stmt->execute([':equipo_id' => $equipoId]);
        return $stmt->fetchAll();
    }

    public function findMembership(int $equipoId, int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM equipo_miembros WHERE equipo_id = :equipo_id AND usuario_id = :usuario_id LIMIT 1'
        );
        $stmt->execute([':equipo_id' => $equipoId, ':usuario_id' => $usuarioId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function invite(int $equipoId, int $usuarioId, string $rol): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO equipo_miembros (equipo_id, usuario_id, rol, estado)
             VALUES (:equipo_id, :usuario_id, :rol, 'invitado')"
        );
        $stmt->execute([':equipo_id' => $equipoId, ':usuario_id' => $usuarioId, ':rol' => $rol]);
    }

    public function activateMembership(int $equipoId, int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE equipo_miembros SET estado = 'activo'
             WHERE equipo_id = :equipo_id AND usuario_id = :usuario_id AND estado = 'invitado'"
        );
        $stmt->execute([':equipo_id' => $equipoId, ':usuario_id' => $usuarioId]);
    }

    public function removeMember(int $equipoId, int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM equipo_miembros WHERE equipo_id = :equipo_id AND usuario_id = :usuario_id'
        );
        $stmt->execute([':equipo_id' => $equipoId, ':usuario_id' => $usuarioId]);
    }

    public function countActiveMembers(int $equipoId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM equipo_miembros WHERE equipo_id = :equipo_id AND estado = 'activo'"
        );
        $stmt->execute([':equipo_id' => $equipoId]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $equipoId): void
    {
        // ON DELETE CASCADE en equipo_miembros se encarga del resto.
        $stmt = $this->pdo->prepare('DELETE FROM equipos WHERE id = :id');
        $stmt->execute([':id' => $equipoId]);
    }
}
