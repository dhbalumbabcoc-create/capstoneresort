<?php
session_start();
require_once 'config/db_config.php';

// Guest must be logged in
if (empty($_SESSION['guest_logged_in']) || empty($_SESSION['guest_email'])) {
    header('Location: guest_login.php'); exit();
}

$booking_id  = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$guest_email = $_SESSION['guest_email'];

if ($booking_id <= 0) { header('Location: guest_dashboard.php?tab=history'); exit(); }

$stmt = $conn->prepare("SELECT b.*, f.name AS facility_name, f.price AS facility_price, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=? AND b.guest_email=?");
$stmt->bind_param("is", $booking_id, $guest_email);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) { header('Location: guest_dashboard.php?tab=history'); exit(); }

// ── Fetch all sibling bookings (same transaction) ──
$sib_stmt = $conn->prepare(
    "SELECT b.*, f.name AS facility_name, f.price AS facility_price, a.name AS area_name FROM bookings b 
     LEFT JOIN facilities f ON b.facility_id = f.id 
     LEFT JOIN areas a ON b.area_id = a.id
     WHERE b.guest_email=? AND b.check_in_date=? AND b.check_out_date=? AND ABS(TIMESTAMPDIFF(SECOND, b.created_at, ?)) <= 10"
);
$sib_stmt->bind_param("ssss",
    $booking['guest_email'],
    $booking['check_in_date'],
    $booking['check_out_date'],
    $booking['created_at']
);
$sib_stmt->execute();
$sib_result = $sib_stmt->get_result();
$siblings = [];
while ($s = $sib_result->fetch_assoc()) $siblings[] = $s;
$sib_stmt->close();

if (empty($siblings)) {
    $siblings = [$booking];
}

// Aggregate across siblings
$sids               = array_column($siblings, 'id');
$all_facility_names = array_unique(array_filter(array_column($siblings, 'facility_name')));
$facility_display   = !empty($all_facility_names) ? implode(', ', $all_facility_names) : ($booking['facility_name'] ?? 'N/A');
$total_price_agg    = array_sum(array_column($siblings, 'total_price'));
$total_adults_agg   = 0;
$total_children_agg = 0;
$total_pwd_agg      = 0;
$total_below5_agg   = 0;
$is_grouped         = count($siblings) > 1;

$all_trans_opts     = [];
$time_slot_labels   = [];
$ts_map = [
    '8am-12pm'   => '8:00 AM – 12:00 PM (Morning)',
    '12pm-5pm'   => '12:00 PM – 5:00 PM (Afternoon)',
    'full_day'   => '8:00 AM – 5:00 PM (Full Day)',
    '8am-5pm'    => '8:00 AM – 5:00 PM',
    'full_night' => 'Full Night (Overnight)',
    'overnight'  => '8:00 AM – 8:00 PM (Overnight)'
];

$facility_cost_sum  = 0.0;
$entrance_cost_sum  = 0.0;
$transport_cost_sum = 0.0;

