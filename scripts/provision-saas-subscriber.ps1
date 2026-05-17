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
    [string]$AdminPassword = "admin123",
    [switch]$StartDatabaseContainer = $true
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($SubscriberCode)) {
    throw "Informe -SubscriberCode."
}
if ([string]::IsNullOrWhiteSpace($SubscriberName)) {
    throw "Informe -SubscriberName."
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
$databaseName = "construtor_pg_" + ($SubscriberCode -replace "[^a-zA-Z0-9_\\-]", "_" ).ToLowerInvariant()
$databaseIdentity = "saas:$SubscriberCode"
$composeProject = "construtor-pg-$($SubscriberCode.ToLowerInvariant())"
$databaseUrl = New-DatabaseUrl -User $DatabaseUser -Password $DatabasePassword -Host $DatabaseHost -Port $DatabasePort -Name $databaseName
$envLocalPath = Join-Path $BackendDir ".env.local"

Write-BackendEnvLocal -TargetPath $envLocalPath -DatabaseUrl $databaseUrl -AppEnvValue $AppEnv -EnvironmentValue $DatabaseEnvironment -IdentityValue $databaseIdentity

if ($StartDatabaseContainer) {
    Push-Location $BackendDir
    try {
        $env:POSTGRES_DB = $databaseName
        $env:POSTGRES_USER = $DatabaseUser
        $env:POSTGRES_PASSWORD = $DatabasePassword
        docker compose -p $composeProject up -d database
    } finally {
        Pop-Location
    }
}

Push-Location $BackendDir
try {
    php bin/console app:install:bootstrap --create-database --database-environment=$DatabaseEnvironment --database-identity=$databaseIdentity
    php bin/console app:subscriber:create --code=$SubscriberCode --name="$SubscriberName" --document="$SubscriberDocument" --admin-username=$AdminUsername --admin-password="$AdminPassword" --admin-display-name="Administrador $SubscriberName"
    php bin/console app:runtime:publish-defaults --fail-on-missing
} finally {
    Pop-Location
}

Write-Host ""
Write-Host "Provisionamento SaaS concluido."
Write-Host "Assinante: $SubscriberCode"
Write-Host "Base: $databaseIdentity"
Write-Host "Banco: $databaseName"
Write-Host "Usuario inicial: $AdminUsername"
