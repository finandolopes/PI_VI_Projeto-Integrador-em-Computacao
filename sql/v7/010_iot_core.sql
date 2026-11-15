-- +statement
CREATE TABLE IF NOT EXISTS iot_devices (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- +statement
CREATE TABLE IF NOT EXISTS iot_telemetry (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
