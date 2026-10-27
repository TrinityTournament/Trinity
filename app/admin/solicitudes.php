<?php

require_once __DIR__ . '/../config.php';

use Trinity\Core\ApiException;
use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\OrganizerService;

SessionManager::start();

Controller::handle(function () {
    if (Request::method() === 'POST') {
        Auth::validateCsrf();
    }
    $admin = Auth::requireRole('admin');
    $service = new OrganizerService();

    if (Request::method() === 'GET') {
        return $service->listForAdmin();
    }

    if (Request::method() === 'POST') {
        $body = Request::body();
        return $service->resolveByAdmin(
            (int) $admin['id'],
            (int) ($body['id'] ?? 0),
            trim((string) ($body['accion'] ?? ''))
        );
    }

    throw new ApiException('Método no permitido.', 405);
});
