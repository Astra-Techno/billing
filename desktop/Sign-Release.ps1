param(
    [Parameter(Mandatory)][string]$Installer,
    [Parameter(Mandatory)][ValidatePattern('^[A-Fa-f0-9]{40}$')][string]$CertificateThumbprint,
    [string]$TimestampUrl = 'http://timestamp.digicert.com'
)
$ErrorActionPreference = 'Stop'
$Installer = [IO.Path]::GetFullPath($Installer)
if (!(Test-Path -LiteralPath $Installer -PathType Leaf)) { throw 'Installer file not found.' }
$certificate = Get-ChildItem Cert:\CurrentUser\My,Cert:\LocalMachine\My -CodeSigningCert -ErrorAction SilentlyContinue | Where-Object Thumbprint -eq $CertificateThumbprint | Select-Object -First 1
if (!$certificate) { throw 'The requested code-signing certificate is not installed or has no private key.' }
$signtool = Get-ChildItem "${env:ProgramFiles(x86)}\Windows Kits\10\bin" -Filter signtool.exe -Recurse -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
if (!$signtool) { throw 'SignTool was not found. Install the Windows SDK signing tools.' }
& $signtool sign /sha1 $CertificateThumbprint /fd SHA256 /tr $TimestampUrl /td SHA256 /v $Installer
if ($LASTEXITCODE -ne 0) { throw 'Code signing failed.' }
& $signtool verify /pa /all /v $Installer
if ($LASTEXITCODE -ne 0) { throw 'The installer signature could not be verified.' }
$signature = Get-AuthenticodeSignature -LiteralPath $Installer
if ($signature.Status -ne 'Valid') { throw "Signature status is $($signature.Status)." }
Get-FileHash -LiteralPath $Installer -Algorithm SHA256 | Format-List
