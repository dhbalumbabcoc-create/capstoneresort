<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verify frontdesk role
if (!is_logged_in() || $_SESSION['user_role'] !== 'frontdesk') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$facility_id = intval($_GET['facility_id'] ?? 0);
$days_ahead   = min(intval($_GET['days'] ?? 365), 365); // max 365 days

if ($facility_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid facility_id']);
    exit();
}

// Fetch facility info
$fac_stmt = $conn->prepare("SELECT f.*, a.name as area_name FROM facilities f LEFT JOIN areas a ON f.area_id = a.id WHERE f.id = ?");
$fac_stmt->bind_param("i", $facility_id);
$fac_stmt->execute();
$fac_res = $fac_stmt->get_result();
$facility = $fac_res->fetch_assoc();
$fac_stmt->close();

if (!$facility) {
    echo json_encode(['success' => false, 'error' => 'Facility not found']);
    exit();
}

// Date range: today → today + days_ahead
$today      = date('Y-m-d');
$range_end  = date('Y-m-d', strtotime($today . " +{$days_ahead} days"));

// Fetch all active/pending bookings that overlap with our date range
$bk_stmt = $conn->prepare(
    "SELECT b.id, b.guest_name, b.check_in_date, b.check_out_date, b.mode, b.status, b.notes
     FROM bookings b
     WHERE b.facility_id = ?
       AND b.status IN ('pending', 'confirmed')
       AND b.check_out_date >= ?
       AND b.check_in_date  <= ?
     ORDER BY b.check_in_date ASC"
);
$bk_stmt->bind_param("iss", $facility_id, $today, $range_end);
$bk_stmt->execute();
$bk_res = $bk_stmt->get_result();

$bookings = [];
while ($row = $bk_res->fetch_assoc()) {
    // Parse time slot from notes
    $b_slot = 'full_day';
    if ($row['mode'] !== 'overnight') {
        if (preg_match('/Time Slot:\s*([a-z0-9_\-]+)/i', $row['notes'], $m)) {
            $b_slot = strtolower($m[1]);
        }
    }
    $row['time_slot'] = $b_slot;
    $bookings[] = $row;
}
$bk_stmt->close();

// Build per-day availability map
// For each day, we track which slots are booked
// Slots: morning (8am-12pm), afternoon (12pm-5pm), overnight
$days = [];
$cursor = new DateTime($today);
$end    = new DateTime($range_end);

while ($cursor <= $end) {
    $date_str = $cursor->format('Y-m-d');

    $day_info = [
        'date'      => $date_str,
        'bookings'  => [],     // bookings active on this day
        // slot availability: true = available
        'morning_available'   => true,
        'afternoon_available' => true,
        'overnight_available' => true,
        'fully_booked'        => false,
    ];

    foreach ($bookings as $bk) {
        $bk_start = $bk['check_in_date'];
        $bk_end   = $bk['check_out_date'];
        $active   = false;

        if ($bk['mode'] === 'overnight') {
            // Overnight: active from check_in up to (but not including) check_out
            $active = ($date_str >= $bk_start && $date_str < $bk_end);
        } else {
            // Daytour: active only on check_in_date
            $active = ($date_str === $bk_start);
        }

        if (!$active) continue;

        $slot = $bk['time_slot'];
        $label = '';

        if ($bk['mode'] === 'overnight') {
            $day_info['overnight_available']  = false;
            $day_info['morning_available']    = false;
            $day_info['afternoon_available']  = false;
            $label = 'Overnight';
        } else {
            $full_slots = ['full_day', '8am-5pm'];
            $morning_slots = ['8am-12pm'];
            $afternoon_slots = ['12pm-5pm'];

            if (in_array($slot, $full_slots)) {
                $day_info['morning_available']    = false;
                $day_info['afternoon_available']  = false;
                $day_info['overnight_available']  = false;
                $label = 'Full Day';
            } elseif (in_array($slot, $morning_slots)) {
                $day_info['morning_available']   = false;
                $day_info['overnight_available'] = false;
                $label = 'Morning';
            } elseif (in_array($slot, $afternoon_slots)) {
                $day_info['afternoon_available']  = false;
                $day_info['overnight_available']  = false;
                $label = 'Afternoon';
            }
        }

        $day_info['bookings'][] = [
            'id'         => intval($bk['id']),
            'guest_name' => $bk['guest_name'],
            'mode'       => $bk['mode'],
            'slot_label' => $label,
            'status'     => $bk['status'],
            'check_in'   => $bk['check_in_date'],
            'check_out'  => $bk['check_out_date'],
        ];
    }

    // Fully booked = all three main slots unavailable
    $day_info['fully_booked'] = (!$day_info['morning_available'] && !$day_info['afternoon_available'] && !$day_info['overnight_available']);

    $days[] = $day_info;
    $cursor->modify('+1 day');
}

echo json_encode([
    'success'  => true,
    'facility' => [
        'id'        => intval($facility['id']),
        'name'      => $facility['name'],
        'type'      => $facility['type'],
        'capacity'  => intval($facility['capacity']),
        'price'     => floatval($facility['price']),
        'area_name' => $facility['area_name'] ?? 'N/A',
        'status'    => $facility['status'],
    ],
    'today'    => $today,
    'days'     => $days,
]);
exit();
