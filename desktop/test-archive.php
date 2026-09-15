<?php
// Test-only archive mutations. Never shipped in the customer package.
copy($argv[1], $argv[2]);
$zip = new ZipArchive(); $zip->open($argv[2]);
$mode = $argv[3];
if ($mode === 'path') $zip->addFromString('uploads/../../escape.txt', 'unsafe');
elseif ($mode === 'checksum') $zip->addFromString('database.sql', 'changed');
elseif ($mode === 'sql') {
    $sql = 'THIS IS INVALID SQL;';
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    $manifest['files']['database.sql'] = hash('sha256', $sql);
    $zip->addFromString('database.sql', $sql);
    $zip->addFromString('manifest.json', json_encode($manifest));
} elseif ($mode === 'scope') {
    $sql = 'DELETE FROM `billing_offline`.`clients`;';
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    $manifest['files']['database.sql'] = hash('sha256', $sql);
    $zip->addFromString('database.sql', $sql);
    $zip->addFromString('manifest.json', json_encode($manifest));
}
$zip->close();
