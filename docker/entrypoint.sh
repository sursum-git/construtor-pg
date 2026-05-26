#!/usr/bin/env sh
set -eu

mkdir -p /srv/app-state/install /srv/app-state/share /app/backend/var
touch /srv/app-state/.env.local
ln -sf /srv/app-state/.env.local /app/backend/.env.local
chown -R www-data:www-data /srv/app-state /app/backend/var

exec "$@"
