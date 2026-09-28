<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Invitaciones a torneo del usuario logueado
//  GET   → lista las invitaciones pendientes
//  PATCH { id, aceptar: bool } → acepta o rechaza una invitación
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

    if (Request::method() === 'GET') {
        return $service->myInvitations((int) $user['id']);
    }

    if (Request::method() === 'PATCH') {
        Auth::validateCsrf();
        $body = Request::body();
        return $service->resolveInvitation(
            (int) ($body['id'] ?? 0),
            (int) $user['id'],
            (bool) ($body['aceptar'] ?? false)
        );
    }

    throw new ApiException('Método no permitido.', 405);
});
