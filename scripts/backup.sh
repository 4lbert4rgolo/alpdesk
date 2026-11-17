#!/usr/bin/env bash
# Backup diário do AlpDesk: banco de dados + imagens enviadas.
# Uso (cron, todo dia às 2h):  0 2 * * * /var/www/alpdesk/scripts/backup.sh
# Depois copie a pasta de backup para FORA do servidor (rsync, rclone, outro PC...).
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/alpdesk}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/alpdesk}"
KEEP_DAYS="${KEEP_DAYS:-14}"

env_value() {
    grep -E "^$1=" "$APP_DIR/.env" | head -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"
DB_HOST="$(env_value DB_HOST)"
STAMP="$(date +%Y-%m-%d_%H%M)"

umask 077
mkdir -p "$BACKUP_DIR"

MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --no-tablespaces \
    -h "${DB_HOST:-127.0.0.1}" -u "$DB_USER" "$DB_NAME" | gzip > "$BACKUP_DIR/db_$STAMP.sql.gz"

if [ -d "$APP_DIR/storage/app/private/task-images" ]; then
    tar -czf "$BACKUP_DIR/imagens_$STAMP.tar.gz" -C "$APP_DIR/storage/app/private" task-images
fi

find "$BACKUP_DIR" -type f -mtime +"$KEEP_DAYS" -delete

echo "Backup concluído em $BACKUP_DIR ($STAMP)"
