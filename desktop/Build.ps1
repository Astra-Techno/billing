param(
    [string]$PhpRoot = 'C:\laragon1\bin\php\php-8.3.16-Win32-vs16-x64',
    [string]$MysqlRoot = 'C:\laragon1\bin\mysql\mysql-8.4.3-winx64'
)
$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot -Parent
$buildRoot = Join-Path $repo ('dist\offline-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
$payloadRoot = Join-Path $buildRoot 'payload'
$desktopRoot = Join-Path $payloadRoot 'desktop'
New-Item -ItemType Directory -Force -Path $desktopRoot | Out-Null
Push-Location $repo
try {
    & node node_modules/vite/bin/vite.js build --outDir desktop/web
    if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
    $htmlPath = Join-Path $PSScriptRoot 'web\index.html'
    $offlineHtml = [IO.File]::ReadAllText($htmlPath)
    $offlineHtml = [regex]::Replace($offlineHtml, '<link[^>]+https://fonts\.[^>]+>', '')
    [IO.File]::WriteAllText($htmlPath, $offlineHtml)
    foreach ($name in @('app','bootstrap','config','database','routes','vendor')) {
        New-Item -ItemType Directory -Force -Path "$payloadRoot\api" | Out-Null
        Copy-Item -LiteralPath "$repo\api\$name" -Destination "$payloadRoot\api\$name" -Recurse
    }
    Copy-Item -LiteralPath "$repo\api\index.php" -Destination "$payloadRoot\api\index.php"
    foreach ($name in @('Launch.ps1','Launch.vbs','php.ini','router.php','environment.php','cli.php','check-runtime.php','README.md','web')) {
        Copy-Item -LiteralPath "$PSScriptRoot\$name" -Destination "$desktopRoot\$name" -Recurse
    }
    New-Item -ItemType Directory -Force -Path "$desktopRoot\runtime\php", "$desktopRoot\runtime\mysql\bin" | Out-Null
    Get-ChildItem -LiteralPath $PhpRoot -File | Where-Object { $_.Extension -eq '.dll' -or $_.Name -in @('php.exe','php-win.exe','README.md','readme-redist-bins.txt') } | Copy-Item -Destination "$desktopRoot\runtime\php"
    Copy-Item -LiteralPath "$PhpRoot\ext" -Destination "$desktopRoot\runtime\php\ext" -Recurse
    Get-ChildItem -LiteralPath "$MysqlRoot\bin" -File | Where-Object { $_.Extension -eq '.dll' -or $_.Name -in @('mysqld.exe','mysql.exe','mysqladmin.exe','mysqldump.exe') } | Copy-Item -Destination "$desktopRoot\runtime\mysql\bin"
    foreach ($name in @('share','LICENSE','README','docs')) { Copy-Item -LiteralPath "$MysqlRoot\$name" -Destination "$desktopRoot\runtime\mysql\$name" -Recurse }
    $redist = Join-Path $PSScriptRoot 'runtime\vc_redist.x64.exe'
    if (!(Test-Path -LiteralPath $redist)) {
        New-Item -ItemType Directory -Force -Path (Split-Path $redist -Parent) | Out-Null
        Invoke-WebRequest -Uri 'https://aka.ms/vc14/vc_redist.x64.exe' -OutFile $redist -UseBasicParsing
    }
    $signature = Get-AuthenticodeSignature -LiteralPath $redist
    if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'O=Microsoft Corporation') { throw 'Visual C++ prerequisite signature is not valid.' }
    Copy-Item -LiteralPath $redist -Destination "$desktopRoot\runtime\vc_redist.x64.exe"
    # Validate extensions against the shipped configuration before creating an installer.
    & "$desktopRoot\runtime\php\php.exe" -c "$desktopRoot\php.ini" -d "extension_dir=$desktopRoot\runtime\php\ext" "$desktopRoot\check-runtime.php"
    if ($LASTEXITCODE -ne 0) { throw 'Bundled PHP extension validation failed.' }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = Join-Path $buildRoot 'AI-Billing-Offline.zip'
    [IO.Compression.ZipFile]::CreateFromDirectory($payloadRoot, $zip)
    $compiler = "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
    $installer = Join-Path $buildRoot 'AI-Billing-Offline-Setup.exe'
    & $compiler /nologo /target:winexe /platform:x64 "/out:$installer" /reference:System.Windows.Forms.dll "/resource:$zip,payload.zip" "/resource:$PSScriptRoot\Install.ps1,Install.ps1" "$PSScriptRoot\Installer.cs"
    if ($LASTEXITCODE -ne 0) { throw 'Installer compilation failed.' }
    Get-FileHash -LiteralPath $installer -Algorithm SHA256 | Format-List | Out-File "$buildRoot\SHA256.txt"
    Write-Output "INSTALLER $installer"
    Write-Output "PORTABLE $zip"
} finally { Pop-Location }
