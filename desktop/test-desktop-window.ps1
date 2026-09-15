param([string]$PackageRoot, [int]$AppPort = 23865, [int]$DatabasePort = 23866, [string]$TestDataRoot)
$ErrorActionPreference = 'Stop'
$app = if ($PackageRoot) { Join-Path $PackageRoot 'desktop' } else { $PSScriptRoot }
$phpRoot = if ($PackageRoot) { "$app\runtime\php" } else { 'C:\laragon1\bin\php\php-8.3.16-Win32-vs16-x64' }
$mysqlRoot = if ($PackageRoot) { "$app\runtime\mysql" } else { 'C:\laragon1\bin\mysql\mysql-8.4.3-winx64' }
$data = Join-Path $PSScriptRoot ('.test-data-window-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '\PC Data')
if ($TestDataRoot) {
    $data = [IO.Path]::GetFullPath($TestDataRoot)
    if (!$data.StartsWith($PSScriptRoot + '\.test-data-window-', [StringComparison]::OrdinalIgnoreCase)) { throw 'Reuse is restricted to isolated desktop test directories.' }
}
New-Item -ItemType Directory -Force -Path $data | Out-Null
$arguments = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-WindowStyle', 'Hidden', '-File', "`"$app\Launch.ps1`"", '-DataRoot', "`"$data`"", '-AppPort', "$AppPort", '-DatabasePort', "$DatabasePort", '-PhpRoot', "`"$phpRoot`"", '-MysqlRoot', "`"$mysqlRoot`"")
$launcher = Start-Process powershell.exe -ArgumentList $arguments -WindowStyle Hidden -PassThru
try {
    $window = $null
    for ($i=0; $i -lt 180; $i++) {
        if (Test-Path -LiteralPath "$data\launcher.log") { throw (Get-Content -LiteralPath "$data\launcher.log" -Raw) }; if ($launcher.HasExited) { throw "Launcher exited. See $data\launcher.log" }
        $engines = @(Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" | Where-Object { $_.CommandLine -and $_.CommandLine.Contains($data + '\desktop-window') })
        foreach ($engine in $engines) {
            $process = Get-Process -Id $engine.ProcessId -ErrorAction SilentlyContinue
            if ($process -and $process.MainWindowHandle -ne 0) { $window = $process; break }
        }
        if ($window) { break }
        Start-Sleep -Seconds 1
    }
    if (!$window) { throw 'No desktop window appeared.' }
    if (!($engines | Where-Object { $_.CommandLine.Contains("--app=http://127.0.0.1:$AppPort") })) { throw 'Desktop app mode was not used.' }
    Add-Type -AssemblyName UIAutomationClient
    Add-Type -AssemblyName UIAutomationTypes
    $element = [Windows.Automation.AutomationElement]::FromHandle($window.MainWindowHandle)
    foreach ($name in @('Address and search bar', 'Search or enter web address')) {
        $condition = New-Object Windows.Automation.PropertyCondition([Windows.Automation.AutomationElement]::NameProperty, $name)
        $bar = $element.FindFirst([Windows.Automation.TreeScope]::Descendants, $condition)
        if ($bar -and !$bar.Current.IsOffscreen -and !$bar.Current.BoundingRectangle.IsEmpty) { throw 'Browser address bar is visible.' }
    }
    $response = Invoke-WebRequest "http://127.0.0.1:$AppPort/desktop-info" -UseBasicParsing
    if ($response.StatusCode -ne 200) { throw 'Local service did not start.' }
    $second = Start-Process powershell.exe -ArgumentList $arguments -WindowStyle Hidden -PassThru
    if (!$second.WaitForExit(15000)) { throw 'Second launch did not reuse the running application.' }
    Invoke-WebRequest "http://127.0.0.1:$AppPort/desktop-info" -UseBasicParsing | Out-Null
    $condition = New-Object Windows.Automation.PropertyCondition([Windows.Automation.AutomationElement]::ProcessIdProperty, $window.Id)
    $windows = [Windows.Automation.AutomationElement]::RootElement.FindAll([Windows.Automation.TreeScope]::Children, $condition)
    $closed = $false
    foreach ($candidate in $windows) {
        if ($candidate.Current.IsOffscreen) { continue }
        $pattern = $null
        if ($candidate.TryGetCurrentPattern([Windows.Automation.WindowPattern]::Pattern, [ref]$pattern)) { $pattern.Close(); $closed = $true }
    }
    if (!$closed) { throw 'Could not close the desktop window through Windows UI Automation.' }
    if (!$launcher.WaitForExit(45000)) { throw 'Closing the desktop window did not stop services.' }
    foreach ($port in @($AppPort,$DatabasePort)) {
        $socket = New-Object Net.Sockets.TcpClient
        try { $socket.Connect('127.0.0.1', $port); throw "Service still listening on $port" } catch [Net.Sockets.SocketException] { } finally { $socket.Dispose() }
    }
    Write-Output 'PASS: dedicated desktop window, hidden address bar, repeated shortcut and graceful service shutdown.'
} finally {
    Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" | Where-Object { $_.CommandLine -and $_.CommandLine.Contains($data + '\desktop-window') } | ForEach-Object { (Get-Process -Id $_.ProcessId -ErrorAction SilentlyContinue).Kill() }
    if (!$launcher.HasExited -and !$launcher.WaitForExit(45000)) { $launcher.Kill() }
}
