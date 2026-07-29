<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_role('owner');

// Add vat_rate column if not exists
$conn->query("ALTER TABLE site_settings ADD COLUMN IF NOT EXISTS vat_rate DECIMAL(5,2) NOT NULL DEFAULT 12.00");
echo "<p style='font-family:sans-serif;padding:20px;'>✅ Migration done — <code>vat_rate</code> column added to <code>site_settings</code>.<br><a href='settings.php'>← Back to Settings</a></p>";
