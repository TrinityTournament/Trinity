<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Consulta de auditoría (solo administrador)
//
//  GET /app/admin/auditoria.php
//      ?tabla=usuarios            (opcional) una de las tablas auditadas
//      &accion=UPDATE             (opcional) INSERT | UPDATE | DELETE
//      &registro_id=12            (opcional) id de la fila afectada
//      &usuario_id=3              (opcional) quién hizo el cambio
//      &desde=2026-10-01          (opcional) AAAA-MM-DD
//      &hasta=2026-10-31          (opcional) AAAA-MM-DD
//      &pagina=1&por_pagina=25    (opcional, máx. 100)
//  GET /app/admin/auditoria.php?resumen=1   → conteo por tabla y acción
//
//  Es de SOLO LECTURA a propósito: no hay forma de modificar ni borrar
//  la auditoría desde la API (ni desde la BD: trinity_app no tiene
//  UPDATE/DELETE sobre esa tabla). Ver docs/auditoria.md.
// ══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';

use Trinity\Core\Auth;
use Trinity\Core\Controller;
use Trinity\Core\Request;
use Trinity\Core\SessionManager;
use Trinity\Services\AuditService;

SessionManager::start();
header('Cache-Control: no-store');

Controller::handle(function () {
    Request::requireMethod('GET');
    Auth::requireRole('admin');

    $service = new AuditService();

    if (Request::query('resumen', '') === '1') {
        return $service->resumen();
    }

    return $service->listar($_GET);
});
