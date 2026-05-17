#!/usr/bin/env bash
set -euo pipefail

BACKEND_DIR="${BACKEND_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../backend" && pwd)}"
MANIFEST_SOURCE="${MANIFEST_SOURCE:-}"
AUTO_ONLY="${AUTO_ONLY:-0}"
CRITICAL_POLICY="${APP_UPDATE_ONPREM_CRITICAL_POLICY:-warn}"
FAIL_ON_PENDING_CRITICAL="${FAIL_ON_PENDING_CRITICAL:-}"
ALLOW_CONSENTED="${ALLOW_CONSENTED:-1}"

if [[ -z "${FAIL_ON_PENDING_CRITICAL}" ]]; then
  if [[ "${CRITICAL_POLICY}" == "block" ]]; then
    FAIL_ON_PENDING_CRITICAL="1"
  else
    FAIL_ON_PENDING_CRITICAL="0"
  fi
fi

while [[ $# -gt 0 ]]; do
  case "$1" in
    --backend-dir=*) BACKEND_DIR="${1#*=}" ;;
    --manifest-source=*) MANIFEST_SOURCE="${1#*=}" ;;
    --auto-only) AUTO_ONLY="1" ;;
    --allow-consented) ALLOW_CONSENTED="1" ;;
    --disallow-consented) ALLOW_CONSENTED="0" ;;
    --fail-on-pending-critical) FAIL_ON_PENDING_CRITICAL="1" ;;
    --no-fail-on-pending-critical) FAIL_ON_PENDING_CRITICAL="0" ;;
    *) echo "Parametro nao suportado: $1" >&2; exit 1 ;;
  esac
  shift
done

if [[ -f /etc/os-release ]]; then
  source /etc/os-release
  if [[ "${ID:-}" != "ubuntu" || "${VERSION_ID:-}" != "24.04" ]]; then
    echo "Este runner foi preparado para Ubuntu 24.04." >&2
    exit 1
  fi
fi

command -v php >/dev/null 2>&1 || { echo "PHP CLI nao encontrado." >&2; exit 1; }

CHECK_ARGS=()
RUN_ARGS=()
if [[ -n "${MANIFEST_SOURCE}" ]]; then
  CHECK_ARGS+=("--source=${MANIFEST_SOURCE}")
  RUN_ARGS+=("--source=${MANIFEST_SOURCE}")
fi
if [[ "${AUTO_ONLY}" == "1" ]]; then
  RUN_ARGS+=("--auto-only")
fi
if [[ "${ALLOW_CONSENTED}" != "1" ]]; then
  RUN_ARGS+=("--disallow-consented")
fi
if [[ "${FAIL_ON_PENDING_CRITICAL}" == "1" ]]; then
  RUN_ARGS+=("--fail-on-pending-critical")
fi

(
  cd "${BACKEND_DIR}"
  php bin/console app:update:check "${CHECK_ARGS[@]}"
  php bin/console app:update:run-pending "${RUN_ARGS[@]}"
  php bin/console app:integrity:monitor --fail-on-invalid
)

echo
echo "Runner on-premise de atualizacao concluido."
