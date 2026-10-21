<?php

// Router del servidor embebido de PHP (solo desarrollo, sin Docker).
// Uso, desde la RAÍZ del proyecto:
//     php -S localhost:8000 -t Trinity-page scripts/server/router.php
$raiz  = dirname(__DIR__, 2);          // raíz del proyecto
$front = $raiz . '/Trinity-page';      // DocumentRoot

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $front . $uri;

// ── Puente hacia app/ (carpeta hermana de Trinity-page/) ──
if ($uri === '/app' || str_starts_with($uri, '/app/')) {
    $appDir      = realpath($raiz . '/app');
    $appTargetRaw = $appDir . substr($uri, strlen('/app'));
    // realpath también resuelve "../" -> evita escapar de app/ con
    // una URL tipo /app/../../../etc/passa.php
    $appTarget   = realpath($appTargetRaw);

    $esValido = $appDir !== false
        && $appTarget !== false
        && str_starts_with($appTarget, $appDir . DIRECTORY_SEPARATOR)
        && substr($appTarget, -4) === '.php';

    // No servir src/ directo (clases internas, no son endpoints).
    if ($esValido && str_starts_with($appTarget, $appDir . '/src/')) {
        http_response_code(403);
        return true;
    }

    if ($esValido && is_file($appTarget)) {
        chdir(dirname($appTarget));
        require $appTarget;
        return true;
    }

    http_response_code(404);
    return true;
}

// Archivo real en disco (asset, .php, .css, .js, etc.) -> que lo
// sirva el propio servidor embebido, tal cual haría con cualquier
// archivo estático o script PHP.
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Raíz del sitio.
if ($uri === '/') {
    readfile($front . '/index.html');
    return true;
}

// Pedido a una carpeta que sí existe pero sin archivo -> también 404,
// no tiene sentido listar directorios.
http_response_code(404);
readfile($front . '/pages/errors/404.html');
return true;