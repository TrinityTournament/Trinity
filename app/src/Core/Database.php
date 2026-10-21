<?php

namespace Trinity\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    // Último contexto de auditoría enviado a MySQL (ver syncAuditContext).
    private static ?int $auditUserId = null;
    private static ?string $auditIp  = null;
    private static bool $auditSynced = false;

    private function __construct()
    {} // La clase no debe instanciarse manualmente

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                Env::get('DB_HOST', 'localhost'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_NAME', 'trinity'),
                'utf8mb4'
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO(
                    $dsn,
                    Env::get('DB_USER', 'root'),
                    Env::get('DB_PASS', ''),
                    $options
                );
            } catch (PDOException $e) {
                error_log('[Database] Conexión fallida: ' . $e->getMessage());
                throw new ApiException('Error de conexión a la base de datos.', 500);
            }
        }

        self::syncAuditContext(self::$instance);

        return self::$instance;
    }

    /** Le cuenta a MySQL QUIÉN está haciendo los cambios, para que los
      * triggers de auditoría (database/03-auditoria.sql) lo graben en la
      * tabla `auditoria`:
      *     @app_user_id  → id del usuario logueado (NULL si no hay sesión)
      *     @app_ip       → IP del cliente
      *
      * Se re-evalúa en CADA llamada a pdo() y solo se manda el SET si algo
      * cambió: hay clases que piden la conexión antes de que el login
      * escriba la sesión (p. ej. AuthService::__construct), y sin esto
      * quedaría fijado el usuario anterior (NULL) durante todo el request.
      *
      * Si el SET falla NO se corta el request: la auditoría quedaría sin
      * usuario, pero la app sigue funcionando (se deja el error en el log).
      */
    private static function syncAuditContext(PDO $pdo): void
    {
        $uid = null;
        if (isset($_SESSION['trinity_user']['id'])) {
            $uid = (int) $_SESSION['trinity_user']['id'];
            if ($uid <= 0) {
                $uid = null;
            }
        }

        // REMOTE_ADDR a propósito: X-Forwarded-For lo puede falsear el cliente.
        $ip = isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null;

        if (self::$auditSynced && $uid === self::$auditUserId && $ip === self::$auditIp) {
            return;
        }

        try {
            $stmt = $pdo->prepare('SET @app_user_id = :uid, @app_ip = :ip');
            $stmt->bindValue(':uid', $uid, $uid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':ip', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            self::$auditUserId = $uid;
            self::$auditIp     = $ip;
            self::$auditSynced = true;
        } catch (PDOException $e) {
            error_log('[Database] No se pudo fijar el contexto de auditoría: ' . $e->getMessage());
        }
    }
}
