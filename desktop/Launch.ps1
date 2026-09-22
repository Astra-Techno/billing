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
Add-Type -AssemblyName System.Drawing
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
    foreach ($entry in (@(Get-DesktopProcesses) + @(Get-DesktopWindows))) {
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
            [AIBillingWindow]::ShowWindow($existing.MainWindowHandle, 3) | Out-Null
            [AIBillingWindow]::SetForegroundWindow($existing.MainWindowHandle) | Out-Null
            return
        }
    }
    $engine = Find-DesktopEngine
    Start-Process -FilePath $engine -ArgumentList @("--app=$url", "--user-data-dir=`"$appProfile`"", '--start-maximized', '--no-first-run', '--no-default-browser-check', '--disable-background-mode') | Out-Null
}
function Get-DesktopProcesses {
    @(Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" | Where-Object { $_.CommandLine -and $_.CommandLine.Contains($appProfile) -and $_.CommandLine -notmatch '--type=' })
}
function Get-DesktopWindows {
    @(Get-Process msedge -ErrorAction SilentlyContinue | Where-Object {
        $_.MainWindowHandle -ne 0 -and ($_.MainWindowTitle -eq 'AI Billing' -or $_.MainWindowTitle -like 'AI Billing *')
    } | ForEach-Object { [pscustomobject]@{ ProcessId = $_.Id } })
}
function Test-DesktopWindowOpen {
    return @(Get-DesktopProcesses).Count -gt 0 -or @(Get-DesktopWindows).Count -gt 0
}
$splash = $null
function Show-Splash {
    $splash = New-Object Windows.Forms.Form
    $splash.FormBorderStyle = 'None'
    $splash.StartPosition = 'CenterScreen'
    $splash.Size = New-Object Drawing.Size(420, 260)
    $splash.BackColor = [Drawing.Color]::FromArgb(24, 24, 40)
    $splash.TopMost = $true
    $splash.ShowInTaskbar = $false
    # Round corners
    $path = New-Object Drawing.Drawing2D.GraphicsPath
    $r = New-Object Drawing.Rectangle(0, 0, 420, 260)
    $radius = 20
    $path.AddArc($r.X, $r.Y, $radius, $radius, 180, 90)
    $path.AddArc($r.Right - $radius, $r.Y, $radius, $radius, 270, 90)
    $path.AddArc($r.Right - $radius, $r.Bottom - $radius, $radius, $radius, 0, 90)
    $path.AddArc($r.X, $r.Bottom - $radius, $radius, $radius, 90, 90)
    $path.CloseFigure()
    $splash.Region = New-Object Drawing.Region($path)
    # Logo
    $logoPath = "$PSScriptRoot\web\logo.png"
    if (Test-Path -LiteralPath $logoPath) {
        $pic = New-Object Windows.Forms.PictureBox
        $pic.Image = [Drawing.Image]::FromFile($logoPath)
        $pic.SizeMode = 'Zoom'
        $pic.Size = New-Object Drawing.Size(80, 80)
        $pic.Location = New-Object Drawing.Point(170, 30)
        $pic.BackColor = [Drawing.Color]::Transparent
        $splash.Controls.Add($pic)
    }
    # App name
    $title = New-Object Windows.Forms.Label
    $title.Text = 'AI Billing'
    $title.Font = New-Object Drawing.Font('Segoe UI', 20, [Drawing.FontStyle]::Bold)
    $title.ForeColor = [Drawing.Color]::White
    $title.AutoSize = $false
    $title.Size = New-Object Drawing.Size(420, 40)
    $title.Location = New-Object Drawing.Point(0, 120)
    $title.TextAlign = 'MiddleCenter'
    $title.BackColor = [Drawing.Color]::Transparent
    $splash.Controls.Add($title)
    # Status text
    $status = New-Object Windows.Forms.Label
    $status.Name = 'StatusLabel'
    $status.Text = 'Starting...'
    $status.Font = New-Object Drawing.Font('Segoe UI', 10)
    $status.ForeColor = [Drawing.Color]::FromArgb(160, 160, 180)
    $status.AutoSize = $false
    $status.Size = New-Object Drawing.Size(420, 25)
    $status.Location = New-Object Drawing.Point(0, 165)
    $status.TextAlign = 'MiddleCenter'
    $status.BackColor = [Drawing.Color]::Transparent
    $splash.Controls.Add($status)
    # Progress bar
    $bar = New-Object Windows.Forms.ProgressBar
    $bar.Name = 'ProgressBar'
    $bar.Style = 'Marquee'
    $bar.MarqueeAnimationSpeed = 30
    $bar.Size = New-Object Drawing.Size(300, 4)
    $bar.Location = New-Object Drawing.Point(60, 210)
    $splash.Controls.Add($bar)
    # Edition label
    $edition = New-Object Windows.Forms.Label
    $edition.Text = 'Offline Edition'
    $edition.Font = New-Object Drawing.Font('Segoe UI', 8)
    $edition.ForeColor = [Drawing.Color]::FromArgb(100, 100, 120)
    $edition.AutoSize = $false
    $edition.Size = New-Object Drawing.Size(420, 20)
    $edition.Location = New-Object Drawing.Point(0, 232)
    $edition.TextAlign = 'MiddleCenter'
    $edition.BackColor = [Drawing.Color]::Transparent
    $splash.Controls.Add($edition)
    $splash.Show()
    $splash.Refresh()
    return $splash
}
function Update-Splash([Windows.Forms.Form]$form, [string]$text) {
    if (!$form -or $form.IsDisposed) { return }
    $label = $form.Controls.Find('StatusLabel', $false)
    if ($label.Count -gt 0) { $label[0].Text = $text; $form.Refresh() }
}
function Close-Splash([Windows.Forms.Form]$form) {
    if (!$form -or $form.IsDisposed) { return }
    foreach ($ctrl in $form.Controls) {
        if ($ctrl -is [Windows.Forms.PictureBox] -and $ctrl.Image) { $ctrl.Image.Dispose() }
    }
    $form.Close(); $form.Dispose()
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
    if (!$Headless) { Find-DesktopEngine | Out-Null; $splash = Show-Splash }
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
    Update-Splash $splash 'Preparing data...'
    Run-Cli 'prepare'
    $state = Get-Content -LiteralPath "$DataRoot\state.json" -Raw | ConvertFrom-Json
    $DatabasePort = [int]$state.db_port
    Assert-FreePort $DatabasePort
    Update-Splash $splash 'Starting database...'
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
    Update-Splash $splash 'Updating database...'
    Run-Cli 'migrate'
    Update-Splash $splash 'Starting billing service...'
    $serverArguments = @('-c', "`"$PSScriptRoot\php.ini`"", '-d', "`"extension_dir=$PhpRoot\ext`"", '-S', "127.0.0.1:$AppPort", "`"$PSScriptRoot\router.php`"")
    $webProcess = Start-Process -FilePath $php -ArgumentList $serverArguments -WorkingDirectory $PSScriptRoot -PassThru -WindowStyle Hidden -RedirectStandardError "$DataRoot\web-error.log"
    Save-Processes
    if ($Headless) {
        Write-Output "READY $url"
        while (!$webProcess.HasExited -and !$dbProcess.HasExited -and !(Test-Path -LiteralPath "$DataRoot\stop")) { Start-Sleep -Seconds 1 }
    } else {
        # Wait for PHP before opening the app, so first launch never shows a connection error.
        Update-Splash $splash 'Almost ready...'
        $ready = $false
        for ($i=0; $i -lt 60; $i++) {
            if ($webProcess.HasExited) { throw 'Local billing service stopped. See web-error.log.' }
            try { Invoke-WebRequest -Uri "$url/desktop-info" -UseBasicParsing -TimeoutSec 2 | Out-Null; $ready = $true; break } catch { Start-Sleep -Milliseconds 500 }
        }
        if (!$ready) { throw 'Local billing service startup timed out.' }
        Update-Splash $splash 'Opening AI Billing...'
        Open-DesktopWindow
        $opened = $false
        for ($i=0; $i -lt 60; $i++) {
            if (Test-DesktopWindowOpen) { $opened = $true; break }
            Start-Sleep -Milliseconds 500
        }
        if (!$opened) { throw 'Could not open the AI Billing desktop window.' }
        Close-Splash $splash; $splash = $null
        # Edge may create the app window after processing --start-maximized; maximize the
        # actual top-level window once its native handle is ready.
        for ($i=0; $i -lt 20; $i++) {
            $window = (@(Get-DesktopProcesses) + @(Get-DesktopWindows)) | ForEach-Object { Get-Process -Id $_.ProcessId -ErrorAction SilentlyContinue } | Where-Object MainWindowHandle -ne 0 | Select-Object -First 1
            if ($window) { Open-DesktopWindow; break }
            Start-Sleep -Milliseconds 250
        }
        # A separate profile keeps the app independent of ordinary browser windows.
        $missingChecks = 0
        while ($missingChecks -lt 20) {
            if ($webProcess.HasExited -or $dbProcess.HasExited) { throw 'A local service stopped. Close and reopen AI Billing Offline.' }
            if (Test-DesktopWindowOpen) { $missingChecks = 0 } else { $missingChecks++ }
            Start-Sleep -Milliseconds 500
        }
    }
} catch {
    Close-Splash $splash; $splash = $null
    $_ | Out-String | Add-Content -LiteralPath "$DataRoot\launcher.log"
    if ($Headless) { Write-Error $_ } else { [Windows.Forms.MessageBox]::Show($_.Exception.Message, 'AI Billing startup failed') | Out-Null }
} finally {
    Close-Splash $splash
    if ($webProcess -and !$webProcess.HasExited) { Stop-Process -Id $webProcess.Id }
    Stop-Database $dbProcess
    if ($owned) { Remove-Item -LiteralPath "$DataRoot\processes.json" -ErrorAction SilentlyContinue }
    if ($owned) { $mutex.ReleaseMutex() }; $mutex.Dispose()
}
