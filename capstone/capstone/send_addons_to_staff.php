<?php
// send_addons_to_staff.php
require_once 'config/db_config.php';
session_start();
$guest_email = $_SESSION['guest_email'] ?? null;
if (!$guest_email) {
    echo json_encode(['success' => false, 'error' => 'Guest not logged in.']);
    exit;
}
$facility_ids = $_POST['facility_ids'] ?? [];
$amenity_ids = $_POST['amenity_ids'] ?? [];
$booking_id = $_POST['booking_id'] ?? null;
if (empty($facility_ids) && empty($amenity_ids)) {
    echo json_encode(['success' => false, 'error' => 'No add-ons selected.']);
    exit;
}
if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'No booking ID provided.']);
    exit;
}
// Save add-ons to booking_addons table (create if not exists)
if (!$conn->query("CREATE TABLE IF NOT EXISTS booking_addons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT,
    facility_id INT,
    amenity_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)") ) {
    echo json_encode(['success' => false, 'error' => 'Failed to create booking_addons table.']);
    exit;
}
foreach ($facility_ids as $fid) {
    $stmt = $conn->prepare("INSERT INTO booking_addons (booking_id, facility_id, amenity_id) VALUES (?, ?, NULL)");
    $stmt->bind_param('ii', $booking_id, $fid);
    $stmt->execute();
    $stmt->close();
}
foreach ($amenity_ids as $aid) {
    $stmt = $conn->prepare("INSERT INTO booking_addons (booking_id, facility_id, amenity_id) VALUES (?, NULL, ?)");
    $stmt->bind_param('ii', $booking_id, $aid);
    $stmt->execute();
    $stmt->close();
}

// Calculate add-ons total

// Recalculate subtotal (base booking fees)
$stmt = $conn->prepare("SELECT b.*, f.price AS facility_price, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id WHERE b.id = ?");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

$facility_price = floatval($booking['facility_price'] ?? 0);
$area_name = $booking['area_name'] ?? '';
$mode = $booking['mode'] ?? 'overnight';
$num_adults = intval($booking['num_adults'] ?? 0);
$num_children = intval($booking['num_children'] ?? 0);
$num_discounted = intval($booking['num_discounted'] ?? 0);
$check_in = $booking['check_in_date'];
$check_out = $booking['check_out_date'];

// Calculate nights
if ($mode === 'daytour') {
    $nights = 1;
} else {
    $d1 = new DateTime($check_in);
    $d2 = new DateTime($check_out);
    $interval = $d1->diff($d2);
    $nights = max(1, (int)$interval->days);
}

// Area rates
function getAreaRates($areaName) {
    $name = strtolower($areaName ?? '');
    $isBoth = strpos($name, 'both') !== false ||
        (strpos($name, 'sinulom') !== false && strpos($name, 'bolao') !== false) ||
        strpos($name, 'sinulom/bolao') !== false ||
        strpos($name, 'sinulom & bolao') !== false ||
        strpos($name, 'sinulom and bolao') !== false;
    if ($isBoth) {
        return ['adult' => 160, 'senior' => 130, 'child' => 85];
    }
    return ['adult' => 110, 'senior' => 90, 'child' => 60];
}
$rates = getAreaRates($area_name);
$entrance_adult = $num_adults * $rates['adult'] * $nights;
$entrance_discounted = $num_discounted * $rates['senior'] * $nights;
$entrance_children = $num_children * $rates['child'] * $nights;
$entrance_kids = 0;
$facility_total = $facility_price * $nights;
$other_charges = 0;
$subtotal = $facility_total + $entrance_adult + $entrance_discounted + $entrance_children + $entrance_kids + $other_charges;

// Calculate add-ons total (all add-ons for this booking)
$addon_total = 0;
$addon_stmt = $conn->prepare("SELECT ba.*, f.price AS facility_price, a.price AS amenity_price FROM booking_addons ba LEFT JOIN facilities f ON ba.facility_id = f.id LEFT JOIN amenities a ON ba.amenity_id = a.id WHERE ba.booking_id = ?");
$addon_stmt->bind_param('i', $booking_id);
$addon_stmt->execute();
$addon_result = $addon_stmt->get_result();
while ($row = $addon_result->fetch_assoc()) {
    if ($row['facility_id']) {
        $addon_total += floatval($row['facility_price']);
    }
    if ($row['amenity_id']) {
        $addon_total += floatval($row['amenity_price']);
    }
}
$addon_stmt->close();

$new_total = $subtotal + $addon_total;
$stmt = $conn->prepare("UPDATE bookings SET total_price = ? WHERE id = ?");
$stmt->bind_param('di', $new_total, $booking_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'new_total' => $new_total]);
exit;
