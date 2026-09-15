$ErrorActionPreference = 'Stop'
$programRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Programs\AI Billing Offline'))
$expectedRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Programs'))
$dataRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'AI Billing\Data'))
if (!$programRoot.StartsWith($expectedRoot + '\', [StringComparison]::OrdinalIgnoreCase) -or (Split-Path $programRoot -Leaf) -ne 'AI Billing Offline') {
    throw 'Unexpected installation path; uninstall stopped.'
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
Remove-Item -LiteralPath $desktopShortcut, $menuShortcut -Force -ErrorAction SilentlyContinue
if (Test-Path -LiteralPath $programRoot) { Remove-Item -LiteralPath $programRoot -Recurse -Force }
[pscustomobject]@{
    ProgramRemoved = !(Test-Path -LiteralPath $programRoot)
    DesktopShortcutRemoved = !(Test-Path -LiteralPath $desktopShortcut)
    MenuShortcutRemoved = !(Test-Path -LiteralPath $menuShortcut)
    DataPreserved = Test-Path -LiteralPath $dataRoot
    DataPath = $dataRoot
} | Format-List
