<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Enviar mensaje de contacto
//  POST { nombre, contacto, asunto, mensaje }
//  Público: no requiere sesión iniciada.
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\ContactService;

SessionManager::start();

Controller::handle(function () {
    Request::requireMethod('POST');

    $body = Request::body();
    return (new ContactService())->send($body);
});
