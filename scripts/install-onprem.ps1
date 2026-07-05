param(
    [string]$BackendDir = "C:\construtor-pg\backend",
    [string]$InstanceCode = "construtor-pg-onprem",
    [string]$DatabaseName = "construtor_pg",
    [string]$DatabaseUser = "app",
    [string]$DatabasePasswordEnv = "CONSTRUTOR_PG_DATABASE_PASSWORD",
    [string]$DatabaseHost = "127.0.0.1",
    [int]$DatabasePort = 5432,
    [string]$AppEnv = "prod",
    [string]$DatabaseEnvironment = "prod",
    [string]$DatabaseIdentity = "onprem:construtor-pg",
    [string]$AdminUsername = "admin",
    [string]$AdminPasswordEnv = "CONSTRUTOR_PG_ADMIN_PASSWORD",
    [string]$SubscriberCode = "default",
    [string]$SubscriberName = "Principal",
    [string]$SubscriberDocument = "",
    [switch]$StartDatabaseContainer = $true
)

$ErrorActionPreference = "Stop"

$databasePassword = [Environment]::GetEnvironmentVariable($DatabasePasswordEnv)
$adminPassword = [Environment]::GetEnvironmentVariable($AdminPasswordEnv)
if ([string]::IsNullOrWhiteSpace($databasePassword)) {
    throw "Informe -DatabasePasswordEnv apontando para uma variavel de ambiente com a senha do banco."
}
if ([string]::IsNullOrWhiteSpace($adminPassword)) {
    throw "Informe -AdminPasswordEnv apontando para uma variavel de ambiente com a senha do administrador."
}

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
$databaseUrl = New-DatabaseUrl -User $DatabaseUser -Password $databasePassword -Host $DatabaseHost -Port $DatabasePort -Name $DatabaseName
$envLocalPath = Join-Path $BackendDir ".env.local"

Write-BackendEnvLocal -TargetPath $envLocalPath -DatabaseUrl $databaseUrl -AppEnvValue $AppEnv -EnvironmentValue $DatabaseEnvironment -IdentityValue $DatabaseIdentity

if ($StartDatabaseContainer) {
    Push-Location $BackendDir
    try {
        $env:POSTGRES_DB = $DatabaseName
        $env:POSTGRES_USER = $DatabaseUser
        $env:POSTGRES_PASSWORD = $databasePassword
        docker compose -p $InstanceCode up -d database
    } finally {
        Pop-Location
    }
}

Push-Location $BackendDir
try {
    php bin/console app:install:bootstrap --create-database --database-environment=$DatabaseEnvironment --database-identity=$DatabaseIdentity
    $env:CONSTRUTOR_PG_ADMIN_PASSWORD = $adminPassword
    php bin/console app:subscriber:create --code=$SubscriberCode --name="$SubscriberName" --document="$SubscriberDocument" --principal --admin-username=$AdminUsername --admin-password-env=CONSTRUTOR_PG_ADMIN_PASSWORD --admin-display-name="Administrador"
    php bin/console app:runtime:publish-defaults --fail-on-missing
} finally {
    Remove-Item Env:\CONSTRUTOR_PG_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    Pop-Location
}

Write-Host ""
Write-Host "Instalacao on-premise concluida."
Write-Host "Backend: $BackendDir"
Write-Host "Base: $DatabaseIdentity"
Write-Host "URL sugerida: http://localhost"
Write-Host "Usuario inicial: $AdminUsername"
