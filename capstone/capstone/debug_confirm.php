<?php
require 'config/db_config.php';

$fixes = [];
$errors = [];

// 1. Add quantity column to booking_addons if missing
$cols_r = $conn->query("SHOW COLUMNS FROM booking_addons LIKE 'quantity'");
if ($cols_r && $cols_r->num_rows === 0) {
    if ($conn->query("ALTER TABLE booking_addons ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER amenity_id")) {
        $fixes[] = "Added 'quantity' column to booking_addons";
    } else {
        $errors[] = "Failed to add quantity: " . $conn->error;
    }
} else {
    $fixes[] = "'quantity' column already exists in booking_addons";
}

// 2. Add num_below5 to bookings if missing (should already exist)
$cols_r2 = $conn->query("SHOW COLUMNS FROM bookings LIKE 'num_below5'");
if ($cols_r2 && $cols_r2->num_rows === 0) {
    if ($conn->query("ALTER TABLE bookings ADD COLUMN num_below5 INT NOT NULL DEFAULT 0 AFTER num_children")) {
        $fixes[] = "Added 'num_below5' column to bookings";
    } else {
        $errors[] = "Failed to add num_below5: " . $conn->error;
    }
} else {
    $fixes[] = "'num_below5' column already exists in bookings";
}

// 3. Add facility_id to booking_addons if missing (already present)
$cols_r3 = $conn->query("SHOW COLUMNS FROM booking_addons LIKE 'facility_id'");
if ($cols_r3 && $cols_r3->num_rows === 0) {
    if ($conn->query("ALTER TABLE booking_addons ADD COLUMN facility_id INT DEFAULT NULL AFTER booking_id")) {
        $fixes[] = "Added 'facility_id' column to booking_addons";
    } else {
        $errors[] = "Failed to add facility_id to booking_addons: " . $conn->error;
    }
} else {
    $fixes[] = "'facility_id' column already exists in booking_addons";
}

echo "=== Schema Fixes ===\n";
foreach ($fixes as $f) echo "[OK] $f\n";
foreach ($errors as $e) echo "[ERR] $e\n";
echo "\nDone!\n";
