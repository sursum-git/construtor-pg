#!/usr/bin/env bash
set -euo pipefail

BACKEND_DIR="${BACKEND_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../backend" && pwd)}"
MANIFEST_SOURCE="${MANIFEST_SOURCE:-}"
AUTO_ONLY="${AUTO_ONLY:-0}"
CRITICAL_POLICY="${APP_UPDATE_ONPREM_CRITICAL_POLICY:-warn}"
CRITICAL_MODE="${APP_UPDATE_ONPREM_CRITICAL_MODE:-prompt_admin}"
FAIL_ON_PENDING_CRITICAL="${FAIL_ON_PENDING_CRITICAL:-}"
ALLOW_CONSENTED="${ALLOW_CONSENTED:-1}"
BACKUP_COMMAND="${BACKUP_COMMAND:-}"
COMPOSE_WORKDIR="${COMPOSE_WORKDIR:-}"
COMPOSE_FILE="${COMPOSE_FILE:-}"
COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-}"
COMPOSE_SERVICES="${COMPOSE_SERVICES:-}"
SKIP_CONTAINER_ROLLOUT="${SKIP_CONTAINER_ROLLOUT:-0}"

AUTO_ONLY_EXPLICIT="0"

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
    --auto-only) AUTO_ONLY="1" ; AUTO_ONLY_EXPLICIT="1" ;;
    --allow-consented) ALLOW_CONSENTED="1" ;;
    --disallow-consented) ALLOW_CONSENTED="0" ;;
    --fail-on-pending-critical) FAIL_ON_PENDING_CRITICAL="1" ;;
    --no-fail-on-pending-critical) FAIL_ON_PENDING_CRITICAL="0" ;;
    --backup-command=*) BACKUP_COMMAND="${1#*=}" ;;
    --compose-workdir=*) COMPOSE_WORKDIR="${1#*=}" ;;
    --compose-file=*) COMPOSE_FILE="${1#*=}" ;;
    --compose-project-name=*) COMPOSE_PROJECT_NAME="${1#*=}" ;;
    --compose-services=*) COMPOSE_SERVICES="${1#*=}" ;;
    --skip-container-rollout) SKIP_CONTAINER_ROLLOUT="1" ;;
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
command -v docker >/dev/null 2>&1 || { echo "Docker nao encontrado." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "Docker Compose plugin nao encontrado." >&2; exit 1; }

CHECK_ARGS=()
RUN_ARGS=()
if [[ -n "${MANIFEST_SOURCE}" ]]; then
  CHECK_ARGS+=("--source=${MANIFEST_SOURCE}")
  RUN_ARGS+=("--source=${MANIFEST_SOURCE}")
fi
if [[ "${AUTO_ONLY_EXPLICIT}" != "1" && "${CRITICAL_MODE}" == "auto" ]]; then
  AUTO_ONLY="1"
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

COMPOSE_CMD=(docker compose)
if [[ -n "${COMPOSE_PROJECT_NAME}" ]]; then
  COMPOSE_CMD+=(-p "${COMPOSE_PROJECT_NAME}")
fi
if [[ -n "${COMPOSE_FILE}" ]]; then
  COMPOSE_CMD+=(-f "${COMPOSE_FILE}")
fi

run_backup_if_needed() {
  if [[ -z "${BACKUP_COMMAND}" ]]; then
    return 0
  fi
  echo "Executando backup opcional antes da atualizacao..."
  bash -lc "${BACKUP_COMMAND}"
}

run_container_rollout_if_needed() {
  if [[ "${SKIP_CONTAINER_ROLLOUT}" == "1" ]]; then
    return 0
  fi
  if [[ -z "${COMPOSE_WORKDIR}" ]]; then
    return 0
  fi
  if [[ ! -d "${COMPOSE_WORKDIR}" ]]; then
    echo "Diretorio do Docker Compose nao encontrado: ${COMPOSE_WORKDIR}" >&2
    exit 1
  fi

  local services=()
  if [[ -n "${COMPOSE_SERVICES}" ]]; then
    IFS=',' read -r -a services <<< "${COMPOSE_SERVICES}"
  fi

  echo "Atualizando containers do ambiente on-premise..."
  (
    cd "${COMPOSE_WORKDIR}"
    "${COMPOSE_CMD[@]}" pull "${services[@]}"
    "${COMPOSE_CMD[@]}" up -d --force-recreate "${services[@]}"
  )
}

(
  cd "${BACKEND_DIR}"
  echo "Verificando versao instalada e manifesto..."
  php bin/console app:update:check "${CHECK_ARGS[@]}"
  if [[ "${CRITICAL_MODE}" == "download_only" ]]; then
    php bin/console app:update:download-pending-critical "${CHECK_ARGS[@]}"
    php bin/console app:integrity:monitor --fail-on-invalid
    echo
    echo "Runner on-premise concluiu apenas o download local do pacote critico."
    exit 0
  fi
  run_backup_if_needed
  php bin/console app:update:run-pending "${RUN_ARGS[@]}"
  php bin/console app:integrity:monitor --fail-on-invalid
)

run_container_rollout_if_needed

echo
echo "Runner on-premise de atualizacao concluido."
