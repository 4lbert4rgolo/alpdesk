#!/usr/bin/env bash
# Atualiza o AlpDesk no servidor. Uso:  ./scripts/deploy.sh
# O build do front (public/build) deve ser enviado separadamente, ou rode npm ci && npm run build antes.
set -euo pipefail

cd "$(dirname "$0")/.."

php artisan down --retry=30 || true
trap 'php artisan up' EXIT

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize

echo "Atualização concluída."
