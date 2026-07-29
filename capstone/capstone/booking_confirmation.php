<?php
session_start();
require_once 'config/db_config.php';

// Support both booking_ids (multi) and booking_id (single)
$booking_ids_raw   = isset($_GET['booking_ids']) ? $_GET['booking_ids'] : '';
$booking_id_single = isset($_GET['booking_id'])  ? intval($_GET['booking_id']) : 0;

$b_ids = [];
if (!empty($booking_ids_raw)) {
    $b_ids = array_filter(array_map('intval', explode(',', $booking_ids_raw)));
} elseif ($booking_id_single > 0) {
    $b_ids = [$booking_id_single];
}

if (empty($b_ids)) {
    header("Location: landing.php");
    exit();
}

$back_url   = 'landing.php';
$back_label = 'Back to Home';
$is_guest   = !empty($_SESSION['guest_logged_in']);

// Fetch ALL bookings
$bookings = [];
foreach ($b_ids as $bid) {
    $stmt = $conn->prepare("SELECT b.*, f.name AS facility_name, f.price AS facility_price, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id WHERE b.id = ?");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    $brow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($brow) $bookings[] = $brow;
}

if (empty($bookings)) {
    header("Location: landing.php");
    exit();
}

// Use the first booking for guest info / dates / mode / status
$booking    = $bookings[0];
$booking_id = $booking['id'];

// ── Parse notes & pricing reconstruction (Combined across all bookings) ───────
$time_slot_map = [
    '8am-12pm'   => '8:00 AM – 12:00 PM (Morning)',
    '12pm-5pm'   => '12:00 PM – 5:00 PM (Afternoon)',
    'full_day'   => '8:00 AM – 5:00 PM (Full Day)',
    '8am-5pm'    => '8:00 AM – 5:00 PM',
    'full_night' => 'Full Night (Overnight)',
    'overnight'  => '8:00 AM – 8:00 PM (Overnight)',
];

$time_slot_labels = [];
$total_adults     = 0;
$total_children   = 0;
$total_pwd        = 0;
$total_below5     = 0;
$total_guests     = 0;

$adult_total_all   = 0.0;
$child_total_all   = 0.0;
$pwd_total_all     = 0.0;
$area_total_all    = 0.0;
$all_facility_cost = 0.0;
$subtotal_all      = 0.0;
$vat_all           = 0.0;
$total_trans_cost_all = 0.0;
$trans_opts        = [];

// Fetch dynamic VAT rate from settings (fallback to 12%)
$vat_rate = 12.00;
$vat_res  = $conn->query("SELECT vat_rate FROM site_settings WHERE id=1 LIMIT 1");
if ($vat_res && $vat_row = $vat_res->fetch_assoc()) {
    $vat_rate = floatval($vat_row['vat_rate'] ?? 12.00);
}
$vat_multiplier = $vat_rate / 100;

// Since nights might depend on mode of each booking
$global_nights = 1;

