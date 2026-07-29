<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !in_array($_SESSION['user_role'], ['admin','frontdesk'])) {
    header("Location: " . BASE_URL . "unauthorized.php");
    exit();
}

$user = get_user_info($_SESSION['user_id'], $conn);

// Handle add booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_booking') {
    $facility_id = intval($_POST['facility_id']);
    $area_id = intval($_POST['area_id']);
    $guest_name = trim(escape_input($_POST['guest_first_name'], $conn) . ' ' . escape_input($_POST['guest_last_name'], $conn));
    $guest_email = escape_input($_POST['guest_email'], $conn);
    $guest_phone = escape_input($_POST['guest_phone'], $conn);
    if (!preg_match('/^\d{11}$/', $guest_phone)) {
        set_error_message('Contact number must be exactly 11 digits (numbers only).');
        header("Location: " . BASE_URL . "admin/walkin_booking.php"); exit();
    }
    $mode = escape_input($_POST['mode'], $conn);
    $time_slot = escape_input($_POST['time_slot'] ?? '', $conn);
    $transportation = escape_input($_POST['transportation'] ?? 'none', $conn);
    $user_notes = escape_input($_POST['notes'] ?? '', $conn);
    $guest_password = trim($_POST['guest_password'] ?? '');

    // For daytour, use the selected check_in_date (not today) so the booking reflects the actual date
    $check_in  = $mode === 'overnight'
        ? escape_input($_POST['check_in_date'], $conn)
        : escape_input($_POST['check_in_date'], $conn);  // use selected date for both
    $check_out = $mode === 'overnight'
        ? escape_input($_POST['check_out_date'], $conn)
        : escape_input($_POST['check_in_date'], $conn);  // daytour: checkout = checkin
    $num_adults = intval($_POST['num_adults']);
    $num_senior_pwd = intval($_POST['num_senior_pwd'] ?? 0);
    $num_children = intval($_POST['num_children']);
    $num_children_below6 = intval($_POST['num_children_below6'] ?? 0);
    $num_children_total = $num_senior_pwd + $num_children + $num_children_below6;
    $num_guests = $num_adults + $num_children_total;
    $total_price = floatval($_POST['total_price']);
    $avail_ok = true; $avail_error = '';
    if ($mode === 'overnight') {
        // Block if any confirmed/pending booking overlaps the overnight dates
        $av = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed') AND check_in_date < ? AND check_out_date > ? LIMIT 1");
        $av->bind_param("iss", $facility_id, $check_out, $check_in);
        $av->execute(); $av->store_result();
        if ($av->num_rows > 0) { $avail_ok = false; $avail_error = 'This facility is already booked for the selected dates. It will be available after the current guest checks out.'; }
        $av->close();
    } else {
        // Block if an overnight booking covers this day
        $av = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed') AND mode='overnight' AND check_in_date <= ? AND check_out_date > ? LIMIT 1");
        $av->bind_param("iss", $facility_id, $check_in, $check_in);
        $av->execute(); $av->store_result();
        if ($av->num_rows > 0) { $avail_ok = false; $avail_error = 'This facility has an overnight booking on the selected date. It will be available after the current guest checks out.'; }
        $av->close();
        // Also block if there's already a confirmed/pending daytour or any booking on the same check-in date
        if ($avail_ok) {
            $av2 = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed') AND check_in_date = ? LIMIT 1");
            $av2->bind_param("is", $facility_id, $check_in);
            $av2->execute(); $av2->store_result();
            if ($av2->num_rows > 0) { $avail_ok = false; $avail_error = 'This facility is already booked for this date. It will be available after the current guest checks out.'; }
            $av2->close();
        }
    }
    if (!$avail_ok) { set_error_message($avail_error); header("Location: " . BASE_URL . "admin/walkin_booking.php"); exit(); }
    
    if (!empty($guest_email) && !empty($guest_password)) {
        $guest_password_hash = password_hash($guest_password, PASSWORD_DEFAULT);
        $ga_stmt = $conn->prepare("INSERT INTO guest_accounts (email, password_hash, full_name, phone) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), phone = VALUES(phone)");
        if ($ga_stmt) { $ga_stmt->bind_param('ssss', $guest_email, $guest_password_hash, $guest_name, $guest_phone); $ga_stmt->execute(); $ga_stmt->close(); }
    }

    $total_price = floatval($_POST['total_price']);
    $vat = round($total_price - ($total_price / 1.12), 2); // default backwards calculation if needed
    // However, JS sends total_price as VAT inclusive. Let's recalculate accurately just to be sure.
    // Actually we just need to embed VAT in notes. The easiest is to receive it from JS or calc it backward:
    $vat = round($total_price / 1.12 * 0.12, 2);

    $notes = "Time Slot: $time_slot | Transport: $transportation | Below5: $num_children_below6 | PWD: $num_senior_pwd | VAT: $vat";
    if (!empty($user_notes)) $notes .= " | Notes: " . $user_notes;

    $stmt = $conn->prepare("INSERT INTO bookings (facility_id, area_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, num_guests, num_adults, num_children, num_below5, num_discounted, mode, booking_type, status, total_price, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'walkin', 'confirmed', ?, ?, ?)");
    $created_by = $_SESSION['user_id'];
    $stmt->bind_param("iisssssiiiiisdsi", $facility_id, $area_id, $guest_name, $guest_email, $guest_phone, $check_in, $check_out, $num_guests, $num_adults, $num_children, $num_children_below6, $num_senior_pwd, $mode, $total_price, $notes, $created_by);
    if ($stmt->execute()) {
        $booking_id = $stmt->insert_id;
        header("Location: " . BASE_URL . "admin/walkin_receipt.php?booking_id=" . $booking_id); exit();
    } else { set_error_message('Error creating booking: ' . $conn->error); }
    $stmt->close();
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout_walkin') {
    $cid = intval($_POST['booking_id']);
    $cs = $conn->prepare("UPDATE bookings SET status='completed' WHERE id=? AND status='confirmed' AND booking_type='walkin'");
    $cs->bind_param("i", $cid);
    if ($cs->execute() && $cs->affected_rows > 0) {
        set_success_message('Guest checked out. Facility is now available.');
        // Send thank-you + feedback email
        $bs = $conn->prepare("SELECT b.*, f.name as facility_name, f.price as facility_price, a.name as area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=?");
        $bs->bind_param("i", $cid); $bs->execute();
        $bd = $bs->get_result()->fetch_assoc(); $bs->close();
        if ($bd && !empty($bd['guest_email'])) {
            require_once '../includes/send_status_email.php';
            sendCheckoutThankYouEmail($bd);
        }
    } else {
        set_error_message('Could not check out this booking.');
    }
    $cs->close();
    header("Location: " . BASE_URL . "admin/walkin_booking.php"); exit();
}

$areas_result      = $conn->query("SELECT * FROM areas WHERE status='active' ORDER BY name");
$facilities_result = $conn->query("SELECT f.*, a.name as area_name FROM facilities f LEFT JOIN areas a ON f.area_id=a.id WHERE f.status='available' ORDER BY f.type, f.name");

