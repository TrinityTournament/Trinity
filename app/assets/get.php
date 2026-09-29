<?php
/** Assets estaticos 
 *  GET ?path=cards/tournament/file.jpeg
 * 
 *  No devuelve JSON, sirve los bytes de la imagen tal cual, para poder
 *  usarse directo en <img src="...">, <link rel="icon"> o url(...) de CSS.
 *  Cada pedido hace una llamada real a la API del bot (ver AssetClient::fetchAsset)
 *  las imagenes ya no viven en Trinity-page/assets/, viven en WhatsApp/assets/. */

require_once __DIR__ . '/../config.php';

use Trinity\Core\AssetClient;
use Trinity\Core\Request;

header('Cache-Control: no-store'); // Se pisa más abajo si el asset se encuentra

$path = trim((string) Request::query('path', ''));

// Doble validación defensiva, el bot ya lo hace pero se hace desde acá tambien.
$valid = $path !== ''
    && !str_contains($path, '..')
    && !str_starts_with($path, '/')
    && preg_match('#^[A-Za-z0-9_\-./]+$#', $path);

if (!$valid) {
    http_response_code(400);
    exit;
}

$asset = AssetClient::fetchAsset($path);

if ($asset === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $asset['mime']);
// Son assets de diseño estaticos, nunca cambian.
// Por esto se cargan por una semana.
header('Cache-Control: public, max-age=86400');
echo $asset['data'];