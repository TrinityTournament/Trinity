<?php

require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TournamentService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('POST');
    Auth::validateCsrf();

    $user = Auth::requireLogin();
    $body = Request::body();

    return (new TournamentService())->update((int) ($body['torneo_id'] ?? 0), (int) $user['id'], $body);
});
