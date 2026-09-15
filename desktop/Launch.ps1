param(
    [string]$DataRoot = "$env:LOCALAPPDATA\AI Billing\Data",
    [int]$AppPort = 18765,
    [int]$DatabasePort = 18766,
    [string]$PhpRoot = "$PSScriptRoot\runtime\php",
    [string]$MysqlRoot = "$PSScriptRoot\runtime\mysql",
    [switch]$Headless
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
$DataRoot = [IO.Path]::GetFullPath($DataRoot)
New-Item -ItemType Directory -Force -Path $DataRoot | Out-Null
$env:BILLING_DESKTOP_DATA = $DataRoot
$env:BILLING_DESKTOP_PORT = "$AppPort"
$env:BILLING_DESKTOP_DB_PORT = "$DatabasePort"
$env:BILLING_MYSQL_BIN = "$MysqlRoot\bin"
$php = "$PhpRoot\php.exe"
$mysql = "$MysqlRoot\bin\mysqld.exe"
$phpArgs = @('-c', "$PSScriptRoot\php.ini", '-d', "extension_dir=$PhpRoot\ext")
$url = "http://127.0.0.1:$AppPort"
$appProfile = Join-Path $DataRoot 'desktop-window'
function Find-DesktopEngine {
    foreach ($candidate in @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe", "$env:LOCALAPPDATA\Microsoft\Edge\Application\msedge.exe")) {
        if (Test-Path -LiteralPath $candidate) { return $candidate }
    }
    throw 'Microsoft Edge is required for the desktop window. Install Edge and reopen AI Billing Offline.'
}
function Open-DesktopWindow {
    foreach ($entry in (Get-DesktopProcesses)) {
        $existing = Get-Process -Id $entry.ProcessId -ErrorAction SilentlyContinue
        if ($existing -and $existing.MainWindowHandle -ne 0) {
            if (!('AIBillingWindow' -as [type])) {
                Add-Type @'
using System;
using System.Runtime.InteropServices;
public static class AIBillingWindow {
    [DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr window, int command);
    [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr window);
}
'@
            }
            [AIBillingWindow]::ShowWindow($existing.MainWindowHandle, 9) | Out-Null
            [AIBillingWindow]::SetForegroundWindow($existing.MainWindowHandle) | Out-Null
            return
        }
    }
    $engine = Find-DesktopEngine
    Start-Process -FilePath $engine -ArgumentList @("--app=$url", "--user-data-dir=`"$appProfile`"", '--no-first-run', '--no-default-browser-check', '--disable-background-mode') | Out-Null
}
function Get-DesktopProcesses {
    @(Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" | Where-Object { $_.CommandLine -and $_.CommandLine.Contains($appProfile) -and $_.CommandLine -notmatch '--type=' })
}
$lockName = 'Local\AIBilling-' + ([Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($DataRoot))).Replace('/','_').Replace('\','_')
$mutex = New-Object Threading.Mutex($false, $lockName)
$owned = $false
$webProcess = $null
$dbProcess = $null
function Run-Cli([string]$Command) {
    & $php @phpArgs "$PSScriptRoot\cli.php" $Command
    if ($LASTEXITCODE -ne 0) { throw "Offline setup failed. See $DataRoot\launcher.log" }
}
function Assert-FreePort([int]$Port) {
    $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, $Port)
    try { $listener.Start() } catch { throw "Port $Port is already in use. Close the other AI Billing instance or contact support." }
    finally { $listener.Stop() }
}
function Stop-Database($Process) {
    if (!$Process -or $Process.HasExited) { return }
    $state = Get-Content -LiteralPath "$DataRoot\state.json" -Raw | ConvertFrom-Json
    $clientConfig = "$DataRoot\shutdown.cnf"
    "[client]`nhost=127.0.0.1`nport=$($state.db_port)`nuser=root`npassword=$($state.password)`nprotocol=tcp" | Set-Content -LiteralPath $clientConfig -Encoding ASCII
    try {
        $shutdown = Start-Process -FilePath "$MysqlRoot\bin\mysqladmin.exe" -ArgumentList "--defaults-extra-file=`"$clientConfig`"", 'shutdown' -WindowStyle Hidden -PassThru -RedirectStandardError "$DataRoot\shutdown.log"
        if (!$shutdown.WaitForExit(15000)) { Stop-Process -Id $shutdown.Id }
        if (!$Process.WaitForExit(15000)) { Stop-Process -Id $Process.Id }
    } finally { Remove-Item -LiteralPath $clientConfig -ErrorAction SilentlyContinue }
}
function Save-Processes {
    $entries = @()
    foreach ($process in @($dbProcess, $webProcess)) {
        if ($process -and !$process.HasExited) { $entries += @{ pid=$process.Id; started=$process.StartTime.ToUniversalTime().Ticks.ToString(); path=$process.Path } }
    }
    ConvertTo-Json -InputObject $entries | Set-Content -LiteralPath "$DataRoot\processes.json" -Encoding UTF8
}
try {
    try { $owned = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $owned = $true }
    if (!$owned) { if (!$Headless) { Open-DesktopWindow }; exit }
    if (!$Headless) { Find-DesktopEngine | Out-Null }
    if (!(Test-Path -LiteralPath $php) -or !(Test-Path -LiteralPath $mysql)) { throw 'Bundled PHP/MySQL runtime is missing.' }
    if (Test-Path -LiteralPath "$DataRoot\processes.json") {
        foreach ($entry in (Get-Content -LiteralPath "$DataRoot\processes.json" -Raw | ConvertFrom-Json)) {
            $previous = Get-Process -Id $entry.pid -ErrorAction SilentlyContinue
            if (!$previous -or $previous.StartTime.ToUniversalTime().Ticks.ToString() -ne $entry.started -or $previous.Path -ne $entry.path) { continue }
            $command = (Get-CimInstance Win32_Process -Filter "ProcessId=$($entry.pid)").CommandLine
            if ($previous.ProcessName -eq 'php' -and $command.Contains($PSScriptRoot + '\router.php')) { Stop-Process -Id $previous.Id }
            if ($previous.ProcessName -eq 'mysqld' -and $command.Contains($DataRoot + '\mysql')) { Stop-Database $previous }
        }
    }
    Assert-FreePort $AppPort
    Run-Cli 'prepare'
    $state = Get-Content -LiteralPath "$DataRoot\state.json" -Raw | ConvertFrom-Json
    $DatabasePort = [int]$state.db_port
    Assert-FreePort $DatabasePort
    $dbDir = "$DataRoot\mysql"
    if (!(Test-Path -LiteralPath "$dbDir\mysql")) {
        $initArguments = @('--no-defaults', '--initialize-insecure', "--basedir=`"$MysqlRoot`"", "--datadir=`"$dbDir`"", '--console')
        $init = Start-Process -FilePath $mysql -ArgumentList $initArguments -PassThru -Wait -WindowStyle Hidden -RedirectStandardError "$DataRoot\initialize.log"
        if ($init.ExitCode -ne 0) { throw 'Could not initialize local database. See initialize.log.' }
    }
    $dbArguments = @('--no-defaults', "--basedir=`"$MysqlRoot`"", "--datadir=`"$dbDir`"", "--port=$DatabasePort", '--bind-address=127.0.0.1', '--mysqlx=OFF', '--local-infile=OFF', "--log-error=`"$DataRoot\mysql-error.log`"", '--innodb-buffer-pool-size=64M')
    $dbProcess = Start-Process -FilePath $mysql -ArgumentList $dbArguments -PassThru -WindowStyle Hidden
    Save-Processes
    $ready = $false
    for ($i=0; $i -lt 120; $i++) {
        if ($dbProcess.HasExited) { throw 'Database stopped unexpectedly. See mysql-error.log.' }
        $client = New-Object Net.Sockets.TcpClient
        try { $client.Connect('127.0.0.1', $DatabasePort); $ready = $true; break } catch { Start-Sleep -Milliseconds 500 } finally { $client.Dispose() }
    }
    if (!$ready) { throw 'Local database startup timed out.' }
    Run-Cli 'migrate'
    $serverArguments = @('-c', "`"$PSScriptRoot\php.ini`"", '-d', "`"extension_dir=$PhpRoot\ext`"", '-S', "127.0.0.1:$AppPort", "`"$PSScriptRoot\router.php`"")
    $webProcess = Start-Process -FilePath $php -ArgumentList $serverArguments -WorkingDirectory $PSScriptRoot -PassThru -WindowStyle Hidden -RedirectStandardError "$DataRoot\web-error.log"
    Save-Processes
    if ($Headless) {
        Write-Output "READY $url"
        while (!$webProcess.HasExited -and !$dbProcess.HasExited -and !(Test-Path -LiteralPath "$DataRoot\stop")) { Start-Sleep -Seconds 1 }
    } else {
        # Wait for PHP before opening the app, so first launch never shows a connection error.
        $ready = $false
        for ($i=0; $i -lt 60; $i++) {
            if ($webProcess.HasExited) { throw 'Local billing service stopped. See web-error.log.' }
            try { Invoke-WebRequest -Uri "$url/desktop-info" -UseBasicParsing -TimeoutSec 2 | Out-Null; $ready = $true; break } catch { Start-Sleep -Milliseconds 500 }
        }
        if (!$ready) { throw 'Local billing service startup timed out.' }
        Open-DesktopWindow
        $opened = $false
        for ($i=0; $i -lt 60; $i++) {
            if (@(Get-DesktopProcesses).Count -gt 0) { $opened = $true; break }
            Start-Sleep -Milliseconds 500
        }
        if (!$opened) { throw 'Could not open the AI Billing desktop window.' }
        # A separate profile keeps the app independent of ordinary browser windows.
        while (@(Get-DesktopProcesses).Count -gt 0) {
            if ($webProcess.HasExited -or $dbProcess.HasExited) { throw 'A local service stopped. Close and reopen AI Billing Offline.' }
            Start-Sleep -Seconds 1
        }
    }
} catch {
    $_ | Out-String | Add-Content -LiteralPath "$DataRoot\launcher.log"
    if ($Headless) { Write-Error $_ } else { [Windows.Forms.MessageBox]::Show($_.Exception.Message, 'AI Billing startup failed') | Out-Null }
} finally {
    if ($webProcess -and !$webProcess.HasExited) { Stop-Process -Id $webProcess.Id }
    Stop-Database $dbProcess
    if ($owned) { Remove-Item -LiteralPath "$DataRoot\processes.json" -ErrorAction SilentlyContinue }
    if ($owned) { $mutex.ReleaseMutex() }; $mutex.Dispose()
}
