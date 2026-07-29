<?php
require_once 'config/db_config.php';
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
if ($booking_id <= 0) { echo json_encode([]); exit; }

$addons = [];
$addon_stmt = $conn->prepare(
    "SELECT ba.quantity, a.id AS amenity_id, a.name AS amenity_name, a.price AS amenity_price
     FROM booking_addons ba
     LEFT JOIN amenities a ON ba.amenity_id = a.id
     WHERE ba.booking_id = ? AND ba.amenity_id IS NOT NULL"
);
$addon_stmt->bind_param('i', $booking_id);
$addon_stmt->execute();
$addon_result = $addon_stmt->get_result();
while ($row = $addon_result->fetch_assoc()) {
    $addons[] = [
        'type'       => 'Amenity',
        'amenity_id' => (int)$row['amenity_id'],
        'name'       => $row['amenity_name'],
        'price'      => floatval($row['amenity_price']),
        'quantity'   => (int)($row['quantity'] ?? 1),
    ];
}
$addon_stmt->close();
echo json_encode($addons);
exit;
