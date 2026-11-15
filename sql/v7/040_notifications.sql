
-- +statement
CREATE TABLE IF NOT EXISTS sd_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 alert_key VARCHAR(191) NOT NULL UNIQUE,
 kind ENUM('sla_resposta','sla_resolucao','dispositivo_inativo') NOT NULL,
 reference_id VARCHAR(64) NOT NULL,
 severity ENUM('aviso','critico') NOT NULL DEFAULT 'aviso',
 title VARCHAR(190) NOT NULL,
 detail TEXT NULL,
 first_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 acknowledged_at DATETIME NULL,
 acknowledged_by VARCHAR(130) NULL,
 acknowledgments INT UNSIGNED NOT NULL DEFAULT 0,
 INDEX idx_notification_open (resolved_at,acknowledged_at,last_detected_at),
 INDEX idx_notification_kind (kind,reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- +statement
CREATE TABLE IF NOT EXISTS sd_notification_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notification_id BIGINT UNSIGNED NOT NULL,
 action ENUM('created','reopened','acknowledged','resolved') NOT NULL,
 actor VARCHAR(130) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 note VARCHAR(500) NULL,
 INDEX idx_notification_history (notification_id,created_at),
 FOREIGN KEY(notification_id) REFERENCES sd_notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
