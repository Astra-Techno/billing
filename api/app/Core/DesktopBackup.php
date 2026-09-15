<?php
namespace App\Core;

use ZipArchive;
use RuntimeException;

class DesktopBackup
{
    public const VERSION = 1;

    public static function home(): string
    {
        if (($_ENV['DESKTOP_MODE'] ?? '') !== 'true') throw new RuntimeException('Available only in the offline edition.', 404);
        return $_ENV['DESKTOP_DATA'];
    }

    public static function state(): array
    {
        return json_decode(file_get_contents(self::home() . '/state.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function saveState(array $state): void
    {
        $path = self::home() . '/state.json';
        $temp = $path . '.new';
        if (file_put_contents($temp, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX) === false || !rename($temp, $path))
            throw new RuntimeException('Could not save installation state.');
    }

    public static function client(string $tool, array $args, ?string $input = null, ?string $output = null, ?array $credentials = null): void
    {
        $state = self::state();
        $config = tempnam(self::home(), 'client-');
        $username = $credentials['user'] ?? 'root'; $password = $credentials['password'] ?? $state['password'];
        file_put_contents($config, "[client]\nhost=127.0.0.1\nport={$state['db_port']}\nuser=$username\npassword=$password\nprotocol=tcp\n");
        $exe = getenv('BILLING_MYSQL_BIN') . '/' . $tool . '.exe';
        $error = tempnam(self::home(), 'error-');
        try {
            $process = proc_open(array_merge([$exe, '--defaults-extra-file=' . $config], $args), [
                0 => ['file', $input ?? 'NUL', 'r'], 1 => ['file', $output ?? 'NUL', 'w'], 2 => ['file', $error, 'w'],
            ], $pipes, null, null, ['bypass_shell' => true, 'create_no_window' => true]);
            if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Database operation failed. ' . substr(file_get_contents($error), 0, 1000));
        } finally { @unlink($config); @unlink($error); }
    }

    public static function create(string $kind = 'manual'): string
    {
        $home = self::home(); $state = self::state();
        if (!is_dir($home . '/backups')) mkdir($home . '/backups', 0700, true);
        $name = 'billing-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.aibackup';
        $destination = $home . '/backups/' . $name;
        $sql = tempnam($home, 'dump-'); $zip = new ZipArchive();
        try {
            self::client('mysqldump', ['--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--hex-blob', '--default-character-set=utf8mb4', $state['database']], null, $sql);
            if ($zip->open($destination . '.partial', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create backup.');
            $files = ['database.sql' => hash_file('sha256', $sql)];
            $zip->addFile($sql, 'database.sql');
            $root = $_ENV['STORAGE_PATH'];
            if (is_dir($root)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if (!$file->isFile() || $file->isLink()) continue;
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                    if (str_starts_with($relative, 'cache/')) continue;
                    $entry = 'uploads/' . $relative;
                    $zip->addFile($file->getPathname(), $entry); $files[$entry] = hash_file('sha256', $file->getPathname());
                }
            }
            $zip->addFromString('manifest.json', json_encode(['format'=>'ai-billing-backup', 'version'=>self::VERSION, 'created_at'=>date(DATE_ATOM), 'kind'=>$kind, 'files'=>$files], JSON_PRETTY_PRINT));
            if (!$zip->close()) throw new RuntimeException('Could not finish backup. Check free disk space.');
            $verify = new ZipArchive();
            if ($verify->open($destination . '.partial') !== true) throw new RuntimeException('Cannot verify backup.');
            try { self::validate($verify); } finally { $verify->close(); }
            if (!rename($destination . '.partial', $destination)) throw new RuntimeException('Could not publish backup.');
            return $name;
        } finally { @unlink($sql); if (is_file($destination . '.partial')) @unlink($destination . '.partial'); }
    }

    public static function daily(): void
    {
        if (!(int)DB::selectOne('SELECT COUNT(*) AS total FROM businesses')->total) return;
        $today = glob(self::home() . '/backups/billing-' . date('Ymd') . '-*.aibackup');
        if (!$today) self::create('automatic');
    }

    public static function validate(ZipArchive $zip): array
    {
        if ($zip->numFiles > 20000) throw new RuntimeException('Too many files in backup.', 422);
        $raw = $zip->getFromName('manifest.json', 1048576);
        $manifest = $raw ? json_decode($raw, true) : null;
        if (($manifest['format'] ?? '') !== 'ai-billing-backup' || ($manifest['version'] ?? 0) !== self::VERSION || !isset($manifest['files']['database.sql']))
            throw new RuntimeException('Unsupported or incomplete backup.', 422);
        $total = 0; $seen = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i); $name = $stat['name'];
            if (isset($seen[$name])) throw new RuntimeException('Duplicate backup entry.', 422);
            $seen[$name] = true;
            if (!preg_match('#^(manifest\.json|database\.sql|uploads/[a-zA-Z0-9_./-]+)$#D', $name) || str_contains($name, '..') || str_ends_with($name, '/'))
                throw new RuntimeException('Unsafe backup path.', 422);
            $zip->getExternalAttributesIndex($i, $opsys, $attributes);
            if ((($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('Links are not allowed in backups.', 422);
            $total += $stat['size'];
            if ($total > 2 * 1024 * 1024 * 1024) throw new RuntimeException('Backup exceeds the 2 GB restore limit.', 422);
            if ($name === 'manifest.json') continue;
            if (!isset($manifest['files'][$name])) throw new RuntimeException('Unlisted backup file.', 422);
            $stream = $zip->getStream($name); $hash = hash_init('sha256');
            hash_update_stream($hash, $stream); fclose($stream);
            if (!hash_equals($manifest['files'][$name], hash_final($hash))) throw new RuntimeException('Backup checksum failed.', 422);
        }
        if (count($seen) !== count($manifest['files']) + 1) throw new RuntimeException('Backup files are missing.', 422);
        return $manifest;
    }

    public static function restore(string $archive): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Cannot read backup.', 422);
        try {
            $manifest = self::validate($zip);
            // Preserve the current installation before touching any imported data.
            self::create('before-restore');
            $state = self::state(); $suffix = bin2hex(random_bytes(6));
            $database = 'billing_restore_' . $suffix; $storage = 'storage-' . $suffix;
            $stage = self::home() . '/' . $storage; mkdir($stage, 0700, true);
            $sql = $stage . '/database.sql';
            foreach ($manifest['files'] as $entry => $hash) {
                $target = $entry === 'database.sql' ? $sql : $stage . '/' . substr($entry, 8);
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
                $in = $zip->getStream($entry); $out = fopen($target, 'wb');
                if (!$in || !$out || stream_copy_to_stream($in, $out) === false) throw new RuntimeException('Could not stage backup.');
                fclose($in); fclose($out);
            }
            $pdo = DB::getPdo();
            $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            // An archive cannot execute SQL against the live database or access server files.
            $restoreUser = 'restore_' . $suffix; $restorePassword = bin2hex(random_bytes(24));
            $pdo->exec("CREATE USER '$restoreUser'@'localhost' IDENTIFIED BY " . $pdo->quote($restorePassword));
            try {
                $pdo->exec("GRANT ALL PRIVILEGES ON `$database`.* TO '$restoreUser'@'localhost'");
                self::client('mysql', ['--binary-mode', '--default-character-set=utf8mb4', $database], $sql, null, ['user'=>$restoreUser,'password'=>$restorePassword]);
            } finally { $pdo->exec("DROP USER IF EXISTS '$restoreUser'@'localhost'"); }
            $check = new \PDO("mysql:host=127.0.0.1;port={$state['db_port']};dbname=$database;charset=utf8mb4", 'root', $state['password'], [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
            foreach (['users','businesses','invoices','invoice_items','payments','_migrations'] as $table) $check->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            if ((int)$check->query('SELECT COUNT(*) FROM businesses')->fetchColumn() !== 1) throw new RuntimeException('This edition requires a single-business backup.', 422);
            $known = array_map('basename', glob(dirname(__DIR__, 2) . '/database/migrations/*.sql'));
            $applied = $check->query('SELECT filename FROM _migrations')->fetchAll(\PDO::FETCH_COLUMN);
            if (array_diff($applied, $known) || array_diff($known, $applied)) throw new RuntimeException('Backup schema does not match this app version. Restore with its matching installer.', 422);
            if (!(int)$check->query("SELECT COUNT(*) FROM business_users WHERE role = 'owner' AND active = 1")->fetchColumn()) throw new RuntimeException('Backup has no active business owner.', 422);
            $check->exec('UPDATE users SET is_super_admin = 0');
            $check->exec('DELETE FROM personal_access_tokens');
            // All imported upload URLs must continue to resolve on this PC.
            $check->exec("UPDATE businesses SET logo = CONCAT('/api/storage/logos/', SUBSTRING_INDEX(logo, '/', -1)) WHERE logo LIKE '%/storage/logos/%'");
            @unlink($sql);
            $state['previous'] = ['database'=>$state['database'], 'storage'=>$state['storage']];
            $state['database'] = $database; $state['storage'] = $storage;
            self::saveState($state);
        } finally { $zip->close(); }
    }
}
