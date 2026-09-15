param([switch]$Silent, [switch]$DeleteData)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
$programRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Programs\AI Billing Offline'))
$expectedRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Programs'))
$dataRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'AI Billing\Data'))
if (!$programRoot.StartsWith($expectedRoot + '\', [StringComparison]::OrdinalIgnoreCase) -or (Split-Path $programRoot -Leaf) -ne 'AI Billing Offline') {
    throw 'Unexpected installation path; uninstall stopped.'
}
if (!$Silent) {
    $choice = [Windows.Forms.MessageBox]::Show(
        "Uninstall AI Billing Offline?`n`nYES: remove the app and KEEP billing data for a future reinstall.`nNO: remove the app and PERMANENTLY DELETE all local billing data and backups.`nCANCEL: do nothing.",
        'Uninstall AI Billing Offline',
        [Windows.Forms.MessageBoxButtons]::YesNoCancel,
        [Windows.Forms.MessageBoxIcon]::Warning
    )
    if ($choice -eq [Windows.Forms.DialogResult]::Cancel) { exit 0 }
    $DeleteData = $choice -eq [Windows.Forms.DialogResult]::No
}
$owned = @(Get-CimInstance Win32_Process | Where-Object {
    $_.ProcessId -ne $PID -and $_.CommandLine -and (
        $_.CommandLine.Contains($programRoot) -or
        $_.CommandLine.Contains($dataRoot + '\desktop-window') -or
        ($_.Name -eq 'mysqld.exe' -and $_.CommandLine.Contains($dataRoot + '\mysql'))
    )
})
$mysqlOwned = @($owned | Where-Object Name -eq 'mysqld.exe')
if ($mysqlOwned.Count -gt 0 -and (Test-Path -LiteralPath "$dataRoot\state.json")) {
    $mysqlAdmin = Get-ChildItem -LiteralPath $programRoot -Filter mysqladmin.exe -File -Recurse | Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
    if ($mysqlAdmin) {
        $state = Get-Content -LiteralPath "$dataRoot\state.json" -Raw | ConvertFrom-Json
        $cnf = Join-Path $env:TEMP ('ai-billing-uninstall-' + [guid]::NewGuid().ToString('N') + '.cnf')
        try {
            @('[client]', 'host=127.0.0.1', "port=$($state.db_port)", 'user=root', "password=$($state.password)", 'protocol=tcp') | Set-Content -LiteralPath $cnf -Encoding ASCII
            $shutdown = Start-Process -FilePath $mysqlAdmin -ArgumentList ('--defaults-extra-file="' + $cnf + '"'), 'shutdown' -WindowStyle Hidden -PassThru -Wait
            if ($shutdown.ExitCode -ne 0) { throw 'Database shutdown failed.' }
        } finally { Remove-Item -LiteralPath $cnf -Force -ErrorAction SilentlyContinue }
    }
}
foreach ($entry in ($owned | Where-Object Name -ne 'mysqld.exe')) {
    try { $process = Get-Process -Id $entry.ProcessId -ErrorAction Stop; $process.Kill(); $process.WaitForExit(10000) | Out-Null } catch {}
}
foreach ($entry in $mysqlOwned) {
    $process = Get-Process -Id $entry.ProcessId -ErrorAction SilentlyContinue
    if ($process -and !$process.WaitForExit(15000)) { $process.Kill() }
}
$desktopShortcut = Join-Path ([Environment]::GetFolderPath('Desktop')) 'AI Billing Offline.lnk'
$menuShortcut = Join-Path ([Environment]::GetFolderPath('Programs')) 'AI Billing Offline.lnk'
$uninstallShortcut = Join-Path ([Environment]::GetFolderPath('Programs')) 'Uninstall AI Billing Offline.lnk'
Remove-Item -LiteralPath $desktopShortcut, $menuShortcut, $uninstallShortcut -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\AI Billing Offline' -Recurse -Force -ErrorAction SilentlyContinue
if ($DeleteData -and (Test-Path -LiteralPath $dataRoot)) {
    $expectedDataParent = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'AI Billing'))
    if (!$dataRoot.StartsWith($expectedDataParent + '\', [StringComparison]::OrdinalIgnoreCase) -or (Split-Path $dataRoot -Leaf) -ne 'Data') { throw 'Unexpected data path; data deletion stopped.' }
    Remove-Item -LiteralPath $dataRoot -Recurse -Force
}
if (Test-Path -LiteralPath $programRoot) { Remove-Item -LiteralPath $programRoot -Recurse -Force }
[pscustomobject]@{
    ProgramRemoved = !(Test-Path -LiteralPath $programRoot)
    DesktopShortcutRemoved = !(Test-Path -LiteralPath $desktopShortcut)
    MenuShortcutRemoved = !(Test-Path -LiteralPath $menuShortcut)
    DataPreserved = Test-Path -LiteralPath $dataRoot
    DataPath = $dataRoot
} | Format-List
if (!$Silent) { [Windows.Forms.MessageBox]::Show('AI Billing Offline was uninstalled successfully.', 'AI Billing Offline') | Out-Null }
