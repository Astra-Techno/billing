<?php
require dirname(__DIR__) . '/api/vendor/autoload.php';
require __DIR__ . '/environment.php';
use App\Core\DB;
use App\Core\DesktopBackup;

try {
    $home = getenv('BILLING_DESKTOP_DATA');
    if (!$home) throw new RuntimeException('Missing data directory.');
    if (!is_dir($home)) mkdir($home, 0700, true);
    if (($argv[1] ?? '') === 'prepare') {
        if (!is_file($home . '/state.json')) {
            file_put_contents($home . '/state.json', json_encode([
                'database'=>'billing_offline', 'storage'=>'storage', 'password'=>bin2hex(random_bytes(24)),
                'db_port'=>(int)(getenv('BILLING_DESKTOP_DB_PORT') ?: 18766), 'secured'=>false,
            ], JSON_PRETTY_PRINT), LOCK_EX);
        }
        exit(0);
    }
    $state = desktopEnvironment();
    $dsn = "mysql:host=127.0.0.1;port={$state['db_port']};charset=utf8mb4";
    if (!$state['secured']) {
        try { $pdo = new PDO($dsn, 'root', $state['password']); }
        catch (PDOException $e) {
            $pdo = new PDO($dsn, 'root', '');
            $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY " . $pdo->quote($state['password']));
        }
        $state['secured'] = true; DesktopBackup::saveState($state);
    }
    $pdo = new PDO($dsn, 'root', $state['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $database = $state['database'];
    if (!preg_match('/^billing_[a-z0-9_]+$/D', $database)) throw new RuntimeException('Invalid database name.');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$database`");
    DB::connect(require dirname(__DIR__) . '/api/config/database.php');
    if (($argv[1] ?? '') === 'migrate') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS _migrations (filename VARCHAR(191) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
        $applied = $pdo->query('SELECT filename FROM _migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(dirname(__DIR__) . '/api/database/migrations/*.sql'); sort($files);
        $pending = array_filter($files, fn($f)=>!in_array(basename($f), $applied, true));
        if ($pending && in_array('001_initial_schema.sql', $applied, true)) DesktopBackup::create('before-update');
        foreach ($pending as $file) {
            $name = basename($file);
            // A desktop install must never contain the shared, seeded platform administrator.
            if ($name !== '010_seed_super_admin.sql') $pdo->exec(file_get_contents($file));
            $pdo->prepare('INSERT INTO _migrations (filename) VALUES (?)')->execute([$name]);
        }
        DesktopBackup::daily();
        echo "Offline database ready.\n";
    } elseif (($argv[1] ?? '') === 'backup') {
        echo DesktopBackup::create() . "\n";
    } elseif (($argv[1] ?? '') === 'restore') {
        DesktopBackup::restore($argv[2] ?? ''); echo "Restored.\n";
    }
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
