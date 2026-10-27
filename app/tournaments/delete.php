<?php

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
    $torneoId = (int) Request::query('torneo_id', '0');

    return (new TournamentService())->deleteDraft($torneoId, (int) $user['id']);
});
