<?php

declare(strict_types=1);
require dirname(__DIR__) . '/api/vendor/autoload.php';

$home = $argv[1] ?? '';
$migration = dirname(__DIR__) . '/api/database/migrations/019_cloud_desktop_licensing.sql';
$documentMigration = dirname(__DIR__) . '/api/database/migrations/020_cloud_desktop_license_document.sql';
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
    $pdo->exec("CREATE TABLE `{$test}`.`business_users` LIKE `{$active}`.`business_users`");
    $pdo->exec("USE `{$test}`");
    $pdo->exec((string)file_get_contents($migration));
    $pdo->exec((string)file_get_contents($documentMigration));
    foreach (['desktop_activation_requests', 'desktop_licenses', 'desktop_license_events'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
        $stmt->execute([$test, $table]);
        if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException("Cloud migration did not create {$table}.");
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = ? AND trigger_name IN (?, ?)');
    $stmt->execute([$test, 'desktop_license_events_no_update', 'desktop_license_events_no_delete']);
    if ((int)$stmt->fetchColumn() !== 2) throw new RuntimeException('Cloud migration did not create both immutable audit triggers.');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='desktop_licenses' AND column_name='license_document'");
    $stmt->execute([$test]);
    if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Cloud migration did not add signed licence storage.');
    $pdo->exec("INSERT INTO users (id,name,email,password,mobile,active,is_super_admin,created_at,updated_at) VALUES (1,'Cloud Admin','admin@example.test','x','',1,1,NOW(),NOW()),(2,'Licence Customer','customer@example.test','x','9876543210',1,0,NOW(),NOW())");
    $pdo->exec("INSERT INTO businesses (id,owner_id,name,slug,business_type,active,created_at,updated_at) VALUES (10,2,'Licence Shop','licence-shop','proprietorship',1,NOW(),NOW())");
    $pdo->exec("INSERT INTO business_users (business_id,user_id,role,accepted_at,active,created_at,updated_at) VALUES (10,2,'owner',NOW(),1,NOW(),NOW())");
    $_ENV['DESKTOP_MODE'] = 'false'; $_ENV['STORAGE_PATH'] = $home . '/cloud-license-test-storage';
    App\Core\DB::connect(['default'=>'mysql','connections'=>['mysql'=>['host'=>'127.0.0.1','port'=>(string)$state['db_port'],'database'=>$test,'username'=>'root','password'=>$state['password'],'charset'=>'utf8mb4']]]);
    $request = App\Base\Task::run('DesktopLicense.request', ['device_id'=>str_repeat('ab',32),'device'=>['pc_name'=>'Test PC','windows_version'=>'Windows Test'],'user'=>['name'=>'Licence Customer','email'=>'customer@example.test','mobile'=>'9876543210'],'company'=>['name'=>'Licence Shop'],'app_version'=>'test']);
    $requestRow = App\Core\DB::selectOne('SELECT id FROM desktop_activation_requests WHERE request_uuid=?', [$request['data']['request_id']]);
    App\Core\Auth::setUser((object)['id'=>1,'is_super_admin'=>1]); App\Core\Auth::setBusinessId(null);
    $listed = App\Base\Task::run('Admin.desktopActivationRequests', []);
    if (($listed['data'][0]->company['name'] ?? '') !== 'Licence Shop') throw new RuntimeException('Admin could not decrypt the activation request.');
    App\Base\Task::run('Admin.approveDesktopActivation', ['request_id'=>$requestRow->id]);
    App\Core\Auth::setUser(null);
    $issued = App\Base\Task::run('DesktopLicense.status', ['request_id'=>$request['data']['request_id'],'request_secret'=>$request['data']['request_secret']]);
    if (($issued['data']['license_status'] ?? '') !== 'active' || empty($issued['data']['license_document'])) throw new RuntimeException('Approved signed licence was not returned.');
    $license = App\Core\DB::selectOne('SELECT id,license_uuid FROM desktop_licenses LIMIT 1');
    App\Core\Auth::setUser((object)['id'=>1,'is_super_admin'=>1]);
    App\Base\Task::run('Admin.setDesktopLicenseStatus', ['license_id'=>$license->id,'status'=>'revoked','reason'=>'Automated test']);
    App\Core\Auth::setUser(null);
    $refreshed = App\Base\Task::run('DesktopLicense.refresh', ['license_id'=>$license->license_uuid,'device_id'=>str_repeat('ab',32)]);
    if (($refreshed['data']['status'] ?? '') !== 'revoked') throw new RuntimeException('Revocation was not returned by refresh.');
    try { $pdo->exec("UPDATE desktop_license_events SET action='tampered' LIMIT 1"); throw new RuntimeException('Immutable audit update unexpectedly succeeded.'); } catch (PDOException $expected) {}
    echo "Cloud migration and desktop exclusion verified.\n";
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$test}`");
}
