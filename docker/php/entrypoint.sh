#!/bin/sh
set -eu

mkdir -p /srv/app-state/share /srv/app-state/install /app/backend/var
chown -R www-data:www-data /srv/app-state /app/backend/var || true

exec "$@"
