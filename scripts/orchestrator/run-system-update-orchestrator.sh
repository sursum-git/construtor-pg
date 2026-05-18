#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
HOST="${HOST:-0.0.0.0}"
PORT="${PORT:-8095}"
CONFIG_FILE="${APP_UPDATE_ORCHESTRATOR_CONFIG:-${ROOT_DIR}/scripts/orchestrator/system-update-orchestrator.config.json}"

command -v php >/dev/null 2>&1 || { echo "PHP CLI nao encontrado." >&2; exit 1; }

if [[ ! -f "${CONFIG_FILE}" ]]; then
  echo "Arquivo de configuracao do orquestrador nao encontrado: ${CONFIG_FILE}" >&2
  echo "Copie system-update-orchestrator.config.sample.json para system-update-orchestrator.config.json e ajuste os alvos." >&2
  exit 1
fi

export APP_UPDATE_ORCHESTRATOR_CONFIG="${CONFIG_FILE}"

cd "${ROOT_DIR}"
php -S "${HOST}:${PORT}" scripts/orchestrator/system-update-orchestrator.php
