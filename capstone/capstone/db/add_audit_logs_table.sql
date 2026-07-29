-- Audit Logs Table
CREATE TABLE IF NOT EXISTS `audit_logs_v2` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`     INT             DEFAULT NULL,
  `username`    VARCHAR(100)    DEFAULT NULL,
  `role`        VARCHAR(50)     DEFAULT NULL,
  `event_type`  VARCHAR(50)     NOT NULL,
  `page`        VARCHAR(255)    DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(500)    DEFAULT NULL,
  `details`     TEXT            DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_type`  (`event_type`),
  KEY `idx_user_id`     (`user_id`),
  KEY `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
