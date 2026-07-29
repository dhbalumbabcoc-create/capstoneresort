<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verify frontdesk role
if (!is_logged_in() || $_SESSION['user_role'] !== 'frontdesk') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$type = trim($_GET['type'] ?? '');
$today = date('Y-m-d');

if (empty($type)) {
    echo json_encode(['success' => false, 'error' => 'Missing detail type']);
    exit();
}

$query = "";
$params = [];
$types_str = "";

switch ($type) {
    case 'today_bookings':
        $query = "SELECT b.id, b.guest_name, b.guest_phone, b.guest_email, b.check_in_date, b.check_out_date, 
                         b.mode, b.booking_type, b.status, b.total_price, b.created_at, b.notes,
                         f.name as facility_name, f.type as facility_type, a.name as area_name,
                         (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE booking_id = b.id AND status = 'completed') as total_paid
                  FROM bookings b
                  JOIN facilities f ON b.facility_id = f.id
                  LEFT JOIN areas a ON b.area_id = a.id
                  WHERE DATE(b.created_at) = ?
                  ORDER BY b.created_at DESC";
        $params = [$today];
        $types_str = "s";
        break;

    case 'pending_online':
        $query = "SELECT b.id, b.guest_name, b.guest_phone, b.guest_email, b.check_in_date, b.check_out_date, 
                         b.mode, b.booking_type, b.status, b.total_price, b.created_at, b.notes,
                         f.name as facility_name, f.type as facility_type, a.name as area_name,
                         (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE booking_id = b.id AND status = 'completed') as total_paid
                  FROM bookings b
                  JOIN facilities f ON b.facility_id = f.id
                  LEFT JOIN areas a ON b.area_id = a.id
                  WHERE b.booking_type = 'online' AND b.status = 'pending'
                  ORDER BY b.created_at DESC";
        break;

    case 'today_checkins':
        $query = "SELECT b.id, b.guest_name, b.guest_phone, b.guest_email, b.check_in_date, b.check_out_date, 
                         b.mode, b.booking_type, b.status, b.total_price, b.created_at, b.notes,
                         f.name as facility_name, f.type as facility_type, a.name as area_name,
                         (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE booking_id = b.id AND status = 'completed') as total_paid
                  FROM bookings b
                  JOIN facilities f ON b.facility_id = f.id
                  LEFT JOIN areas a ON b.area_id = a.id
                  WHERE DATE(b.check_in_date) = ? AND b.status IN ('pending', 'confirmed')
                  ORDER BY b.created_at DESC";
        $params = [$today];
        $types_str = "s";
        break;

    case 'today_checkouts':
        $query = "SELECT b.id, b.guest_name, b.guest_phone, b.guest_email, b.check_in_date, b.check_out_date, 
                         b.mode, b.booking_type, b.status, b.total_price, b.created_at, b.notes,
                         f.name as facility_name, f.type as facility_type, a.name as area_name,
                         (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE booking_id = b.id AND status = 'completed') as total_paid
                  FROM bookings b
                  JOIN facilities f ON b.facility_id = f.id
                  LEFT JOIN areas a ON b.area_id = a.id
                  WHERE DATE(b.check_out_date) = ? AND b.status IN ('pending', 'confirmed')
                  ORDER BY b.created_at DESC";
        $params = [$today];
        $types_str = "s";
        break;

    case 'occupied_facilities':
        // Return rich facility + occupant data
        $query = "SELECT b.id as booking_id, b.guest_name, b.guest_phone, b.guest_email,
                         b.check_in_date, b.check_out_date, b.mode, b.booking_type,
                         b.status, b.total_price, b.created_at, b.notes,
                         b.num_guests, b.num_adults, b.num_discounted, b.num_children,
                         f.id as facility_id, f.name as facility_name, f.type as facility_type,
                         f.capacity, f.price as facility_price, f.amenities,
                         f.image_path,
                         a.name as area_name,
                         (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE booking_id = b.id AND status = 'completed') as total_paid
                  FROM bookings b
                  JOIN facilities f ON b.facility_id = f.id
                  LEFT JOIN areas a ON b.area_id = a.id
                  WHERE b.status IN ('pending', 'confirmed')
                    AND b.check_out_date >= ?
                  ORDER BY f.type ASC, f.name ASC, b.check_in_date ASC";
        $params = [$today];
        $types_str = "s";
        break;

    case 'today_revenue':
        $query = "SELECT b.id, b.guest_name, b.guest_phone, b.guest_email, b.check_in_date, b.check_out_date, 
                         b.mode, b.booking_type, b.status, b.total_price, b.created_at, b.notes,
                         f.name as facility_name, f.type as facility_type, a.name as area_name,
                         (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE booking_id = b.id AND status = 'completed') as total_paid
                  FROM bookings b
                  JOIN facilities f ON b.facility_id = f.id
                  LEFT JOIN areas a ON b.area_id = a.id
                  WHERE DATE(b.created_at) = ? AND b.status IN ('completed', 'pending', 'confirmed')
                  ORDER BY b.created_at DESC";
        $params = [$today];
        $types_str = "s";
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid detail type']);
        exit();
}

$stmt = $conn->prepare($query);
if ($types_str) {
    $stmt->bind_param($types_str, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$bookings = [];
while ($row = $res->fetch_assoc()) {
    // Normalize id: occupied_facilities uses booking_id alias
    if (!isset($row['id']) && isset($row['booking_id'])) {
        $row['id'] = $row['booking_id'];
    }

    // Parse time slot for daytour from notes
    $b_slot = 'full_day';
    if (isset($row['mode']) && $row['mode'] !== 'overnight') {
        if (preg_match('/Time Slot:\s*([a-z0-9_\-]+)/i', $row['notes'] ?? '', $m)) {
            $b_slot = strtolower($m[1]);
        }
    }
    $row['time_slot'] = $b_slot;

    // Format creation time and check-in/out for easier JSON reading
    $row['created_at_fmt'] = isset($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : '';
    $row['check_in_fmt']   = date('M d, Y', strtotime($row['check_in_date']));
    $row['check_out_fmt']  = date('M d, Y', strtotime($row['check_out_date']));
    $row['total_price']    = floatval($row['total_price'] ?? 0);

    // Process payments and remaining balance
    $booking_type = strtolower($row['booking_type'] ?? '');
    $total_price  = floatval($row['total_price'] ?? 0);
    $total_paid   = isset($row['total_paid']) ? floatval($row['total_paid']) : 0.0;

    // For walk-in bookings that are confirmed/completed and have no payments recorded, assume fully paid
    if (($booking_type === 'walkin' || $booking_type === 'walk-in') && $total_paid == 0 && in_array($row['status'], ['confirmed', 'completed'])) {
        $total_paid = $total_price;
    }

    $row['total_paid'] = $total_paid;
    $row['remaining_balance'] = max(0.0, $total_price - $total_paid);

    // Compute nights / days remaining until check-out
    $checkout_ts = strtotime($row['check_out_date']);
    $today_ts    = strtotime(date('Y-m-d'));
    $diff_days   = (int)(($checkout_ts - $today_ts) / 86400);
    $row['days_remaining'] = $diff_days; // 0 = checking out today, >0 = still staying

    // Compute days until check-in for upcoming bookings
    $checkin_ts  = strtotime($row['check_in_date']);
    $row['days_until_checkin'] = (int)(($checkin_ts - $today_ts) / 86400);
    $row['is_upcoming'] = ($checkin_ts > $today_ts);

    $bookings[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'type' => $type,
    'bookings' => $bookings
]);
exit();
