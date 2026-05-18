#!/usr/bin/env bash
set -euo pipefail

BACKEND_DIR="${BACKEND_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../backend" && pwd)}"
SUBSCRIBER_CODE="${SUBSCRIBER_CODE:-}"
SUBSCRIBER_NAME="${SUBSCRIBER_NAME:-}"
SUBSCRIBER_DOCUMENT="${SUBSCRIBER_DOCUMENT:-}"
DATABASE_USER="${DATABASE_USER:-app}"
DATABASE_PASSWORD="${DATABASE_PASSWORD:-!ChangeMe!}"
DATABASE_HOST="${DATABASE_HOST:-127.0.0.1}"
DATABASE_PORT="${DATABASE_PORT:-5432}"
APP_ENV_VALUE="${APP_ENV_VALUE:-prod}"
DATABASE_ENVIRONMENT="${DATABASE_ENVIRONMENT:-prod}"
DATABASE_IDENTITY="${DATABASE_IDENTITY:-}"
DATABASE_NAME="${DATABASE_NAME:-}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
START_DATABASE_CONTAINER="${START_DATABASE_CONTAINER:-1}"
ONLY_STEP="${ONLY_STEP:-}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --backend-dir=*) BACKEND_DIR="${1#*=}" ;;
    --subscriber-code=*) SUBSCRIBER_CODE="${1#*=}" ;;
    --subscriber-name=*) SUBSCRIBER_NAME="${1#*=}" ;;
    --subscriber-document=*) SUBSCRIBER_DOCUMENT="${1#*=}" ;;
    --database-user=*) DATABASE_USER="${1#*=}" ;;
    --database-password=*) DATABASE_PASSWORD="${1#*=}" ;;
    --database-host=*) DATABASE_HOST="${1#*=}" ;;
    --database-port=*) DATABASE_PORT="${1#*=}" ;;
    --app-env=*) APP_ENV_VALUE="${1#*=}" ;;
    --database-environment=*) DATABASE_ENVIRONMENT="${1#*=}" ;;
    --database-identity=*) DATABASE_IDENTITY="${1#*=}" ;;
    --database-name=*) DATABASE_NAME="${1#*=}" ;;
    --admin-username=*) ADMIN_USERNAME="${1#*=}" ;;
    --admin-password=*) ADMIN_PASSWORD="${1#*=}" ;;
    --no-database-container) START_DATABASE_CONTAINER="0" ;;
    --only-step=*) ONLY_STEP="${1#*=}" ;;
    *) echo "Parametro nao suportado: $1" >&2; exit 1 ;;
  esac
  shift
done

if [[ -z "${SUBSCRIBER_CODE}" || -z "${SUBSCRIBER_NAME}" ]]; then
  echo "Informe subscriber-code e subscriber-name." >&2
  exit 1
fi

if [[ -z "${ADMIN_PASSWORD}" ]]; then
  echo "Informe admin-password." >&2
  exit 1
fi

if [[ -z "${DATABASE_IDENTITY}" ]]; then
  DATABASE_IDENTITY="saas:${SUBSCRIBER_CODE}"
fi

if [[ -z "${DATABASE_NAME}" ]]; then
  DATABASE_NAME="construtor_pg_$(echo "${SUBSCRIBER_CODE}" | tr '[:upper:]-' '[:lower:]_' | tr -cd 'a-z0-9_')"
fi

command -v php >/dev/null 2>&1 || { echo "PHP CLI nao encontrado." >&2; exit 1; }
command -v docker >/dev/null 2>&1 || { echo "Docker nao encontrado." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "Docker Compose plugin nao encontrado." >&2; exit 1; }

ENCODED_PASSWORD="$(php -r 'echo rawurlencode($argv[1]);' "$DATABASE_PASSWORD")"
DATABASE_URL="postgresql://${DATABASE_USER}:${ENCODED_PASSWORD}@${DATABASE_HOST}:${DATABASE_PORT}/${DATABASE_NAME}?serverVersion=16&charset=utf8"

cat > "${BACKEND_DIR}/.env.local" <<EOF
APP_ENV="${APP_ENV_VALUE}"
DATABASE_URL="${DATABASE_URL}"
APP_DATABASE_ENVIRONMENT="${DATABASE_ENVIRONMENT}"
APP_DATABASE_IDENTITY="${DATABASE_IDENTITY}"
EOF

run_step() {
  local step="$1"
  echo "== STEP:${step} =="
  case "${step}" in
    prepare_env)
      return 0
      ;;
    start_database)
      if [[ "${START_DATABASE_CONTAINER}" != "1" ]]; then
        echo "Container de banco desabilitado por configuracao."
        return 0
      fi
      (
        cd "${BACKEND_DIR}"
        POSTGRES_DB="${DATABASE_NAME}" POSTGRES_USER="${DATABASE_USER}" POSTGRES_PASSWORD="${DATABASE_PASSWORD}" \
          docker compose -p "construtor-pg-${SUBSCRIBER_CODE}" up -d database
      )
      return 0
      ;;
    bootstrap_app)
      (
        cd "${BACKEND_DIR}"
        php bin/console app:install:bootstrap --create-database --database-environment="${DATABASE_ENVIRONMENT}" --database-identity="${DATABASE_IDENTITY}"
      )
      return 0
      ;;
    create_subscriber)
      (
        cd "${BACKEND_DIR}"
        php bin/console app:subscriber:create --code="${SUBSCRIBER_CODE}" --name="${SUBSCRIBER_NAME}" --document="${SUBSCRIBER_DOCUMENT}" --admin-username="${ADMIN_USERNAME}" --admin-password="${ADMIN_PASSWORD}" --admin-display-name="Administrador ${SUBSCRIBER_NAME}"
      )
      return 0
      ;;
    publish_defaults)
      (
        cd "${BACKEND_DIR}"
        php bin/console app:runtime:publish-defaults --fail-on-missing
      )
      return 0
      ;;
    *)
      echo "Step nao suportado: ${step}" >&2
      return 1
      ;;
  esac
}

if [[ -n "${ONLY_STEP}" ]]; then
  run_step "${ONLY_STEP}"
  exit 0
fi

if [[ "${START_DATABASE_CONTAINER}" == "1" ]]; then
  (
    cd "${BACKEND_DIR}"
    POSTGRES_DB="${DATABASE_NAME}" POSTGRES_USER="${DATABASE_USER}" POSTGRES_PASSWORD="${DATABASE_PASSWORD}" \
      docker compose -p "construtor-pg-${SUBSCRIBER_CODE}" up -d database
  )
fi

run_step prepare_env
run_step bootstrap_app
run_step create_subscriber
run_step publish_defaults

echo
echo "Provisionamento SaaS concluido."
echo "Assinante: ${SUBSCRIBER_CODE}"
echo "Base: ${DATABASE_IDENTITY}"
echo "Banco: ${DATABASE_NAME}"
