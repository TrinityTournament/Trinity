<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Detalle de un equipo
//  GET ?id=123
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

    $equipoId = (int) Request::query('id', '0');
    return (new TeamService())->getDetail($equipoId, (int) $user['id']);
});
