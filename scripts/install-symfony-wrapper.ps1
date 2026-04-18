$projectRoot = Split-Path -Parent $PSScriptRoot
$wrapperPath = Join-Path $PSScriptRoot 'symfony-wrapper.ps1'

if (-not (Test-Path $wrapperPath)) {
    throw "Wrapper script not found: $wrapperPath"
}

if (-not (Test-Path $PROFILE)) {
    New-Item -ItemType File -Path $PROFILE -Force | Out-Null
}

$profileContent = Get-Content -Path $PROFILE -Raw
$markerStart = '# >>> Carrieri Symfony wrapper >>>'
$markerEnd = '# <<< Carrieri Symfony wrapper <<<'

$block = @"
$markerStart
function symfony {
    param(
        [Parameter(ValueFromRemainingArguments = `$true)]
        [string[]]`$args
    )
    & "$wrapperPath" @args
}
$markerEnd
"@

if ($profileContent -match [regex]::Escape($markerStart)) {
    $pattern = [regex]::Escape($markerStart) + '.*?' + [regex]::Escape($markerEnd)
    $profileContent = [regex]::Replace($profileContent, $pattern, $block, [System.Text.RegularExpressions.RegexOptions]::Singleline)
} else {
    if ($profileContent -and -not $profileContent.EndsWith("`n")) {
        $profileContent += "`r`n"
    }
    $profileContent += "`r`n$block`r`n"
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($PROFILE, $profileContent, $utf8NoBom)

Write-Host "PowerShell profile updated: $PROFILE"
Write-Host "Open a new terminal, then use: symfony serve"