param(
  [string]$OutputDir = "dist"
)

$ErrorActionPreference = "Stop"
Push-Location $PSScriptRoot
New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$env:CGO_ENABLED = "0"
$env:GOOS = "linux"; $env:GOARCH = "amd64"
go build -o "$OutputDir/construtor-builder-installer-linux" ./cmd/system-builder
go build -o "$OutputDir/construtor-subscriber-installer-linux" ./cmd/subscriber

$env:GOOS = "windows"; $env:GOARCH = "amd64"
go build -o "$OutputDir/construtor-builder-installer.exe" ./cmd/system-builder
go build -o "$OutputDir/construtor-subscriber-installer.exe" ./cmd/subscriber

Pop-Location
