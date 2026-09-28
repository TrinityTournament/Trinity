<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Invitar a un usuario a un equipo (solo el capitán)
//  POST { equipo_id, usuario, rol? }  — "usuario" es el @usuario, sin @
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

    return (new TeamService())->invite(
        (int) ($body['equipo_id'] ?? 0),
        (int) $user['id'],
        trim((string) ($body['usuario'] ?? '')),
        (string) ($body['rol'] ?? 'jugador')
    );
});
