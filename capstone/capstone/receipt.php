<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

require_login();

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
if ($booking_id <= 0) {
    die('Invalid booking ID');
}

// Fetch the base requested booking
$stmt = $conn->prepare("SELECT b.*, f.name AS facility_name, f.price AS facility_price, a.name AS area_name, u.first_name, u.last_name FROM bookings b LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id LEFT JOIN users u ON b.created_by = u.id WHERE b.id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    die('Booking not found');
}

// Find all sibling bookings from the same session/transaction
$sibling_stmt = $conn->prepare("SELECT b.*, f.name AS facility_name, f.price AS facility_price, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id WHERE b.guest_email = ? AND b.check_in_date = ? AND b.check_out_date = ? AND b.created_at = ?");
$sibling_stmt->bind_param("ssss", $booking['guest_email'], $booking['check_in_date'], $booking['check_out_date'], $booking['created_at']);
$sibling_stmt->execute();
$sibling_res = $sibling_stmt->get_result();
$bookings_list = [];
while ($srow = $sibling_res->fetch_assoc()) {
    $bookings_list[] = $srow;
}
$sibling_stmt->close();

if (empty($bookings_list)) {
    $bookings_list = [$booking];
}

$guest_name = htmlspecialchars($booking['guest_name']);
$area_name = htmlspecialchars($booking['area_name'] ?? 'N/A');
$check_in = $booking['check_in_date'];
$check_out = $booking['check_out_date'];
$mode = $booking['mode'] ?? 'overnight';

// Aggregate guest counts across sibling bookings
$num_adults = 0;
$num_children = 0;
$num_discounted = 0;
$num_below5 = 0;
$total_price = 0.0;

foreach ($bookings_list as $bk) {
    $num_adults += intval($bk['num_adults'] ?? 0);
    $num_children += intval($bk['num_children'] ?? 0);
    $num_discounted += intval($bk['num_discounted'] ?? 0);
    $total_price += floatval($bk['total_price']);
    
    $bk_notes = $bk['notes'] ?? '';
    if (preg_match('/Below5:\s*(\d+)/i', $bk_notes, $m)) {
        $num_below5 += intval($m[1]);
    }
}

$num_guests = $num_adults + $num_discounted + $num_children;

if ($mode === 'daytour') {
    $nights = 1;
} else {
    $d1 = new DateTime($check_in);
    $d2 = new DateTime($check_out);
    $interval = $d1->diff($d2);
    $nights = max(1, (int)$interval->days);
}

function fmt($v) { return '₱' . number_format($v, 2); }

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

