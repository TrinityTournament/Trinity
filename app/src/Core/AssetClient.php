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