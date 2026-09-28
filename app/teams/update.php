<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Editar equipo (solo el capitán)
//  PATCH { id, nombre?, disciplina?, descripcion?, logo? }
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TeamService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('PATCH');
    Auth::validateCsrf();

    $user = Auth::requireLogin();
    $body = Request::body();

    return (new TeamService())->update((int) ($body['id'] ?? 0), (int) $user['id'], $body);
});
