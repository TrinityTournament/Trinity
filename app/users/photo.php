<?php

require_once __DIR__ . '/../config.php';

use Trinity\Core\BotStorageClient;
use Trinity\Core\Request;

header('Cache-Control: no-store'); // se pisa más abajo si la foto se encuentra

$id = trim((string) Request::query('id', ''));

if ($id === '') {
    http_response_code(400);
    exit;
}

$foto = BotStorageClient::fetchPhoto($id);

if ($foto === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $foto['mime']);

header('Cache-Control: public, max-age=3600');
echo $foto['data'];