foreach ($siblings as $s) {
    $total_adults_agg   += intval($s['num_adults'] ?? 0);
    $total_children_agg += intval($s['num_children'] ?? 0);
    $total_pwd_agg      += intval($s['num_discounted'] ?? 0);

    // Parse notes
    if (!empty($s['notes'])) {
        if (preg_match('/Time Slot:\s*([a-z0-9_\-]+)/i', $s['notes'], $m)) {
            $ts = strtolower($m[1]);
            if (isset($ts_map[$ts]) && !in_array($ts_map[$ts], $time_slot_labels)) {
                $time_slot_labels[] = $ts_map[$ts];
            }
        }
        if (preg_match('/Below5:\s*(\d+)/i', $s['notes'], $m)) {
            $total_below5_agg += intval($m[1]);
        }
        if (preg_match('/PWD:\s*(\d+)/i', $s['notes'], $m) && empty($s['num_discounted'])) {
            $total_pwd_agg += intval($m[1]);
        }
        if (preg_match('/Transport:\s*([a-z0-9_\-]+)/i', $s['notes'], $m)) {
            $tr = strtolower($m[1]);
            if ($tr !== 'none' && !in_array($tr, $all_trans_opts)) {
                $all_trans_opts[] = $tr;
            }
        }
    }

    // Facility cost calculation
    $fp = floatval($s['facility_price'] ?? 0);
    $nights_s = 1;
    if ($s['mode'] === 'overnight' && $s['check_in_date'] && $s['check_out_date'] && $s['check_out_date'] > $s['check_in_date']) {
        $nights_s = max(1, (int)((strtotime($s['check_out_date']) - strtotime($s['check_in_date'])) / 86400));
    }
    $facility_cost_sum += ($s['mode'] === 'overnight') ? ($fp * $nights_s) : $fp;

    // Entrance rates
    $area_lc = strtolower($s['area_name'] ?? '');
    $is_both = strpos($area_lc, 'both') !== false || (strpos($area_lc, 'sinulom') !== false && strpos($area_lc, 'bolao') !== false);
    $r_adult = $is_both ? 160 : 110;
    $r_child = $is_both ? 85 : 60;
    $r_pwd   = round($r_adult * 0.80, 2);

    $entrance_cost_sum += (intval($s['num_adults']) * $r_adult) + (intval($s['num_children']) * $r_child) + (intval($s['num_discounted']) * $r_pwd);

    // Transport calculation per booking
    if (!empty($s['notes']) && preg_match('/Transport:\s*([a-z0-9_\-]+)/i', $s['notes'], $m)) {
        $tr = strtolower($m[1]);
        $tr_guests = max(0, intval($s['num_adults']) + intval($s['num_discounted']));
        if ($tr === 'tignapoloan') $transport_cost_sum += $tr_guests * 50;
        elseif ($tr === 'cdo') $transport_cost_sum += $tr_guests * 250;
        elseif ($tr === 'private') $transport_cost_sum += 3500;
    }
}

