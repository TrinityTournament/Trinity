<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Detalle de un torneo
//  GET ?id=123
//  No requiere sesión para torneos públicos (buscar.html →
//  detalle.html); si hay sesión, la respuesta incluye flags
//  relativos al usuario (ya_inscripto, es_organizador).
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\TournamentService;

SessionManager::start();
header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');

    $torneoId = (int) Request::query('id', '0');
    $viewer   = SessionManager::user();
    $viewerId = isset($viewer['id']) ? (int) $viewer['id'] : null;

    return (new TournamentService())->getDetail($torneoId, $viewerId);
});
