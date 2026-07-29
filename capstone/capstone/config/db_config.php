<?php
// Database Configuration & Global Timezone
date_default_timezone_set('Asia/Manila');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'resort_management');

// ── 1. Connect (auto-create DB if missing) ───────────────────────────────────
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_errno) {
    // Error 1049 = Unknown database — create it then reconnect
    if ((int)$conn->connect_errno === 1049) {
        $bootstrapConn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
        if ($bootstrapConn->connect_error) {
            die("Connection failed: " . $bootstrapConn->connect_error);
        }
        $safeDbName = str_replace('`', '``', DB_NAME);
        if (!$bootstrapConn->query("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
            die("Database creation failed: " . $bootstrapConn->error);
        }
        $bootstrapConn->close();
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// ── 2. Auto-import full schema on first run (when `users` table is missing) ──
$coreTableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if (!($coreTableCheck instanceof mysqli_result) || $coreTableCheck->num_rows === 0) {
    $schemaPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'resort_db.sql';
    if (!file_exists($schemaPath) || !is_readable($schemaPath)) {
        die("Database setup failed: schema file not found at {$schemaPath}");
    }
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        die("Database setup failed: unable to read schema file.");
    }
    // Suppress strict mode errors during bulk import
    mysqli_report(MYSQLI_REPORT_OFF);
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }
    if ($conn->errno) {
        die("Database setup failed: " . $conn->error);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

// ── 3. Define base URL ────────────────────────────────────────────────────────
define('BASE_URL', 'http://localhost/capstone/capstone/');

// ── 4. Safe guards for existing installs (missing columns / schema drift) ─────
// These are idempotent — safe to run every request on an existing DB.
// New installs get everything from resort_db.sql, so these just protect upgrades.

mysqli_report(MYSQLI_REPORT_OFF); // suppress errors for ALTER on existing columns

// --- audit_logs_v2 ---
$conn->query("CREATE TABLE IF NOT EXISTS `audit_logs_v2` (
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
  KEY `idx_event_type` (`event_type`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- guest_accounts ---
$conn->query("CREATE TABLE IF NOT EXISTS `guest_accounts` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(100) NOT NULL,
  `phone`         VARCHAR(20)  DEFAULT NULL,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- guest_otps ---
$conn->query("CREATE TABLE IF NOT EXISTS `guest_otps` (
  `id`             INT(11)     NOT NULL AUTO_INCREMENT,
  `email`          VARCHAR(100) NOT NULL,
  `guest_name`     VARCHAR(100) NOT NULL,
  `contact_number` VARCHAR(30)  NOT NULL,
  `otp`            VARCHAR(6)   NOT NULL,
  `expires_at`     DATETIME     NOT NULL,
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- password_resets ---
$conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT          NOT NULL,
  `otp`        VARCHAR(6)   NOT NULL DEFAULT '',
  `token`      VARCHAR(64)  DEFAULT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_token`   (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- guest_password_resets ---
$conn->query("CREATE TABLE IF NOT EXISTS `guest_password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guest_id`   INT          NOT NULL,
  `otp`        VARCHAR(6)   NOT NULL DEFAULT '',
  `token`      VARCHAR(64)  DEFAULT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_guest_id` (`guest_id`),
  KEY `idx_token`    (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- site_settings ---
$conn->query("CREATE TABLE IF NOT EXISTS `site_settings` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `resort_name`    VARCHAR(255) NOT NULL DEFAULT 'Sinulom and Bolao Cold Spring',
  `tagline`        VARCHAR(255) DEFAULT 'Cold Spring',
  `contact_info`   VARCHAR(255) DEFAULT '(example) 0917-123-4567',
  `business_hours` VARCHAR(255) DEFAULT '8:00 AM - 5:00 PM',
  `logo`           VARCHAR(255) DEFAULT 'logo.jpg',
  `vat_rate`       DECIMAL(5,2) NOT NULL DEFAULT 12.00,
  `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Column guards — only run ALTER when column is truly missing
// payments.reference_number
$r = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'reference_number'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE `payments` ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL AFTER `method`");
}
// payments.proof_of_payment
$r = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'proof_of_payment'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE `payments` ADD COLUMN `proof_of_payment` VARCHAR(255) DEFAULT NULL AFTER `reference_number`");
}

// bookings.status — ensure 'unpaid' is in the ENUM
$conn->query("ALTER TABLE `bookings` MODIFY `status` ENUM('unpaid','pending','confirmed','declined','cancelled','completed') DEFAULT 'pending'");

// users.status — ensure 'pending' is in the ENUM
$conn->query("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active','pending','inactive') DEFAULT 'pending'");

// bookings.num_below5
$r = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'num_below5'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE `bookings` ADD COLUMN `num_below5` INT NOT NULL DEFAULT 0 AFTER `num_children`");
}

// booking_addons.quantity
$r = $conn->query("SHOW COLUMNS FROM `booking_addons` LIKE 'quantity'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE `booking_addons` ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `amenity_id`");
}

// site_settings.vat_rate
$r = $conn->query("SHOW COLUMNS FROM `site_settings` LIKE 'vat_rate'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE `site_settings` ADD COLUMN `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 12.00");
}

// Seed site_settings row 1 if missing
$r = $conn->query("SELECT `id` FROM `site_settings` WHERE `id` = 1");
if ($r && $r->num_rows === 0) {
    $conn->query("INSERT INTO `site_settings` (`id`,`resort_name`,`tagline`,`contact_info`,`business_hours`,`logo`,`vat_rate`)
                  VALUES (1,'Sinulom and Bolao Cold Spring','Cold Spring','(example) 0917-123-4567','8:00 AM - 5:00 PM','logo.jpg',12.00)");
}

// users — staff columns
foreach ([
    'must_change_password' => "ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0",
    'email_verified'       => "ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 1",
    'setup_otp'            => "ALTER TABLE `users` ADD COLUMN `setup_otp` VARCHAR(6) DEFAULT NULL",
    'setup_otp_expires'    => "ALTER TABLE `users` ADD COLUMN `setup_otp_expires` DATETIME DEFAULT NULL",
] as $col => $alterSql) {
    $r = $conn->query("SHOW COLUMNS FROM `users` LIKE '{$col}'");
    if ($r && $r->num_rows === 0) {
        $conn->query($alterSql);
    }
}

// facilities.image_path
$r = $conn->query("SHOW COLUMNS FROM `facilities` LIKE 'image_path'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE `facilities` ADD COLUMN `image_path` VARCHAR(255) DEFAULT NULL");
}

// Repair missing AUTO_INCREMENT on primary keys (legacy installs)
foreach ([
    ['facilities', 'id',       false],
    ['amenities',  'id',       false],
    ['audit_logs', 'audit_id', true],
] as [$tbl, $col, $needsPK]) {
    $r = $conn->query("SHOW COLUMNS FROM `{$tbl}` LIKE '{$col}'");
    if ($r && $row = $r->fetch_assoc()) {
        if (strpos($row['Extra'], 'auto_increment') === false) {
            if ($needsPK && empty($row['Key'])) {
                $conn->query("ALTER TABLE `{$tbl}` ADD PRIMARY KEY (`{$col}`)");
            }
            // For facilities: remap any id=0 rows first
            if ($tbl === 'facilities') {
                $zeroCheck = $conn->query("SELECT `id` FROM `facilities` WHERE `id` = 0");
                if ($zeroCheck && $zeroCheck->num_rows > 0) {
                    $maxRes = $conn->query("SELECT MAX(`id`) AS max_id FROM `facilities` WHERE `id` > 0");
                    $maxId  = ($maxRes && $mr = $maxRes->fetch_assoc()) ? intval($mr['max_id']) : 0;
                    $newId  = max(10, $maxId + 1);
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                    $conn->query("UPDATE `bookings`      SET `facility_id` = {$newId} WHERE `facility_id` = 0");
                    $conn->query("UPDATE `booking_addons` SET `facility_id` = {$newId} WHERE `facility_id` = 0");
                    $conn->query("UPDATE `maintenance`   SET `facility_id` = {$newId} WHERE `facility_id` = 0");
                    $conn->query("UPDATE `facilities`    SET `id`          = {$newId} WHERE `id` = 0");
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                }
            }
            $conn->query("ALTER TABLE `{$tbl}` MODIFY `{$col}` INT NOT NULL AUTO_INCREMENT");
        }
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>