$walkin_bookings_arr = [];
$wb = $conn->query("SELECT b.*, f.name as facility_name, a.name as area_name, u.first_name, u.last_name, u.role FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id LEFT JOIN users u ON b.created_by=u.id WHERE b.booking_type='walkin' ORDER BY b.created_at DESC");
if ($wb) { while ($r = $wb->fetch_assoc()) $walkin_bookings_arr[] = $r; }
$walkin_total     = count($walkin_bookings_arr);
$walkin_confirmed = count(array_filter($walkin_bookings_arr, fn($r) => $r['status'] === 'confirmed'));
$walkin_completed = count(array_filter($walkin_bookings_arr, fn($r) => $r['status'] === 'completed'));
$walkin_today     = count(array_filter($walkin_bookings_arr, fn($r) => ($r['check_in_date'] ?? '') === date('Y-m-d')));

$areas_js = []; if ($areas_result) { while ($a = $areas_result->fetch_assoc()) $areas_js[] = $a; }
$facilities_js = []; if ($facilities_result) { while ($f = $facilities_result->fetch_assoc()) $facilities_js[] = $f; }
?><!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="../assets/css/owner-sidebar.css">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Walk-in Booking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php require_once '../includes/admin_page_styles.php'; ?>
<style>
:root{--gd:#1a3d2b;--gm:#2d5a3d;--gl:#4a7c59;--cream:#f5f0e8;--card:#fff;--card2:#f8f5ef;--txt:#1a1a1a;--muted:#6b7280;--red:#c62828;--border:#e2ddd5}
.pb-steps-bar{display:flex;align-items:flex-start;margin-bottom:32px}
.pb-step-item{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;z-index:1}
.pb-step-connector{flex:1;height:2px;background:var(--border);margin-top:23px;z-index:0;align-self:flex-start;transition:background .3s}
.pb-step-connector.done{background:var(--gd)}
.pb-step-circle{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;border:2.5px solid var(--border);background:#fff;color:var(--muted);transition:all .3s;margin-bottom:8px}
.pb-step-item.active .pb-step-circle{background:var(--gd);border-color:var(--gd);color:#fff;box-shadow:0 4px 16px rgba(26,61,43,.35)}
.pb-step-item.done .pb-step-circle{background:var(--gm);border-color:var(--gm);color:#fff}
.pb-step-label{font-size:.72rem;font-weight:600;color:var(--muted);text-align:center;white-space:nowrap}
.pb-step-item.active .pb-step-label{color:var(--gd);font-weight:700}
.pb-step-item.done .pb-step-label{color:var(--gm)}
.pb-card{background:var(--card);border-radius:20px;box-shadow:0 4px 32px rgba(0,0,0,.08);overflow:hidden;margin-bottom:24px}
.pb-card-hdr{background:var(--gd);padding:20px 28px;display:flex;align-items:center;gap:12px}
.pb-card-hdr i{color:rgba(255,255,255,.8);font-size:1.1rem}
.pb-card-hdr h2{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;color:#fff;margin:0}
.pb-card-body{padding:28px}
.pb-form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.pb-form-group{margin-bottom:16px}
.pb-form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--txt);margin-bottom:6px}
.pb-form-group label .req{color:var(--red)}
.pb-input,.pb-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'Inter',sans-serif;font-size:.9rem;color:var(--txt);background:#fff;outline:none;transition:border-color .2s,box-shadow .2s}
.pb-input:focus,.pb-select:focus{border-color:var(--gd);box-shadow:0 0 0 3px rgba(26,61,43,.1)}
textarea.pb-input{resize:vertical;min-height:90px}
.pb-fac-box{background:#f0faf4;border:1.5px solid #a7f3d0;border-radius:12px;padding:16px;margin-top:12px;display:none}
.pb-fac-box.show{display:block}
.pb-fac-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #d1fae5;font-size:.85rem}
.pb-fac-row:last-child{border-bottom:none}
.pb-fac-row span{color:var(--muted)}
.pb-fac-row strong{color:var(--gd);font-weight:700}
.pb-summary-card{background:var(--card2);border-radius:14px;overflow:hidden;margin-bottom:20px}
.pb-summary-hdr{background:var(--gm);padding:14px 20px;font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:#fff}
.pb-summary-row{display:flex;justify-content:space-between;align-items:center;padding:11px 20px;border-bottom:1px solid var(--border);font-size:.88rem}
.pb-summary-row:last-child{border-bottom:none}
.pb-summary-row .lbl{color:var(--muted);font-weight:500}
.pb-summary-row .val{color:var(--txt);font-weight:600;text-align:right;max-width:60%}
.pb-summary-total{background:var(--gd);padding:14px 20px;display:flex;justify-content:space-between;align-items:center}
.pb-summary-total .lbl{color:rgba(255,255,255,.8);font-weight:600}
.pb-summary-total .val{color:#fff;font-size:1.15rem;font-weight:800}
.pb-btn-row{display:flex;gap:12px;justify-content:space-between;margin-top:24px}
.pb-btn-back{padding:12px 28px;border-radius:50px;border:1.5px solid var(--border);background:#fff;color:var(--muted);font-size:.9rem;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px}
.pb-btn-back:hover{border-color:var(--gd);color:var(--gd)}
.pb-btn-next{padding:12px 32px;border-radius:50px;border:none;background:var(--gd);color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(26,61,43,.3)}
.pb-btn-next:hover{background:var(--gm);transform:translateY(-1px)}
.pb-btn-confirm{width:100%;padding:15px;border-radius:50px;border:none;background:var(--gd);color:#fff;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 6px 24px rgba(26,61,43,.35);margin-bottom:12px}
.pb-btn-confirm:hover{background:var(--gm);transform:translateY(-2px)}
.pb-btn-print{padding:12px 24px;border-radius:50px;border:1.5px solid var(--border);background:#fff;color:var(--muted);font-size:.88rem;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px}
.pb-btn-print:hover{border-color:var(--gd);color:var(--gd)}
.pb-cal-wrap{max-width:480px;margin:0 auto}
.pb-cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.pb-cal-nav-btn{width:30px;height:30px;border-radius:50%;border:1.5px solid var(--border);background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--gd);font-size:.78rem;transition:all .2s}
.pb-cal-nav-btn:hover{background:var(--gd);color:#fff;border-color:var(--gd)}
.pb-cal-month{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:var(--txt)}
.pb-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;margin-bottom:8px}
.pb-cal-dow{text-align:center;font-size:.65rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;padding:6px 0}
.pb-cal-dow.wknd{color:var(--gl)}
.pb-cal-day{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:50%;font-size:.8rem;font-weight:500;cursor:pointer;transition:all .15s;border:2px solid transparent;user-select:none}
.pb-cal-day:hover:not(.disabled):not(.empty):not(.past){background:#e8f5e9;border-color:var(--gl)}
.pb-cal-day.today{border-color:var(--gd);color:var(--gd);font-weight:700}
.pb-cal-day.selected{background:var(--gd)!important;color:#fff!important;border-color:transparent!important;font-weight:700;box-shadow:0 2px 10px rgba(26,61,43,.4)}
.pb-cal-day.past{color:#ccc;cursor:not-allowed;pointer-events:none}
.pb-cal-day.empty{pointer-events:none}
.pb-cal-selected-bar{background:#f0faf4;border:1.5px solid #a7f3d0;border-radius:10px;padding:10px 16px;margin-bottom:20px;font-size:.9rem;color:var(--gd);font-weight:600;display:flex;align-items:center;gap:8px}
.pb-ts-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;max-width:480px;margin-left:auto;margin-right:auto}
.pb-ts-card{border:2px solid var(--border);border-radius:12px;padding:12px 14px;cursor:pointer;transition:all .2s;background:#fff;display:flex;align-items:center;gap:10px}
.pb-ts-card:hover{border-color:var(--gl);background:#f0faf4}
.pb-ts-card.selected{border-color:var(--gd);background:var(--gd)}
.pb-ts-card.selected .pb-ts-icon,.pb-ts-card.selected .pb-ts-name,.pb-ts-card.selected .pb-ts-time{color:#fff!important}
.pb-ts-icon{font-size:1.3rem;color:var(--gl);flex-shrink:0}
.pb-ts-name{font-size:.88rem;font-weight:700;color:var(--txt);white-space:nowrap}
.pb-ts-time{font-size:.75rem;color:var(--muted);white-space:nowrap}
.pb-checkout-box{background:#f0faf4;border:1.5px solid #a7f3d0;border-radius:12px;padding:14px;margin-top:14px;max-width:480px;margin-left:auto;margin-right:auto}
.pb-checkout-box label{font-size:.82rem;font-weight:600;color:var(--txt);margin-bottom:6px;display:block}
.pb-step-panel{display:none}
.pb-step-panel.active{display:block}
.pb-wrap{max-width:760px;margin:0 auto;padding:32px 20px 60px}
.print-receipt{display:none}
.receipt-table{width:100%;border-collapse:collapse;font-size:13px}
.receipt-table th,.receipt-table td{padding:6px 4px;border-bottom:1px solid #e5e7eb}
.receipt-table th{text-align:left}
.receipt-total{font-weight:700}
@media print{body *{visibility:hidden}.print-receipt,.print-receipt *{display:block!important;visibility:visible}.print-receipt{position:absolute;left:0;top:0;width:100%;padding:20px}}
@media(max-width:600px){.pb-form-row{grid-template-columns:1fr}.pb-ts-grid{grid-template-columns:1fr}.pb-step-label{font-size:.62rem}.pb-step-circle{width:40px;height:40px;font-size:.95rem}}
</style>
</head>
<body>
<div class="main-container">
<?php require_once '../includes/admin_sidebar.php'; ?>
<div class="content">
<div class="dash-topbar">
  <div>
    <div class="dash-topbar-title"><i class="fas fa-user-plus me-2" style="color:#1B7D3A;"></i>Walk-in Guest Booking</div>
    <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
  </div>
  <div class="d-flex align-items-center gap-3">
    <span class="dash-topbar-badge"><i class="fas fa-shield-alt me-1"></i>Admin</span>
  </div>
</div>
<div class="dash-body">
<?php display_success_message(); display_error_message(); ?>
<!-- KPI row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon blue"><i class="fas fa-list"></i></div><div><div class="kpi-num"><?= $walkin_total ?></div><div class="kpi-lbl">Total Walk-ins</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon green"><i class="fas fa-check-circle"></i></div><div><div class="kpi-num"><?= $walkin_confirmed ?></div><div class="kpi-lbl">Confirmed</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon blue"><i class="fas fa-sign-out-alt"></i></div><div><div class="kpi-num"><?= $walkin_completed ?></div><div class="kpi-lbl">Completed</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon yellow"><i class="fas fa-calendar-day"></i></div><div><div class="kpi-num"><?= $walkin_today ?></div><div class="kpi-lbl">Today</div></div></div></div>
</div>
<!-- Booking Form Card -->
<div class="table-card">
<div class="pb-wrap" style="max-width:100%;padding:24px 0 0">
<!-- Step bar -->
<div class="pb-steps-bar">
  <div class="pb-step-item active" id="si1"><div class="pb-step-circle"><i class="fas fa-calendar-alt"></i></div><div class="pb-step-label">Schedule</div></div>
  <div class="pb-step-connector" id="sc1"></div>
  <div class="pb-step-item" id="si2"><div class="pb-step-circle"><i class="fas fa-user"></i></div><div class="pb-step-label">Guest Details</div></div>
  <div class="pb-step-connector" id="sc2"></div>
  <div class="pb-step-item" id="si3"><div class="pb-step-circle"><i class="fas fa-clipboard-list"></i></div><div class="pb-step-label">Booking Details</div></div>
  <div class="pb-step-connector" id="sc3"></div>
  <div class="pb-step-item" id="si4"><div class="pb-step-circle"><i class="fas fa-check"></i></div><div class="pb-step-label">Confirm</div></div>
</div>

<form method="POST" id="bookingForm">
<input type="hidden" name="action" value="add_booking">
<input type="hidden" name="check_in_date"  id="check_in_date">
<input type="hidden" name="check_out_date" id="check_out_date">
<input type="hidden" name="mode"           id="booking_mode" value="daytour">

<!-- STEP 1: Schedule -->
<div class="pb-step-panel active" id="panel1">
  <div class="pb-card">
    <div class="pb-card-hdr"><i class="fas fa-calendar-alt"></i><h2>Select Date &amp; Time</h2></div>
    <div class="pb-card-body">
      <!-- Calendar -->
      <div class="pb-cal-wrap">
      <div class="pb-cal-nav">
        <button type="button" class="pb-cal-nav-btn" id="cal-prev"><i class="fas fa-chevron-left"></i></button>
        <span class="pb-cal-month" id="cal-month-label"></span>
        <button type="button" class="pb-cal-nav-btn" id="cal-next"><i class="fas fa-chevron-right"></i></button>
      </div>
      <div class="pb-cal-grid">
        <div class="pb-cal-dow">Mon</div><div class="pb-cal-dow">Tue</div><div class="pb-cal-dow">Wed</div>
        <div class="pb-cal-dow">Thu</div><div class="pb-cal-dow">Fri</div>
        <div class="pb-cal-dow wknd">Sat</div><div class="pb-cal-dow wknd">Sun</div>
      </div>
      <div class="pb-cal-grid" id="cal-days"></div>
      <div class="pb-cal-selected-bar"><i class="fas fa-calendar-check"></i><span id="cal-selected-text">No date selected — please click a date above</span></div>
      </div><!-- /pb-cal-wrap -->
      <!-- Time slots -->
      <div style="font-size:.9rem;font-weight:700;color:var(--txt);margin-bottom:4px;text-align:center;"><i class="fas fa-clock me-2" style="color:var(--gl);"></i>Available Time Slots</div>
      <div class="pb-ts-grid" style="grid-template-columns:repeat(3,1fr);max-width:560px;margin-left:auto;margin-right:auto;">
        <div class="pb-ts-card" data-value="8am-12pm"><i class="fas fa-sun pb-ts-icon"></i><div><div class="pb-ts-name">Morning</div><div class="pb-ts-time">8:00 AM – 12:00 PM</div></div></div>
        <div class="pb-ts-card" data-value="12pm-5pm"><i class="fas fa-cloud-sun pb-ts-icon"></i><div><div class="pb-ts-name">Afternoon</div><div class="pb-ts-time">12:00 PM – 5:00 PM</div></div></div>
        <div class="pb-ts-card" data-value="full_day"><i class="fas fa-calendar-day pb-ts-icon"></i><div><div class="pb-ts-name">Full Day</div><div class="pb-ts-time">8:00 AM – 5:00 PM</div></div></div>
      </div>
      <!-- Overnight checkout -->
      <div class="pb-checkout-box" id="checkout-section" style="display:none;">
        <label><i class="fas fa-calendar-minus me-2"></i>Check-out Date *</label>
        <input type="date" class="pb-input" id="checkout-input">
        <div id="nights-display" style="font-size:.82rem;color:var(--gd);font-weight:700;margin-top:6px;"></div>
      </div>
    </div>
  </div>
  <div class="pb-btn-row" style="justify-content:flex-end;">
    <button type="button" class="pb-btn-next" onclick="nextStep()">Next <i class="fas fa-arrow-right"></i></button>
  </div>
</div>

<!-- STEP 2: Guest Details -->
<div class="pb-step-panel" id="panel2">
  <div class="pb-card">
    <div class="pb-card-hdr"><i class="fas fa-user"></i><h2>Guest Details</h2></div>
    <div class="pb-card-body">
      <div class="pb-form-row">
        <div class="pb-form-group">
          <label>First Name <span class="req">*</span></label>
          <input type="text" class="pb-input" name="guest_first_name" id="guest_first_name" placeholder="Juan" autocapitalize="words" oninput="capFirst(this)" required>
        </div>
        <div class="pb-form-group">
          <label>Last Name <span class="req">*</span></label>
          <input type="text" class="pb-input" name="guest_last_name" id="guest_last_name" placeholder="Dela Cruz" autocapitalize="words" oninput="capFirst(this)" required>
        </div>
      </div>
      <div class="pb-form-row">
        <div class="pb-form-group">
          <label>Contact Number <span class="req">*</span></label>
          <input type="tel" class="pb-input" name="guest_phone" id="guest_phone" placeholder="09XXXXXXXXX" inputmode="numeric" maxlength="11" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)" required>
        </div>
        <div class="pb-form-group">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" class="pb-input" name="guest_email" id="guest_email" placeholder="optional">
        </div>
      </div>
      <div class="pb-form-row">
        <div class="pb-form-group">
          <label>Password</label>
          <div style="position:relative;">
            <input type="password" class="pb-input" name="guest_password" id="guest_password" placeholder="Create a password (optional)" style="padding-right:44px;">
            <button type="button" onclick="togglePwVis('guest_password','eyePass')" tabindex="-1" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.9rem;"><i class="fas fa-eye" id="eyePass"></i></button>
          </div>
        </div>
        <div class="pb-form-group">
          <label>Confirm Password</label>
          <div style="position:relative;">
            <input type="password" class="pb-input" id="confirm_password" placeholder="Re-enter password" style="padding-right:44px;">
            <button type="button" onclick="togglePwVis('confirm_password','eyeConfirm')" tabindex="-1" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.9rem;"><i class="fas fa-eye" id="eyeConfirm"></i></button>
          </div>
        </div>
      </div>
      <div id="pwMatchMsg" style="display:none;font-size:.8rem;margin-top:-8px;margin-bottom:8px;"></div>
    </div>
  </div>
  <div class="pb-btn-row">
    <button type="button" class="pb-btn-back" onclick="prevStep()"><i class="fas fa-arrow-left"></i> Back</button>
    <button type="button" class="pb-btn-next" onclick="nextStep()">Next <i class="fas fa-arrow-right"></i></button>
  </div>
</div>

<!-- STEP 3: Booking Details -->
<div class="pb-step-panel" id="panel3">
  <div class="pb-card">
    <div class="pb-card-hdr"><i class="fas fa-clipboard-list"></i><h2>Booking Details</h2></div>
    <div class="pb-card-body">
      <div class="pb-form-row">
        <div class="pb-form-group">
          <label>Time Slot <span class="req">*</span></label>
          <select class="pb-select" id="time_slot_display" name="time_slot" onchange="syncTimeSlot(this)" required>
            <option value="">Select Time Slot</option>
            <option value="8am-12pm">Morning (8:00 AM – 12:00 PM)</option>
            <option value="12pm-5pm">Afternoon (12:00 PM – 5:00 PM)</option>
            <option value="full_day">Full Day (8:00 AM – 5:00 PM)</option>
            <option value="overnight">Overnight (8:00 AM – 8:00 PM)</option>
          </select>
        </div>
        <div class="pb-form-group">
          <label>Booking Mode <span class="req">*</span></label>
          <select class="pb-select" id="mode_display" onchange="syncMode(this)">
            <option value="">Select Booking Mode</option>
            <option value="daytour">Day Tour</option>
            <option value="overnight">Overnight</option>
          </select>
        </div>
      </div>
      <div class="pb-form-group">
        <label>Location <span class="req">*</span></label>
        <select class="pb-select" name="area_id" id="area_id" required>
          <option value="">Select Location</option>
          <?php foreach ($areas_js as $area): ?>
          <option value="<?= $area['id'] ?>" data-regular="<?= $area['price_regular'] ?>" data-discounted="<?= $area['price_discounted'] ?>" data-children="<?= $area['price_children'] ?>" data-free-age="<?= $area['free_below_age'] ?>">
            <?= htmlspecialchars($area['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pb-form-group">
        <label>Facility <span class="req">*</span></label>
        <select class="pb-select" name="facility_id" id="facility_id" required>
          <option value="">Select Facility</option>
          <?php foreach ($facilities_js as $f): ?>
          <option value="<?= $f['id'] ?>" data-price="<?= $f['price'] ?>" data-type="<?= $f['type'] ?>" data-capacity="<?= $f['capacity'] ?>" data-name="<?= htmlspecialchars($f['name']) ?>">
            <?= htmlspecialchars($f['name']) ?> (<?= ucfirst($f['type']) ?>) — &#8369;<?= number_format($f['price'],2) ?>/night
          </option>
          <?php endforeach; ?>
        </select>
        <div class="pb-fac-box" id="fac-info-box">
          <div class="pb-fac-row"><span>Facility:</span><strong id="fac-name">—</strong></div>
          <div class="pb-fac-row"><span>Price:</span><strong id="fac-price">—</strong></div>
          <div class="pb-fac-row"><span>Max Capacity:</span><strong id="fac-cap">—</strong></div>
          <div class="pb-fac-row"><span>Total Guests:</span><strong id="fac-guests">—</strong></div>
        </div>
        <!-- Availability warning -->
        <div id="avail-warning" style="display:none;background:#fdecea;border:1.5px solid #f5c6cb;border-radius:10px;padding:10px 14px;margin-top:10px;font-size:.84rem;color:#c62828;align-items:center;gap:8px;">
          <i class="fas fa-exclamation-circle" style="flex-shrink:0;"></i>
          <span></span>
        </div>
      </div>
      <div class="pb-form-row">
        <div class="pb-form-group">
          <label>Check-in Date <span class="req">*</span></label>
          <input type="date" class="pb-input" id="ci_display" onchange="syncCheckIn(this)">
        </div>
        <div class="pb-form-group" id="checkout-date-group" style="display:none;">
          <label>Check-out Date <span class="req">*</span></label>
          <input type="date" class="pb-input" id="co_display" onchange="syncCheckOut(this)">
        </div>
      </div>
      <div class="pb-form-row">
        <div class="pb-form-group">
          <label>No. of Adults <span class="req">*</span></label>
          <input type="number" class="pb-input" name="num_adults" id="num_adults" min="1" value="1" required oninput="updateFacInfo();updateSummary()">
        </div>
        <div class="pb-form-group">
          <label>No. of Children <span style="color:var(--muted);font-weight:400;font-size:.78rem;">(Age 5+)</span></label>
          <input type="number" class="pb-input" name="num_children" id="num_children" min="0" value="0" oninput="updateFacInfo();updateSummary()">
        </div>
      </div>
      <div class="pb-form-row">
        <div class="pb-form-group">
          <label>Children Below 5 <span style="background:#d1fae5;color:#065f46;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:50px;margin-left:4px;">FREE</span></label>
          <input type="number" class="pb-input" name="num_children_below6" id="num_children_below6" min="0" value="0" oninput="updateFacInfo();updateSummary()">
        </div>
        <div class="pb-form-group">
          <label>PWD / Seniors <span style="background:#fef3c7;color:#92400e;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:50px;margin-left:4px;">20% OFF</span></label>
          <input type="number" class="pb-input" name="num_senior_pwd" id="num_senior_pwd" min="0" value="0" oninput="updateFacInfo();updateSummary()">
        </div>
      </div>
      <div class="pb-form-group">
        <label>Transportation <span style="color:var(--muted);font-weight:400">(Optional)</span></label>
        <select class="pb-select" name="transportation" id="transportation" onchange="updateSummary()">
          <option value="none">None</option>
          <option value="tignapoloan">Tignapoloan Crossing → Resort (₱50/person)</option>
          <option value="cdo">Cagayan De Oro → Resort (₱250/person)</option>
          <option value="private">Private Vehicle Rental (6 Hours) (₱3,500)</option>
        </select>
      </div>
      <div class="pb-form-group">
        <label>Special Requests <span style="color:var(--muted);font-weight:400">(Optional)</span></label>
        <textarea class="pb-input" name="notes" placeholder="Any special requests or notes..."></textarea>
      </div>
    </div>
  </div>
  <div class="pb-btn-row">
    <button type="button" class="pb-btn-back" onclick="prevStep()"><i class="fas fa-arrow-left"></i> Back</button>
    <button type="button" class="pb-btn-next" onclick="nextStep()">Next <i class="fas fa-arrow-right"></i></button>
  </div>
</div>

<!-- STEP 4: Confirm -->
<div class="pb-step-panel" id="panel4">
  <!-- Print receipt (hidden on screen) -->
  <div class="print-receipt">
    <div style="text-align:center;margin-bottom:16px;">
      <h4 style="margin:0;font-weight:700;">SINULOM FALLS AND BOLAO COLD SPRING</h4>
      <div style="font-size:13px;">System-Generated Receipt</div>
      <div style="font-size:12px;margin-top:8px;">Receipt No.: <strong id="receipt_no">#PENDING</strong><br>Date: <strong id="receipt_date">-</strong> &nbsp; Time: <strong id="receipt_time">-</strong></div>
    </div>
    <table class="receipt-table"><tbody>
      <tr><th>Customer Name:</th><td id="receipt_customer_name">-</td></tr>
      <tr><th>Location:</th><td id="receipt_location">-</td></tr>
      <tr><th>Facility:</th><td id="receipt_facility_name">-</td></tr>
      <tr><th>Mode:</th><td id="receipt_mode">-</td></tr>
      <tr><th>Check-in:</th><td id="receipt_checkin">-</td></tr>
      <tr><th>Check-out:</th><td id="receipt_checkout">-</td></tr>
      <tr><th>Adults:</th><td id="receipt_adults">-</td></tr>
      <tr><th>Children:</th><td id="receipt_children">-</td></tr>
      <tr class="receipt-total"><th>TOTAL AMOUNT</th><td id="receipt_total">&#8369;0.00</td></tr>
    </tbody></table>
    <div style="margin-top:12px;font-size:12px;">Processed By: <?php echo htmlspecialchars($_SESSION['user_first_name'] ?? 'Frontdesk'); ?></div>
  </div>

  <!-- On-screen summary -->
  <div class="pb-summary-card">
    <div class="pb-summary-hdr">Booking Summary</div>
    <div class="pb-summary-row"><span class="lbl">Schedule Date</span><span class="val" id="sum_date">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Time Slot</span><span class="val" id="sum_slot">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Guest Name</span><span class="val" id="sum_name">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Contact</span><span class="val" id="sum_phone">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Email</span><span class="val" id="sum_email">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Booking Mode</span><span class="val" id="sum_mode">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Location</span><span class="val" id="sum_area">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Facility</span><span class="val" id="sum_facility">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Check-in</span><span class="val" id="sum_checkin">—</span></div>
    <div class="pb-summary-row" id="sum_checkout_row"><span class="lbl">Check-out</span><span class="val" id="sum_checkout">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Adults</span><span class="val" id="sum_adults">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Children</span><span class="val" id="sum_children">—</span></div>
    <div class="pb-summary-hdr" style="background:#4a7c59;font-size:.82rem;padding:10px 20px;">Price Breakdown</div>
    <div class="pb-summary-row"><span class="lbl">Facility Price</span><span class="val" id="sum_fac_price">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Adults (<span id="sum_adult_rate">—</span>/pax)</span><span class="val" id="sum_adult_total">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Children (<span id="sum_child_rate">—</span>/pax)</span><span class="val" id="sum_child_total">—</span></div>
    <div class="pb-summary-row" id="sum_pwd_row" style="display:none;"><span class="lbl">PWD/Seniors (<span id="sum_pwd_rate">—</span>/pax)</span><span class="val" id="sum_pwd_total">—</span></div>
    <div class="pb-summary-row"><span class="lbl">Location Subtotal</span><span class="val" id="sum_location_total">—</span></div>
    <div class="pb-summary-row" id="sum_transport_row" style="display:none;"><span class="lbl">Transportation</span><span class="val" id="sum_transport_total">—</span></div>
    <div class="pb-summary-row" style="border-top:2px solid var(--border);margin-top:4px;"><span class="lbl" style="font-weight:700;">Subtotal</span><span class="val" id="sum_subtotal">—</span></div>
    <div class="pb-summary-row"><span class="lbl">VAT (12%)</span><span class="val" id="sum_vat">—</span></div>
    <div class="pb-summary-total"><span class="lbl">Total Amount (VAT Inclusive)</span><span class="val" id="sum_total">&#8369;0.00</span></div>
  </div>
  <input type="hidden" name="total_price" id="total_price_hidden">
  <button type="submit" class="pb-btn-confirm"><i class="fas fa-check-circle"></i> Confirm Booking</button>
  <div class="pb-btn-row">
    <button type="button" class="pb-btn-back" onclick="prevStep()"><i class="fas fa-arrow-left"></i> Back</button>
    <button type="button" class="pb-btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
  </div>
</div>
</form>
</div><!-- /pb-wrap -->
</div><!-- /table-card -->

<!-- ── Confirmed Walk-in Bookings (Check Out) ── -->
<?php
$confirmed_walkins = array_values(array_filter($walkin_bookings_arr, fn($r) => $r['status'] === 'confirmed'));
if (!empty($confirmed_walkins)):
?>
<div class="table-card mt-4">
  <div class="section-hdr mb-3">
    <h5><i class="fas fa-sign-out-alt me-2" style="color:#1565c0;"></i>Confirmed Walk-in Bookings — Check Out</h5>
    <p>Mark guests as checked out to free up the facility.</p>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr><th>#</th><th>Guest Name</th><th>Phone</th><th>Facility</th><th>Location</th><th>Check-in</th><th>Mode</th><th>Price</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($confirmed_walkins as $bk): ?>
      <tr>
        <td><strong style="color:#1B7D3A;">#<?= $bk['id'] ?></strong></td>
        <td><?= htmlspecialchars($bk['guest_name']) ?></td>
        <td><?= htmlspecialchars($bk['guest_phone'] ?: '—') ?></td>
        <td><?= htmlspecialchars($bk['facility_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($bk['area_name'] ?? '—') ?></td>
        <td><?= date('M d, Y', strtotime($bk['check_in_date'])) ?></td>
        <td><?= ucfirst($bk['mode'] ?? '—') ?></td>
        <td><strong>&#8369;<?= number_format($bk['total_price'], 2) ?></strong></td>
        <td>
          <form method="POST" onsubmit="return confirm('Mark <?= htmlspecialchars($bk['guest_name']) ?> as checked out? This will free up the facility.');" style="display:inline;">
            <input type="hidden" name="action" value="checkout_walkin">
            <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
            <button type="submit" style="background:linear-gradient(135deg,#1565c0,#1976d2);color:#fff;border:none;border-radius:8px;padding:7px 14px;font-size:.82rem;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
              <i class="fas fa-sign-out-alt"></i> Check Out
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div><!-- /dash-body -->
</div><!-- /content -->
</div><!-- /main-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Helpers ── */
const setText = (id,v) => { const e=document.getElementById(id); if(e) e.textContent=v; };
const setHtml = (id,v) => { const e=document.getElementById(id); if(e) e.innerHTML=v; };

/* ── Capitalize ── */
function capFirst(el){ const p=el.selectionStart; el.value=el.value.replace(/\b\w/g,c=>c.toUpperCase()); el.setSelectionRange(p,p); }

function togglePwVis(inputId, iconId) {
  const inp = document.getElementById(inputId);
  const ico = document.getElementById(iconId);
  if(!inp || !ico) return;
  if (inp.type === 'password') { inp.type = 'text'; ico.classList.replace('fa-eye','fa-eye-slash'); }
  else { inp.type = 'password'; ico.classList.replace('fa-eye-slash','fa-eye'); }
}

document.addEventListener('DOMContentLoaded', () => {
  function checkPwMatch() {
    const pw = document.getElementById('guest_password').value;
    const cp = document.getElementById('confirm_password').value;
    const msg = document.getElementById('pwMatchMsg');
    if (!pw && !cp) { msg.style.display='none'; return; }
    if (!cp) { msg.style.display='none'; return; }
    if (pw === cp) {
      msg.style.display='block'; msg.style.color='#059669';
      msg.innerHTML='<i class="fas fa-check-circle"></i> Passwords match';
    } else {
      msg.style.display='block'; msg.style.color='var(--red)';
      msg.innerHTML='<i class="fas fa-times-circle"></i> Passwords do not match';
    }
  }
  document.getElementById('guest_password').addEventListener('input', checkPwMatch);
  document.getElementById('confirm_password').addEventListener('input', checkPwMatch);
});

/* ── Refs ── */
const bookingModeInput = document.getElementById('booking_mode');
const checkInHidden    = document.getElementById('check_in_date');
const checkOutHidden   = document.getElementById('check_out_date');
const checkoutSection  = document.getElementById('checkout-section');
const checkoutInput    = document.getElementById('checkout-input');
const nightsDisplay    = document.getElementById('nights-display');
const areaSelect       = document.getElementById('area_id');
const facilitySelect   = document.getElementById('facility_id');
const numAdults        = document.getElementById('num_adults');
const numChildren      = document.getElementById('num_children');
const guestFirstName   = document.getElementById('guest_first_name');
const guestLastName    = document.getElementById('guest_last_name');
const guestEmail       = document.getElementById('guest_email');
const guestPhone       = document.getElementById('guest_phone');
const getGuestName     = () => ((guestFirstName?.value||'')+' '+(guestLastName?.value||'')).trim();

/* ── Mode sync ── */
function syncMode(sel){
  bookingModeInput.value = sel.value;
  const isON = sel.value === 'overnight';
  checkoutSection.style.display = isON ? 'block' : 'none';
  // Show/hide checkout date field in step 3
  const coGroup = document.getElementById('checkout-date-group');
  if (coGroup) coGroup.style.display = isON ? 'block' : 'none';
  if(!isON){ checkOutHidden.value = checkInHidden.value||''; nightsDisplay.textContent=''; }
  updateSummary();
}
function syncCheckIn(el){ checkInHidden.value=el.value; if(bookingModeInput.value!=='overnight') checkOutHidden.value=el.value; updateSummary(); }
function syncCheckOut(el){ checkOutHidden.value=el.value; if(checkInHidden.value&&el.value){ const n=Math.ceil((new Date(el.value)-new Date(checkInHidden.value))/86400000); nightsDisplay.textContent=n>0?n+' night'+(n!==1?'s':''):''; } updateSummary(); }

/* ── Time slot selection ── */
let selectedTimeSlot = '';
function syncTimeSlot(sel) {
  selectedTimeSlot = sel.value;
  document.querySelectorAll('.pb-ts-card').forEach(c => {
    c.classList.remove('selected');
    if (c.getAttribute('data-value') === sel.value) c.classList.add('selected');
  });
  const isON = sel.value === 'overnight';
  bookingModeInput.value = isON ? 'overnight' : 'daytour';
  checkoutSection.style.display = isON ? 'block' : 'none';
  if(!isON){ checkOutHidden.value = checkInHidden.value||''; nightsDisplay.textContent=''; }
  const md = document.getElementById('mode_display');
  if(md) md.value = isON ? 'overnight' : 'daytour';
  const coGroup = document.getElementById('checkout-date-group');
  if(coGroup) coGroup.style.display = isON ? 'block' : 'none';
  updateSummary();
}

document.querySelectorAll('.pb-ts-card').forEach(card=>{
  card.addEventListener('click',function(){
    document.querySelectorAll('.pb-ts-card').forEach(c=>c.classList.remove('selected'));
    this.classList.add('selected');
    selectedTimeSlot = this.getAttribute('data-value');
    const isON = selectedTimeSlot==='overnight';
    bookingModeInput.value = isON?'overnight':'daytour';
    checkoutSection.style.display = isON?'block':'none';
    if(!isON){ checkOutHidden.value=checkInHidden.value||''; nightsDisplay.textContent=''; }
    // Sync mode_display select and checkout date group
    const md = document.getElementById('mode_display');
    if(md) md.value = isON?'overnight':'daytour';
    const tsd = document.getElementById('time_slot_display');
    if(tsd) tsd.value = selectedTimeSlot;
    const coGroup = document.getElementById('checkout-date-group');
    if(coGroup) coGroup.style.display = isON?'block':'none';
    updateSummary();
  });
});

/* ── Checkout date ── */
checkoutInput.addEventListener('change',function(){
  checkOutHidden.value=this.value;
  const co=document.getElementById('co_display'); if(co) co.value=this.value;
  if(checkInHidden.value&&this.value){ const n=Math.ceil((new Date(this.value)-new Date(checkInHidden.value))/86400000); nightsDisplay.textContent=n>0?n+' night'+(n!==1?'s':''):''; }
  updateSummary();
});

/* ── Calendar ── */
(function(){
  const MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];
  const today=new Date(); today.setHours(0,0,0,0);
  let vy=today.getFullYear(), vm=today.getMonth(), sel=null;
  function render(){
    document.getElementById('cal-month-label').textContent=MONTHS[vm]+' '+vy;
    const grid=document.getElementById('cal-days'); grid.innerHTML='';
    const fd=new Date(vy,vm,1).getDay(), off=fd===0?6:fd-1;
    const dim=new Date(vy,vm+1,0).getDate();
    for(let i=0;i<off;i++){const d=document.createElement('div');d.className='pb-cal-day empty';grid.appendChild(d);}
    for(let d=1;d<=dim;d++){
      const cell=document.createElement('div'); cell.className='pb-cal-day'; cell.textContent=d;
      const cd=new Date(vy,vm,d); const dow=cd.getDay();
      if(dow===0||dow===6) cell.style.color='var(--gl)';
      if(cd<today) cell.classList.add('past');
      if(cd.getTime()===today.getTime()) cell.classList.add('today');
      if(sel&&cd.getTime()===sel.getTime()) cell.classList.add('selected');
      if(!cell.classList.contains('past')){
        cell.addEventListener('click',function(){
          sel=cd;
          const val=vy+'-'+String(vm+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
          checkInHidden.value=val;
          const ci=document.getElementById('ci_display'); if(ci) ci.value=val;
          if(bookingModeInput.value!=='overnight') checkOutHidden.value=val;
          const next=new Date(cd); next.setDate(next.getDate()+1);
          checkoutInput.min=next.toISOString().split('T')[0];
          document.getElementById('cal-selected-text').textContent=cd.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
          render(); updateSummary(); checkFacilityAvailability();
        });
      }
      grid.appendChild(cell);
    }
    if(sel) document.getElementById('cal-selected-text').textContent=sel.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  }
  document.getElementById('cal-prev').addEventListener('click',()=>{vm--;if(vm<0){vm=11;vy--;}render();});
  document.getElementById('cal-next').addEventListener('click',()=>{vm++;if(vm>11){vm=0;vy++;}render();});
  render();
})();

/* ── Area rates ── */
function getAreaRates(name){
  const n=(name||'').toLowerCase();
  const both=n.includes('both')||(n.includes('sinulom')&&n.includes('bolao'))||n.includes('sinulom/bolao')||n.includes('sinulom & bolao')||n.includes('sinulom and bolao');
  return both?{adult:160,senior:128,child:85}:{adult:110,senior:88,child:60};
}

/* ── Facility info box ── */
function updateFacInfo(){
  const box=document.getElementById('fac-info-box');
  if(!facilitySelect.value){box.classList.remove('show');return;}
  const opt=facilitySelect.options[facilitySelect.selectedIndex];
  const name=opt.getAttribute('data-name')||opt.textContent.split(' (')[0];
  const price=parseFloat(opt.getAttribute('data-price'))||0;
  const cap=opt.getAttribute('data-capacity')||'N/A';
  const adults=parseInt(numAdults.value)||0, children=parseInt(numChildren.value)||0;
  const pwd=parseInt(document.getElementById('num_senior_pwd').value)||0, b5=parseInt(document.getElementById('num_children_below6').value)||0;
  setText('fac-name',name);
  setHtml('fac-price','&#8369;'+price.toFixed(2)+'/night');
  setText('fac-cap',cap+' pax');
  setText('fac-guests',(adults+children+pwd+b5)+' pax');
  box.classList.add('show');
}

/* ── Cost calculation ── */
function calcCosts(){
  const mode=bookingModeInput.value;
  let facCost=0, areaCost=0, nights=1, transportCost=0;
  if(facilitySelect.value){
    const fp=parseFloat(facilitySelect.options[facilitySelect.selectedIndex].getAttribute('data-price'))||0;
    if(mode==='overnight'&&checkInHidden.value&&checkOutHidden.value){
      nights=Math.ceil((new Date(checkOutHidden.value)-new Date(checkInHidden.value))/86400000);
      if(nights>0) facCost=fp*nights; else nights=1;
    } else { facCost=fp; nights=1; }
  }
  if(areaSelect.value){
    const an=areaSelect.options[areaSelect.selectedIndex].textContent;
    const r=getAreaRates(an);
    const adults = parseInt(numAdults.value)||0;
    const children = parseInt(numChildren.value)||0;
    const seniors = parseInt(document.getElementById('num_senior_pwd').value)||0;
    areaCost=((adults)*r.adult+(children)*r.child+(seniors)*r.senior)*(mode==='overnight'&&nights>0?nights:1);
    
    // Transport (Children are free)
    const trans = document.getElementById('transportation').value;
    const transportGuests = Math.max(0, adults + seniors);
    if(trans === 'tignapoloan') transportCost = transportGuests * 50;
    else if(trans === 'cdo') transportCost = transportGuests * 250;
    else if(trans === 'private') transportCost = 3500;
  }
  const subtotal = facCost + areaCost + transportCost;
  const vat = Math.round(subtotal * 0.12 * 100) / 100;
  const total = subtotal + vat;
  return {facCost,areaCost,transportCost,subtotal,vat,nights,total};
}

function updateSummary(){
  const {facCost,areaCost,transportCost,subtotal,vat,total}=calcCosts();
  const sumSub = document.getElementById('sum_subtotal');
  const sumVat = document.getElementById('sum_vat');
  if(sumSub) sumSub.innerHTML = '&#8369;'+subtotal.toFixed(2);
  if(sumVat) sumVat.innerHTML = '&#8369;'+vat.toFixed(2);
  setHtml('sum_total','&#8369;'+total.toFixed(2));
  document.getElementById('total_price_hidden').value=total.toFixed(2);
  updateFacInfo();
}

/* ── Step navigation ── */
let currentStep=1;
const TOTAL=4;

function showStep(s){
  for(let i=1;i<=TOTAL;i++){
    const panel=document.getElementById('panel'+i);
    if(panel) panel.classList.toggle('active',i===s);
    const si=document.getElementById('si'+i);
    if(si){ si.classList.remove('active','done'); if(i===s) si.classList.add('active'); else if(i<s) si.classList.add('done'); }
    const sc=document.getElementById('sc'+i);
    if(sc) sc.classList.toggle('done',i<s);
  }
  // Sync mode_display select when entering step 3
  if(s===3){
    const md=document.getElementById('mode_display');
    if(md && bookingModeInput.value) md.value=bookingModeInput.value;
    // Sync check-in date display
    const ci=document.getElementById('ci_display');
    if(ci && checkInHidden.value) ci.value=checkInHidden.value;
    // Show/hide checkout date group
    const coGroup=document.getElementById('checkout-date-group');
    if(coGroup) coGroup.style.display=bookingModeInput.value==='overnight'?'block':'none';
    const co=document.getElementById('co_display');
    if(co && checkOutHidden.value && bookingModeInput.value==='overnight') co.value=checkOutHidden.value;
  }
  if(s===TOTAL) buildReview();
}

function buildReview(){
  const mode=bookingModeInput.value;
  const tsMap={'8am-12pm':'Morning (8:00 AM – 12:00 PM)','12pm-5pm':'Afternoon (12:00 PM – 5:00 PM)','full_day':'Full Day (8:00 AM – 5:00 PM)','overnight':'Overnight (8:00 AM – 8:00 PM)'};
  if(checkInHidden.value) setText('sum_date',new Date(checkInHidden.value).toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'}));
  setText('sum_slot',tsMap[selectedTimeSlot]||selectedTimeSlot||'—');
  setText('sum_name',getGuestName()||'—');
  setText('sum_phone',guestPhone.value||'—');
  setText('sum_email',guestEmail.value||'—');
  setText('sum_mode',mode==='overnight'?'Overnight':'Day Tour');
  if(areaSelect.value) setText('sum_area',areaSelect.options[areaSelect.selectedIndex].textContent);
  if(facilitySelect.value) setText('sum_facility',facilitySelect.options[facilitySelect.selectedIndex].getAttribute('data-name')||facilitySelect.options[facilitySelect.selectedIndex].textContent.split(' (')[0]);
  if(checkInHidden.value) setText('sum_checkin',new Date(checkInHidden.value).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}));
  const coRow=document.getElementById('sum_checkout_row');
  if(mode==='overnight'&&checkOutHidden.value){ if(coRow) coRow.style.display='flex'; setText('sum_checkout',new Date(checkOutHidden.value).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'})); }
  else { if(coRow) coRow.style.display='none'; }
  setText('sum_adults',numAdults.value||'0');
  setText('sum_children',numChildren.value||'0');
  const {facCost,areaCost,transportCost,subtotal,vat,nights,total}=calcCosts();
  const fmt = v => '&#8369;'+v.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
  // Price breakdown
  const areaName = areaSelect.value ? areaSelect.options[areaSelect.selectedIndex].textContent : '';
  const rates = getAreaRates(areaName);
  const adults = parseInt(numAdults.value)||0;
  const children = parseInt(numChildren.value)||0;
  const seniors = parseInt(document.getElementById('num_senior_pwd').value)||0;
  const nightsLabel = (bookingModeInput.value==='overnight' && nights>1) ? ' × '+nights+' night(s) = '+fmt(facCost) : '';
  const facBasePrice = facilitySelect.value ? (parseFloat(facilitySelect.options[facilitySelect.selectedIndex].getAttribute('data-price'))||0) : 0;
  setHtml('sum_fac_price', fmt(facBasePrice) + nightsLabel);
  setHtml('sum_adult_rate', fmt(rates.adult));
  setHtml('sum_adult_total', adults+' × '+fmt(rates.adult)+' = '+fmt(adults*rates.adult));
  setHtml('sum_child_rate', fmt(rates.child));
  setHtml('sum_child_total', children+' × '+fmt(rates.child)+' = '+fmt(children*rates.child));
  
  const pwdRow = document.getElementById('sum_pwd_row');
  if(seniors > 0) {
    if(pwdRow) pwdRow.style.display = 'flex';
    setHtml('sum_pwd_rate', fmt(rates.senior));
    setHtml('sum_pwd_total', seniors+' × '+fmt(rates.senior)+' = '+fmt(seniors*rates.senior));
  } else {
    if(pwdRow) pwdRow.style.display = 'none';
  }
  
  const transRow = document.getElementById('sum_transport_row');
  if(transportCost > 0) {
    if(transRow) transRow.style.display = 'flex';
    setHtml('sum_transport_total', fmt(transportCost));
  } else {
    if(transRow) transRow.style.display = 'none';
  }

  setHtml('sum_location_total', fmt(areaCost));
  const sumSub = document.getElementById('sum_subtotal');
  const sumVat = document.getElementById('sum_vat');
  if(sumSub) sumSub.innerHTML = fmt(subtotal);
  if(sumVat) sumVat.innerHTML = fmt(vat);
  setHtml('sum_total',fmt(total));
  document.getElementById('total_price_hidden').value=total.toFixed(2);
  // Receipt
  const now=new Date();
  setText('receipt_date',now.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}));
  setText('receipt_time',now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}));
  setText('receipt_customer_name',getGuestName()||'—');
  if(areaSelect.value) setText('receipt_location',areaSelect.options[areaSelect.selectedIndex].textContent);
  if(facilitySelect.value) setText('receipt_facility_name',facilitySelect.options[facilitySelect.selectedIndex].getAttribute('data-name')||'—');
  setText('receipt_mode',mode==='overnight'?'Overnight':'Day Tour');
  if(checkInHidden.value) setText('receipt_checkin',new Date(checkInHidden.value).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}));
  if(checkOutHidden.value) setText('receipt_checkout',new Date(checkOutHidden.value).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}));
  setText('receipt_adults',numAdults.value||'0');
  setText('receipt_children',numChildren.value||'0');
  
  const recTrans = document.getElementById('receipt_transport_row');
  if(transportCost > 0) {
    if(recTrans) recTrans.style.display = 'table-row';
    setText('receipt_transport', fmt(transportCost));
  } else {
    if(recTrans) recTrans.style.display = 'none';
  }
  
  setText('receipt_subtotal', fmt(subtotal));
  setText('receipt_vat', fmt(vat));
  setHtml('receipt_total',fmt(total));
}

function validateStep(s){
  if(s===1){
    if(!checkInHidden.value){alert('Please select a date.');return false;}
    if(!selectedTimeSlot){alert('Please select a time slot.');return false;}
  } else if(s===2){
    if(!getGuestName()){alert('Please enter guest name.');guestFirstName.focus();return false;}
    if(!guestPhone.value.trim()){alert('Please enter contact number.');guestPhone.focus();return false;}
    if(!/^\d{11}$/.test(guestPhone.value)){alert('Contact number must be exactly 11 digits.');guestPhone.focus();return false;}
  } else if(s===3){
    // Sync mode from hidden input if dropdown not explicitly set
    const md=document.getElementById('mode_display');
    if(md && !md.value && bookingModeInput.value) md.value=bookingModeInput.value;
    if(!bookingModeInput.value){alert('Please select booking mode.');return false;}
    if(!areaSelect.value){alert('Please select a location.');areaSelect.focus();return false;}
    if(!facilitySelect.value){alert('Please select a facility.');facilitySelect.focus();return false;}
    if(!checkInHidden.value){alert('Please select a check-in date from the calendar.');return false;}
    if(bookingModeInput.value==='overnight'&&!checkOutHidden.value){alert('Please select a check-out date.');return false;}
    if(bookingModeInput.value==='overnight'&&checkOutHidden.value<=checkInHidden.value){alert('Check-out date must be after check-in date.');return false;}
    // Block if availability warning is showing
    const warn=document.getElementById('avail-warning');
    if(warn&&warn.style.display!=='none'){alert('This facility is not available on the selected date. Please choose a different facility or date.');return false;}
    if(!numAdults.value||parseInt(numAdults.value)<1){alert('Please enter at least 1 adult.');numAdults.focus();return false;}
  }
  return true;
}

function nextStep(){ if(validateStep(currentStep)){currentStep++;showStep(currentStep);} }
function prevStep(){ currentStep--;showStep(currentStep); }

[areaSelect,facilitySelect].forEach(el=>el.addEventListener('change',()=>{updateFacInfo();updateSummary();checkFacilityAvailability();}));
[numAdults,numChildren].forEach(el=>el.addEventListener('input',()=>{updateFacInfo();updateSummary();}));

/* ── Real-time availability check ── */
function checkFacilityAvailability(){
  const fid = facilitySelect.value;
  const ci  = checkInHidden.value;
  const co  = checkOutHidden.value;
  const mode = bookingModeInput.value;
  const warn = document.getElementById('avail-warning');
  if (!fid || !ci) { if(warn) warn.style.display='none'; return; }
  const url = `<?= BASE_URL ?>admin/check_facility_availability.php?facility_id=${fid}&check_in=${ci}&check_out=${co}&mode=${mode}`;
  fetch(url).then(r=>r.json()).then(data=>{
    if (!data.available) {
      if(warn){ warn.style.display='flex'; warn.querySelector('span').textContent='This facility is already booked on the selected date. It will be available after the current guest checks out.'; }
    } else {
      if(warn) warn.style.display='none';
    }
  }).catch(()=>{});
}

showStep(1);
updateSummary();
// admin sidebar init
</script>
</body>
</html>
