<?php

namespace Trinity\Models;

use PDO;
use Trinity\Core\Database;

class ContactModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function create(?int $usuarioId, string $nombre, string $contacto, string $asunto, string $mensaje): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mensajes_contacto (usuario_id, nombre, contacto, asunto, mensaje)
             VALUES (:usuario_id, :nombre, :contacto, :asunto, :mensaje)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':nombre'     => $nombre,
            ':contacto'   => $contacto,
            ':asunto'     => $asunto,
            ':mensaje'    => $mensaje,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
