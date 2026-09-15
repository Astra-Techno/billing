<?php
require __DIR__ . '/environment.php';
desktopEnvironment();
$expectedHost = parse_url($_ENV['FRONTEND_URL'], PHP_URL_HOST) . ':' . parse_url($_ENV['FRONTEND_URL'], PHP_URL_PORT);
if (($_SERVER['HTTP_HOST'] ?? '') !== $expectedHost || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403); exit('Local access only.');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && $origin !== $_ENV['FRONTEND_URL']) { http_response_code(403); exit('Origin denied.'); }
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) { http_response_code(400); exit; }
if ($path === '/desktop-config.js') {
    header('Content-Type: application/javascript'); header('Cache-Control: no-store');
    echo 'window.__BILLING_DESKTOP__ = true;'; return;
}
if ($path === '/desktop-info') {
    require dirname(__DIR__) . '/api/vendor/autoload.php';
    \App\Core\DB::connect(require dirname(__DIR__) . '/api/config/database.php');
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['needs_setup'=>(int)\App\Core\DB::selectOne('SELECT COUNT(*) AS total FROM users')->total === 0]); return;
}
if (str_starts_with($path, '/api/storage/')) {
    $root = realpath($_ENV['STORAGE_PATH']);
    $file = realpath($_ENV['STORAGE_PATH'] . '/' . substr($path, 13));
    if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)
        || !preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $file)) { http_response_code(404); return; }
    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($file));
    header('X-Content-Type-Options: nosniff'); readfile($file); return;
}
if (str_starts_with($path, '/api/')) {
    // The desktop edition does not expose maintenance, public cards or team invitations.
    if (preg_match('#^/api/(run-migrate|migrate|entity|shop|guest-task)#', $path)) { http_response_code(404); return; }
    if (preg_match('#^/api/(task/(Admin|Staff|Invite)/|task/Business/(inviteMember|removeMember)|(?:list|all|item|count|options|group-list)/(Admin|User)(?:[:/]|$))#', $path)) { http_response_code(404); return; }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $origin !== $_ENV['FRONTEND_URL']) { http_response_code(403); exit('Origin required.'); }
    require dirname(__DIR__) . '/api/index.php'; return;
}
$root = realpath(__DIR__ . '/web');
$file = realpath(__DIR__ . '/web' . $path);
if ($file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file) && !str_ends_with($file, '.php')) {
    $types = ['js'=>'application/javascript','css'=>'text/css','svg'=>'image/svg+xml','png'=>'image/png','html'=>'text/html'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file); return;
}
header('Content-Type: text/html'); header('Cache-Control: no-store'); readfile(__DIR__ . '/web/index.html');
