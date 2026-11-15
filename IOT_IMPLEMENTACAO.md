# Implementação IoT - CONFINTER

## O que foi implementado
- Serviço central IoT: `php/iot_service.php`
- API de ingestão: `api/iot_ingest.php`
- API de métricas: `api/iot_metrics.php`
- Integração no monitoramento em tempo real: `admin/monitoramento.php`
- Integração de métricas IoT na API principal: `api/get_dados_tempo_real.php`

## Esquema de dados
O módulo cria automaticamente (se não existir):
- `iot_devices`: cadastro e status dos dispositivos
- `iot_telemetry`: leituras de sensores + status de qualidade

## Captura de dados (ingestão)
Endpoint:
- `POST /api/iot_ingest.php`

Headers opcionais:
- `Content-Type: application/json`
- `X-IoT-Key: <chave>` (obrigatório se `IOT_API_KEY` estiver definido no ambiente)

Payload mínimo:
```json
{
  "device_id": "sensor_filial_01",
  "metric": "temperatura",
  "value": 24.7
}
```

Payload completo:
```json
{
  "device_id": "sensor_filial_01",
  "device_name": "Sensor Ambiente Recepção",
  "location": "Filial Poá - Recepção",
  "metric": "temperatura",
  "value": 24.7,
  "unit": "C",
  "min": 18,
  "max": 27,
  "captured_at": "2026-02-25 16:10:00",
  "metadata": {
    "battery": 84,
    "signal": "strong"
  }
}
```

## Análise e tratamento
- Normalização de valor (`processed_value`)
- Classificação de qualidade (`ok`, `warning`, `alerta`)
- Upsert de dispositivo com atualização de `last_seen`
- Agregações para dashboard:
  - dispositivos online
  - leituras na última hora
  - alertas nas últimas 24h
  - últimas leituras

## Visualização no painel
Página:
- `admin/monitoramento.php`

Blocos IoT exibidos:
- Dispositivos online
- Leituras IoT (1h)
- Alertas IoT (24h)
- Tabela das últimas leituras

## Segurança
Se quiser exigir autenticação por chave na ingestão IoT:
- Defina a variável de ambiente `IOT_API_KEY`
- Envie a mesma chave em `X-IoT-Key`

## Teste rápido com cURL
```bash
curl -X POST http://localhost/PI-V/api/iot_ingest.php \
  -H "Content-Type: application/json" \
  -d "{\"device_id\":\"sensor_lab_01\",\"metric\":\"umidade\",\"value\":58.2,\"unit\":\"%\"}"
```

## Observações
- O módulo é resiliente: se não houver dados IoT, o painel segue funcional.
- As tabelas IoT são criadas automaticamente na primeira ingestão ou consulta de métricas.
