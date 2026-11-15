<?php

function iotTableExists(mysqli $conexao, string $table): bool
{
    $safe = $conexao->real_escape_string($table);
    $result = $conexao->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function ensureIotSchema(mysqli $conexao): void
{
    static $validated=false;
    if ($validated) return;
    if (!filter_var(getenv('IOT_RUNTIME_SCHEMA_COMPAT') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
        foreach (['iot_devices','iot_telemetry'] as $table) {
           $name=$conexao->real_escape_string($table);
           $result=$conexao->query("SHOW TABLES LIKE '$name'");
           if (!$result || $result->num_rows===0) throw new RuntimeException('Esquema IoT ausente. Execute scripts/migrate_v7.php.');
        }
        $validated=true;return;
    }
    $conexao->query(
        "CREATE TABLE IF NOT EXISTS iot_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(120) DEFAULT NULL,
            location VARCHAR(120) DEFAULT NULL,
            status ENUM('online','offline','maintenance') DEFAULT 'online',
            metadata JSON DEFAULT NULL,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_last_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conexao->query(
        "CREATE TABLE IF NOT EXISTS iot_telemetry (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL,
            metric VARCHAR(80) NOT NULL,
            value DECIMAL(15,4) NOT NULL,
            unit VARCHAR(20) DEFAULT NULL,
            quality_status ENUM('ok','warning','alerta') DEFAULT 'ok',
            processed_value DECIMAL(15,4) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            captured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_metric_time (device_id, metric, captured_at),
            INDEX idx_captured_at (captured_at),
            INDEX idx_quality_status (quality_status),
            CONSTRAINT fk_iot_telemetry_device FOREIGN KEY (device_id) REFERENCES iot_devices(device_id)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $validated=true;
}

function normalizeIotDatetime(?string $value): string
{
    if ($value) {
        $time = strtotime($value);
        if ($time !== false) {
            return date('Y-m-d H:i:s', $time);
        }
    }

    return date('Y-m-d H:i:s');
}

function ingestIotPayload(mysqli $conexao, array $payload): array
{
    ensureIotSchema($conexao);

    $deviceId = trim((string) ($payload['device_id'] ?? ''));
    $metric = trim((string) ($payload['metric'] ?? $payload['sensor'] ?? ''));
    $unit = trim((string) ($payload['unit'] ?? ''));
    $name = trim((string) ($payload['device_name'] ?? $payload['name'] ?? ''));
    $location = trim((string) ($payload['location'] ?? ''));

    if ($deviceId === '' || !preg_match('/^[A-Za-z0-9_.:-]{3,64}$/', $deviceId)) {
        throw new InvalidArgumentException('device_id inválido');
    }

    if ($metric === '' || !preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', $metric)) {
        throw new InvalidArgumentException('metric inválido');
    }

    if (strlen($name) > 120 || strlen($location) > 120 || strlen($unit) > 20) {
        throw new InvalidArgumentException('Metadados do dispositivo excedem os limites');
    }

    if (!isset($payload['value']) || !is_numeric($payload['value'])) {
        throw new InvalidArgumentException('value deve ser numérico');
    }

    $value = (float) $payload['value'];
    if (!is_finite($value)) { throw new InvalidArgumentException("value fora do intervalo permitido"); }
    $processedValue = round($value, 4);

    $min = isset($payload['min']) && is_numeric($payload['min']) ? (float) $payload['min'] : null;
    $max = isset($payload['max']) && is_numeric($payload['max']) ? (float) $payload['max'] : null;

    $quality = 'ok';
    if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
        $quality = 'alerta';
    }

    $capturedAt = normalizeIotDatetime(isset($payload['captured_at']) ? (string) $payload['captured_at'] : null);

    $deviceMeta = $payload['device_metadata'] ?? null;
    $readingMeta = $payload['metadata'] ?? null;

    $deviceMetaJson = $deviceMeta !== null ? json_encode($deviceMeta, JSON_UNESCAPED_UNICODE) : null;
    $readingMetaJson = $readingMeta !== null ? json_encode($readingMeta, JSON_UNESCAPED_UNICODE) : null;

    $stmtDevice = $conexao->prepare(
        "INSERT INTO iot_devices (device_id, name, location, status, metadata, last_seen)
         VALUES (?, NULLIF(?, ''), NULLIF(?, ''), 'online', ?, NOW())
         ON DUPLICATE KEY UPDATE
            name = COALESCE(NULLIF(VALUES(name), ''), name),
            location = COALESCE(NULLIF(VALUES(location), ''), location),
            status = 'online',
            metadata = COALESCE(VALUES(metadata), metadata),
            last_seen = NOW()"
    );

    if (!$stmtDevice) {
        throw new RuntimeException('Falha ao preparar upsert de device: ' . $conexao->error);
    }

    $stmtDevice->bind_param('ssss', $deviceId, $name, $location, $deviceMetaJson);
    if (!$stmtDevice->execute()) {
        $msg = $stmtDevice->error;
        $stmtDevice->close();
        throw new RuntimeException('Falha ao salvar device: ' . $msg);
    }
    $stmtDevice->close();

    $stmtTelemetry = $conexao->prepare(
        'INSERT INTO iot_telemetry (device_id, metric, value, unit, quality_status, processed_value, metadata, captured_at)
         VALUES (?, ?, ?, NULLIF(?, \'\'), ?, ?, ?, ?)' 
    );

    if (!$stmtTelemetry) {
        throw new RuntimeException('Falha ao preparar insert de telemetria: ' . $conexao->error);
    }

    $stmtTelemetry->bind_param(
        'ssdssdss',
        $deviceId,
        $metric,
        $value,
        $unit,
        $quality,
        $processedValue,
        $readingMetaJson,
        $capturedAt
    );

    if (!$stmtTelemetry->execute()) {
        $msg = $stmtTelemetry->error;
        $stmtTelemetry->close();
        throw new RuntimeException('Falha ao salvar telemetria: ' . $msg);
    }

    $insertId = (int) $stmtTelemetry->insert_id;
    $stmtTelemetry->close();

    return [
        'id' => $insertId,
        'device_id' => $deviceId,
        'metric' => $metric,
        'value' => $value,
        'unit' => $unit,
        'quality_status' => $quality,
        'captured_at' => $capturedAt,
    ];
}

function getIotMetrics(mysqli $conexao, int $windowHours = 24): array
{
    ensureIotSchema($conexao);

    $windowHours = max(1, min(168, $windowHours));

    $metrics = [
        'devices_online' => 0,
        'devices_total' => 0,
        'readings_last_hour' => 0,
        'readings_window' => 0,
        'alerts_window' => 0,
        'avg_value_window' => 0,
        'latest' => [],
    ];

    $sqlDevicesOnline = "SELECT COUNT(*) AS total FROM iot_devices WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    $sqlDevicesTotal = "SELECT COUNT(*) AS total FROM iot_devices";
    $sqlReadingsHour = "SELECT COUNT(*) AS total FROM iot_telemetry WHERE captured_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $sqlWindow = "SELECT COUNT(*) AS total, COALESCE(AVG(processed_value),0) AS media FROM iot_telemetry WHERE captured_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
    $sqlAlerts = "SELECT COUNT(*) AS total FROM iot_telemetry WHERE quality_status = 'alerta' AND captured_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
    $sqlLatest = "SELECT device_id, metric, value, unit, quality_status, captured_at FROM iot_telemetry ORDER BY captured_at DESC LIMIT 12";

    if ($result = $conexao->query($sqlDevicesOnline)) {
        $metrics['devices_online'] = (int) ($result->fetch_assoc()['total'] ?? 0);
    }
    if ($result = $conexao->query($sqlDevicesTotal)) {
        $metrics['devices_total'] = (int) ($result->fetch_assoc()['total'] ?? 0);
    }
    if ($result = $conexao->query($sqlReadingsHour)) {
        $metrics['readings_last_hour'] = (int) ($result->fetch_assoc()['total'] ?? 0);
    }

    $stmtWindow = $conexao->prepare($sqlWindow);
    if ($stmtWindow) {
        $stmtWindow->bind_param('i', $windowHours);
        if ($stmtWindow->execute()) {
            $row = $stmtWindow->get_result()->fetch_assoc();
            $metrics['readings_window'] = (int) ($row['total'] ?? 0);
            $metrics['avg_value_window'] = round((float) ($row['media'] ?? 0), 2);
        }
        $stmtWindow->close();
    }

    $stmtAlerts = $conexao->prepare($sqlAlerts);
    if ($stmtAlerts) {
        $stmtAlerts->bind_param('i', $windowHours);
        if ($stmtAlerts->execute()) {
            $row = $stmtAlerts->get_result()->fetch_assoc();
            $metrics['alerts_window'] = (int) ($row['total'] ?? 0);
        }
        $stmtAlerts->close();
    }

    if ($result = $conexao->query($sqlLatest)) {
        while ($row = $result->fetch_assoc()) {
            $metrics['latest'][] = [
                'device_id' => (string) ($row['device_id'] ?? ''),
                'metric' => (string) ($row['metric'] ?? ''),
                'value' => (float) ($row['value'] ?? 0),
                'unit' => (string) ($row['unit'] ?? ''),
                'quality_status' => (string) ($row['quality_status'] ?? 'ok'),
                'captured_at' => (string) ($row['captured_at'] ?? ''),
            ];
        }
    }

    return $metrics;
}
