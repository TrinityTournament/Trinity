<?php
// TRINITY — Free Fire: datos del jugador para la página
// GET                         → cuenta vinculada del usuario logueado
// GET ?uid=219110511&region=BR → esa cuenta puntual
// Respuesta: { perfil: { nombre, uid, region, nivel, rangoMax, rangoMaxEscuadras, clan } }
require_once __DIR__ . '/../../config.php';

use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\GameAccounts\FreeFireService;

SessionManager::start();
header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');

    $user   = SessionManager::user();
    $userId = isset($user['id']) ? (int) $user['id'] : null;

    return (new FreeFireService())->stats(
        $userId,
        trim((string) Request::query('uid', '')),
        trim((string) Request::query('region', ''))
    );
});
