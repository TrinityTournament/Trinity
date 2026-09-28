<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Crear equipo
//  POST { nombre, disciplina, descripcion?, logo? }
//  El creador queda como capitán automáticamente.
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TeamService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('POST');
    Auth::validateCsrf();

    $user = Auth::requireLogin();
    $body = Request::body();

    return (new TeamService())->create((int) $user['id'], $body);
});
