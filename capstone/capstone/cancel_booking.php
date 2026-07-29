<?php
// cancel_booking.php
require_once 'config/db_config.php';
header('Content-Type: application/json');

$booking_id = intval($_POST['booking_id'] ?? 0);
if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit;
}

$stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
$stmt->bind_param('i', $booking_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel booking']);
}
$stmt->close();
