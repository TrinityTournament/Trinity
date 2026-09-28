<?php

namespace Trinity\Models;

use PDO;
use PDOException;
use Trinity\Core\ApiException;
use Trinity\Core\Database;

/**
 * El wrapper `guarded()` de abajo queda como red de seguridad: si en algún
 * despliegue las tablas `torneos`/`torneo_*` todavía no se corrieron,
 * devuelve un 501 claro en vez de un 500 sin explicación, en lugar de
 * romper el resto del sistema (gestión de usuarios, etc.).
 */
class TournamentModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Crea un torneo nuevo y devuelve su id.
     *
     * @param array<string,mixed> $data claves: organizador_id, titulo, deporte,
     *   descripcion, formato, max_participantes, fecha_inicio, visibilidad,
     *   banner_url, estado
     */
    public function create(array $data): int
    {
        return $this->guarded(function () use ($data) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO torneos
                    (organizador_id, titulo, deporte, descripcion, formato,
                     max_participantes, fecha_inicio, visibilidad, banner_url, estado)
                 VALUES
                    (:organizador_id, :titulo, :deporte, :descripcion, :formato,
                     :max_participantes, :fecha_inicio, :visibilidad, :banner_url, :estado)'
            );
            $stmt->execute([
                ':organizador_id'    => $data['organizador_id'],
                ':titulo'            => $data['titulo'],
                ':deporte'           => $data['deporte'],
                ':descripcion'       => $data['descripcion'],
                ':formato'           => $data['formato'],
                ':max_participantes' => $data['max_participantes'],
                ':fecha_inicio'      => $data['fecha_inicio'],
                ':visibilidad'       => $data['visibilidad'],
                ':banner_url'        => $data['banner_url'],
                ':estado'            => $data['estado'],
            ]);

            return (int) $this->pdo->lastInsertId();
        });
    }

    public function findById(int $torneoId): ?array
    {
        return $this->guarded(function () use ($torneoId) {
            $stmt = $this->pdo->prepare(
                'SELECT t.*, u.nombre AS organizador_nombre, u.usuario AS organizador_usuario
                 FROM   torneos  t
                 JOIN   usuarios u ON u.id = t.organizador_id
                 WHERE  t.id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => $torneoId]);
            $row = $stmt->fetch();
            return $row ?: null;
        });
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function forOrganizer(int $organizerId, ?string $estado): array
    {
        return $this->guarded(function () use ($organizerId, $estado) {
            $select = 'SELECT id, titulo, deporte, formato, estado, fecha_inicio, max_participantes,
                        (SELECT COUNT(*) FROM torneo_participantes tp WHERE tp.torneo_id = torneos.id) AS inscritos
                       FROM torneos
                       WHERE organizador_id = :uid';

            if ($estado) {
                $stmt = $this->pdo->prepare($select . ' AND estado = :estado ORDER BY creado_en DESC');
                $stmt->execute([':uid' => $organizerId, ':estado' => $estado]);
            } else {
                $stmt = $this->pdo->prepare($select . ' ORDER BY creado_en DESC');
                $stmt->execute([':uid' => $organizerId]);
            }

            return $stmt->fetchAll();
        });
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function allWithOrganizer(): array
    {
        return $this->guarded(function () {
            $stmt = $this->pdo->query(
                'SELECT t.id, t.titulo, t.deporte, t.estado, t.fecha_inicio,
                        t.max_participantes, t.creado_en,
                        u.nombre  AS organizador_nombre,
                        u.usuario AS organizador_usuario
                 FROM   torneos  t
                 JOIN   usuarios u ON u.id = t.organizador_id
                 ORDER  BY t.creado_en DESC'
            );
            return $stmt->fetchAll();
        });
    }

    public function findOwnedByOrganizer(int $torneoId, int $organizerId): ?array
    {
        return $this->guarded(function () use ($torneoId, $organizerId) {
            $stmt = $this->pdo->prepare(
                'SELECT id, titulo, estado FROM torneos
                 WHERE id = :id AND organizador_id = :org_id LIMIT 1'
            );
            $stmt->execute([':id' => $torneoId, ':org_id' => $organizerId]);
            $row = $stmt->fetch();
            return $row ?: null;
        });
    }

    public function alreadyInvited(int $torneoId, int $invitadoId): bool
    {
        return $this->guarded(function () use ($torneoId, $invitadoId) {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM torneo_invitaciones
                 WHERE torneo_id = :torneo_id AND invitado_id = :invitado_id LIMIT 1'
            );
            $stmt->execute([':torneo_id' => $torneoId, ':invitado_id' => $invitadoId]);
            return (bool) $stmt->fetch();
        });
    }

    public function alreadyParticipant(int $torneoId, int $usuarioId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM torneo_participantes
                 WHERE torneo_id = :torneo_id AND usuario_id = :uid LIMIT 1'
            );
            $stmt->execute([':torneo_id' => $torneoId, ':uid' => $usuarioId]);
            return (bool) $stmt->fetch();
        } catch (PDOException) {
            // Tabla de participantes aún no existe: se ignora esta verificación.
            return false;
        }
    }

    public function updateEstado(int $torneoId, string $estado): void
    {
        $this->guarded(function () use ($torneoId, $estado) {
            $stmt = $this->pdo->prepare('UPDATE torneos SET estado = :estado WHERE id = :id');
            $stmt->execute([':estado' => $estado, ':id' => $torneoId]);
            return null;
        });
    }

    public function insertInvitation(int $torneoId, int $organizadorId, int $invitadoId): void
    {
        $this->guarded(function () use ($torneoId, $organizadorId, $invitadoId) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO torneo_invitaciones (torneo_id, organizador_id, invitado_id, estado)
                 VALUES (:torneo_id, :org_id, :invitado_id, 'pendiente')"
            );
            $stmt->execute([
                ':torneo_id'   => $torneoId,
                ':org_id'      => $organizadorId,
                ':invitado_id' => $invitadoId,
            ]);
            return null;
        });
    }

    public function countParticipants(int $torneoId): int
    {
        return $this->guarded(function () use ($torneoId) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM torneo_participantes WHERE torneo_id = :id'
            );
            $stmt->execute([':id' => $torneoId]);
            return (int) $stmt->fetchColumn();
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function listParticipants(int $torneoId): array
    {
        return $this->guarded(function () use ($torneoId) {
            $stmt = $this->pdo->prepare(
                'SELECT u.id, u.nombre, u.usuario, u.foto_url, tp.inscrito_en
                 FROM   torneo_participantes tp
                 JOIN   usuarios u ON u.id = tp.usuario_id
                 WHERE  tp.torneo_id = :id
                 ORDER  BY tp.inscrito_en ASC'
            );
            $stmt->execute([':id' => $torneoId]);
            return $stmt->fetchAll();
        });
    }

    public function registerParticipant(int $torneoId, int $usuarioId): void
    {
        $this->guarded(function () use ($torneoId, $usuarioId) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO torneo_participantes (torneo_id, usuario_id) VALUES (:tid, :uid)'
            );
            $stmt->execute([':tid' => $torneoId, ':uid' => $usuarioId]);
            return null;
        });
    }

    public function unregisterParticipant(int $torneoId, int $usuarioId): bool
    {
        return $this->guarded(function () use ($torneoId, $usuarioId) {
            $stmt = $this->pdo->prepare(
                'DELETE FROM torneo_participantes WHERE torneo_id = :tid AND usuario_id = :uid'
            );
            $stmt->execute([':tid' => $torneoId, ':uid' => $usuarioId]);
            return $stmt->rowCount() > 0;
        });
    }

    /** Torneos en los que el usuario está inscripto como participante
      * (no como organizador — ver forOrganizer para eso).
      * @return array<int,array<string,mixed>> */
    public function forParticipant(int $usuarioId): array
    {
        return $this->guarded(function () use ($usuarioId) {
            $stmt = $this->pdo->prepare(
                'SELECT t.id, t.titulo, t.deporte, t.formato, t.estado, t.fecha_inicio,
                        t.max_participantes, t.banner_url,
                        u.nombre AS organizador_nombre, u.usuario AS organizador_usuario,
                        (SELECT COUNT(*) FROM torneo_participantes tp2 WHERE tp2.torneo_id = t.id) AS inscritos
                 FROM   torneo_participantes tp
                 JOIN   torneos  t ON t.id = tp.torneo_id
                 JOIN   usuarios u ON u.id = t.organizador_id
                 WHERE  tp.usuario_id = :uid
                 ORDER  BY t.fecha_inicio DESC'
            );
            $stmt->execute([':uid' => $usuarioId]);
            return $stmt->fetchAll();
        });
    }

    public function findInvitation(int $invitacionId): ?array
    {
        return $this->guarded(function () use ($invitacionId) {
            $stmt = $this->pdo->prepare(
                'SELECT ti.*, t.titulo AS torneo_titulo, t.estado AS torneo_estado
                 FROM   torneo_invitaciones ti
                 JOIN   torneos t ON t.id = ti.torneo_id
                 WHERE  ti.id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $invitacionId]);
            $row = $stmt->fetch();
            return $row ?: null;
        });
    }

    public function resolveInvitation(int $invitacionId, string $estado): void
    {
        $this->guarded(function () use ($invitacionId, $estado) {
            $stmt = $this->pdo->prepare(
                "UPDATE torneo_invitaciones SET estado = :estado WHERE id = :id AND estado = 'pendiente'"
            );
            $stmt->execute([':estado' => $estado, ':id' => $invitacionId]);
            return null;
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingInvitationsForUser(int $usuarioId): array
    {
        return $this->guarded(function () use ($usuarioId) {
            $stmt = $this->pdo->prepare(
                "SELECT ti.id, ti.torneo_id, ti.creado_en,
                        t.titulo, t.deporte, t.fecha_inicio,
                        u.nombre AS organizador_nombre, u.usuario AS organizador_usuario
                 FROM   torneo_invitaciones ti
                 JOIN   torneos  t ON t.id = ti.torneo_id
                 JOIN   usuarios u ON u.id = ti.organizador_id
                 WHERE  ti.invitado_id = :uid AND ti.estado = 'pendiente'
                 ORDER  BY ti.creado_en DESC"
            );
            $stmt->execute([':uid' => $usuarioId]);
            return $stmt->fetchAll();
        });
    }

    
    // Ejecuta $fn y convierte "tabla no existe" en un 501 legible.
    // Deja pasar cualquier otro tipo de error tal cual.
    private function guarded(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                throw new ApiException(
                    'Las tablas de torneos no existen en esta base de datos. '
                    . 'Corré el database.sql actualizado (incluye torneos, '
                    . 'torneo_participantes y torneo_invitaciones).',
                    501
                );
            }
            throw $e;
        }
    }

    /**
     * Búsqueda pública de torneos (buscar.html). Solo torneos con
     * visibilidad='publico' y que ya salieron de borrador.
     *
     * @return array{torneos: array<int, array<string, mixed>>, total: int, page: int}
     */
    public function searchPublic(string $texto, string $deporte, string $formato, string $estado, int $page, int $perPage): array
    {
        return $this->guarded(function () use ($texto, $deporte, $formato, $estado, $page, $perPage) {
            $where  = ["visibilidad = 'publico'", "estado != 'en_creacion'"];
            $params = [];

            if ($texto !== '') {
                $where[] = '(titulo LIKE :texto OR deporte LIKE :texto)';
                $params[':texto'] = '%' . $texto . '%';
            }
            if ($deporte !== '') {
                $where[] = 'LOWER(deporte) = LOWER(:deporte)';
                $params[':deporte'] = $deporte;
            }
            if ($formato !== '') {
                $where[] = 'formato = :formato';
                $params[':formato'] = $formato;
            }
            if ($estado !== '') {
                $where[] = 'estado = :estado';
                $params[':estado'] = $estado;
            }

            $whereSql = implode(' AND ', $where);
            $offset   = max(0, ($page - 1) * $perPage);

            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM torneos WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                "SELECT
                    t.id, t.titulo, t.deporte, t.formato, t.estado, t.fecha_inicio,
                    t.max_participantes, t.banner_url, t.creado_en,
                    (SELECT COUNT(*) FROM torneo_participantes tp WHERE tp.torneo_id = t.id) AS inscritos
                 FROM torneos t
                 WHERE {$whereSql}
                 ORDER BY t.fecha_inicio ASC
                 LIMIT :limit OFFSET :offset"
            );
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'torneos' => $stmt->fetchAll(),
                'total'   => $total,
                'page'    => $page,
            ];
        });
    }
}
