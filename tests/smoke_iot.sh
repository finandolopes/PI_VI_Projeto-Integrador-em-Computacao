#!/usr/bin/env bash
set -euo pipefail
: "${BASE_URL:?Defina BASE_URL por exemplo http://localhost}" 
: "${IOT_API_KEY:?Defina IOT_API_KEY em ambiente local de testes}" 
DEVICE="esp32_smoke_$(date +%s)"
PAYLOAD=$(printf '{"device_id":"%s","metric":"temperatura","value":49.1,"unit":"C"}' "$DEVICE")
for i in 1 2; do
  echo "Envio $i, dispositivo $DEVICE" 
  curl --fail-with-body -sS -X POST "$BASE_URL/api/iot_ingest.php" -H 'Content-Type: application/json' -H "X-IoT-Key: $IOT_API_KEY" -d "$PAYLOAD"
  echo
 done
printf 'Confirme: regra temperatura > limite criada, primeiro evento created, segundo suppressed e ambas as leituras persistidas.\n'
