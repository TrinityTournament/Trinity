<?php

namespace Trinity\Models;

use PDO;
use Trinity\Core\Database;

class NewsModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @param array<string,mixed> $data claves: autor_id, titulo, resumen,
     *   contenido, categoria, imagen_url, estado
     */
    public function create(array $data): int
    {
        $publicadoEn = $data['estado'] === 'publicada' ? 'NOW()' : 'NULL';

        $stmt = $this->pdo->prepare(
            "INSERT INTO noticias (autor_id, titulo, resumen, contenido, categoria, imagen_url, estado, publicado_en)
             VALUES (:autor_id, :titulo, :resumen, :contenido, :categoria, :imagen_url, :estado, {$publicadoEn})"
        );
        $stmt->execute([
            ':autor_id'   => $data['autor_id'],
            ':titulo'     => $data['titulo'],
            ':resumen'    => $data['resumen'],
            ':contenido'  => $data['contenido'],
            ':categoria'  => $data['categoria'],
            ':imagen_url' => $data['imagen_url'],
            ':estado'     => $data['estado'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.nombre AS autor_nombre, u.usuario AS autor_usuario
             FROM   noticias n
             JOIN   usuarios u ON u.id = n.autor_id
             WHERE  n.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listPublished(?string $categoria, int $limit): array
    {
        $where  = ["estado = 'publicada'"];
        $params = [];

        if ($categoria !== null && $categoria !== '') {
            $where[] = 'categoria = :categoria';
            $params[':categoria'] = $categoria;
        }

        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.titulo, n.resumen, n.contenido, n.categoria, n.imagen_url,
                    n.publicado_en, u.nombre AS autor_nombre, u.usuario AS autor_usuario
             FROM   noticias n
             JOIN   usuarios u ON u.id = n.autor_id
             WHERE  ' . implode(' AND ', $where) . '
             ORDER  BY n.publicado_en DESC
             LIMIT :limit'
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listPending(): array
    {
        $stmt = $this->pdo->query(
            "SELECT n.id, n.titulo, n.resumen, n.categoria, n.creado_en,
                    u.nombre AS autor_nombre, u.usuario AS autor_usuario
             FROM   noticias n
             JOIN   usuarios u ON u.id = n.autor_id
             WHERE  n.estado = 'pendiente'
             ORDER  BY n.creado_en ASC"
        );
        return $stmt->fetchAll();
    }

    /** Solicitudes de noticia enviadas por un organizador (para que vea
      * el estado de lo que mandó — aprobado/rechazado/pendiente).
      * @return array<int,array<string,mixed>> */
    public function listByAuthor(int $autorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, titulo, resumen, categoria, estado, creado_en, resuelto_en
             FROM   noticias
             WHERE  autor_id = :autor_id
             ORDER  BY creado_en DESC'
        );
        $stmt->execute([':autor_id' => $autorId]);
        return $stmt->fetchAll();
    }

    public function resolve(int $id, string $estado, int $adminId): void
    {
        $publicadoEn = $estado === 'publicada' ? ', publicado_en = NOW()' : '';

        $stmt = $this->pdo->prepare(
            "UPDATE noticias
             SET    estado = :estado, resuelto_por = :admin_id, resuelto_en = NOW() {$publicadoEn}
             WHERE  id = :id AND estado = 'pendiente'"
        );
        $stmt->execute([
            ':estado'   => $estado,
            ':admin_id' => $adminId,
            ':id'       => $id,
        ]);
    }
}
