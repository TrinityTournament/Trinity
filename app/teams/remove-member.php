<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Expulsar a un miembro (capitán) o salir del equipo (uno mismo)
//  DELETE ?equipo_id=123&usuario_id=456
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TeamService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('DELETE');
    Auth::validateCsrf();

    $user = Auth::requireLogin();

    $equipoId  = (int) Request::query('equipo_id', '0');
    $objetivoId = (int) Request::query('usuario_id', '0');

    return (new TeamService())->removeMember($equipoId, (int) $user['id'], $objetivoId);
});
