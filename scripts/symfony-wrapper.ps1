param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$SymfonyArgs
)

$projectRoot = Split-Path -Parent $PSScriptRoot
$lanScript = Join-Path $PSScriptRoot 'start-lan-server.ps1'

function Get-ServePort {
    param([string[]]$Args)

    $port = 8000
    for ($i = 0; $i -lt $Args.Count; $i++) {
        $arg = $Args[$i]

        if ($arg -match '^--port=(\d+)$') {
            return [int]$Matches[1]
        }

        if ($arg -eq '--port' -and ($i + 1) -lt $Args.Count -and $Args[$i + 1] -match '^\d+$') {
            return [int]$Args[$i + 1]
        }

        if ($arg -eq '-p' -and ($i + 1) -lt $Args.Count -and $Args[$i + 1] -match '^\d+$') {
            return [int]$Args[$i + 1]
        }
    }

    return $port
}

if (-not $SymfonyArgs -or $SymfonyArgs.Count -eq 0) {
    $SymfonyArgs = @('list')
}

$symfonyBinary = Get-Command symfony -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
if (-not $symfonyBinary) {
    throw 'Symfony CLI not found in PATH.'
}

$first = $SymfonyArgs[0].ToLowerInvariant()
if ($first -eq 'serve' -or $first -eq 'server:start') {
    $port = Get-ServePort -Args $SymfonyArgs
    & $lanScript -Port $port -UpdateOnly
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }

    $hasAllowAllIp = $SymfonyArgs -contains '--allow-all-ip'
    $hasNoTls = $SymfonyArgs -contains '--no-tls'
    $hasListenIp = $false

    foreach ($arg in $SymfonyArgs) {
        if ($arg -eq '--listen-ip' -or $arg -match '^--listen-ip=') {
            $hasListenIp = $true
            break
        }
    }

    if (-not $hasAllowAllIp -and -not $hasListenIp) {
        $SymfonyArgs += '--allow-all-ip'
    }

    if (-not $hasNoTls) {
        $SymfonyArgs += '--no-tls'
    }

    # Avoid stale localhost binds when restarting with different network flags.
    & $symfonyBinary.Source 'server:stop' | Out-Null
}

Set-Location $projectRoot
& $symfonyBinary.Source @SymfonyArgs
exit $LASTEXITCODE