foreach ($bookings as $bk) {
    $bk_time_slot  = '';
    $bk_num_below5 = 0;
    $bk_num_pwd    = 0;
    $bk_vat_amount = null;
    $bk_transport  = 'none';

    if (!empty($bk['notes'])) {
        if (preg_match('/Time Slot:\s*([a-z0-9_\-]+)/i', $bk['notes'], $m))  $bk_time_slot  = strtolower($m[1]);
        if (preg_match('/Below5:\s*(\d+)/i',             $bk['notes'], $m))  $bk_num_below5 = intval($m[1]);
        if (preg_match('/PWD:\s*(\d+)/i',                $bk['notes'], $m))  $bk_num_pwd    = intval($m[1]);
        if (preg_match('/VAT:\s*([\d.]+)/i',             $bk['notes'], $m))  $bk_vat_amount = floatval($m[1]);
        if (preg_match('/Transport:\s*([a-z0-9_\-]+)/i', $bk['notes'], $m))  $bk_transport  = strtolower($m[1]);
    }

    $lbl = $time_slot_map[$bk_time_slot] ?? ($bk_time_slot ?: '');
    if ($lbl !== '' && !in_array($lbl, $time_slot_labels)) {
        $time_slot_labels[] = $lbl;
    }

    $bk_num_adults_db   = intval($bk['num_adults']     ?? 0);
    $bk_num_children_db = intval($bk['num_children']   ?? 0);
    $bk_num_pwd_db      = intval($bk['num_discounted'] ?? $bk_num_pwd);
    $bk_num_below5_db   = intval($bk['num_below5']     ?? $bk_num_below5);
    $bk_num_guests      = intval($bk['num_guests']     ?? 0);

    $total_adults   += $bk_num_adults_db;
    $total_children += $bk_num_children_db;
    $total_pwd      += $bk_num_pwd_db;
    $total_below5   += $bk_num_below5_db;
    $total_guests   += $bk_num_guests;

    $bk_mode = $bk['mode'];
    $bk_ci   = $bk['check_in_date'];
    $bk_co   = $bk['check_out_date'];
    $bk_nights = 1;
    if ($bk_mode === 'overnight' && $bk_ci && $bk_co && $bk_co > $bk_ci) {
        $d1 = new DateTime($bk_ci);
        $d2 = new DateTime($bk_co);
        $bk_nights = max(1, (int)$d1->diff($d2)->days);
    }
    $global_nights = max($global_nights, $bk_nights);

    // Area rates (mirror getLandingAreaRates)
    $bk_areaNameLower = strtolower(trim((string)($bk['area_name'] ?? '')));
    $bk_rate_adult = 110.0; $bk_rate_child = 60.0;
    if (in_array($bk_areaNameLower, ['both', 'combo', 'combo package', 'sinulom + bolao'], true)) {
        $bk_rate_adult = 160.0; $bk_rate_child = 85.0;
    }
    $bk_rate_pwd = round($bk_rate_adult * 0.80, 2);

    $bk_adult_total = $bk_num_adults_db   * $bk_rate_adult;
    $bk_child_total = $bk_num_children_db * $bk_rate_child;
    $bk_pwd_total   = $bk_num_pwd_db      * $bk_rate_pwd;
    $bk_area_total  = $bk_adult_total + $bk_child_total + $bk_pwd_total;

    $adult_total_all += $bk_adult_total;
    $child_total_all += $bk_child_total;
    $pwd_total_all   += $bk_pwd_total;
    $area_total_all  += $bk_area_total;

    $fp = floatval($bk['facility_price'] ?? 0);
    $bk_facility_cost = ($bk_mode === 'overnight') ? $fp * $bk_nights : $fp;
    $all_facility_cost += $bk_facility_cost;

    // Calculate transport cost per booking
    $bk_trans_cost = 0;
    if ($bk_transport !== 'none') {
        $trans_opts[] = $bk_transport;
        $bk_trans_guests = max(0, $bk_num_adults_db + $bk_num_pwd_db);
        if ($bk_transport === 'tignapoloan') {
            $bk_trans_cost = $bk_trans_guests * 50;
        } elseif ($bk_transport === 'cdo') {
            $bk_trans_cost = $bk_trans_guests * 250;
        } elseif ($bk_transport === 'private') {
            $bk_trans_cost = 3500;
        }
        $total_trans_cost_all += $bk_trans_cost;
    }

    $bk_subtotal = $bk_facility_cost + $bk_area_total + $bk_trans_cost;
    $subtotal_all += $bk_subtotal;
    $vat_all += ($bk_vat_amount !== null) ? $bk_vat_amount : round($bk_subtotal * $vat_multiplier, 2);
}

$time_slot_label_combined = implode(', ', $time_slot_labels);
$subtotal                 = $subtotal_all;
$vat                      = $vat_all;
$grand_total              = array_sum(array_column($bookings, 'total_price'));
$combined_transportation  = count($trans_opts) > 0 ? implode(', ', array_unique($trans_opts)) : 'none';

$display_vat_rate = $vat_rate;
if ($subtotal_all > 0) {
    $display_vat_rate = ($vat_all / $subtotal_all) * 100;
}

