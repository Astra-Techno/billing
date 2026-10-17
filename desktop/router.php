<?php
require __DIR__ . '/environment.php';
desktopEnvironment();
$networkMode = $_ENV['DESKTOP_NETWORK_MODE'] ?? 'SinglePc';
$requestHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$requestIp = $_SERVER['REMOTE_ADDR'] ?? '';
$expectedPort = (int)parse_url($_ENV['FRONTEND_URL'], PHP_URL_PORT);
$allowedHosts = ['127.0.0.1:' . $expectedPort, 'localhost:' . $expectedPort];
if ($networkMode === 'MainPc') {
    $allowedHosts[] = strtolower(($_ENV['DESKTOP_NETWORK_HOST'] ?? gethostname()) . ':' . $expectedPort);
    if (!empty($_SERVER['SERVER_ADDR'])) $allowedHosts[] = strtolower($_SERVER['SERVER_ADDR'] . ':' . $expectedPort);
}
$isPrivateClient = in_array($requestIp, ['127.0.0.1','::1'], true)
    || preg_match('/^10\./', $requestIp)
    || preg_match('/^192\.168\./', $requestIp)
    || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $requestIp);
if (!in_array($requestHost, $allowedHosts, true) || ($networkMode === 'SinglePc' && !in_array($requestIp, ['127.0.0.1','::1'], true)) || ($networkMode === 'MainPc' && !$isPrivateClient)) {
    http_response_code(403); exit('This AI Billing service is not available from this address.');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestOrigin = 'http://' . $requestHost;
if ($origin && strtolower($origin) !== $requestOrigin) { http_response_code(403); exit('Origin denied.'); }
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) { http_response_code(400); exit; }
if ($path === '/desktop-config.js') {
    header('Content-Type: application/javascript'); header('Cache-Control: no-store');
    echo 'window.__BILLING_DESKTOP__ = true;window.__BILLING_NETWORK_MODE__=' . json_encode($networkMode) . ';'; return;
}
if ($path === '/desktop-info') {
    require dirname(__DIR__) . '/api/vendor/autoload.php';
    \App\Core\DB::connect(require dirname(__DIR__) . '/api/config/database.php');
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['needs_setup'=>(int)\App\Core\DB::selectOne('SELECT COUNT(*) AS total FROM users')->total === 0,'network_mode'=>$networkMode,'server_name'=>$_ENV['DESKTOP_NETWORK_HOST'] ?? '127.0.0.1','port'=>$expectedPort]); return;
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
    if (preg_match('#^/api/(run-migrate|migrate|entity|shop|invoice/(?:bluetooth-print|[0-9]+/bluetooth-print))#', $path)) { http_response_code(404); return; }
    if (str_starts_with($path, '/api/guest-task/') && !preg_match('#^/api/guest-task/BillingCounter/(requestJoin|pairingStatus|heartbeat)$#', $path)) { http_response_code(404); return; }
    if (preg_match('#^/api/(task/(Admin|Invite)/|task/Business/(inviteMember|removeMember)|(?:list|all|item|count|options|group-list)/(Admin|User)(?:[:/]|$))#', $path)) { http_response_code(404); return; }
    $isPairingCall = preg_match('#^/api/guest-task/BillingCounter/(requestJoin|pairingStatus|heartbeat)$#', $path);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !$isPairingCall && strtolower($origin) !== $requestOrigin) { http_response_code(403); exit('Origin required.'); }
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
