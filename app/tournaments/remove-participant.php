<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Sacar a un participante (solo antes de iniciar el torneo)
//  DELETE ?torneo_id=123&usuario_id=456
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TournamentService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('DELETE');
    Auth::validateCsrf();

    $user = Auth::requireLogin();

    $torneoId      = (int) Request::query('torneo_id', '0');
    $participantId = (int) Request::query('usuario_id', '0');

    return (new TournamentService())->removeParticipant($torneoId, (int) $user['id'], $participantId);
});
