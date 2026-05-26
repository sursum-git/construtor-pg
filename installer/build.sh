#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"
output_dir="${1:-dist}"
mkdir -p "${output_dir}"

CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -o "${output_dir}/construtor-builder-installer-linux" ./cmd/system-builder
CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -o "${output_dir}/construtor-subscriber-installer-linux" ./cmd/subscriber
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -o "${output_dir}/construtor-builder-installer.exe" ./cmd/system-builder
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -o "${output_dir}/construtor-subscriber-installer.exe" ./cmd/subscriber
