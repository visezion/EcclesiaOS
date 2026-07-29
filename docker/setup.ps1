[CmdletBinding()]
param(
    [switch] $UseRegistry
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$environmentPath = Join-Path $projectRoot '.env.docker'
$examplePath = Join-Path $projectRoot '.env.docker.example'

Set-Location $projectRoot

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker Desktop or Docker Engine with Compose v2 is required.'
}

function New-RandomBase64([int] $Length) {
    $bytes = New-Object byte[] $Length
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }

    return [Convert]::ToBase64String($bytes)
}

function New-RandomHex([int] $Length) {
    $bytes = New-Object byte[] $Length
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }

    return ($bytes | ForEach-Object { $_.ToString('x2') }) -join ''
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

if (-not (Test-Path $environmentPath)) {
    Copy-Item -LiteralPath $examplePath -Destination $environmentPath
    Set-EnvironmentValue 'APP_KEY' ('base64:' + (New-RandomBase64 32))

    $databasePassword = New-RandomHex 24
    Set-EnvironmentValue 'DB_PASSWORD' $databasePassword
    Set-EnvironmentValue 'MYSQL_PASSWORD' $databasePassword
    Set-EnvironmentValue 'MYSQL_ROOT_PASSWORD' (New-RandomHex 32)

    Write-Host 'Created .env.docker with generated application and database secrets.'
}

docker compose --env-file $environmentPath config --quiet

if ($UseRegistry) {
    docker compose --env-file $environmentPath pull
    docker compose --env-file $environmentPath up -d --no-build --remove-orphans
}
else {
    docker compose --env-file $environmentPath up -d --build --remove-orphans
}

docker compose --env-file $environmentPath exec -T app php artisan about
Write-Host 'EcclesiaOS is running at the APP_URL configured in .env.docker.'

