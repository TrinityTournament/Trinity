<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Ranking de jugadores
//  GET ?disciplina=Clash%20Royale  (opcional — sin filtro = global)
//  Público, no requiere sesión.
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Services\RankingService;

header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');

    $disciplina = Request::query('disciplina', '');
    return (new RankingService())->top($disciplina !== '' ? $disciplina : null);
});
