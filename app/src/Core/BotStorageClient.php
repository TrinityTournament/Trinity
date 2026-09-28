<?php

namespace Trinity\Core;

/**
 * Habla con el mismo servidor de Node que WhatsAppClient (carpeta
 * /WhatsApp), pero contra las rutas de storage en vez de las de
 * mensajería. Las fotos de perfil de Trinity NO se guardan en MySQL
 * — se suben acá al crear/editar el perfil, y se piden acá (con una
 * llamada HTTP real, sin cachear una copia en la base) cada vez que
 * hace falta mostrarlas — ver app/users/photo.php.
 */
final class BotStorageClient
{
    private function __construct()
    {
    }

    /**
     * Sube una foto (data URL completo, "data:image/jpeg;base64,...")
     * y devuelve el id generado, o null si falló.
     */
    public static function uploadPhoto(string $dataUrl): ?string
    {
        $url = self::baseUrl() . '/api/storage/photo';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['data' => $dataUrl]),
            CURLOPT_HTTPHEADER     => self::headers(true),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $raw   = curl_exec($ch);
        $errno = curl_errno($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $code !== 200 || $raw === false) {
            error_log(sprintf(
                '[BotStorageClient] FALLÓ upload. curl_errno=%s http_code=%s respuesta=%s',
                $errno,
                $code,
                $raw === false ? '(sin respuesta)' : $raw
            ));
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? ($data['id'] ?? null) : null;
    }

    /**
     * Trae los bytes crudos de una foto guardada.
     *
     * @return array{mime:string, data:string}|null
     */
    public static function fetchPhoto(string $id): ?array
    {
        $url = self::baseUrl() . '/api/storage/photo/' . rawurlencode($id);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => self::headers(false),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $raw     = curl_exec($ch);
        $errno   = curl_errno($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headers = curl_getinfo($ch);
        curl_close($ch);

        if ($errno !== 0 || $code !== 200 || $raw === false) {
            return null;
        }

        return [
            'mime' => $headers['content_type'] ?? 'image/jpeg',
            'data' => $raw,
        ];
    }

    /**
     * Borra una foto vieja (best-effort — si falla, solo queda un
     * archivo huérfano en el disco del bot, no es un error fatal
     * para el usuario que está guardando su foto nueva).
     */
    public static function deletePhoto(string $id): bool
    {
        if ($id === '') {
            return true;
        }

        $url = self::baseUrl() . '/api/storage/photo/' . rawurlencode($id);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => self::headers(false),
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $code !== 200) {
            error_log("[BotStorageClient] No se pudo borrar la foto vieja {$id} (no crítico).");
            return false;
        }
        return true;
    }

    private static function baseUrl(): string
    {
        $full = trim(Env::get('WA_BOT_URL', ''));
        if ($full !== '') {
            return rtrim($full, '/');
        }

        $host = Env::get('WA_BOT_HOST', '127.0.0.1');
        $port = Env::get('WA_BOT_PORT', '3001');
        return 'http://' . $host . ':' . $port;
    }

    private static function headers(bool $withContentType): array
    {
        $headers = [
            'X-WA-Secret: ' . Env::get('WA_SECRET', ''),
            'ngrok-skip-browser-warning: true',
        ];
        if ($withContentType) {
            array_unshift($headers, 'Content-Type: application/json');
        }
        return $headers;
    }
}
