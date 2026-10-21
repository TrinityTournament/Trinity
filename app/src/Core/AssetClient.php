<?php

namespace Trinity\Core;

/** Habla con el mismo servidor de Node que WhatsAppClient y
 *  BotStorageClient ( /WhatsApp ), pero contra la ruta de 
 *  assets estaticos. Las imagenes del sitio ya no viven en Trinity-page/assets
 *  sino en /WhatsApp/assets, y se piden por una llamada HTTP cada que hace falta mostrarlas
 */

final class AssetClient {
    private function __construct() {}

    /** Trae los bytes crudos de un asset a partir de su ruta relativa
     *  dentro de WhatsApp/assets/
     * 
     * @return array{mime:string, data:string}|null */

    public static function fetchAsset(string $relPath): ?array {
        // Si hay ASSETS_DIR (Docker), se lee directo del disco y el sitio se ve
        // completo aunque el bot no esté levantado. Sin esa variable, todo sigue
        // igual que antes: se le pide el asset al bot por HTTP.
        $local = self::fetchLocal($relPath);
        if ($local !== null) {
            return $local;
        }

        $url = self::baseUrl() . '/api/assets?path=' . rawurlencode($relPath);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => self::headers(),
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
            'mime' => $headers['content_type'] ?? 'application/octet-stream',
            'data' => $raw,
        ];
    }

    /** Mismas defensas que WhatsApp/src/lib/assetStorage.js: solo extensiones de
     *  imagen conocidas y la ruta resuelta no puede salir de ASSETS_DIR.
     *
     * @return array{mime:string, data:string}|null */
    private static function fetchLocal(string $relPath): ?array {
        $dir = trim(Env::get('ASSETS_DIR', ''));
        if ($dir === '' || str_contains($relPath, "\0")) {
            return null;
        }

        $mimes = [
            'ico' => 'image/x-icon', 'png' => 'image/png', 'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        $mime = $mimes[strtolower(pathinfo($relPath, PATHINFO_EXTENSION))] ?? null;
        $root = realpath($dir);
        if ($mime === null || $root === false) {
            return null;
        }

        $file = realpath($root . DIRECTORY_SEPARATOR . $relPath);
        if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return null;
        }

        $data = file_get_contents($file);
        return $data === false ? null : ['mime' => $mime, 'data' => $data];
    }

    private static function baseUrl(): string {
        $full = trim(Env::get('WA_BOT_URL', ''));
        if ($full !== '') {
            return rtrim($full, '/');
        }

        $host = Env::get('WA_BOT_HOST', '127.0.0.1');
        $port = Env::get('WA_BOT_PORT', '3001');
        return 'http://' . $host . ':' . $port;
    }

    private static function headers(): array {
        return [ 
            'X-WA-Secret: ' . Env::get('WA_SECRET', ''),
            'ngrok-skip-browser-warning: true',
        ];  
    }
}