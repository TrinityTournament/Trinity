<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Torneos en los que el usuario logueado participa
//  (no los que organiza — para eso ver get-my-tournaments.php)
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TournamentService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('GET');
    $user = Auth::requireLogin();

    return (new TournamentService())->myParticipations((int) $user['id']);
});
