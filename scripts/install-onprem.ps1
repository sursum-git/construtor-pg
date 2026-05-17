param(
    [string]$BackendDir = "C:\construtor-pg\backend",
    [string]$InstanceCode = "construtor-pg-onprem",
    [string]$DatabaseName = "construtor_pg",
    [string]$DatabaseUser = "app",
    [string]$DatabasePassword = "!ChangeMe!",
    [string]$DatabaseHost = "127.0.0.1",
    [int]$DatabasePort = 5432,
    [string]$AppEnv = "prod",
    [string]$DatabaseEnvironment = "prod",
    [string]$DatabaseIdentity = "onprem:construtor-pg",
    [string]$AdminUsername = "admin",
    [string]$AdminPassword = "admin123",
    [string]$SubscriberCode = "default",
    [string]$SubscriberName = "Principal",
    [string]$SubscriberDocument = "",
    [switch]$StartDatabaseContainer = $true
)

$ErrorActionPreference = "Stop"

function New-DatabaseUrl {
    param(
        [string]$User,
        [string]$Password,
        [string]$Host,
        [int]$Port,
        [string]$Name
    )

    $encodedPassword = [System.Uri]::EscapeDataString($Password)
    return "postgresql://${User}:$encodedPassword@${Host}`:$Port/$Name?serverVersion=16&charset=utf8"
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

    @(
        "APP_ENV=`"$AppEnvValue`""
        "DATABASE_URL=`"$DatabaseUrl`""
        "APP_DATABASE_ENVIRONMENT=`"$EnvironmentValue`""
        "APP_DATABASE_IDENTITY=`"$IdentityValue`""
    ) | Set-Content -Path $TargetPath -Encoding UTF8
}

$projectDir = Split-Path $BackendDir -Parent
$databaseUrl = New-DatabaseUrl -User $DatabaseUser -Password $DatabasePassword -Host $DatabaseHost -Port $DatabasePort -Name $DatabaseName
$envLocalPath = Join-Path $BackendDir ".env.local"

Write-BackendEnvLocal -TargetPath $envLocalPath -DatabaseUrl $databaseUrl -AppEnvValue $AppEnv -EnvironmentValue $DatabaseEnvironment -IdentityValue $DatabaseIdentity

if ($StartDatabaseContainer) {
    Push-Location $BackendDir
    try {
        $env:POSTGRES_DB = $DatabaseName
        $env:POSTGRES_USER = $DatabaseUser
        $env:POSTGRES_PASSWORD = $DatabasePassword
        docker compose -p $InstanceCode up -d database
    } finally {
        Pop-Location
    }
}

Push-Location $BackendDir
try {
    php bin/console app:install:bootstrap --create-database --database-environment=$DatabaseEnvironment --database-identity=$DatabaseIdentity
    php bin/console app:subscriber:create --code=$SubscriberCode --name="$SubscriberName" --document="$SubscriberDocument" --principal --admin-username=$AdminUsername --admin-password="$AdminPassword" --admin-display-name="Administrador"
    php bin/console app:runtime:publish-defaults --fail-on-missing
} finally {
    Pop-Location
}

Write-Host ""
Write-Host "Instalacao on-premise concluida."
Write-Host "Backend: $BackendDir"
Write-Host "Base: $DatabaseIdentity"
Write-Host "URL sugerida: http://localhost"
Write-Host "Usuario inicial: $AdminUsername"
