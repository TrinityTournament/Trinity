<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Crear noticia
//  POST { titulo, resumen?, contenido, categoria, imagen? }
//  Admin  → se publica directo.
//  Organizador → queda pendiente de aprobación de un admin.
//  Cualquier otro rol → 403.
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\NewsService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('POST');
    Auth::validateCsrf();

    $user = Auth::requireRole('admin', 'organizador');
    $body = Request::body();

    return (new NewsService())->create((int) $user['id'], $user['rol'], $body);
});
