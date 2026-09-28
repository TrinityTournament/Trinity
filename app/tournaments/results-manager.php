<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Panel de resultados del organizador (resultados.html)
//  GET ?torneo_id=123
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\MatchService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('GET');
    $user = Auth::requireLogin();

    $torneoId = (int) Request::query('torneo_id', '0');
    return (new MatchService())->getResultsManager($torneoId, (int) $user['id']);
});
