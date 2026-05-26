param(
    [string]$PublicBaseUrl,
    [string]$OutputDir = "outputs\installer-artifacts",
    [string]$Version = (Get-Date -Format "yyyyMMddHHmmss")
)

$ErrorActionPreference = "Stop"
$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$installerDir = Join-Path $repoRoot "installer"

if (-not $PublicBaseUrl) {
    throw "Informe -PublicBaseUrl https://..."
}
if (-not $env:APP_INSTALLER_ARTIFACT_SIGNING_KEY) {
    throw "Configure APP_INSTALLER_ARTIFACT_SIGNING_KEY antes de publicar."
}

Push-Location $installerDir
try {
    .\build.ps1
}
finally {
    Pop-Location
}

$distDir = Join-Path $installerDir "dist"
$artifactOutputDir = Join-Path $repoRoot $OutputDir
php (Join-Path $PSScriptRoot "publish-installer-artifacts.php") `
    --dist-dir=$distDir `
    --public-base-url=$PublicBaseUrl `
    --output-dir=$artifactOutputDir `
    --version=$Version