// Fetch add-ons from booking_addons
$addons = [];
$addon_total_sum = 0.0;
if (!empty($sids)) {
    $placeholders = implode(',', array_fill(0, count($sids), '?'));
    $addon_stmt = $conn->prepare("
        SELECT ba.*, a.name AS amenity_name, a.price AS amenity_price 
        FROM booking_addons ba 
        JOIN amenities a ON ba.amenity_id = a.id 
        WHERE ba.booking_id IN ($placeholders)
    ");
    $types = str_repeat('i', count($sids));
    $addon_stmt->bind_param($types, ...$sids);
    $addon_stmt->execute();
    $addon_result = $addon_stmt->get_result();
    while ($arow = $addon_result->fetch_assoc()) {
        $addons[] = $arow;
        $addon_total_sum += floatval($arow['amenity_price']) * intval($arow['quantity']);
    }
    $addon_stmt->close();
}

$bid_primary = 'SB-' . date('Y', strtotime($booking['created_at'])) . '-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT);
if (count($sids) > 1) {
    $bid_list = array_map(fn($id) => 'SB-' . date('Y', strtotime($booking['created_at'])) . '-' . str_pad($id, 3, '0', STR_PAD_LEFT), $sids);
    $bid_display = implode(', ', $bid_list);
} else {
    $bid_display = $bid_primary;
}

$status = strtolower($booking['status']);
$nights = max(1, (int)((strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / 86400));
$dur    = $booking['mode'] === 'daytour' ? 'Day Tour' : $nights . ' Night' . ($nights != 1 ? 's' : '');

$ts_label = !empty($time_slot_labels) ? implode(', ', $time_slot_labels) : '—';

$status_colors = [
    'confirmed'   => ['bg'=>'#e8f5e9','color'=>'#1a7a3a','border'=>'#c8e6c9','label'=>'Confirmed'],
    'completed'   => ['bg'=>'#e3f2fd','color'=>'#1565c0','border'=>'#bbdefb','label'=>'Completed'],
    'checked in'  => ['bg'=>'#e3f2fd','color'=>'#1565c0','border'=>'#bbdefb','label'=>'Checked In'],
    'checked out' => ['bg'=>'#e3f2fd','color'=>'#1565c0','border'=>'#bbdefb','label'=>'Checked Out'],
    'cancelled'   => ['bg'=>'#fdecea','color'=>'#c62828','border'=>'#f5c6cb','label'=>'Cancelled'],
    'declined'    => ['bg'=>'#fdecea','color'=>'#c62828','border'=>'#f5c6cb','label'=>'Declined'],
    'pending'     => ['bg'=>'#fff8e1','color'=>'#e65100','border'=>'#ffe082','label'=>'Pending'],
];
$sc = $status_colors[$status] ?? $status_colors['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Booking Receipt #<?= $bid_primary ?> — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gd:#1a3d2b;--gm:#2d5a3d;--cream:#eeeee8;--txt:#1a1a1a;--muted:#6b7280;--border:#e2ddd5}
body{font-family:'Inter',sans-serif;background:var(--cream);min-height:100vh; -webkit-print-color-adjust: exact; print-color-adjust: exact;}

/* Navbar */
.nb{background:var(--gd);padding:14px 32px;display:flex;align-items:center;justify-content:space-between;}
.nb-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
.nb-brand img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.2);}
.nb-brand-txt strong{display:block;font-size:.88rem;font-weight:700;color:#fff;line-height:1.2;}
.nb-brand-txt span{font-size:.7rem;color:rgba(255,255,255,.65);}
.nb-back{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.25);color:#fff;padding:8px 18px;border-radius:50px;font-size:.84rem;font-weight:600;text-decoration:none;transition:background .2s;}
.nb-back:hover{background:rgba(255,255,255,.22);color:#fff;}

/* Page */
.page{max-width:480px;margin:0 auto;padding:32px 20px 60px;}
.page-eyebrow{font-size:.68rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:var(--gd);margin-bottom:8px;display:flex;align-items:center;gap:10px;justify-content:center;}
.page-eyebrow::before,.page-eyebrow::after{content:'';width:24px;height:1px;background:var(--gd);}
.page-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:800;color:var(--txt);text-align:center;margin-bottom:24px;}

/* Receipt card */
.receipt-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 25px -5px rgba(0,0,0,.05), 0 8px 10px -6px rgba(0,0,0,.05);border:1px solid #e5e7eb;margin-bottom:20px;position:relative;}
.receipt-header{background:var(--gd);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.receipt-header-left h2{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:2px;}
.receipt-header-left p{font-size:.74rem;color:rgba(255,255,255,.7);margin:0;}
.receipt-status{padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;}
.receipt-body{padding:20px 24px;}

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
  background-color: var(--cream);
  border-radius: 50%;
}
.ticket-divider::before { left: -31px; }
.ticket-divider::after { right: -31px; }

/* Section label */
.sec-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);margin:16px 0 8px;padding-bottom:4px;border-bottom:1px solid #f3f4f6;}
.sec-label:first-child{margin-top:0;}

/* Detail rows */
.detail-row{display:flex;justify-content:space-between;align-items:baseline;padding:5px 0;}
.detail-label{font-size:.82rem;color:var(--muted);font-weight:500;}
.detail-value{font-size:.84rem;font-weight:600;color:var(--txt);text-align:right;max-width:65%;}

/* Total box */
.total-box{background:var(--gd);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;margin-top:16px;}
.total-box .lbl{font-size:.82rem;font-weight:600;color:#a7f3d0;text-transform:uppercase;letter-spacing:0.5px;}
.total-box .val{font-size:1.2rem;font-weight:800;color:#fff;}

/* Actions */
.receipt-actions{display:flex;gap:12px;justify-content:center;margin-top:20px;flex-wrap:wrap;}
.btn-dashboard{display:inline-flex;align-items:center;gap:8px;background:var(--gd);color:#fff;padding:10px 24px;border-radius:50px;font-weight:700;font-size:.88rem;text-decoration:none;transition:all .2s;box-shadow:0 4px 12px rgba(26,61,43,.15);}
.btn-dashboard:hover{background:var(--gm);transform:translateY(-1px);color:#fff;}
.btn-print{display:inline-flex;align-items:center;gap:8px;background:#fff;color:var(--muted);padding:9px 20px;border-radius:50px;font-weight:600;font-size:.84rem;text-decoration:none;border:1.5px solid var(--border);transition:all .2s;cursor:pointer;}
.btn-print:hover{border-color:var(--gd);color:var(--gd);}

@media print{
  .nb,.receipt-actions,.page-eyebrow,.page-title{display:none!important;}
  body{background:#fff;}
  .page{padding:0;margin:0 auto;}
  .receipt-card{box-shadow:none;border:1px dashed #ccc;border-radius:0;margin:0 auto;max-width:440px !important;width:100% !important;page-break-inside:avoid;break-inside:avoid;}
  .receipt-body{padding:16px 20px;}
  .ticket-divider::before, .ticket-divider::after { display:none!important; }
  @page { size: auto; margin: 5mm; }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="nb">
  <a href="guest_dashboard.php" class="nb-brand">
    <img src="images/logo.jpg" alt="Logo">
    <div class="nb-brand-txt">
      <strong>Sinulom &amp; Bolao</strong>
      <span>Cold Spring Resort</span>
    </div>
  </a>
  <a href="guest_dashboard.php?tab=history" class="nb-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</nav>

<!-- Page -->
<div class="page">
  <div class="page-eyebrow">Receipt</div>
  <h1 class="page-title">Booking Receipt</h1>

  <div class="receipt-card">
    <div class="receipt-header">
      <div class="receipt-header-left">
        <h2>Booking #<?= $bid_display ?></h2>
        <p>Issued: <?= date('F d, Y', strtotime($booking['created_at'])) ?></p>
      </div>
      <span class="receipt-status"><?= $sc['label'] ?></span>
    </div>
    <div class="receipt-body">

      <div class="sec-label">Guest Information</div>
      <div class="detail-row">
        <span class="detail-label">Guest Name</span>
        <span class="detail-value"><?= htmlspecialchars($booking['guest_name']) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value"><?= htmlspecialchars($booking['guest_email']) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Contact</span>
        <span class="detail-value"><?= htmlspecialchars($booking['guest_phone'] ?: '—') ?></span>
      </div>

      <div class="ticket-divider"></div>

      <div class="sec-label">Booking Details</div>
      <div class="detail-row">
        <span class="detail-label">Booking Mode</span>
        <span class="detail-value"><?= ucfirst($booking['mode']) ?></span>
      </div>
      <?php if ($ts_label !== '—'): ?>
      <div class="detail-row">
        <span class="detail-label">Time Slot</span>
        <span class="detail-value"><?= htmlspecialchars($ts_label) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($booking['area_name'])): ?>
      <div class="detail-row">
        <span class="detail-label">Location</span>
        <span class="detail-value"><?= htmlspecialchars($booking['area_name']) ?></span>
      </div>
      <?php endif; ?>
      <div class="detail-row">
        <span class="detail-label"><?= $is_grouped ? 'Facilities' : 'Facility' ?></span>
        <span class="detail-value"><?= htmlspecialchars($facility_display) ?></span>
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
        <span class="detail-value"><?= $total_adults_agg ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Children</span>
        <span class="detail-value"><?= $total_children_agg ?></span>
      </div>
      <?php if ($total_pwd_agg > 0): ?>
      <div class="detail-row">
        <span class="detail-label">PWD / Senior</span>
        <span class="detail-value"><?= $total_pwd_agg ?></span>
      </div>
      <?php endif; ?>
      <?php if ($total_below5_agg > 0): ?>
      <div class="detail-row">
        <span class="detail-label">Kids (Below 5)</span>
        <span class="detail-value"><?= $total_below5_agg ?> (Free)</span>
      </div>
      <?php endif; ?>
      <?php if (!empty($all_trans_opts)): 
          $trans_labels = array_map(function($tr) {
              return $tr === 'tignapoloan' ? 'Tignapoloan Crossing' : ($tr === 'cdo' ? 'Cagayan De Oro' : 'Private Vehicle Rental');
          }, $all_trans_opts);
      ?>
      <div class="detail-row">
        <span class="detail-label">Transportation</span>
        <span class="detail-value"><?= implode(', ', $trans_labels) ?></span>
      </div>
      <?php endif; ?>

      <?php if (!empty($addons)): ?>
      <div class="ticket-divider"></div>
      <div class="sec-label">Add-ons</div>
      <?php foreach ($addons as $ad): ?>
      <div class="detail-row">
        <span class="detail-label"><?= htmlspecialchars($ad['amenity_name']) ?> (x<?= intval($ad['quantity']) ?>)</span>
        <span class="detail-value">&#8369;<?= number_format(floatval($ad['amenity_price']) * intval($ad['quantity']), 2) ?></span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <div class="ticket-divider"></div>

      <div class="total-box">
        <span class="lbl">Total Amount<?= $is_grouped ? ' (All Facilities)' : '' ?></span>
        <span class="val">&#8369;<?= number_format($total_price_agg, 2) ?></span>
      </div>

    </div>
  </div>

  <div class="receipt-actions">
    <a href="guest_dashboard.php?tab=history" class="btn-dashboard">
      <i class="fas fa-th-large"></i> Back to Dashboard
    </a>
    <button class="btn-print" onclick="window.print()">
      <i class="fas fa-print"></i> Print Receipt
    </button>
  </div>
</div>

</body>
</html>
