<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_role'] !== 'frontdesk') {
    header("Location: " . BASE_URL . "unauthorized.php"); exit();
}

$user = get_user_info($_SESSION['user_id'], $conn);

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
if ($booking_id <= 0) { header("Location: " . BASE_URL . "frontdesk/walkin_booking.php"); exit(); }

$stmt = $conn->prepare("SELECT b.*, f.name AS facility_name, f.price AS facility_price, f.type AS facility_type, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=? AND b.booking_type='walkin'");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) { header("Location: " . BASE_URL . "frontdesk/walkin_booking.php"); exit(); }

$nights = max(1, (int)((strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / 86400));
$dur    = $booking['mode'] === 'daytour' ? 'Day Tour' : $nights . ' Night' . ($nights != 1 ? 's' : '');
$processed_by = ($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? '');
$now = date('F d, Y h:i A');

// Price breakdown
$area_lc    = strtolower($booking['area_name'] ?? '');
$isBoth     = strpos($area_lc,'both')!==false || (strpos($area_lc,'sinulom')!==false && strpos($area_lc,'bolao')!==false);
$rate_adult = $isBoth ? 160 : 110;
$rate_child = $isBoth ? 85  : 60;
$fac_price  = floatval($booking['facility_price'] ?? 0);
$fac_total  = $fac_price * ($booking['mode'] === 'overnight' ? $nights : 1);
$adult_total = intval($booking['num_adults'])   * $rate_adult;
$child_total = intval($booking['num_children']) * $rate_child;

// Add-ons
$addons = [];
$addon_total = 0.0;
$ao_res = $conn->query("SELECT a.name, a.price, ba.quantity FROM booking_addons ba JOIN amenities a ON ba.amenity_id=a.id WHERE ba.booking_id=$booking_id AND ba.amenity_id IS NOT NULL");
if ($ao_res) { while ($ao = $ao_res->fetch_assoc()) { $addons[] = $ao; $addon_total += floatval($ao['price']) * intval($ao['quantity'] ?? 1); } }

// Send receipt email if guest has email
if (!empty($booking['guest_email'])) {
    require_once '../includes/send_booking_email.php';
    $email_data = [
        'booking_id'    => $booking_id,
        'guest_name'    => $booking['guest_name']    ?? '',
        'guest_email'   => $booking['guest_email']   ?? '',
        'guest_phone'   => $booking['guest_phone']   ?? '',
        'facility_name' => $booking['facility_name'] ?? 'N/A',
        'facility_price'=> $fac_price,
        'area_name'     => $booking['area_name']     ?? 'N/A',
        'check_in_date' => $booking['check_in_date'] ?? '',
        'check_out_date'=> $booking['check_out_date'] ?? '',
        'num_adults'    => $booking['num_adults']    ?? 0,
        'num_children'  => $booking['num_children']  ?? 0,
        'mode'          => $booking['mode']          ?? 'daytour',
        'time_slot'     => '',
        'total_price'   => $booking['total_price']   ?? 0,
        'status'        => 'confirmed',
        'booking_type'  => 'walkin',
    ];
    try { sendBookingConfirmationEmail($email_data); } catch (\Exception $e) { error_log("Walkin receipt email: " . $e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Receipt #<?= $booking_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php require_once '../includes/frontdesk_page_styles.php'; ?>
    <style>
    /* Receipt card */
    .receipt-wrap{max-width:440px;margin:0 auto;}
    .receipt-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 25px -5px rgba(0,0,0,.05), 0 8px 10px -6px rgba(0,0,0,.05);border:1px solid #e5e7eb;margin-bottom:24px;position:relative; -webkit-print-color-adjust: exact; print-color-adjust: exact;}
    .receipt-hdr{background:#1a3d2b;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
    .receipt-hdr-left h2{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:2px;}
    .receipt-hdr-left p{font-size:.74rem;color:rgba(255,255,255,.7);margin:0;}
    .receipt-confirmed{background:#e8f5e9;color:#1a7a3a;border:1px solid #c8e6c9;padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
    .receipt-body{padding:20px 24px;}
    .sec-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin:16px 0 8px;padding-bottom:4px;border-bottom:1px solid #f3f4f6;}
    .sec-label:first-child{margin-top:0;}
    .detail-row{display:flex;justify-content:space-between;align-items:baseline;padding:5px 0;}
    .detail-label{font-size:.82rem;color:#6b7280;font-weight:500;}
    .detail-value{font-size:.84rem;font-weight:600;color:#1a1a1a;text-align:right;max-width:65%;}
    
    /* Ticket Divider */
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
      background-color: #f8f9fa;
      border-radius: 50%;
    }
    .ticket-divider::before { left: -31px; }
    .ticket-divider::after { right: -31px; }

    .total-box{background:#1a3d2b;border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;margin-top:16px;}
    .total-box .lbl{font-size:.82rem;font-weight:600;color:#a7f3d0;text-transform:uppercase;letter-spacing:0.5px;}
    .total-box .val{font-size:1.2rem;font-weight:800;color:#fff;}
    .processed-by{background:#f8f5f0;border-radius:10px;padding:10px 14px;margin-top:16px;font-size:.78rem;color:#6b7280;display:flex;align-items:center;gap:8px;}
    .processed-by strong{color:#1a1a1a;}
    
    /* Action buttons */
    .receipt-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    .btn-dashboard{display:inline-flex;align-items:center;gap:8px;background:#1a3d2b;color:#fff;padding:10px 24px;border-radius:50px;font-weight:700;font-size:.88rem;text-decoration:none;transition:all .2s;box-shadow:0 4px 12px rgba(26,61,43,.15);}
    .btn-dashboard:hover{background:#2d5a3d;transform:translateY(-1px);color:#fff;}
    .btn-new{display:inline-flex;align-items:center;gap:8px;background:#fff;color:#1a3d2b;padding:9px 20px;border-radius:50px;font-weight:600;font-size:.84rem;text-decoration:none;border:1.5px solid #1a3d2b;transition:all .2s;}
    .btn-new:hover{background:#f0faf4;color:#1a3d2b;}
    .btn-print{display:inline-flex;align-items:center;gap:8px;background:#fff;color:#6b7280;padding:9px 20px;border-radius:50px;font-weight:600;font-size:.84rem;border:1.5px solid #e2ddd5;transition:all .2s;cursor:pointer;}
    .btn-print:hover{border-color:#1a3d2b;color:#1a3d2b;}
    
    /* Success banner */
    .success-banner{background:#e8f5e9;border:1.5px solid #c8e6c9;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:20px;}
    .success-banner i{font-size:1.4rem;color:#1a7a3a;flex-shrink:0;}
    .success-banner h4{font-size:0.92rem;font-weight:700;color:#1a7a3a;margin:0 0 2px;}
    .success-banner p{font-size:.78rem;color:#2d6a4f;margin:0;}
    
    @media print{
      .dash-topbar,.receipt-actions,.gd-sidebar,.sidebar-col,.success-banner,.dash-sidebar{display:none!important;}
      body{background:#fff!important;}
      .content {margin-left: 0 !important; padding: 0 !important;}
      .main-container {display: block !important;}
      .receipt-wrap {max-width:440px !important; margin:0 auto !important;}
      .receipt-card{box-shadow:none;border:1px dashed #ccc;border-radius:0;margin:0 auto;width: 100% !important;page-break-inside:avoid;break-inside:avoid;}
      .receipt-body{padding:16px 20px;}
      .ticket-divider::before, .ticket-divider::after { display:none!important; }
      @page { size: auto; margin: 5mm; }
    }
    </style>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/frontdesk_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-receipt me-2" style="color:#1B7D3A;"></i>Walk-in Booking Receipt</div>
                <div class="dash-topbar-sub"><?= date('l, F j, Y') ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-concierge-bell me-1"></i>Front Desk</span>
            </div>
        </div>

        <div class="dash-body">
            <div class="receipt-wrap">

                <!-- Success banner -->
                <div class="success-banner">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <h4>Booking Confirmed!</h4>
                        <p>Walk-in booking #<?= str_pad($booking_id, 6, '0', STR_PAD_LEFT) ?> has been successfully recorded and confirmed.</p>
                    </div>
                </div>

                <!-- Receipt card -->
                <div class="receipt-card">
                    <div class="receipt-hdr">
                        <div class="receipt-hdr-left">
                            <h2>Booking #<?= str_pad($booking_id, 6, '0', STR_PAD_LEFT) ?></h2>
                            <p>Issued: <?= $now ?></p>
                        </div>
                        <span class="receipt-confirmed"><i class="fas fa-check me-1"></i>Confirmed</span>
                    </div>
                    <div class="receipt-body">

                        <div class="sec-label">Guest Information</div>
                        <div class="detail-row">
                            <span class="detail-label">Guest Name</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['guest_name']) ?></span>
                        </div>
                        <?php if (!empty($booking['guest_email'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['guest_email']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span class="detail-label">Contact</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['guest_phone'] ?: '—') ?></span>
                        </div>

                        <div class="ticket-divider"></div>

                        <div class="sec-label">Booking Details</div>
                        <div class="detail-row">
                            <span class="detail-label">Booking Type</span>
                            <span class="detail-value">Walk-in</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Booking Mode</span>
                            <span class="detail-value"><?= ucfirst($booking['mode']) ?></span>
                        </div>
                        <?php if (!empty($booking['area_name'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Location</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['area_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span class="detail-label">Facility</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['facility_name'] ?? '—') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Check-in</span>
                            <span class="detail-value"><?= date('F j, Y', strtotime($booking['check_in_date'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Check-out</span>
                            <span class="detail-value"><?= $booking['mode'] === 'daytour' ? 'Same Day' : date('F j, Y', strtotime($booking['check_out_date'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value"><?= $dur ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Adults</span>
                            <span class="detail-value"><?= intval($booking['num_adults']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Children</span>
                            <span class="detail-value"><?= intval($booking['num_children']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Guests</span>
                            <span class="detail-value"><?= intval($booking['num_guests']) ?> pax</span>
                        </div>

                        <div class="ticket-divider"></div>

                        <!-- Price Breakdown -->
                        <div class="sec-label">Price Breakdown</div>
                        <?php if ($fac_price > 0): ?>
                        <div class="detail-row">
                            <span class="detail-label">Facility (<?= htmlspecialchars($booking['facility_name'] ?? '') ?>)<?= $booking['mode']==='overnight' && $nights>1 ? ' × '.$nights.' nights' : '' ?></span>
                            <span class="detail-value">&#8369;<?= number_format($fac_total, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span class="detail-label">Adults (<?= intval($booking['num_adults']) ?> × &#8369;<?= number_format($rate_adult, 2) ?>/pax)</span>
                            <span class="detail-value">&#8369;<?= number_format($adult_total, 2) ?></span>
                        </div>
                        <?php if (intval($booking['num_children']) > 0): ?>
                        <div class="detail-row">
                            <span class="detail-label">Children (<?= intval($booking['num_children']) ?> × &#8369;<?= number_format($rate_child, 2) ?>/pax)</span>
                            <span class="detail-value">&#8369;<?= number_format($child_total, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($addons)): ?>
                        <div class="detail-row" style="background:#f0faf4;padding:6px 8px;border-radius:6px;margin:4px 0;">
                            <span class="detail-label" style="color:#1a3d2b;font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.8px;">Add-ons</span>
                        </div>
                        <?php foreach ($addons as $ao): ?>
                        <div class="detail-row" style="padding-left:12px;">
                            <span class="detail-label"><?= htmlspecialchars($ao['name']) ?> × <?= intval($ao['quantity'] ?? 1) ?></span>
                            <span class="detail-value">&#8369;<?= number_format(floatval($ao['price']) * intval($ao['quantity'] ?? 1), 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="detail-row">
                            <span class="detail-label" style="color:#1a3d2b;font-weight:600;">Add-ons Subtotal</span>
                            <span class="detail-value" style="color:#1a3d2b;">&#8369;<?= number_format($addon_total, 2) ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="ticket-divider"></div>

                        <div class="total-box">
                            <span class="lbl">Total Amount Paid</span>
                            <span class="val">&#8369;<?= number_format($booking['total_price'], 2) ?></span>
                        </div>

                        <div class="processed-by">
                            <i class="fas fa-user-check" style="color:#1a3d2b;"></i>
                            Processed by: <strong><?= htmlspecialchars(trim($processed_by) ?: 'Front Desk') ?></strong>
                            &nbsp;&bull;&nbsp; <?= $now ?>
                        </div>

                    </div>
                </div>

                <!-- Action buttons -->
                <div class="receipt-actions">
                    <a href="<?= BASE_URL ?>frontdesk/dashboard.php" class="btn-dashboard">
                        <i class="fas fa-th-large"></i> Back to Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>frontdesk/walkin_booking.php" class="btn-new">
                        <i class="fas fa-plus"></i> New Booking
                    </a>
                    <button class="btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
if (typeof initFrontdeskSidebar === 'function') initFrontdeskSidebar();
</script>
</body>
</html>
