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
        $shortcut = $shell.CreateShortcut((Join-Path $folder 'AI Billing Offline.lnk'))
        $shortcut.TargetPath = "$env:WINDIR\System32\wscript.exe"
        $shortcut.Arguments = '"' + (Join-Path $release 'desktop\Launch.vbs') + '"'
        $shortcut.WorkingDirectory = Join-Path $release 'desktop'
        $shortcut.Description = 'Offline billing and local backup/restore'
        $shortcut.Save()
    }
    [Windows.Forms.MessageBox]::Show('Installed successfully. Open AI Billing Offline from your desktop. Your existing billing data is preserved.', 'AI Billing Offline') | Out-Null
} catch { [Windows.Forms.MessageBox]::Show($_.Exception.Message, 'Installation failed') | Out-Null; exit 1 }
