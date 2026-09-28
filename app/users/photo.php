<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Foto de perfil
//  GET ?id=<id devuelto al subir la foto>
//
//  No devuelve JSON: sirve los bytes de la imagen tal cual, para
//  poder usarse directo como <img src="...photo.php?id=...">.
//  Cada pedido hace una llamada real a la API del bot (ver
//  BotStorageClient::fetchPhoto) — la imagen nunca se guarda en
//  MySQL. Es pública (sin sesión) porque la foto de perfil ya es
//  información pública en el resto del sitio (perfil, buscador de
//  usuarios, lista de seguidores, etc).
// ══════════════════════════════════════════════════════════
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
// Esta sí es una respuesta al navegador (no server-a-server), tiene
// sentido dejar que la cachee un rato — no contradice "cada vez que
// el SERVIDOR necesita la foto, llama a la API del bot": esa llamada
// sigue pasando siempre, esto solo evita que el mismo navegador la
// vuelva a pedir en cada request de la misma sesión de navegación.
header('Cache-Control: public, max-age=3600');
echo $foto['data'];