// ── Auto-send receipt email (once per session) ───────────────────────────────
$email_sent = false;
if (!empty($booking['guest_email'])) {
    $booking_data_email = [
        'booking_id'     => $booking['id'],
        'booking_ids'    => array_values($b_ids), // All booking IDs for payment deduplication
        'guest_name'     => $booking['guest_name'],
        'guest_email'    => $booking['guest_email'],
        'guest_phone'    => $booking['guest_phone'],
        'facility_name'  => implode(', ', array_column($bookings, 'facility_name')),
        'area_name'      => implode(', ', array_unique(array_filter(array_column($bookings, 'area_name')))),
        'check_in_date'  => $booking['check_in_date'],
        'check_out_date' => $booking['check_out_date'],
        'num_adults'     => $total_adults,
        'num_children'   => $total_children,
        'num_pwd'        => $total_pwd,
        'num_below5'     => $total_below5,
        'mode'           => $booking['mode'],
        'time_slot'      => '',
        'notes'          => implode(' | ', array_column($bookings, 'notes')),
        'total_price'    => $grand_total,
        'status'         => $booking['status'],
        'transportation' => $combined_transportation,
        'transport_cost' => $total_trans_cost_all,
        'vat'            => $vat,
    ];
    $session_key = 'email_sent_booking_' . implode('_', $b_ids);
    if (!isset($_SESSION[$session_key])) {
        require_once 'includes/send_booking_email.php';
        $email_sent = sendBookingConfirmationEmail($booking_data_email);
        $_SESSION[$session_key] = true;
    } else {
        $email_sent = true;
    }
}

// ── Fetch payment records for ALL booking IDs (grouped by reference_number) ──
$payment = null;
$total_paid = 0.0;
$all_payments = [];
$ref_groups = []; // reference_number => payment_assoc
foreach ($b_ids as $bid) {
    $pstmt = $conn->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY paid_at ASC");
    $pstmt->bind_param("i", $bid);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    while ($pr = $pres->fetch_assoc()) {
        $ref_key = trim($pr['reference_number']);
        if ($ref_key !== '') {
            if (!isset($ref_groups[$ref_key])) {
                $ref_groups[$ref_key] = $pr;
            } else {
                $ref_groups[$ref_key]['amount_paid'] = floatval($ref_groups[$ref_key]['amount_paid']) + floatval($pr['amount_paid']);
                if ($pr['status'] === 'completed') {
                    $ref_groups[$ref_key]['status'] = 'completed';
                }
            }
        } else {
            $all_payments[] = $pr;
        }
    }
    $pstmt->close();
}
foreach ($ref_groups as $grouped_p) {
    $all_payments[] = $grouped_p;
}
foreach ($all_payments as $pr) {
    $total_paid += floatval($pr['amount_paid']);
}
$payment = count($all_payments) > 0 ? $all_payments[0] : null;
$remaining_balance = $grand_total - $total_paid;

