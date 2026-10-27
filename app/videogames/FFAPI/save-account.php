<?php

require_once __DIR__ . '/../../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\GameAccounts\FreeFireService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('POST');
    Auth::validateCsrf();
    $user = Auth::requireLogin();

    $body = Request::body();
    return (new FreeFireService())->save(
        (int) $user['id'],
        (string) ($body['uid']    ?? ''),
        (string) ($body['region'] ?? '')
    );
});
