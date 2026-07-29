-- Audit Logs v2 — tracks login, logout, unauthorized access, and page visits
-- Run this once against the resort_management database

CREATE TABLE IF NOT EXISTS `audit_logs_v2` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      DEFAULT NULL COMMENT 'NULL = unauthenticated attempt',
  `username`    VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot of username at time of event',
  `role`        VARCHAR(50)  DEFAULT NULL COMMENT 'Snapshot of role at time of event',
  `event_type`  ENUM(
                  'login_success',
                  'login_failed',
                  'logout',
                  'unauthorized_access',
                  'page_access'
                ) NOT NULL,
  `page`        VARCHAR(255) DEFAULT NULL COMMENT 'Script path that was accessed',
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(500) DEFAULT NULL,
  `details`     TEXT         DEFAULT NULL COMMENT 'Extra context (e.g. attempted role)',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
