<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verify frontdesk role
if (!is_logged_in() || $_SESSION['user_role'] !== 'frontdesk') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$target_date = trim($_GET['date'] ?? date('Y-m-d'));
$slot = trim($_GET['slot'] ?? 'full_day');

if (empty($target_date)) {
    $target_date = date('Y-m-d');
}

// Calculate target intervals and slots
$check_in = $target_date;
if ($slot === 'overnight') {
    $mode = 'overnight';
    $time_slot = '';
    $check_out = date('Y-m-d', strtotime($target_date . ' +1 day'));
} else {
    $mode = 'daytour';
    $time_slot = $slot;
    $check_out = $target_date;
}

// Fetch all non-archived facilities
$facilities_query = "SELECT f.*, a.name as area_name 
                     FROM facilities f 
                     LEFT JOIN areas a ON f.area_id = a.id 
                     WHERE f.status != 'archived'
                     ORDER BY f.type, f.name";
$facilities_res = $conn->query($facilities_query);
$facilities = [];
if ($facilities_res) {
    while ($row = $facilities_res->fetch_assoc()) {
        $facilities[$row['id']] = [
            'id' => intval($row['id']),
            'name' => $row['name'],
            'type' => $row['type'],
            'capacity' => intval($row['capacity']),
            'price' => floatval($row['price']),
            'status' => $row['status'], // available, maintenance, unavailable
            'area_name' => $row['area_name'] ?? 'N/A',
            'is_available' => true,
            'conflict_reason' => '',
            'schedules' => []
        ];
    }
}

// Fetch active bookings starting on/after current date or overlapping target date
// To cover everything relevant, select bookings where check_out_date >= target_date
$bookings_query = "SELECT b.id, b.facility_id, b.guest_name, b.guest_phone, b.check_in_date, b.check_out_date, b.mode, b.status, b.notes, b.total_price
                   FROM bookings b
                   WHERE b.status IN ('pending', 'confirmed') AND b.check_out_date >= ?
                   ORDER BY b.check_in_date ASC, b.id ASC";
$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("s", $target_date);
$stmt->execute();
$bookings_res = $stmt->get_result();

$all_bookings = [];
while ($row = $bookings_res->fetch_assoc()) {
    // Parse time slot for daytour bookings from notes
    $b_slot = 'full_day';
    if ($row['mode'] !== 'overnight') {
        if (preg_match('/Time Slot:\s*([a-z0-9_\-]+)/i', $row['notes'], $m)) {
            $b_slot = strtolower($m[1]);
        }
    }
    $row['time_slot'] = $b_slot;
    $all_bookings[] = $row;
}
$stmt->close();

// Process availability and populate schedules
foreach ($facilities as $fac_id => &$fac) {
    // If facility is not available by base status, mark it
    if ($fac['status'] !== 'available') {
        $fac['is_available'] = false;
        $fac['conflict_reason'] = $fac['status'] === 'maintenance' ? 'Under Maintenance' : 'Unavailable';
    }

    // Filter bookings for this specific facility
    foreach ($all_bookings as $booking) {
        if (intval($booking['facility_id']) !== $fac_id) {
            continue;
        }

        // Add to facility schedule
        $fac['schedules'][] = [
            'id' => intval($booking['id']),
            'guest_name' => $booking['guest_name'],
            'check_in_date' => $booking['check_in_date'],
            'check_out_date' => $booking['check_out_date'],
            'mode' => $booking['mode'],
            'time_slot' => $booking['time_slot'],
            'status' => $booking['status']
        ];

        // Check conflict with the target date/slot if facility is still considered available
        if ($fac['is_available']) {
            $isOverlap = false;
            $bStart = $booking['check_in_date'];
            $bEnd = $booking['check_out_date'];
            $b_slot = $booking['time_slot'];

            if ($booking['mode'] === 'overnight') {
                if ($mode === 'overnight') {
                    // Both overnight: overlap if intervals overlap
                    $isOverlap = (max($check_in, $bStart) < min($check_out, $bEnd));
                } else {
                    // Target is daytour: overlaps if target day falls within booking overnight duration
                    $isOverlap = ($check_in >= $bStart && $check_in < $bEnd);
                }
            } else {
                // Booking is daytour
                if ($mode === 'overnight') {
                    // Target is overnight: overlaps if booking day is inside target overnight interval
                    $isOverlap = ($bStart >= $check_in && $bStart < $check_out);
                } else {
                    // Both are daytours: overlaps if same date and slots conflict
                    if ($check_in === $bStart) {
                        if ($time_slot === $b_slot) {
                            $isOverlap = true;
                        } else {
                            $full_slots = ['full_day', '8am-5pm'];
                            $part_slots = ['8am-12pm', '12pm-5pm'];
                            if (in_array($time_slot, $full_slots) && in_array($b_slot, $part_slots)) {
                                $isOverlap = true;
                            } elseif (in_array($time_slot, $part_slots) && in_array($b_slot, $full_slots)) {
                                $isOverlap = true;
                            } elseif (in_array($time_slot, $full_slots) && in_array($b_slot, $full_slots)) {
                                $isOverlap = true;
                            }
                        }
                    }
                }
            }

            if ($isOverlap) {
                $fac['is_available'] = false;
                $slot_display = $booking['mode'] === 'overnight' ? 'Overnight' : 'Daytour (' . str_replace('_', ' ', $b_slot) . ')';
                $fac['conflict_reason'] = "Booked: {$slot_display} by {$booking['guest_name']} (#{$booking['id']})";
            }
        }
    }
}
unset($fac); // break reference

// Return clean indexed array
echo json_encode([
    'success' => true,
    'date' => $target_date,
    'slot' => $slot,
    'facilities' => array_values($facilities)
]);
exit();
