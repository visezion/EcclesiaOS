[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+([-.][0-9A-Za-z.-]+)?$')]
    [string] $Version
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$environmentPath = Join-Path $projectRoot '.env.docker'

Set-Location $projectRoot

if (-not (Test-Path $environmentPath)) {
    throw 'Run docker/setup.ps1 before updating.'
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker Desktop or Docker Engine with Compose v2 is required.'
}

function Invoke-Docker {
    param(
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]] $Arguments
    )

    & docker @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker command failed: docker $($Arguments -join ' ')"
    }
}

function Set-EnvironmentValue([string] $Name, [string] $Value) {
    $content = [IO.File]::ReadAllText($environmentPath)
    $pattern = '(?m)^' + [Regex]::Escape($Name) + '=.*$'
    $content = [Regex]::Replace($content, $pattern, "$Name=$Value")
    [IO.File]::WriteAllText(
        $environmentPath,
        $content,
        [Text.UTF8Encoding]::new($false)
    )
}

$currentContent = [IO.File]::ReadAllText($environmentPath)
$currentMatch = [Regex]::Match($currentContent, '(?m)^ECCLESIAOS_VERSION=(.+)$')
$currentVersion = if ($currentMatch.Success) { $currentMatch.Groups[1].Value.Trim() } else { 'unknown' }

Write-Host "Creating a database backup before updating $currentVersion to $Version..."
Invoke-Docker compose --env-file $environmentPath --profile tools run --rm backup

$previousOverride = $env:ECCLESIAOS_VERSION
try {
    $env:ECCLESIAOS_VERSION = $Version
    Invoke-Docker compose --env-file $environmentPath pull app web queue scheduler

    Set-EnvironmentValue 'ECCLESIAOS_VERSION' $Version
    Invoke-Docker compose --env-file $environmentPath up -d --no-build --remove-orphans
    Invoke-Docker compose --env-file $environmentPath exec -T app php artisan about
}
finally {
    $env:ECCLESIAOS_VERSION = $previousOverride
}

Write-Host "EcclesiaOS was updated to $Version. The pre-update SQL backup is in backups/."
