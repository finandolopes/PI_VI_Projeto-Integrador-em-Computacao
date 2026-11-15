-- +statement
CREATE TABLE IF NOT EXISTS crm_activity_v7 (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 client_id INT NOT NULL, request_id INT NULL, action VARCHAR(60) NOT NULL,
 previous_status VARCHAR(40) NULL, current_status VARCHAR(40) NULL,
 actor VARCHAR(130) NOT NULL, note VARCHAR(1000) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_crm_client (client_id,created_at), INDEX idx_crm_request (request_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- +statement
CREATE TABLE IF NOT EXISTS finance_entries_v7 (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 idempotency_key CHAR(64) NOT NULL UNIQUE,
 entry_type ENUM('receber','pagar') NOT NULL,
 client_id INT NULL, request_id INT NULL, description VARCHAR(240) NOT NULL,
 amount DECIMAL(14,2) NOT NULL, due_date DATE NULL,
 status ENUM('rascunho','programado','liquidado','cancelado') NOT NULL DEFAULT 'rascunho',
 source ENUM('manual','importacao') NOT NULL DEFAULT 'manual',
 created_by VARCHAR(130) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_finance_due (due_date,status), INDEX idx_finance_client (client_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- +statement
CREATE TABLE IF NOT EXISTS cf_operational_events_v7 (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entity_type ENUM('chamado','dispositivo','requisicao','financeiro','sistema') NOT NULL,
 entity_id VARCHAR(64) NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 actor VARCHAR(130) NOT NULL,
 summary VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_event_entity (entity_type,entity_id,created_at), INDEX idx_event_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- +statement
CREATE TABLE IF NOT EXISTS cf_job_runs_v7 (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_name VARCHAR(80) NOT NULL,
 outcome ENUM('ok','erro') NOT NULL,
 duration_ms INT UNSIGNED NULL,
 summary VARCHAR(300) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_job_name_time (job_name,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- +statement
CREATE TABLE IF NOT EXISTS cf_api_errors_v7 (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 component VARCHAR(60) NOT NULL, category VARCHAR(60) NOT NULL,
 request_id CHAR(16) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_api_error_component (component,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- +statement
CREATE TABLE IF NOT EXISTS cf_user_prefs_v7 (
 user_key VARCHAR(130) NOT NULL, preference_key VARCHAR(60) NOT NULL,
 preference_value VARCHAR(200) NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(user_key,preference_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
