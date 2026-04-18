param(
    [int]$Port = 8000,
    [switch]$NoEnvUpdate,
    [switch]$UpdateOnly
)

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

function Test-PrivateIPv4 {
    param([string]$Address)

    if (-not $Address) { return $false }

    return $Address -match '^10\.' -or
            $Address -match '^192\.168\.' -or
            $Address -match '^172\.(1[6-9]|2[0-9]|3[0-1])\.'
}

function Get-LanIPv4 {
    $candidates = Get-NetIPConfiguration |
            Where-Object {
                $_.NetAdapter.Status -eq 'Up' -and
                        $_.IPv4Address -and
                        $_.NetAdapter.HardwareInterface -eq $true -and
                        $_.InterfaceAlias -notmatch 'vEthernet|VirtualBox|VMware|Hyper-V|Loopback|Tailscale|ZeroTier|WireGuard|Bluetooth'
            } |
            ForEach-Object { $_.IPv4Address.IPAddress }

    $preferred = $candidates | Where-Object { Test-PrivateIPv4 $_ } | Select-Object -First 1
    if ($preferred) {
        return $preferred
    }

    return $candidates | Select-Object -First 1
}

$ip = Get-LanIPv4

if (-not $ip) {
    throw 'Could not detect an active Wi-Fi/Ethernet IPv4 address.'
}

$publicUrl = "http://$ip`:$Port"

if (-not $NoEnvUpdate) {
    $envLocalPath = Join-Path $projectRoot '.env.local'
    $content = if (Test-Path $envLocalPath) { Get-Content $envLocalPath -Raw } else { '' }

    if ($content -match '(?m)^APP_PUBLIC_URL=') {
        $content = [regex]::Replace($content, '(?m)^APP_PUBLIC_URL=.*$', "APP_PUBLIC_URL=$publicUrl")
    }
    else {
        if ($content -and -not $content.EndsWith("`n")) {
            $content += "`r`n"
        }

        $content += "APP_PUBLIC_URL=$publicUrl`r`n"
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($envLocalPath, $content, $utf8NoBom)
    Write-Host "Updated .env.local with APP_PUBLIC_URL=$publicUrl"
}

if ($UpdateOnly) {
    Write-Host "Prepared LAN URL: $publicUrl"
}
else {
    Write-Host "Starting LAN server at $publicUrl"
    php -S "0.0.0.0:$Port" -t public
}