$ErrorActionPreference = 'Stop'
$mutex = [Threading.Mutex]::new($false, 'Local\AI-Billing-Discovery-v1')
if (!$mutex.WaitOne(0)) { exit 0 }
$udp = $null
try {
    $udp = [Net.Sockets.UdpClient]::new(18764)
    $udp.Client.ReceiveTimeout = 1000
    while ($true) {
        try {
            $remote = [Net.IPEndPoint]::new([Net.IPAddress]::Any, 0)
            $bytes = $udp.Receive([ref]$remote)
            if ([Text.Encoding]::ASCII.GetString($bytes) -ne 'AI_BILLING_DISCOVER_V1') { continue }
            $payload = @{ service='ai-billing-main-pc'; version=1; server_name=$env:COMPUTERNAME; port=18765; tls_port=18443 } | ConvertTo-Json -Compress
            $reply = [Text.Encoding]::UTF8.GetBytes($payload)
            [void]$udp.Send($reply, $reply.Length, $remote)
        } catch [Net.Sockets.SocketException] {
            if ($_.Exception.SocketErrorCode -ne 'TimedOut') { throw }
        }
    }
} finally {
    if ($udp) { $udp.Dispose() }
    $mutex.ReleaseMutex(); $mutex.Dispose()
}
