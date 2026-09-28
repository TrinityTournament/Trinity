<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Detalle de una noticia publicada
//  GET ?id=123
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Services\NewsService;

header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');

    $id = (int) Request::query('id', '0');
    return (new NewsService())->getPublished($id);
});
