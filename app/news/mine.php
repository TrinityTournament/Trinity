<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Mis noticias / solicitudes de noticia enviadas
//  GET — el organizador ve el estado de lo que mandó.
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\NewsService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('GET');
    $user = Auth::requireRole('admin', 'organizador');

    return (new NewsService())->myRequests((int) $user['id']);
});
