<?php
require_once 'config/db_config.php';
$results = [];
$results[] = ['col'=>'must_change_password', 'ok'=>$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0")];
$results[] = ['col'=>'email_verified',       'ok'=>$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 1")];
$results[] = ['col'=>'setup_otp',            'ok'=>$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS setup_otp VARCHAR(6) DEFAULT NULL")];
$results[] = ['col'=>'setup_otp_expires',    'ok'=>$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS setup_otp_expires DATETIME DEFAULT NULL")];
foreach ($results as $r) {
    echo $r['col'] . ': ' . ($r['ok'] ? 'OK' : $conn->error) . "\n";
}
echo "Done.\n";
