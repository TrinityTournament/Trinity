<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Generar la siguiente ronda (solo formato suizo)
//  POST { torneo_id }
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\MatchService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('POST');
    Auth::validateCsrf();

    $user = Auth::requireLogin();
    $body = Request::body();

    return (new MatchService())->generateNextRound((int) ($body['torneo_id'] ?? 0), (int) $user['id']);
});
