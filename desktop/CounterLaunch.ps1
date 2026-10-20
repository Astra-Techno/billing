$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
# Accept self-signed certificates from the Main PC's TLS proxy
Add-Type @'
using System.Net;
using System.Net.Security;
using System.Security.Cryptography.X509Certificates;
public static class TlsTrustL {
    public static void AcceptSelfSigned() {
        ServicePointManager.ServerCertificateValidationCallback = delegate { return true; };
    }
}
'@
[TlsTrustL]::AcceptSelfSigned()
$configPath = Join-Path $env:LOCALAPPDATA 'AI Billing Counter\connection.json'
try {
    if (!(Test-Path -LiteralPath $configPath)) { throw 'This counter is not connected. Run AI Billing Counter Setup first.' }
    $config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
    $secure = ConvertTo-SecureString $config.protected_credential
    $credential = [Net.NetworkCredential]::new('', $secure).Password
    $heartbeat = $null
    for ($attempt=0; $attempt -lt 6 -and !$heartbeat; $attempt++) {
        try { $heartbeat = Invoke-RestMethod -Uri "$($config.server_url)/api/guest-task/BillingCounter/heartbeat" -Method Post -ContentType 'application/json' -Body (@{device_uuid=$config.device_uuid;device_credential=$credential;app_version='1.0.0'} | ConvertTo-Json) -TimeoutSec 8 }
        catch { if ($attempt -ge 5) { throw }; Start-Sleep -Seconds 5 }
    }
    if (!$heartbeat.success -or $heartbeat.data.status -ne 'active') { throw 'The Main PC did not accept this billing counter.' }
    if (!$heartbeat.data.device_session) { throw 'The Main PC did not create a secure counter session.' }
    $edge = @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe", "$env:LOCALAPPDATA\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
    if (!$edge) { throw 'Microsoft Edge is required.' }
    $profile = Join-Path $env:LOCALAPPDATA 'AI Billing Counter\window'
    $device = [Uri]::EscapeDataString([string]$config.device_uuid)
    $session = [Uri]::EscapeDataString([string]$heartbeat.data.device_session)
    $url = "$($config.server_url)/pos#device=$device&session=$session"
    # --ignore-certificate-errors is needed for the self-signed TLS cert on the LAN
    $edgeArgs = @("--app=$url", "--user-data-dir=`"$profile`"", '--start-maximized', '--no-first-run', '--no-default-browser-check', '--disable-background-mode')
    if ($config.server_url -match '^https://') { $edgeArgs += '--ignore-certificate-errors' }
    Start-Process -FilePath $edge -ArgumentList $edgeArgs | Out-Null
} catch {
    [Windows.Forms.MessageBox]::Show($_.Exception.Message + "`n`nCheck that the Main PC and shop router are switched on.", 'AI Billing Counter') | Out-Null
    exit 1
}

