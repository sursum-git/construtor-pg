$ErrorActionPreference = 'Stop'

$BackendDir = $env:BACKEND_DIR
if ([string]::IsNullOrWhiteSpace($BackendDir)) {
  $BackendDir = (Resolve-Path (Join-Path $PSScriptRoot '..\backend')).Path
}
$ManifestSource = $env:MANIFEST_SOURCE
$AutoOnly = $false
$DisallowConsented = $false
$CriticalPolicy = [string]::IsNullOrWhiteSpace($env:APP_UPDATE_ONPREM_CRITICAL_POLICY) ? 'warn' : $env:APP_UPDATE_ONPREM_CRITICAL_POLICY
$CriticalMode = [string]::IsNullOrWhiteSpace($env:APP_UPDATE_ONPREM_CRITICAL_MODE) ? 'prompt_admin' : $env:APP_UPDATE_ONPREM_CRITICAL_MODE
$FailOnPendingCritical = if ($CriticalPolicy -eq 'block') { $true } else { $false }
$BackupCommand = $env:BACKUP_COMMAND
$ComposeWorkdir = $env:COMPOSE_WORKDIR
$ComposeFile = $env:COMPOSE_FILE
$ComposeProjectName = $env:COMPOSE_PROJECT_NAME
$ComposeServices = $env:COMPOSE_SERVICES
$SkipContainerRollout = $false

foreach ($arg in $args) {
  if ($arg -like '--backend-dir=*') { $BackendDir = $arg.Substring(14); continue }
  if ($arg -like '--manifest-source=*') { $ManifestSource = $arg.Substring(18); continue }
  if ($arg -eq '--auto-only') { $AutoOnly = $true; continue }
  if ($arg -eq '--disallow-consented') { $DisallowConsented = $true; continue }
  if ($arg -eq '--allow-consented') { $DisallowConsented = $false; continue }
  if ($arg -eq '--no-fail-on-pending-critical') { $FailOnPendingCritical = $false; continue }
  if ($arg -eq '--fail-on-pending-critical') { $FailOnPendingCritical = $true; continue }
  if ($arg -like '--backup-command=*') { $BackupCommand = $arg.Substring(17); continue }
  if ($arg -like '--compose-workdir=*') { $ComposeWorkdir = $arg.Substring(18); continue }
  if ($arg -like '--compose-file=*') { $ComposeFile = $arg.Substring(15); continue }
  if ($arg -like '--compose-project-name=*') { $ComposeProjectName = $arg.Substring(23); continue }
  if ($arg -like '--compose-services=*') { $ComposeServices = $arg.Substring(19); continue }
  if ($arg -eq '--skip-container-rollout') { $SkipContainerRollout = $true; continue }
  throw "Parametro nao suportado: $arg"
}

$checkArgs = @('bin/console', 'app:update:check')
$runArgs = @('bin/console', 'app:update:run-pending')
if (-not [string]::IsNullOrWhiteSpace($ManifestSource)) {
  $checkArgs += "--source=$ManifestSource"
  $runArgs += "--source=$ManifestSource"
}
if (-not $AutoOnly -and $CriticalMode -eq 'auto') { $AutoOnly = $true }
if ($AutoOnly) { $runArgs += '--auto-only' }
if ($DisallowConsented) { $runArgs += '--disallow-consented' }
if ($FailOnPendingCritical) { $runArgs += '--fail-on-pending-critical' }

docker compose version | Out-Null

Push-Location $BackendDir
try {
  & php @checkArgs
  if ($CriticalMode -eq 'download_only') {
    $downloadArgs = @('bin/console', 'app:update:download-pending-critical')
    if (-not [string]::IsNullOrWhiteSpace($ManifestSource)) {
      $downloadArgs += "--source=$ManifestSource"
    }
    & php @downloadArgs
    & php bin/console app:integrity:monitor --fail-on-invalid
    return
  }
  if (-not [string]::IsNullOrWhiteSpace($BackupCommand)) {
    Write-Host 'Executando backup opcional antes da atualizacao...'
    powershell -NoProfile -Command $BackupCommand
  }
  & php @runArgs
  & php bin/console app:integrity:monitor --fail-on-invalid
} finally {
  Pop-Location
}

if (-not $SkipContainerRollout -and -not [string]::IsNullOrWhiteSpace($ComposeWorkdir)) {
  $composeArgs = @('compose')
  if (-not [string]::IsNullOrWhiteSpace($ComposeProjectName)) {
    $composeArgs += @('-p', $ComposeProjectName)
  }
  if (-not [string]::IsNullOrWhiteSpace($ComposeFile)) {
    $composeArgs += @('-f', $ComposeFile)
  }
  $services = @()
  if (-not [string]::IsNullOrWhiteSpace($ComposeServices)) {
    $services = $ComposeServices.Split(',') | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }
  }
  Push-Location $ComposeWorkdir
  try {
    & docker @composeArgs pull @services
    & docker @composeArgs up -d --force-recreate @services
  } finally {
    Pop-Location
  }
}
