#!/usr/bin/env bash
set -euo pipefail

BACKEND_DIR="${BACKEND_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../backend" && pwd)}"
INSTANCE_CODE="${INSTANCE_CODE:-construtor-pg-onprem}"
DATABASE_NAME="${DATABASE_NAME:-construtor_pg}"
DATABASE_USER="${DATABASE_USER:-app}"
DATABASE_PASSWORD="${DATABASE_PASSWORD:-!ChangeMe!}"
DATABASE_HOST="${DATABASE_HOST:-127.0.0.1}"
DATABASE_PORT="${DATABASE_PORT:-5432}"
APP_ENV_VALUE="${APP_ENV_VALUE:-prod}"
DATABASE_ENVIRONMENT="${DATABASE_ENVIRONMENT:-prod}"
DATABASE_IDENTITY="${DATABASE_IDENTITY:-onprem:construtor-pg}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
SUBSCRIBER_CODE="${SUBSCRIBER_CODE:-default}"
SUBSCRIBER_NAME="${SUBSCRIBER_NAME:-Principal}"
SUBSCRIBER_DOCUMENT="${SUBSCRIBER_DOCUMENT:-}"
START_DATABASE_CONTAINER="${START_DATABASE_CONTAINER:-1}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --backend-dir=*) BACKEND_DIR="${1#*=}" ;;
    --instance-code=*) INSTANCE_CODE="${1#*=}" ;;
    --database-name=*) DATABASE_NAME="${1#*=}" ;;
    --database-user=*) DATABASE_USER="${1#*=}" ;;
    --database-password=*) DATABASE_PASSWORD="${1#*=}" ;;
    --database-host=*) DATABASE_HOST="${1#*=}" ;;
    --database-port=*) DATABASE_PORT="${1#*=}" ;;
    --app-env=*) APP_ENV_VALUE="${1#*=}" ;;
    --database-environment=*) DATABASE_ENVIRONMENT="${1#*=}" ;;
    --database-identity=*) DATABASE_IDENTITY="${1#*=}" ;;
    --admin-username=*) ADMIN_USERNAME="${1#*=}" ;;
    --admin-password=*) ADMIN_PASSWORD="${1#*=}" ;;
    --subscriber-code=*) SUBSCRIBER_CODE="${1#*=}" ;;
    --subscriber-name=*) SUBSCRIBER_NAME="${1#*=}" ;;
    --subscriber-document=*) SUBSCRIBER_DOCUMENT="${1#*=}" ;;
    --no-database-container) START_DATABASE_CONTAINER="0" ;;
    *) echo "Parametro nao suportado: $1" >&2; exit 1 ;;
  esac
  shift
done

if [[ ! -f /etc/os-release ]]; then
  echo "Nao foi possivel validar o sistema operacional." >&2
  exit 1
fi

source /etc/os-release
if [[ "${ID:-}" != "ubuntu" || "${VERSION_ID:-}" != "24.04" ]]; then
  echo "Este instalador exige Ubuntu 24.04." >&2
  exit 1
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

if [[ "${START_DATABASE_CONTAINER}" == "1" ]]; then
  (
    cd "${BACKEND_DIR}"
    POSTGRES_DB="${DATABASE_NAME}" POSTGRES_USER="${DATABASE_USER}" POSTGRES_PASSWORD="${DATABASE_PASSWORD}" \
      docker compose -p "${INSTANCE_CODE}" up -d database
  )
fi

(
  cd "${BACKEND_DIR}"
  php bin/console app:install:bootstrap --create-database --database-environment="${DATABASE_ENVIRONMENT}" --database-identity="${DATABASE_IDENTITY}"
  php bin/console app:subscriber:create --code="${SUBSCRIBER_CODE}" --name="${SUBSCRIBER_NAME}" --document="${SUBSCRIBER_DOCUMENT}" --principal --admin-username="${ADMIN_USERNAME}" --admin-password="${ADMIN_PASSWORD}" --admin-display-name="Administrador"
  php bin/console app:runtime:publish-defaults --fail-on-missing
)

echo
echo "Instalacao on-premise concluida."
echo "Backend: ${BACKEND_DIR}"
echo "Base: ${DATABASE_IDENTITY}"
echo "Usuario inicial: ${ADMIN_USERNAME}"
