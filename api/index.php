<?php

declare(strict_types=1);

error_reporting(E_ALL);

// Display errors directly in the browser
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Global helpers (debugMode, config, request, etc.)
require __DIR__ . '/app/Helper.php';

// Bootstrap & run
$app = require __DIR__ . '/bootstrap/app.php';
$app->run();
