<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Mis equipos (donde soy miembro activo o invitado)
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TeamService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('GET');
    $user = Auth::requireLogin();

    return (new TeamService())->myTeams((int) $user['id']);
});
