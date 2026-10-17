<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = dirname(__DIR__, 3);
$requested = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($requested)) {
    return false;
}

if (str_starts_with($path, '/billing/api')) {
    require $root . '/billing/api/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
