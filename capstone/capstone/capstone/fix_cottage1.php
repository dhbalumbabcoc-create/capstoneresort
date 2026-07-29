<?php
require_once 'config/db_config.php';

// Show all facilities and their status
$r = $conn->query("SELECT id, name, status FROM facilities ORDER BY name");
echo "Current facilities:\n";
while ($row = $r->fetch_assoc()) {
    echo "ID: {$row['id']} | {$row['name']} | Status: {$row['status']}\n";
}

// Fix: set Cottage 1 (and any other archived facilities) back to available
$fix = $conn->query("UPDATE facilities SET status = 'available' WHERE name = 'Cottage 1' AND status = 'archived'");
echo "\nFixed Cottage 1: " . ($conn->affected_rows > 0 ? "Updated to available" : "No change needed") . "\n";

// Show updated list
$r2 = $conn->query("SELECT id, name, status FROM facilities ORDER BY name");
echo "\nUpdated facilities:\n";
while ($row = $r2->fetch_assoc()) {
    echo "ID: {$row['id']} | {$row['name']} | Status: {$row['status']}\n";
}
