$ErrorActionPreference = 'Stop'

$BackendDir = $env:BACKEND_DIR
if ([string]::IsNullOrWhiteSpace($BackendDir)) {
  $BackendDir = (Resolve-Path (Join-Path $PSScriptRoot '..\backend')).Path
}
$ManifestSource = $env:MANIFEST_SOURCE
$AutoOnly = $false
$DisallowConsented = $false
$FailOnPendingCritical = $true

foreach ($arg in $args) {
  if ($arg -like '--backend-dir=*') { $BackendDir = $arg.Substring(14); continue }
  if ($arg -like '--manifest-source=*') { $ManifestSource = $arg.Substring(18); continue }
  if ($arg -eq '--auto-only') { $AutoOnly = $true; continue }
  if ($arg -eq '--disallow-consented') { $DisallowConsented = $true; continue }
  if ($arg -eq '--allow-consented') { $DisallowConsented = $false; continue }
  if ($arg -eq '--no-fail-on-pending-critical') { $FailOnPendingCritical = $false; continue }
  if ($arg -eq '--fail-on-pending-critical') { $FailOnPendingCritical = $true; continue }
  throw "Parametro nao suportado: $arg"
}

$checkArgs = @('bin/console', 'app:update:check')
$runArgs = @('bin/console', 'app:update:run-pending')
if (-not [string]::IsNullOrWhiteSpace($ManifestSource)) {
  $checkArgs += "--source=$ManifestSource"
  $runArgs += "--source=$ManifestSource"
}
if ($AutoOnly) { $runArgs += '--auto-only' }
if ($DisallowConsented) { $runArgs += '--disallow-consented' }
if ($FailOnPendingCritical) { $runArgs += '--fail-on-pending-critical' }

Push-Location $BackendDir
try {
  & php @checkArgs
  & php @runArgs
  & php bin/console app:integrity:monitor --fail-on-invalid
} finally {
  Pop-Location
}