// Fetch add-ons for all bookings in the group
$addons = [];
$addon_total = 0;
$sids = array_column($bookings_list, 'id');
$placeholders = implode(',', array_fill(0, count($sids), '?'));
$addon_stmt = $conn->prepare("SELECT ba.*, f.name AS facility_name, f.price AS facility_price, a.name AS amenity_name, a.price AS amenity_price FROM booking_addons ba LEFT JOIN facilities f ON ba.facility_id = f.id LEFT JOIN amenities a ON ba.amenity_id = a.id WHERE ba.booking_id IN ($placeholders)");
$types = str_repeat('i', count($sids));
$addon_stmt->bind_param($types, ...$sids);
$addon_stmt->execute();
$addon_result = $addon_stmt->get_result();
while ($row = $addon_result->fetch_assoc()) {
    if ($row['facility_id']) {
        $addons[] = [
            'type' => 'Facility',
            'name' => $row['facility_name'],
            'price' => floatval($row['facility_price'])
        ];
        $addon_total += floatval($row['facility_price']);
    }
    if ($row['amenity_id']) {
        $addons[] = [
            'type' => 'Amenity',
            'name' => $row['amenity_name'],
            'price' => floatval($row['amenity_price'])
        ];
        $addon_total += floatval($row['amenity_price']);
    }
}
$addon_stmt->close();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $guest_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .receipt-card {
            max-width: 440px;
            margin: 0 auto;
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            position: relative;
        }
        .resort-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #1a3d2b;
            text-align: center;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .resort-sub {
            font-size: 0.78rem;
            color: #6b7280;
            text-align: center;
            margin-bottom: 16px;
            font-weight: 500;
        }
        .ticket-divider {
            border-top: 2px dashed #e5e7eb;
            margin: 16px 0;
            position: relative;
        }
        .ticket-divider::before, .ticket-divider::after {
            content: '';
            position: absolute;
            top: -6px;
            width: 12px;
            height: 12px;
            background-color: #f3f4f6;
            border-radius: 50%;
        }
        .ticket-divider::before { left: -31px; }
        .ticket-divider::after { right: -31px; }
        
        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            margin-bottom: 5px;
            color: #4b5563;
        }
        .meta-row span {
            color: #6b7280;
        }
        .meta-row strong {
            font-weight: 600;
            color: #111827;
        }
        .sec-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8c9b90;
            margin: 16px 0 8px 0;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 4px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 0.82rem;
            padding: 4px 0;
        }
        .detail-row span {
            color: #6b7280;
            font-weight: 500;
        }
        .detail-row strong {
            color: #1f2937;
            font-weight: 600;
            text-align: right;
            max-width: 65%;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .total-box {
            background-color: #1a3d2b;
            color: #fff;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
        }
        .total-box span {
            font-size: 0.82rem;
            font-weight: 600;
            color: #a7f3d0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .total-box strong {
            font-size: 1.2rem;
            font-weight: 800;
        }
        .footer-info {
            text-align: center;
            font-size: 0.76rem;
            color: #6b7280;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed #e5e7eb;
        }
        .btn-print-receipt {
            background-color: #1a3d2b;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(26,61,43,0.15);
        }
        .btn-print-receipt:hover {
            background-color: #24573d;
            color: #fff;
            transform: translateY(-1px);
        }
        
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                border: 1px dashed #ccc !important;
                max-width: 440px !important;
                margin: 0 auto !important;
                padding: 16px !important;
                border-radius: 0 !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .ticket-divider::before, .ticket-divider::after {
                display: none !important;
            }
            @page {
                size: auto;
                margin: 5mm;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="resort-title">SINULOM FALLS & BOLAO SPRING</div>
        <div class="resort-sub">Cold Spring Resort Receipt</div>
        
        <div class="meta-row">
            <span>Receipt No.:</span>
            <strong>#<?php echo implode(', #', $sids); ?></strong>
        </div>
        <div class="meta-row">
            <span>Date:</span>
            <strong><?php echo $transaction_date; ?></strong>
        </div>
        <div class="meta-row">
            <span>Time:</span>
            <strong><?php echo $transaction_time; ?></strong>
        </div>
        
        <div class="ticket-divider"></div>
        
        <div class="sec-title">Customer Information</div>
        <div class="detail-row">
            <span>Customer Name</span>
            <strong><?php echo $guest_name; ?></strong>
        </div>
        <div class="detail-row">
            <span>Location</span>
            <strong><?php echo $area_name; ?></strong>
        </div>
        <div class="detail-row">
            <span>Facility</span>
            <strong><?php echo $facility_name; ?></strong>
        </div>
        <div class="detail-row">
            <span>Date Booked</span>
            <strong><?php echo $transaction_date; ?></strong>
        </div>
        <div class="detail-row">
            <span>Booking Status</span>
            <?php 
                $st = strtolower($booking['status'] ?? '');
                $st_color = '#e65100';
                $st_bg = '#fff8e1';
                $st_label = 'Pending';
                
                if ($st === 'confirmed') {
                    $st_color = '#1b7d3a';
                    $st_bg = '#e8f5e9';
                    $st_label = 'Confirmed';
                } elseif ($st === 'completed') {
                    $st_color = '#1565c0';
                    $st_bg = '#e3f2fd';
                    $st_label = 'Completed';
                } elseif ($st === 'pending' || $st === 'unpaid') {
                    $st_color = '#e65100';
                    $st_bg = '#fff8e1';
                    $st_label = ($st === 'unpaid') ? 'Pending' : ucfirst($st);
                } else {
                    $st_color = '#c62828';
                    $st_bg = '#fdecea';
                    $st_label = ucfirst($st);
                }
            ?>
            <strong class="status-badge" style="background-color: <?php echo $st_bg; ?>; color: <?php echo $st_color; ?>;">
                <?php echo $st_label; ?>
            </strong>
        </div>
        
        <div class="ticket-divider"></div>
        
        <div class="sec-title">Payment Summary</div>
        <div class="detail-row">
            <span>Adult Entrance</span>
            <strong><?php echo fmt($entrance_adult); ?></strong>
        </div>
        <div class="detail-row">
            <span>PWD/Senior Entrance</span>
            <strong><?php echo fmt($entrance_discounted); ?></strong>
        </div>
        <div class="detail-row">
            <span>Children Entrance</span>
            <strong><?php echo fmt($entrance_children); ?></strong>
        </div>
        <div class="detail-row">
            <span>Kids Entrance (below 6)</span>
            <strong><?php echo fmt($entrance_kids); ?></strong>
        </div>
        <div class="detail-row">
            <span>Facilities & Rentals</span>
            <strong><?php echo fmt($facility_total); ?></strong>
        </div>
        <div class="detail-row">
            <span>Other Charges</span>
            <strong><?php echo fmt($other_charges); ?></strong>
        </div>
        
        <?php if ($transport_cost > 0): ?>
        <div class="detail-row">
            <span>Transportation (<?php 
                echo $transportation === 'tignapoloan' ? 'Tignapoloan' : 
                     ($transportation === 'cdo' ? 'CDO' : 'Private Van'); 
            ?>)</span>
            <strong><?php echo fmt($transport_cost); ?></strong>
        </div>
        <?php endif; ?>
        
        <div class="sec-title">Add-ons</div>
        <?php if (count($addons) > 0): ?>
            <?php foreach ($addons as $addon): ?>
                <div class="detail-row">
                    <span><?= htmlspecialchars($addon['type']) ?>: <?= htmlspecialchars($addon['name']) ?></span>
                    <strong><?= fmt($addon['price']) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="detail-row"><span class="text-muted italic">No add-ons selected</span><strong>—</strong></div>
        <?php endif; ?>
        
        <div class="ticket-divider"></div>
        
        <div class="detail-row">
            <span>Subtotal</span>
            <strong><?php echo fmt($subtotal); ?></strong>
        </div>
        
        <div class="total-box">
            <span>Total Paid</span>
            <strong><?php echo fmt($total_with_addons); ?></strong>
        </div>
        
        <div class="footer-info">
            <div>Processed By: <strong><?php echo $creator_name; ?></strong></div>
            <div class="mt-1" style="font-size:0.7rem; color:#9ca3af;">Thank you for booking with us!</div>
        </div>
        
        <div class="mt-4 text-center no-print">
            <button onclick="window.print()" class="btn btn-print-receipt">Print Receipt</button>
        </div>
    </div>
</body>
</html>l>
