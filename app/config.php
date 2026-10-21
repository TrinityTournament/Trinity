<?php
// ══════════════════════════════════════════════════════════
//  TRINITY — Bootstrap de la API
//
//  1. Registra el autoloader de las clases propias (namespace
//     Trinity\...) que viven en src/ (dentro de esta misma carpeta).
//  2. Carga variables de entorno desde .env (vlucas/phpdotenv),
//     con fallback a variables ya presentes en el entorno
//     (Apache SetEnv / Docker env_file).
//
//  Todo endpoint bajo app/ debe empezar con:
//      require_once __DIR__ . '/../config.php';   (o la ruta
//      relativa que corresponda según su profundidad)
//
//  UBICACIÓN: app/ y Trinity-page/ son carpetas hermanas en la raíz
//  del proyecto. composer.json, vendor/ y el .env (solo sin Docker)
//  también viven en esa raíz, un nivel arriba de esta carpeta: por
//  eso las dos rutas de abajo apuntan a  __DIR__ . '/..'.
// ══════════════════════════════════════════════════════════

// ── AUTOLOADER PROPIO (namespace Trinity\...) ─────────────
spl_autoload_register(function (string $class): void {
    $prefix  = 'Trinity\\';
    $baseDir = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// ── AUTOLOADER DE COMPOSER (Dotenv, etc.) ─────────────────
// vendor/ vive en la raíz del proyecto (hermana de app/), no acá.
require_once __DIR__ . '/../vendor/autoload.php';

// ── CARGAR .env ────────────────────────────────────────────
// El .env real (solo sin Docker) está en la raíz del proyecto.
// En Docker las variables llegan por `environment:` del compose y ya
// están presentes; createImmutable + safeLoad no las pisa.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
