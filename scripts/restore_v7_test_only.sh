#!/usr/bin/env bash
set -euo pipefail
[[ $# -eq 1 ]] || { echo 'Uso: scripts/restore_v7_test_only.sh backup.sql.gz' >&2; exit 2; }
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a
[[ "${DB_NAME:-}" == *_test && "${CONFIRM_RESTORE:-}" == 'test_only' ]] || { echo 'RECUSADO: somente DB_NAME *_test e CONFIRM_RESTORE=test_only' >&2;exit 3;}
command -v mysql >/dev/null || exit 2
gzip -t "$1"
TMP_CONFIG=$(mktemp)
trap 'rm -f "$TMP_CONFIG"' EXIT
umask 077
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" "${DB_USER:?}" "${DB_PASS:?}" > "$TMP_CONFIG"
gzip -dc "$1" | mysql --defaults-extra-file="$TMP_CONFIG" "$DB_NAME"
echo 'Restauração de TESTE executada. Validar contagens/integridade antes de aprovação.'
