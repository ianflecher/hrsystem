# Prints this computer's primary LAN IPv4 address — the adapter that has a
# default gateway (the real office network), so VirtualBox/host-only adapters
# (e.g. 192.168.56.x) and loopback are skipped. Used by start.bat.

$ip = Get-NetIPConfiguration |
    Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } |
    Select-Object -First 1 -ExpandProperty IPv4Address |
    Select-Object -ExpandProperty IPAddress

if (-not $ip) {
    # Fallback: first real IPv4 that isn't loopback (127.x) or APIPA (169.254.x).
    $ip = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' } |
        Select-Object -First 1).IPAddress
}

if (-not $ip) {
    # Some Windows policies block the NetTCPIP cmdlets. ipconfig is still
    # available in that case and gives us the address needed by LAN clients.
    $ip = (ipconfig 2>$null |
        Select-String -Pattern 'IPv4 Address[^:]*:\s*(\d{1,3}(?:\.\d{1,3}){3})' |
        ForEach-Object { $_.Matches[0].Groups[1].Value } |
        Where-Object { $_ -notmatch '^(127\.|169\.254\.)' } |
        Select-Object -First 1)
}

if (-not $ip) { Write-Output 'ERROR' } else { Write-Output $ip }
