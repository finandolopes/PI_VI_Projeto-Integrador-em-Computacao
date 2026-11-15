#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
echo '[1/4] Sintaxe PHP'
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l > /tmp/confinter_v7_lint.txt
if grep -E 'Parse error|Fatal error|Errors parsing' /tmp/confinter_v7_lint.txt; then exit 1; fi
echo "PHP OK: $(wc -l < /tmp/confinter_v7_lint.txt) arquivos"
echo '[2/4] Regras e validações'
php tests/test_v7_unit.php
php tests/test_sla.php
php tests/test_policies.php
php tests/test_notifications_allocation.php
echo '[3/4] Scripts JavaScript'
node --check assets/js/confinter-service-scenes.js
node --check admin/assets/js/confinter-admin-design-system.js
echo '[4/4] Disponibilidade das migrações'
for f in sql/v7/{001_core_baseline,010_iot_core,020_v7_operations,030_service_desk,040_notifications}.sql; do test -s "$f" || exit 1; done
echo 'PASSOU: verificações sem banco. Homologação com banco e navegador ainda necessária.'
