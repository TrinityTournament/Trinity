<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Moderación de noticias (solo admin)
//  GET   → lista las noticias pendientes de aprobación
//  PATCH { id, aprobar: bool } → aprueba (publica) o rechaza
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\ApiException;
use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\NewsService;

SessionManager::start();

Controller::handle(function () {
    $user = Auth::requireRole('admin');
    $service = new NewsService();

    if (Request::method() === 'GET') {
        return $service->pending();
    }

    if (Request::method() === 'PATCH') {
        Auth::validateCsrf();
        $body = Request::body();
        return $service->resolve(
            (int) ($body['id'] ?? 0),
            (int) $user['id'],
            (bool) ($body['aprobar'] ?? false)
        );
    }

    throw new ApiException('Método no permitido.', 405);
});
