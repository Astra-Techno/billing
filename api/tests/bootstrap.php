<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Stub global helpers that depend on app bootstrap (DB, config, etc.)
// These let Query::__toString() and other pure methods run without the full app.

if (!function_exists('debugMode')) {
    function debugMode(): bool { return false; }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed { return $default; }
}
