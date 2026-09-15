<?php
namespace App\Task;

use App\Base\Task;
use App\Core\DesktopBackup;

class Desktop extends Task
{
    private function authorize(): void
    {
        DesktopBackup::home(); $this->requireBusiness(); $this->requireRole(['owner', 'admin']);
    }

    public function status(array $input): array
    {
        $this->authorize(); $warning = null;
        try { DesktopBackup::daily(); } catch (\Throwable $e) { $warning = 'Automatic backup failed. Check disk space and create a manual backup.'; }
        $backups = [];
        foreach (glob(DesktopBackup::home() . '/backups/*.aibackup') as $file)
            $backups[] = ['name'=>basename($file), 'size'=>filesize($file), 'created_at'=>date(DATE_ATOM, filemtime($file))];
        usort($backups, fn($a,$b)=>strcmp($b['name'],$a['name']));
        return $this->success(['backups'=>$backups, 'warning'=>$warning]);
    }

    public function backup(array $input): array
    {
        $this->authorize(); return $this->success(['name'=>DesktopBackup::create()], 'Backup created.');
    }

    public function download(array $input): array
    {
        $this->authorize(); $name = $input['name'] ?? '';
        if (!preg_match('/^billing-[a-zA-Z0-9-]+\.aibackup$/D', $name)) $this->fail('Invalid backup.', 422);
        $file = DesktopBackup::home() . '/backups/' . $name;
        if (!is_file($file)) $this->fail('Backup not found.', 404);
        header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Content-Length: '.filesize($file)); readfile($file); exit;
    }

    public function restore(array $input): array
    {
        $this->authorize();
        if (($input['confirmation'] ?? '') !== 'RESTORE') $this->fail('Type RESTORE to confirm replacing current data.', 422);
        $file = $_FILES['backup'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) $this->fail('Backup upload failed. Maximum size is 512 MB.', 422);
        DesktopBackup::restore($file['tmp_name']);
        return $this->success(null, 'Backup restored. Sign in with the account from your backup.');
    }
}
