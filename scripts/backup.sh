#!/usr/bin/env bash
# Backup diário do AlpDesk: banco de dados + imagens enviadas.
# Uso (cron, todo dia às 2h):  0 2 * * * /var/www/alpdesk/scripts/backup.sh
# Envia uma cópia para o Google Drive (rclone, remote "gdrive"). Para desligar: RCLONE_REMOTE= ./backup.sh
# Log:  0 2 * * * /var/www/alpdesk/scripts/backup.sh >> /var/backups/alpdesk/backup.log 2>&1
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/alpdesk}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/alpdesk}"
KEEP_DAYS="${KEEP_DAYS:-14}"
RCLONE_REMOTE="${RCLONE_REMOTE:-gdrive:AlpDesk-Backups}"
REMOTE_KEEP_DAYS="${REMOTE_KEEP_DAYS:-30}"

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

find "$BACKUP_DIR" -type f \( -name 'db_*' -o -name 'imagens_*' \) -mtime +"$KEEP_DAYS" -delete

# Cópia para fora do servidor (Google Drive via rclone). Desligada se RCLONE_REMOTE estiver vazio.
if [ -n "$RCLONE_REMOTE" ]; then
    rclone copy "$BACKUP_DIR" "$RCLONE_REMOTE" --include 'db_*' --include 'imagens_*'
    rclone delete "$RCLONE_REMOTE" --min-age "${REMOTE_KEEP_DAYS}d" --include 'db_*' --include 'imagens_*'
    echo "Cópia enviada para $RCLONE_REMOTE"
fi

echo "$(date '+%Y-%m-%d %H:%M') Backup concluído em $BACKUP_DIR ($STAMP)"
