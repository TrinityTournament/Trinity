<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Responder a una invitación de equipo
//  POST { equipo_id, aceptar: bool }
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

    return (new TeamService())->respondInvite(
        (int) ($body['equipo_id'] ?? 0),
        (int) $user['id'],
        (bool) ($body['aceptar'] ?? false)
    );
});
