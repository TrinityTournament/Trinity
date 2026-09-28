<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Cargar el resultado de un partido (solo el organizador)
//  POST { torneo_id, partido_id, resultado1, resultado2 }
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

    $r1 = isset($body['resultado1']) && $body['resultado1'] !== '' ? (int) $body['resultado1'] : null;
    $r2 = isset($body['resultado2']) && $body['resultado2'] !== '' ? (int) $body['resultado2'] : null;

    return (new MatchService())->recordResult(
        (int) ($body['torneo_id'] ?? 0),
        (int) $user['id'],
        (int) ($body['partido_id'] ?? 0),
        $r1,
        $r2
    );
});
