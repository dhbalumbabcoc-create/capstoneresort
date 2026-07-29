<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['available' => false, 'error' => 'Unauthorized']); exit(); }

$facility_id = intval($_GET['facility_id'] ?? 0);
$check_in    = trim($_GET['check_in']    ?? '');
$check_out   = trim($_GET['check_out']   ?? '');
$mode        = trim($_GET['mode']        ?? 'daytour');

if (!$facility_id || !$check_in) { echo json_encode(['available' => true]); exit(); }

$booked = false;

if ($mode === 'overnight' && $check_out) {
    $av = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed') AND check_in_date < ? AND check_out_date > ? LIMIT 1");
    $av->bind_param("iss", $facility_id, $check_out, $check_in);
    $av->execute(); $av->store_result();
    $booked = $av->num_rows > 0;
    $av->close();
} else {
    // Check overnight covering this day
    $av = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed') AND mode='overnight' AND check_in_date <= ? AND check_out_date > ? LIMIT 1");
    $av->bind_param("iss", $facility_id, $check_in, $check_in);
    $av->execute(); $av->store_result();
    $booked = $av->num_rows > 0;
    $av->close();
    // Check same-date booking
    if (!$booked) {
        $av2 = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed') AND check_in_date=? LIMIT 1");
        $av2->bind_param("is", $facility_id, $check_in);
        $av2->execute(); $av2->store_result();
        $booked = $av2->num_rows > 0;
        $av2->close();
    }
}

echo json_encode(['available' => !$booked]);
