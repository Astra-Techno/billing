<?php

declare(strict_types=1);

$home = $argv[1] ?? '';
$migration = dirname(__DIR__) . '/api/database/migrations/019_cloud_desktop_licensing.sql';
$state = json_decode((string)file_get_contents($home . '/state.json'), true, 512, JSON_THROW_ON_ERROR);
$active = $state['database'];
$test = 'billing_cloud_license_test_' . bin2hex(random_bytes(4));
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=' . $state['db_port'] . ';charset=utf8mb4',
    'root',
    $state['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

foreach (['desktop_activation_requests', 'desktop_licenses', 'desktop_license_events'] as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([$active, $table]);
    if ((int)$stmt->fetchColumn() !== 0) throw new RuntimeException("Cloud table {$table} was created in the desktop database.");
}

try {
    $pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE `{$test}`.`users` LIKE `{$active}`.`users`");
    $pdo->exec("CREATE TABLE `{$test}`.`businesses` LIKE `{$active}`.`businesses`");
    $pdo->exec("USE `{$test}`");
    $pdo->exec((string)file_get_contents($migration));
    foreach (['desktop_activation_requests', 'desktop_licenses', 'desktop_license_events'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
        $stmt->execute([$test, $table]);
        if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException("Cloud migration did not create {$table}.");
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = ? AND trigger_name IN (?, ?)');
    $stmt->execute([$test, 'desktop_license_events_no_update', 'desktop_license_events_no_delete']);
    if ((int)$stmt->fetchColumn() !== 2) throw new RuntimeException('Cloud migration did not create both immutable audit triggers.');
    echo "Cloud migration and desktop exclusion verified.\n";
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$test}`");
}