function fmtPHP($v) { return '₱' . number_format($v, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Submitted — Sinulom &amp; Bolao Resort</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:#f5f0e8;min-height:100vh;display:flex;flex-direction:column;}
        .nb{background:#1a3d2b;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;}
        .nb-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
        .nb-brand img{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.2);}
        .nb-brand-txt strong{display:block;font-size:.9rem;font-weight:700;color:#fff;line-height:1.2;}
        .nb-brand-txt span{font-size:.72rem;color:rgba(255,255,255,.65);}
        .nb-back{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff;padding:8px 18px;border-radius:50px;font-size:.85rem;font-weight:600;text-decoration:none;transition:background .2s;}
        .nb-back:hover{background:rgba(255,255,255,.22);color:#fff;}
        .page{flex:1;display:flex;flex-direction:column;align-items:center;padding:32px 24px 60px;}
        .page-eyebrow{font-size:.72rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#4a7c59;margin-bottom:10px;display:flex;align-items:center;gap:10px;}
        .page-eyebrow::before,.page-eyebrow::after{content:'';width:28px;height:1px;background:#4a7c59;}
        .page-title{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:800;color:#1a1a1a;margin-bottom:32px;text-align:center;}
        /* Success card */
        .success-card{background:#fff;border-radius:20px;padding:40px 36px;max-width:1200px;width:100%;text-align:center;box-shadow:0 4px 32px rgba(0,0,0,.08);margin-bottom:20px;}
        .check-icon{width:80px;height:80px;border-radius:50%;background:#f0faf4;border:2px solid #c8e6c9;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:2rem;color:#1a3d2b;}
        .success-card h2{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:800;color:#1a1a1a;margin-bottom:10px;}
        .sub-text{font-size:.9rem;color:#6b7280;line-height:1.7;margin-bottom:6px;}
        .email-highlight{font-weight:700;color:#1a3d2b;}
        .booking-ref{display:inline-block;background:#f0faf4;border:1px solid #c8e6c9;border-radius:10px;padding:8px 20px;font-size:.85rem;font-weight:700;color:#1a3d2b;margin:16px 0 12px;}
        .status-badge{display:inline-flex;align-items:center;gap:8px;background:#fff8e1;color:#e65100;border:2px solid #fbbf24;border-radius:14px;padding:12px 28px;font-size:1.05rem;font-weight:800;letter-spacing:.3px;margin:12px 0 28px;}
        .status-badge i{font-size:1.1rem;animation:pulse-dot 1.5s infinite;}@keyframes pulse-dot{0%,100%{opacity:1;}50%{opacity:.4;}}
        .btn-home{display:inline-flex;align-items:center;gap:8px;background:#1a3d2b;color:#fff;padding:13px 32px;border-radius:50px;font-weight:700;font-size:.92rem;text-decoration:none;transition:all .3s;box-shadow:0 6px 20px rgba(26,61,43,.3);}
        .btn-home:hover{background:#2d5a3d;transform:translateY(-2px);color:#fff;}
        .btn-login{display:inline-flex;align-items:center;gap:8px;background:#fff;color:#1a3d2b;padding:12px 28px;border-radius:50px;font-weight:700;font-size:.88rem;text-decoration:none;transition:all .3s;border:2px solid #1a3d2b;margin-top:10px;}
        .btn-login:hover{background:#f0faf4;transform:translateY(-2px);color:#1a3d2b;}
        /* Receipt card */
        .receipt-card{background:#fff;border-radius:20px;max-width:1200px;width:100%;box-shadow:0 4px 32px rgba(0,0,0,.08);overflow:hidden;}
        .receipt-hdr{background:linear-gradient(135deg, #10301d 0%, #1a3d2b 50%, #2d5a3d 100%);padding:20px 28px;display:flex;align-items:center;gap:12px;border-bottom:3px solid #d4af37;}
        .receipt-hdr i{color:#fbbf24;font-size:1.2rem;}
        .receipt-hdr h3{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:800;color:#fff;margin:0;letter-spacing:0.5px;}
        .section-label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#1a3d2b;background:#eaf4ee;padding:10px 24px;border-left:4px solid #1a3d2b;display:flex;align-items:center;gap:8px;}
        .section-label i{color:#1a3d2b;}
        .detail-row{display:flex;justify-content:space-between;align-items:center;padding:11px 24px;border-bottom:1px solid #f5f0e8;}
        .detail-row:nth-of-type(even){background-color:#fcfbfa;}
        .detail-row:last-child{border-bottom:none;}
        .detail-label{font-size:.82rem;color:#6b7280;display:flex;align-items:center;gap:6px;}
        .detail-label i{color:#4a7c59;}
        .detail-value{font-size:.88rem;font-weight:600;color:#1a1a1a;text-align:right;max-width:58%;}
        .badge-free{background:#d1fae5;color:#065f46;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:50px;border:1px solid #a7f3d0;}
        .badge-disc{background:#fef3c7;color:#92400e;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:50px;border:1px solid #fde68a;}
        .subtotal-row{display:flex;justify-content:space-between;align-items:center;padding:12px 24px;border-bottom:1px solid #e5e7eb;background:#fafafa;}
        .subtotal-row .lbl{font-size:.85rem;font-weight:700;color:#1a1a1a;}
        .subtotal-row .val{font-size:.9rem;font-weight:700;color:#1a1a1a;}
        .vat-row{display:flex;justify-content:space-between;align-items:center;padding:10px 24px;border-bottom:1px solid #e5e7eb;background:#fafafa;}
        .vat-row .lbl{font-size:.82rem;color:#6b7280;}
        .vat-row .val{font-size:.85rem;color:#6b7280;font-weight:600;}
        .total-row{background:linear-gradient(135deg, #10301d 0%, #1a3d2b 100%);padding:18px 24px;display:flex;justify-content:space-between;align-items:center;border-top:2px solid #d4af37;}
        .total-row .tl{font-size:.92rem;font-weight:700;color:rgba(255,255,255,.9);}
        .total-row .tv{font-size:1.35rem;font-weight:800;color:#fbbf24;}
        .receipt-print-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .receipt-print-col {
            width: 100%;
        }
        .receipt-print-col:first-child {
            border-right: 2px solid #eaf4ee;
        }
        
        /* Force color adjust in printing */
        body, .receipt-card, .receipt-hdr, .section-label, .total-row, .subtotal-row, .vat-row, .badge-free, .badge-disc, .detail-row {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        @media(max-width:600px){.receipt-card,.success-card{border-radius:12px;} .detail-row{flex-direction:column;align-items:flex-start;gap:4px;} .detail-value{max-width:100%;text-align:left;}}
        @media print{
            @page {
                size: landscape;
                margin: 8mm;
            }
            .nb, .success-card, .no-print, .page-eyebrow, .page-title { display: none !important; }
            body { background: #fff; margin: 0; padding: 0; }
            .page { padding: 0; width: 100%; max-width: 100%; }
            .receipt-card { box-shadow: none; border: 1px solid #ddd; max-width: 100%; width: 100%; border-radius: 0; }
            .receipt-print-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                padding: 15px 24px;
            }
            .receipt-print-col {
                width: 100%;
            }
            .receipt-hdr {
                padding: 12px 24px;
                background: linear-gradient(135deg, #10301d 0%, #1a3d2b 50%, #2d5a3d 100%) !important;
                border-bottom: 3px solid #d4af37 !important;
            }
            .section-label {
                padding: 8px 15px;
                margin-top: 10px;
                background: #eaf4ee !important;
                color: #1a3d2b !important;
                border-left: 4px solid #1a3d2b !important;
            }
            .section-label:first-child {
                margin-top: 0;
            }
            .detail-row {
                padding: 8px 15px;
            }
            .detail-row:nth-of-type(even){
                background-color:#fcfbfa !important;
            }
            .subtotal-row {
                padding: 10px 15px;
                background: #fafafa !important;
            }
            .vat-row {
                padding: 8px 15px;
                background: #fafafa !important;
            }
            .total-row {
                padding: 14px 15px;
                background: linear-gradient(135deg, #10301d 0%, #1a3d2b 100%) !important;
                border-top: 2px solid #d4af37 !important;
            }
            .total-row .tv {
                color: #fbbf24 !important;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="nb">
    <a href="<?= $is_guest ? 'guest_dashboard.php' : 'landing.php' ?>" class="nb-brand">
        <img src="images/logo.jpg" alt="Logo">
        <div class="nb-brand-txt">
            <strong>Sinulom &amp; Bolao</strong>
            <span>Cold Spring Resort</span>
        </div>
    </a>

</nav>

<!-- PAGE -->
<div class="page">
    <div class="page-eyebrow">Reservation</div>
    <h1 class="page-title">Booking Submitted</h1>

    <!-- Success Card -->
    <div class="success-card">
        <div class="check-icon" <?php if ($booking['status'] === 'confirmed') echo 'style="background:var(--gd);"'; ?>><i class="fas fa-check"></i></div>
        <h2><?php 
            if ($booking['status'] === 'confirmed') echo 'Booking Confirmed!';
            else echo 'Booking Submitted!'; 
        ?></h2>
        <p class="sub-text"><?php 
            if ($booking['status'] === 'confirmed') echo 'Your booking has been confirmed by our staff.';
            else echo 'Your booking has been received. Our staff will review and confirm it shortly.'; 
        ?></p>
        <?php if (!empty($booking['guest_email'])): ?>
        <p class="sub-text" style="margin-top:10px;">
            <?php if ($email_sent): ?>
                <i class="fas fa-check-circle" style="color:#1a3d2b;"></i> A receipt has been sent to:<br>
            <?php else: ?>
                <i class="fas fa-envelope" style="color:#e65100;"></i> Confirmation will be sent to:<br>
            <?php endif; ?>
            <span class="email-highlight"><?php echo htmlspecialchars($booking['guest_email']); ?></span>
        </p>
        <?php endif; ?>
        <div class="booking-ref">
            Booking Ref: #<?php echo str_pad($b_ids[0], 6, '0', STR_PAD_LEFT); ?>
        </div>
        <div class="status-badge" <?php 
            if ($booking['status'] === 'confirmed') echo 'style="background:#e8f5e9;color:#1a7a3a;border-color:#c8e6c9;"'; 
            else echo 'style="background:#fff8e1;color:#e65100;border-color:#fbbf24;"'; 
        ?>>
            <?php if ($booking['status'] === 'confirmed'): ?>
                <i class="fas fa-check-circle"></i> Confirmed
            <?php else: ?>
                <i class="fas fa-hourglass-half"></i> Pending Confirmation
            <?php endif; ?>
        </div>
        <div style="margin-top:auto;padding-top:8px;display:flex;flex-direction:column;align-items:center;gap:0;">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn-home">
                <i class="fas fa-home"></i> <?= $back_label ?>
            </a>
            <?php if (!$is_guest): ?>
            <a href="guest_login.php" class="btn-login">
                <i class="fas fa-user-circle"></i> View My Bookings (Guest Login)
            </a>
            <p style="font-size:.75rem;color:#9ca3af;margin-top:8px;text-align:center;">
                <i class="fas fa-key" style="margin-right:4px;"></i>Use the <strong>email &amp; password</strong> you set during booking.
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Full Receipt Card -->
    <div class="receipt-card">
        <div class="receipt-hdr">
            <i class="fas fa-receipt"></i>
            <h3>Official Booking Receipt</h3>
        </div>

        <div class="receipt-print-grid">
            <div class="receipt-print-col">
                <!-- Guest Information -->
                <div class="section-label"><i class="fas fa-user" style="margin-right:6px;"></i> Guest Information</div>
        <div class="detail-row">
            <span class="detail-label">Guest Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Contact Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email Address</span>
            <span class="detail-value"><?php echo htmlspecialchars($booking['guest_email']); ?></span>
        </div>

        <!-- Booking Details -->
        <div class="section-label"><i class="fas fa-clipboard-list" style="margin-right:6px;"></i> Booking Details</div>
        <div class="detail-row">
            <span class="detail-label">Booking Reference</span>
            <span class="detail-value">#<?php echo str_pad($b_ids[0], 6, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Booking Mode</span>
            <span class="detail-value"><?php 
                $modes = array_unique(array_column($bookings, 'mode'));
                $mode_labels = array_map(function($m) { return ($m === 'overnight') ? 'Overnight' : 'Day Tour'; }, $modes);
                echo implode(', ', $mode_labels); 
            ?></span>
        </div>
        <?php if (!empty($time_slot_label_combined)): ?>
        <div class="detail-row">
            <span class="detail-label"><i class="fas fa-clock" style="color:#4a7c59;"></i> Time Slot</span>
            <span class="detail-value"><?php echo htmlspecialchars($time_slot_label_combined); ?></span>
        </div>
        <?php endif; ?>
        <?php 
        $area_names = [];
        foreach ($bookings as $bk) {
            if (!empty($bk['area_name']) && !in_array($bk['area_name'], $area_names)) {
                $area_names[] = $bk['area_name'];
            }
        }
        if (!empty($area_names)):
        ?>
        <div class="detail-row">
            <span class="detail-label"><i class="fas fa-map-marker-alt" style="color:#4a7c59;"></i> Location</span>
            <span class="detail-value"><?php echo htmlspecialchars(implode(', ', $area_names)); ?></span>
        </div>
        <?php endif; ?>
        <div class="detail-row" style="align-items: flex-start;">
            <span class="detail-label"><i class="fas fa-building" style="color:#4a7c59;"></i> Facilities</span>
            <span class="detail-value">
                <?php 
                $fac_names = [];
                foreach ($bookings as $bk) {
                    $fac_names[] = htmlspecialchars($bk['facility_name']);
                }
                echo implode('<br>', $fac_names);
                ?>
            </span>
        </div>
        <?php
        $same_dates = true;
        $first_ci = $bookings[0]['check_in_date'];
        $first_co = $bookings[0]['check_out_date'];
        $first_mode = $bookings[0]['mode'];
        foreach ($bookings as $bk) {
            if ($bk['check_in_date'] !== $first_ci || $bk['check_out_date'] !== $first_co || $bk['mode'] !== $first_mode) {
                $same_dates = false;
                break;
            }
        }
        if ($same_dates):
        ?>
        <div class="detail-row">
            <span class="detail-label">Check-in Date</span>
            <span class="detail-value"><?php echo date('F j, Y (D)', strtotime($first_ci)); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Check-out Date</span>
            <span class="detail-value">
                <?php echo ($first_mode === 'daytour') ? 'Same Day' : date('F j, Y (D)', strtotime($first_co)); ?>
            </span>
        </div>
        <?php if ($first_mode === 'overnight' && $global_nights > 1): ?>
        <div class="detail-row">
            <span class="detail-label">Duration</span>
            <span class="detail-value"><?php echo $global_nights; ?> Night(s)</span>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="detail-row" style="align-items: flex-start;">
            <span class="detail-label">Dates per Booking</span>
            <span class="detail-value" style="font-size:0.82rem;">
                <?php foreach ($bookings as $bk): 
                    $bk_ci   = $bk['check_in_date'];
                    $bk_co   = $bk['check_out_date'];
                    $bk_mode = $bk['mode'];
                ?>
                    <div style="margin-bottom: 6px;">
                        <strong><?php echo htmlspecialchars($bk['facility_name']); ?>:</strong><br>
                        <?php echo date('M j, Y', strtotime($bk_ci)); ?> to 
                        <?php echo ($bk_mode === 'daytour') ? 'Same Day' : date('M j, Y', strtotime($bk_co)); ?>
                    </div>
                <?php endforeach; ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Guest Count -->
        <div class="section-label"><i class="fas fa-users" style="margin-right:6px;"></i> Guest Count</div>
        <div class="detail-row">
            <span class="detail-label">Adults</span>
            <span class="detail-value"><?php echo $total_adults; ?> pax</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Children (Ages 6&ndash;17)</span>
            <span class="detail-value"><?php echo $total_children; ?> pax</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Children Age 5 &amp; Below <span class="badge-free">FREE</span></span>
            <span class="detail-value"><?php echo $total_below5; ?> pax</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">PWD / Seniors <span class="badge-disc">20% OFF</span></span>
            <span class="detail-value"><?php echo $total_pwd; ?> pax</span>
        </div>
        <div class="detail-row">
            <span class="detail-label" style="font-weight:600;">Total Guests</span>
            <span class="detail-value" style="color:#1a3d2b;"><?php echo $total_guests; ?> pax</span>
        </div>

        </div><!-- /receipt-print-col -->
        <div class="receipt-print-col">

        <!-- Price Breakdown -->
        <div class="section-label"><i class="fas fa-tags" style="margin-right:6px;"></i> Price Breakdown</div>
        <?php foreach ($bookings as $bk): 
            $fp = floatval($bk['facility_price'] ?? 0);
            $bk_mode = $bk['mode'];
            $bk_ci   = $bk['check_in_date'];
            $bk_co   = $bk['check_out_date'];
            $bk_nights = 1;
            if ($bk_mode === 'overnight' && $bk_ci && $bk_co && $bk_co > $bk_ci) {
                $d1 = new DateTime($bk_ci);
                $d2 = new DateTime($bk_co);
                $bk_nights = max(1, (int)$d1->diff($d2)->days);
            }
            $bk_facility_cost = ($bk_mode === 'overnight') ? $fp * $bk_nights : $fp;
        ?>
        <div class="detail-row">
            <span class="detail-label">Facility (<?php echo htmlspecialchars($bk['facility_name']); ?>)</span>
            <span class="detail-value">
                <?php echo fmtPHP($fp); ?><?php if ($bk_mode === 'overnight' && $bk_nights > 1) echo ' × ' . $bk_nights . ' nights = ' . fmtPHP($bk_facility_cost); ?>
            </span>
        </div>
        <?php endforeach; ?>
        <div class="detail-row">
            <span class="detail-label">Adults Entry Fee Total</span>
            <span class="detail-value"><?php echo fmtPHP($adult_total_all); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Children Entry Fee Total</span>
            <span class="detail-value"><?php echo fmtPHP($child_total_all); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Children Age 5 &amp; Below <span class="badge-free">FREE</span> &times; <?php echo $total_below5; ?></span>
            <span class="detail-value" style="color:#059669;font-weight:700;">₱0.00</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">PWD/Seniors Entry Fee Total <span class="badge-disc">20% OFF</span></span>
            <span class="detail-value"><?php echo fmtPHP($pwd_total_all); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Location Entry Total</span>
            <span class="detail-value"><?php echo fmtPHP($area_total_all); ?></span>
        </div>
        <?php if ($total_trans_cost_all > 0): ?>
        <div class="detail-row">
            <span class="detail-label">Transportation (<?php 
                $trans_labels = [];
                foreach (array_unique($trans_opts) as $opt) {
                    if ($opt === 'tignapoloan') $trans_labels[] = 'Tignapoloan Crossing';
                    elseif ($opt === 'cdo') $trans_labels[] = 'Cagayan De Oro';
                    elseif ($opt === 'private') $trans_labels[] = 'Private Vehicle Rental';
                }
                echo htmlspecialchars(implode(', ', $trans_labels));
            ?>)</span>
            <span class="detail-value"><?php echo fmtPHP($total_trans_cost_all); ?></span>
        </div>
        <?php endif; ?>

        <!-- Totals -->
        <div class="subtotal-row">
            <span class="lbl">Subtotal</span>
            <span class="val"><?php echo fmtPHP($subtotal); ?></span>
        </div>
        <div class="vat-row">
            <span class="lbl">VAT (<?php echo floatval(number_format($display_vat_rate, 2)); ?>%)</span>
            <span class="val"><?php echo fmtPHP($vat); ?></span>
        </div>
        <div class="total-row">
            <span class="tl">Total Amount <small style="font-size:.75rem;opacity:.7;">(VAT Inclusive)</small></span>
            <span class="tv"><?php echo fmtPHP($grand_total); ?></span>
        </div>

        <!-- Payment Summary -->
        <?php if ($payment): ?>
        <div class="section-label"><i class="fas fa-mobile-alt" style="margin-right:6px;color:#0070e0;"></i> GCash Payment Summary</div>
        <?php foreach ($all_payments as $prow): ?>
        <div class="detail-row">
            <span class="detail-label"><i class="fas fa-qrcode" style="color:#0070e0;"></i> Amount Paid via GCash</span>
            <span class="detail-value" style="color:#059669;font-weight:700;"><?php echo fmtPHP($prow['amount_paid']); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Reference Number</span>
            <span class="detail-value" style="font-family:monospace;background:#f3f4f6;padding:2px 8px;border-radius:6px;"><?php echo htmlspecialchars($prow['reference_number']); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Status</span>
            <span class="detail-value">
                <?php if ($prow['status'] === 'completed'): ?>
                <span style="background:#d1fae5;color:#065f46;font-size:.72rem;font-weight:700;padding:4px 12px;border-radius:50px;border:1px solid #a7f3d0;"><i class="fas fa-check-circle" style="margin-right:4px;"></i> Payment Received</span>
                <?php else: ?>
                <span style="background:#fff8e1;color:#92400e;font-size:.72rem;font-weight:700;padding:4px 12px;border-radius:50px;border:1px solid #fde68a;"><i class="fas fa-clock" style="margin-right:4px;"></i> Payment Pending</span>
                <?php endif; ?>
            </span>
        </div>
        <?php endforeach; ?>
        <div style="background:#<?php echo $remaining_balance <= 0 ? 'dcfce7;border-color:#86efac;' : 'fff8e1;border-color:#fde68a;' ?>border:1px solid;border-radius:0;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:.88rem;font-weight:700;color:#<?php echo $remaining_balance <= 0 ? '065f46' : '92400e'; ?>">
                <?php echo $remaining_balance <= 0 ? '✓ Fully Paid' : 'Remaining Balance (Pay at Resort)'; ?>
            </span>
            <span style="font-size:1.1rem;font-weight:800;color:#<?php echo $remaining_balance <= 0 ? '059669' : 'e65100'; ?>">
                <?php echo $remaining_balance <= 0 ? 'No Balance Due' : fmtPHP($remaining_balance); ?>
            </span>
        </div>
        <?php else: ?>
        <div class="section-label"><i class="fas fa-credit-card" style="margin-right:6px;"></i> Payment</div>
        <div class="detail-row">
            <span class="detail-label">Downpayment</span>
            <span class="detail-value" style="color:#9ca3af;">No payment submitted yet</span>
        </div>
        <div style="background:#fff8e1;border:1px solid #fde68a;border-radius:0;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:.88rem;font-weight:700;color:#92400e;">Amount Due at Resort</span>
            <span style="font-size:1.1rem;font-weight:800;color:#e65100;"><?php echo fmtPHP($grand_total); ?></span>
        </div>
        <?php endif; ?>

        </div><!-- /receipt-print-col -->
        </div><!-- /receipt-print-grid -->

    </div><!-- /receipt-card -->

    <div style="margin-top:24px;text-align:center;" class="no-print">
        <button onclick="window.print()" style="display:inline-flex;align-items:center;gap:8px;background:#fff;color:#1a3d2b;padding:12px 28px;border-radius:50px;font-weight:700;font-size:.9rem;text-decoration:none;border:2px solid #1a3d2b;transition:all .2s;cursor:pointer;">
            <i class="fas fa-print"></i> Print Receipt
        </button>
    </div>

</div><!-- /page -->
</body>
</html>
