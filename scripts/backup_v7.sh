#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[[ -f .env ]] || { echo 'Arquivo .env ausente' >&2; exit 2; }
set -a; . ./.env; set +a
: "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASS:?}"
command -v mysqldump >/dev/null || { echo 'Instale mysqldump (mariadb-client)' >&2; exit 2; }
mkdir -p backups
umask 077
TMP_CONFIG=$(mktemp)
trap 'rm -f "$TMP_CONFIG"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" "$DB_USER" "$DB_PASS" > "$TMP_CONFIG"
OUT="backups/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump --defaults-extra-file="$TMP_CONFIG" --single-transaction --routines --triggers --default-character-set=utf8mb4 "$DB_NAME" | gzip -9 > "$OUT"
gzip -t "$OUT"
echo "Backup verificado em $OUT (restringir acesso e retenção conforme LGPD)."
