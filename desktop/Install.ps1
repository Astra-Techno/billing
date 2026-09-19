param([Parameter(Mandatory=$true)][string]$Payload)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.Windows.Forms
$programRoot = "$env:LOCALAPPDATA\Programs\AI Billing Offline"
$release = Join-Path $programRoot ('release-' + (Get-Date -Format 'yyyyMMddHHmmss'))
try {
    if (![Environment]::Is64BitOperatingSystem) { throw 'AI Billing Offline requires 64-bit Windows.' }
    New-Item -ItemType Directory -Force -Path $release | Out-Null
    [IO.Compression.ZipFile]::ExtractToDirectory($Payload, $release)
    $vc = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' -ErrorAction SilentlyContinue
    if (!$vc -or $vc.Installed -ne 1 -or [version]($vc.Version.TrimStart('v')) -lt [version]'14.40.0.0') {
        $prerequisite = Join-Path $release 'desktop\runtime\vc_redist.x64.exe'
        $runtimeInstall = Start-Process -FilePath $prerequisite -ArgumentList '/install /passive /norestart' -Verb RunAs -PassThru -Wait -WindowStyle Hidden
        if ($runtimeInstall.ExitCode -notin @(0,3010,1638)) { throw 'Microsoft Visual C++ runtime installation failed.' }
    }
    $shell = New-Object -ComObject WScript.Shell
    foreach ($folder in @([Environment]::GetFolderPath('Desktop'), [Environment]::GetFolderPath('Programs'))) {
        if (!(Test-Path -LiteralPath $folder)) { New-Item -ItemType Directory -Force -Path $folder | Out-Null }
        $shortcut = $shell.CreateShortcut((Join-Path $folder 'AI Billing Offline.lnk'))
        $shortcut.TargetPath = "$env:WINDIR\System32\wscript.exe"
        $shortcut.Arguments = '"' + (Join-Path $release 'desktop\Launch.vbs') + '"'
        $shortcut.WorkingDirectory = Join-Path $release 'desktop'
        $shortcut.Description = 'Offline billing and local backup/restore'
        $shortcut.IconLocation = (Join-Path $release 'desktop\app.ico') + ',0'
        $shortcut.Save()
    }
    $uninstallShortcut = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath('Programs')) 'Uninstall AI Billing Offline.lnk'))
    $uninstallShortcut.TargetPath = "$env:WINDIR\System32\wscript.exe"
    $uninstallShortcut.Arguments = '"' + (Join-Path $release 'desktop\Uninstall.vbs') + '"'
    $uninstallShortcut.WorkingDirectory = Join-Path $release 'desktop'
    $uninstallShortcut.IconLocation = (Join-Path $release 'desktop\app.ico') + ',0'
    $uninstallShortcut.Description = 'Uninstall AI Billing Offline'
    $uninstallShortcut.Save()
    if (!(Test-Path -LiteralPath (Join-Path ([Environment]::GetFolderPath('Programs')) 'AI Billing Offline.lnk'))) { throw 'The Start Menu shortcut could not be created.' }

    $uninstallKey = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\AI Billing Offline'
    New-Item -Path $uninstallKey -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name DisplayName -Value 'AI Billing Offline' -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name Publisher -Value 'AI Billing' -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name DisplayVersion -Value (Get-Date -Format 'yyyy.MM.dd.HHmm') -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name DisplayIcon -Value ((Join-Path $release 'desktop\app.ico') + ',0') -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name UninstallString -Value ('"' + "$env:WINDIR\System32\wscript.exe" + '" "' + (Join-Path $release 'desktop\Uninstall.vbs') + '"') -PropertyType String -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name NoModify -Value 1 -PropertyType DWord -Force | Out-Null
    New-ItemProperty -Path $uninstallKey -Name NoRepair -Value 1 -PropertyType DWord -Force | Out-Null
    $installedShortcut = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath('Programs')) 'AI Billing Offline.lnk'))
    if ($installedShortcut.Arguments -notlike ('*' + $release + '*')) { throw 'The new version was extracted, but the launcher shortcut was not updated.' }
    [Windows.Forms.MessageBox]::Show('Installed successfully. Open AI Billing Offline from your desktop. Your existing billing data is preserved.', 'AI Billing Offline') | Out-Null
} catch { [Windows.Forms.MessageBox]::Show($_.Exception.Message, 'Installation failed') | Out-Null; exit 1 }
