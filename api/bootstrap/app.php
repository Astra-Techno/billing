<?php

use Slim\Factory\AppFactory;
use App\Core\DB;
use App\Core\Cache;
use App\Middleware\CorsMiddleware;
use App\Middleware\RequestMiddleware;

// ── Config ────────────────────────────────────────────────────────────────────
$appConfig = require __DIR__ . '/../config/app.php';
$dbConfig  = require __DIR__ . '/../config/database.php';

// ── Database ──────────────────────────────────────────────────────────────────
DB::connect($dbConfig);

// ── Cache ─────────────────────────────────────────────────────────────────────
Cache::init(__DIR__ . '/../storage/cache');

// ── Slim App ──────────────────────────────────────────────────────────────────
$app = AppFactory::create();

// Strip the sub-folder prefix so routes match correctly
// e.g. APP_URL=http://localhost/billing/api → basePath=/billing/api
$basePath = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?: '';
if ($basePath) $app->setBasePath(rtrim($basePath, '/'));

$app->addRoutingMiddleware();

// Body parsing must run before RequestMiddleware so getParsedBody() has JSON data
$app->add(new RequestMiddleware());
$app->addBodyParsingMiddleware();

// CORS — runs first
$app->add(new CorsMiddleware());

// Error middleware
$errorMiddleware = $app->addErrorMiddleware(
    $appConfig['debug'],
    true,
    true
);

// Custom JSON error handler
$errorMiddleware->setDefaultErrorHandler(function ($request, \Throwable $e) use ($app) {
    $status  = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 400;
    $payload = ['success' => false, 'message' => $e->getMessage()];

    if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        $payload['file']  = $e->getFile();
        $payload['line']  = $e->getLine();
    }

    $origin   = $_ENV['FRONTEND_URL'] ?? '*';
    $response = $app->getResponseFactory()->createResponse($status);
    $response->getBody()->write(json_encode($payload));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin',      $origin)
        ->withHeader('Access-Control-Allow-Headers',     'Content-Type, Authorization, X-Requested-With, X-Business-ID')
        ->withHeader('Access-Control-Allow-Methods',     'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// ── Routes ────────────────────────────────────────────────────────────────────
require __DIR__ . '/../routes/api.php';

return $app;
