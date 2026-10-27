<?php

require_once __DIR__ . '/../../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\GameAccounts\FreeFireService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('GET');
    $user = Auth::requireLogin();
    return (new FreeFireService())->getAccount((int) $user['id']);
});
