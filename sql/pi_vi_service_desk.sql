-- PI VI: executar no mesmo banco MySQL do projeto; não apaga estruturas existentes.
CREATE TABLE IF NOT EXISTS sd_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, protocol VARCHAR(32) NOT NULL UNIQUE,
 access_token_hash CHAR(64) NOT NULL, origin ENUM('manual','iot') NOT NULL,
 requester_name VARCHAR(130) NOT NULL, requester_email VARCHAR(190) NULL,
 title VARCHAR(180) NOT NULL, description TEXT NOT NULL,
 category VARCHAR(80) NOT NULL DEFAULT 'Geral', priority ENUM('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
 status ENUM('aberto','em_atendimento','aguardando','resolvido','fechado') NOT NULL DEFAULT 'aberto',
 assigned_to VARCHAR(130) NULL, device_id VARCHAR(64) NULL, telemetry_id BIGINT NULL, rule_id BIGINT UNSIGNED NULL,
 first_response_at DATETIME NULL, resolved_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX sd_ticket_status (status,priority,created_at), INDEX sd_ticket_device (device_id,rule_id,status),
 INDEX sd_ticket_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sd_ticket_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL,
 actor VARCHAR(130) NOT NULL, event_type VARCHAR(60) NOT NULL,
 old_value TEXT NULL, new_value TEXT NULL, note TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX sd_history_ticket (ticket_id,created_at),
 FOREIGN KEY (ticket_id) REFERENCES sd_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sd_iot_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL,
 device_id VARCHAR(64) NULL, metric VARCHAR(80) NOT NULL,
 operator ENUM('gt','gte','lt','lte') NOT NULL, threshold DECIMAL(15,4) NOT NULL,
 priority ENUM('baixa','media','alta','critica') NOT NULL DEFAULT 'alta',
 cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 900,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX sd_rule_match (metric,enabled,device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sd_iot_rule_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rule_id BIGINT UNSIGNED NOT NULL,
 device_id VARCHAR(64) NOT NULL, telemetry_id BIGINT NOT NULL,
 ticket_id BIGINT UNSIGNED NULL, outcome ENUM('created','suppressed') NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX sd_event_dedupe (device_id,rule_id,created_at),
 FOREIGN KEY (rule_id) REFERENCES sd_iot_rules(id),
 FOREIGN KEY (ticket_id) REFERENCES sd_tickets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
