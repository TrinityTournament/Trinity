<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Listado público de noticias publicadas
//  GET ?categoria=torneos  (opcional)
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Services\NewsService;

header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');

    $categoria = Request::query('categoria', '');
    return (new NewsService())->listPublished($categoria !== '' ? $categoria : null);
});
