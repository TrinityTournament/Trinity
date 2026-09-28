<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Calendario / llaves / posiciones de un torneo
//  GET ?torneo_id=123
//  Mismo criterio de visibilidad que detail.php: público salvo que
//  el torneo sea privado, en cuyo caso solo lo ve su organizador.
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\MatchService;

SessionManager::start();
header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');

    $torneoId = (int) Request::query('torneo_id', '0');
    $viewer   = SessionManager::user();
    $viewerId = isset($viewer['id']) ? (int) $viewer['id'] : null;

    return (new MatchService())->getMatchesView($torneoId, $viewerId);
});
