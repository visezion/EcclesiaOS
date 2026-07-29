[CmdletBinding()]
param(
    [switch] $UseRegistry,
    [switch] $SkipAdmin
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$environmentPath = Join-Path $projectRoot '.env.docker'
$examplePath = Join-Path $projectRoot '.env.docker.example'

Set-Location $projectRoot

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

Invoke-Docker compose --env-file $environmentPath config --quiet

if ($UseRegistry) {
    Invoke-Docker compose --env-file $environmentPath pull
    Invoke-Docker compose --env-file $environmentPath up -d --no-build --remove-orphans
}
else {
    Invoke-Docker compose --env-file $environmentPath up -d --build --remove-orphans
}

Invoke-Docker compose --env-file $environmentPath exec -T app php artisan about

if (-not $SkipAdmin) {
    & docker compose --env-file $environmentPath exec -T app php artisan app:bootstrap-admin --check --no-interaction *> $null
    $administratorExists = $LASTEXITCODE -eq 0

    if (-not $administratorExists) {
        $administratorName = Read-Host 'First administrator name [Church Administrator]'
        if ([string]::IsNullOrWhiteSpace($administratorName)) {
            $administratorName = 'Church Administrator'
        }

        $administratorEmail = Read-Host 'First administrator email'
        $securePassword = Read-Host 'First administrator password (12+ characters, mixed case and number)' -AsSecureString
        $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
        try {
            $administratorPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
            Invoke-Docker compose --env-file $environmentPath exec -T `
                -e "BOOTSTRAP_ADMIN_NAME=$administratorName" `
                -e "BOOTSTRAP_ADMIN_EMAIL=$administratorEmail" `
                -e "BOOTSTRAP_ADMIN_PASSWORD=$administratorPassword" `
                app php artisan app:bootstrap-admin --no-interaction
        }
        finally {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
            $administratorPassword = $null
        }
    }
}

Write-Host 'EcclesiaOS is running at the APP_URL configured in .env.docker.'
