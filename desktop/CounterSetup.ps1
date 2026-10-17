$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName Microsoft.VisualBasic

$dataRoot = Join-Path $env:LOCALAPPDATA 'AI Billing Counter'
New-Item -ItemType Directory -Force -Path $dataRoot | Out-Null
$configPath = Join-Path $dataRoot 'connection.json'

function Ask([string]$message, [string]$title, [string]$default = '') {
    return [Microsoft.VisualBasic.Interaction]::InputBox($message, $title, $default).Trim()
}
function Post-Json([string]$url, [hashtable]$body) {
    Invoke-RestMethod -Uri $url -Method Post -ContentType 'application/json' -Body ($body | ConvertTo-Json -Depth 5) -TimeoutSec 12
}
function Find-MainPcs {
    $udp = [Net.Sockets.UdpClient]::new()
    try {
        $udp.EnableBroadcast = $true; $udp.Client.ReceiveTimeout = 700
        $message = [Text.Encoding]::ASCII.GetBytes('AI_BILLING_DISCOVER_V1')
        [void]$udp.Send($message, $message.Length, [Net.IPEndPoint]::new([Net.IPAddress]::Broadcast,18764))
        $found = @(); $until = (Get-Date).AddSeconds(3)
        while ((Get-Date) -lt $until) {
            try {
                $remote = [Net.IPEndPoint]::new([Net.IPAddress]::Any,0)
                $reply = [Text.Encoding]::UTF8.GetString($udp.Receive([ref]$remote)) | ConvertFrom-Json
                if ($reply.service -eq 'ai-billing-main-pc') { $found += [pscustomobject]@{Name=$reply.server_name;Address=$remote.Address.IPAddressToString;Port=[int]$reply.port} }
            } catch [Net.Sockets.SocketException] { if ($_.Exception.SocketErrorCode -ne 'TimedOut') { throw } }
        }
        return @($found | Sort-Object Address -Unique)
    } finally { $udp.Dispose() }
}

try {
    $servers = @(Find-MainPcs)
    $suggested = if ($servers.Count -eq 1) { $servers[0].Name } else { '' }
    $foundText = if ($servers.Count) { "`n`nFound automatically:`n" + (($servers | ForEach-Object { "$($_.Name) - $($_.Address)" }) -join "`n") } else { "`n`nNo Main PC was found automatically. Check that both PCs use the same shop router." }
    $serverName = Ask "Enter the detected Main PC IP address, or enter its computer name.$foundText" 'Connect billing counter' $suggested
    if (!$serverName) { throw 'Main PC name is required.' }
    if ($serverName -notmatch '^[A-Za-z0-9.-]+$') { throw 'Main PC name contains unsupported characters.' }
    $serverUrl = "http://${serverName}:18765"
    $info = Invoke-RestMethod -Uri "$serverUrl/desktop-info" -TimeoutSec 8
    if ($info.network_mode -ne 'MainPc') { throw 'The selected computer is not running AI Billing in Main PC mode.' }
    $code = Ask 'On the Main PC, open Settings > Billing Counters > Connect a counter. Enter the six-digit code shown there.' 'Enter pairing code'
    if ($code -notmatch '^\d{6}$') { throw 'Enter the six-digit pairing code.' }

    $deviceFile = Join-Path $dataRoot 'device-id.txt'
    $deviceUuid = if (Test-Path -LiteralPath $deviceFile) { (Get-Content -LiteralPath $deviceFile -Raw).Trim() } else { [guid]::NewGuid().ToString() }
    Set-Content -LiteralPath $deviceFile -Value $deviceUuid -Encoding ASCII
    $machineGuid = (Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid
    $fingerprintSource = "AI-Billing-Counter-v1|$machineGuid|$env:COMPUTERNAME"
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $fingerprint = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($fingerprintSource)))).Replace('-','').ToLowerInvariant() } finally { $sha.Dispose() }
    $joined = Post-Json "$serverUrl/api/guest-task/BillingCounter/requestJoin" @{
        pairing_code=$code; device_uuid=$deviceUuid; device_fingerprint=$fingerprint
        device_name=$env:COMPUTERNAME; platform=[Environment]::OSVersion.VersionString; app_version='1.0.0'
    }
    if (!$joined.success) { throw $(if ($joined.message) { $joined.message } else { 'Pairing request failed.' }) }

    [Windows.Forms.MessageBox]::Show("Request sent to the Main PC.`n`nAsk the owner to choose a counter and click Approve. This installer will wait for up to 10 minutes.", 'Waiting for approval') | Out-Null
    $active = $null
    for ($attempt=0; $attempt -lt 200; $attempt++) {
        Start-Sleep -Seconds 3
        try {
            $status = Post-Json "$serverUrl/api/guest-task/BillingCounter/pairingStatus" @{ request_id=$joined.data.request_id; request_secret=$joined.data.request_secret }
            if ($status.data.status -eq 'active') { $active = $status.data; break }
            if ($status.data.status -in @('rejected','expired')) { throw "Pairing was $($status.data.status)." }
        } catch {
            if ($_.Exception.Message -match 'Pairing was (rejected|expired)') { throw }
        }
    }
    if (!$active) { throw 'Approval timed out. Create a new pairing code and run Counter Setup again.' }
    $secureCredential = ConvertTo-SecureString $active.device_credential -AsPlainText -Force | ConvertFrom-SecureString
    @{
        server_url=$serverUrl; server_name=$serverName; device_uuid=$deviceUuid
        protected_credential=$secureCredential; business_id=$active.business_id
        counter=$active.counter; paired_at=(Get-Date).ToUniversalTime().ToString('o')
    } | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $configPath -Encoding UTF8
    [Windows.Forms.MessageBox]::Show("Connected successfully to $($active.counter.name). Open AI Billing Counter from the desktop.", 'AI Billing Counter') | Out-Null
} catch {
    [Windows.Forms.MessageBox]::Show($_.Exception.Message, 'Counter connection failed') | Out-Null
    exit 1
}

