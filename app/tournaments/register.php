<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Inscripción a torneo
//  POST   { torneo_id }  → inscribe al usuario logueado
//  DELETE ?torneo_id=123 → cancela su inscripción
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\ApiException;
use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TournamentService;

SessionManager::start();

Controller::handle(function () {
    $user = Auth::requireLogin();
    $service = new TournamentService();

    if (Request::method() === 'POST') {
        Auth::validateCsrf();
        $body = Request::body();
        return $service->register((int) ($body['torneo_id'] ?? 0), (int) $user['id']);
    }

    if (Request::method() === 'DELETE') {
        Auth::validateCsrf();
        $torneoId = (int) Request::query('torneo_id', '0');
        return $service->unregister($torneoId, (int) $user['id']);
    }

    throw new ApiException('Método no permitido.', 405);
});
