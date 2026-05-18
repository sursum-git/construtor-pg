param(
    [string]$BackendDir = "C:\construtor-pg\backend",
    [string]$SubscriberCode,
    [string]$SubscriberName,
    [string]$SubscriberDocument = "",
    [string]$DatabaseUser = "app",
    [string]$DatabasePassword = "!ChangeMe!",
    [string]$DatabaseHost = "127.0.0.1",
    [int]$DatabasePort = 5432,
    [string]$AppEnv = "prod",
    [string]$DatabaseEnvironment = "prod",
    [string]$AdminUsername = "admin",
    [string]$AdminPassword = "",
    [switch]$StartDatabaseContainer = $true,
    [string]$OnlyStep = ""
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($SubscriberCode)) {
    throw "Informe -SubscriberCode."
}
if ([string]::IsNullOrWhiteSpace($SubscriberName)) {
    throw "Informe -SubscriberName."
}
if ([string]::IsNullOrWhiteSpace($AdminPassword)) {
    throw "Informe -AdminPassword."
}

function New-DatabaseUrl {
    param(
        [string]$User,
        [string]$Password,
        [string]$DatabaseHostName,
        [int]$Port,
        [string]$Name
    )

    $encodedPassword = [System.Uri]::EscapeDataString($Password)
    return "postgresql://${User}:$encodedPassword@${DatabaseHostName}`:$Port/${Name}?serverVersion=16&charset=utf8"
}

function Write-BackendEnvLocal {
    param(
        [string]$TargetPath,
        [string]$DatabaseUrl,
        [string]$AppEnvValue,
        [string]$EnvironmentValue,
        [string]$IdentityValue
    )

    if (Test-Path $TargetPath) {
        Copy-Item $TargetPath ($TargetPath + ".bak")
    }

    $content = (
        @(
        "APP_ENV=`"$AppEnvValue`""
        "DATABASE_URL=`"$DatabaseUrl`""
        "APP_DATABASE_ENVIRONMENT=`"$EnvironmentValue`""
        "APP_DATABASE_IDENTITY=`"$IdentityValue`""
    ) -join [Environment]::NewLine
    ) + [Environment]::NewLine

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($TargetPath, $content, $utf8NoBom)
}

$projectDir = Split-Path $BackendDir -Parent
$databaseName = "construtor_pg_" + ($SubscriberCode -replace "[^a-zA-Z0-9_\\-]", "_" ).ToLowerInvariant()
$databaseIdentity = "saas:$SubscriberCode"
$composeProject = "construtor-pg-$($SubscriberCode.ToLowerInvariant())"
$databaseUrl = New-DatabaseUrl -User $DatabaseUser -Password $DatabasePassword -DatabaseHostName $DatabaseHost -Port $DatabasePort -Name $databaseName
$envLocalPath = Join-Path $BackendDir ".env.local"

Write-BackendEnvLocal -TargetPath $envLocalPath -DatabaseUrl $databaseUrl -AppEnvValue $AppEnv -EnvironmentValue $DatabaseEnvironment -IdentityValue $databaseIdentity

function Invoke-Step {
    param([string]$Step)

    Write-Host "== STEP:$Step =="
    switch ($Step) {
        "prepare_env" {
            return
        }
        "start_database" {
            if (-not $StartDatabaseContainer) {
                Write-Host "Container de banco desabilitado por configuracao."
                return
            }
            Push-Location $BackendDir
            try {
                $env:POSTGRES_DB = $databaseName
                $env:POSTGRES_USER = $DatabaseUser
                $env:POSTGRES_PASSWORD = $DatabasePassword
                docker compose -p $composeProject up -d database
            } finally {
                Pop-Location
            }
            return
        }
        "bootstrap_app" {
            Push-Location $BackendDir
            try {
                php bin/console app:install:bootstrap --create-database --database-environment=$DatabaseEnvironment --database-identity=$databaseIdentity
            } finally {
                Pop-Location
            }
            return
        }
        "create_subscriber" {
            Push-Location $BackendDir
            try {
                php bin/console app:subscriber:create --code=$SubscriberCode --name="$SubscriberName" --document="$SubscriberDocument" --admin-username=$AdminUsername --admin-password="$AdminPassword" --admin-display-name="Administrador $SubscriberName"
            } finally {
                Pop-Location
            }
            return
        }
        "publish_defaults" {
            Push-Location $BackendDir
            try {
                php bin/console app:runtime:publish-defaults --fail-on-missing
            } finally {
                Pop-Location
            }
            return
        }
        default {
            throw "Step nao suportado: $Step"
        }
    }
}

if (-not [string]::IsNullOrWhiteSpace($OnlyStep)) {
    Invoke-Step -Step $OnlyStep
    exit 0
}

if ($StartDatabaseContainer) {
    Invoke-Step -Step "start_database"
}
Invoke-Step -Step "prepare_env"
Invoke-Step -Step "bootstrap_app"
Invoke-Step -Step "create_subscriber"
Invoke-Step -Step "publish_defaults"

Write-Host ""
Write-Host "Provisionamento SaaS concluido."
Write-Host "Assinante: $SubscriberCode"
Write-Host "Base: $databaseIdentity"
Write-Host "Banco: $databaseName"
Write-Host "Usuario inicial: $AdminUsername"
