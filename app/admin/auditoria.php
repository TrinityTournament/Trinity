<?php

require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\AuditService;

SessionManager::start();
header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');
    Auth::requireRole('admin');

    $service = new AuditService();

    if (Request::query('resumen', '') === '1') {
        return $service->resumen();
    }

    return $service->listar($_GET);
});
