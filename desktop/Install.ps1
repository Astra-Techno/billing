param([Parameter(Mandatory=$true)][string]$Payload)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
$programRoot = "$env:LOCALAPPDATA\Programs\AI Billing Offline"
$release = Join-Path $programRoot ('release-' + (Get-Date -Format 'yyyyMMddHHmmss'))
function Select-InstallMode {
    $form = New-Object Windows.Forms.Form
    $form.Text = 'Choose AI Billing setup'
    $form.StartPosition = 'CenterScreen'
    $form.ClientSize = New-Object Drawing.Size(520, 330)
    $form.FormBorderStyle = 'FixedDialog'; $form.MaximizeBox = $false; $form.MinimizeBox = $false
    $title = New-Object Windows.Forms.Label
    $title.Text = 'How will this computer be used?'; $title.Font = New-Object Drawing.Font('Segoe UI',16,[Drawing.FontStyle]::Bold); $title.AutoSize=$true; $title.Location=New-Object Drawing.Point(25,22); $form.Controls.Add($title)
    $hint = New-Object Windows.Forms.Label
    $hint.Text = 'Choose one option. You can reinstall later to change the mode.'; $hint.AutoSize=$true; $hint.ForeColor=[Drawing.Color]::DimGray; $hint.Location=New-Object Drawing.Point(28,58); $form.Controls.Add($hint)
    $choices = @(
        @{Mode='SinglePc';Title='This PC only';Text='For a shop using one billing computer.'},
        @{Mode='MainPc';Title='Main PC';Text='Stores shared products, stock and bills for all counters.'},
        @{Mode='CounterPc';Title='Billing Counter';Text='Connects this PC to an existing AI Billing Main PC.'}
    )
    $top=92
    foreach($choice in $choices){
        $button=New-Object Windows.Forms.Button; $button.Text="$($choice.Title)`r`n$($choice.Text)"; $button.Tag=$choice.Mode; $button.TextAlign='MiddleLeft'; $button.Padding=New-Object Windows.Forms.Padding(12,0,0,0); $button.Size=New-Object Drawing.Size(465,58); $button.Location=New-Object Drawing.Point(25,$top); $button.Add_Click({$form.Tag=$this.Tag;$form.DialogResult=[Windows.Forms.DialogResult]::OK;$form.Close()}); $form.Controls.Add($button); $top+=68
    }
    if($form.ShowDialog() -ne [Windows.Forms.DialogResult]::OK){throw 'Installation cancelled.'}
    return [string]$form.Tag
}
try {
    if (![Environment]::Is64BitOperatingSystem) { throw 'AI Billing Offline requires 64-bit Windows.' }
    New-Item -ItemType Directory -Force -Path $release | Out-Null
    [IO.Compression.ZipFile]::ExtractToDirectory($Payload, $release)
    $dataRoot = "$env:LOCALAPPDATA\AI Billing\Data"
    New-Item -ItemType Directory -Force -Path $dataRoot | Out-Null
    $modeFile = Join-Path $dataRoot 'network-mode.txt'
    if (Test-Path -LiteralPath $modeFile) {
        $networkMode = (Get-Content -LiteralPath $modeFile -Raw).Trim()
    } else {
        $networkMode = Select-InstallMode
        Set-Content -LiteralPath $modeFile -Value $networkMode -Encoding ASCII
    }
    if ($networkMode -notin @('SinglePc','MainPc','CounterPc')) { throw 'Saved installation mode is invalid.' }
    $vc = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' -ErrorAction SilentlyContinue
    if ($networkMode -ne 'CounterPc' -and (!$vc -or $vc.Installed -ne 1 -or [version]($vc.Version.TrimStart('v')) -lt [version]'14.40.0.0')) {
        $prerequisite = Join-Path $release 'desktop\runtime\vc_redist.x64.exe'
        $runtimeInstall = Start-Process -FilePath $prerequisite -ArgumentList '/install /passive /norestart' -Verb RunAs -PassThru -Wait -WindowStyle Hidden
        if ($runtimeInstall.ExitCode -notin @(0,3010,1638)) { throw 'Microsoft Visual C++ runtime installation failed.' }
    }
    $firewallName = 'AI Billing Main PC (Private LAN)'
    $discoveryFirewallName = 'AI Billing Main PC Discovery (Private LAN)'
    if ($networkMode -eq 'MainPc') {
        $phpPath = Join-Path $release 'desktop\runtime\php\php.exe'
        $firewallCommand = "Get-NetFirewallRule -DisplayName '$firewallName' -ErrorAction SilentlyContinue | Remove-NetFirewallRule; Get-NetFirewallRule -DisplayName '$discoveryFirewallName' -ErrorAction SilentlyContinue | Remove-NetFirewallRule; New-NetFirewallRule -DisplayName '$firewallName' -Direction Inbound -Action Allow -Protocol TCP -LocalPort 18765 -Program '$phpPath' -Profile Private | Out-Null; New-NetFirewallRule -DisplayName '$discoveryFirewallName' -Direction Inbound -Action Allow -Protocol UDP -LocalPort 18764 -Profile Private | Out-Null"
        $firewall = Start-Process -FilePath 'powershell.exe' -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-Command',$firewallCommand -Verb RunAs -PassThru -Wait -WindowStyle Hidden
        if ($firewall.ExitCode -ne 0) { throw 'Could not allow AI Billing on the private shop network.' }
    }
    $shell = New-Object -ComObject WScript.Shell
    foreach ($folder in @([Environment]::GetFolderPath('Desktop'), [Environment]::GetFolderPath('Programs'))) {
        if (!(Test-Path -LiteralPath $folder)) { New-Item -ItemType Directory -Force -Path $folder | Out-Null }
        $shortcutName = if ($networkMode -eq 'CounterPc') { 'AI Billing Counter.lnk' } else { 'AI Billing Offline.lnk' }
        $shortcut = $shell.CreateShortcut((Join-Path $folder $shortcutName))
        $shortcut.TargetPath = "$env:WINDIR\System32\wscript.exe"
        $launcher = if ($networkMode -eq 'CounterPc') { 'desktop\CounterLaunch.vbs' } else { 'desktop\Launch.vbs' }
        $shortcut.Arguments = '"' + (Join-Path $release $launcher) + '"'
        $shortcut.WorkingDirectory = Join-Path $release 'desktop'
        $shortcut.Description = if ($networkMode -eq 'MainPc') { 'AI Billing Main PC for shop counters' } elseif ($networkMode -eq 'CounterPc') { 'AI Billing shared billing counter' } else { 'Offline billing and local backup/restore' }
        $shortcut.IconLocation = (Join-Path $release 'desktop\app.ico') + ',0'
        $shortcut.Save()
    }
    $startupShortcutPath = Join-Path ([Environment]::GetFolderPath('Startup')) 'AI Billing Main PC Server.lnk'
    if ($networkMode -eq 'MainPc') {
        $startupShortcut = $shell.CreateShortcut($startupShortcutPath)
        $startupShortcut.TargetPath = "$env:WINDIR\System32\wscript.exe"
        $startupShortcut.Arguments = '"' + (Join-Path $release 'desktop\ServerLaunch.vbs') + '"'
        $startupShortcut.WorkingDirectory = Join-Path $release 'desktop'
        $startupShortcut.Description = 'Starts the AI Billing shop server when Windows starts'
        $startupShortcut.IconLocation = (Join-Path $release 'desktop\app.ico') + ',0'
        $startupShortcut.Save()
    } else {
        Remove-Item -LiteralPath $startupShortcutPath -Force -ErrorAction SilentlyContinue
    }
    $counterSetupShortcutPath = Join-Path ([Environment]::GetFolderPath('Programs')) 'AI Billing Counter Setup.lnk'
    if ($networkMode -eq 'CounterPc') {
        $counterSetupShortcut = $shell.CreateShortcut($counterSetupShortcutPath)
        $counterSetupShortcut.TargetPath = "$env:WINDIR\System32\wscript.exe"
        $counterSetupShortcut.Arguments = '"' + (Join-Path $release 'desktop\CounterSetup.vbs') + '"'
        $counterSetupShortcut.WorkingDirectory = Join-Path $release 'desktop'
        $counterSetupShortcut.Description = 'Connect or reconnect this billing counter to the Main PC'
        $counterSetupShortcut.IconLocation = (Join-Path $release 'desktop\app.ico') + ',0'
        $counterSetupShortcut.Save()
    } else {
        Remove-Item -LiteralPath $counterSetupShortcutPath -Force -ErrorAction SilentlyContinue
    }
    $uninstallShortcut = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath('Programs')) 'Uninstall AI Billing Offline.lnk'))
    $uninstallShortcut.TargetPath = "$env:WINDIR\System32\wscript.exe"
    $uninstallShortcut.Arguments = '"' + (Join-Path $release 'desktop\Uninstall.vbs') + '"'
    $uninstallShortcut.WorkingDirectory = Join-Path $release 'desktop'
    $uninstallShortcut.IconLocation = (Join-Path $release 'desktop\app.ico') + ',0'
    $uninstallShortcut.Description = 'Uninstall AI Billing Offline'
    $uninstallShortcut.Save()
    $expectedShortcut = if ($networkMode -eq 'CounterPc') { 'AI Billing Counter.lnk' } else { 'AI Billing Offline.lnk' }
    if (!(Test-Path -LiteralPath (Join-Path ([Environment]::GetFolderPath('Programs')) $expectedShortcut))) { throw 'The Start Menu shortcut could not be created.' }

    $uninstallKey = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\AI Billing Offline'
    New-Item -Path $uninstallKey -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name DisplayName -Value 'AI Billing Offline' -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name Publisher -Value 'AI Billing' -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name DisplayVersion -Value (Get-Date -Format 'yyyy.MM.dd.HHmm') -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name DisplayIcon -Value ((Join-Path $release 'desktop\app.ico') + ',0') -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name UninstallString -Value ('"' + "$env:WINDIR\System32\wscript.exe" + '" "' + (Join-Path $release 'desktop\Uninstall.vbs') + '"') -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name NoModify -Value 1 -PropertyType DWord -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name NoRepair -Value 1 -PropertyType DWord -Force | Out-Null
    $installedShortcut = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath('Programs')) $expectedShortcut))
    if ($installedShortcut.Arguments -notlike ('*' + $release + '*')) { throw 'The new version was extracted, but the launcher shortcut was not updated.' }
    $modeLabel = if ($networkMode -eq 'MainPc') { 'Main PC mode' } elseif ($networkMode -eq 'CounterPc') { 'Billing Counter mode' } else { 'single-PC mode' }
    if ($networkMode -eq 'CounterPc') {
        & (Join-Path $release 'desktop\CounterSetup.ps1')
        if ($LASTEXITCODE -ne 0) { throw 'Counter setup was not completed.' }
    }
    [Windows.Forms.MessageBox]::Show("Installed successfully in $modeLabel. Open AI Billing from your desktop. Your existing billing data is preserved.", 'AI Billing Offline') | Out-Null
} catch { [Windows.Forms.MessageBox]::Show($_.Exception.Message, 'Installation failed') | Out-Null; exit 1 }
