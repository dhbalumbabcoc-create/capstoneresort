<?php
session_start();
require_once 'config/db_config.php';

$booking_ids_raw = isset($_GET['booking_ids']) ? $_GET['booking_ids'] : '';
$booking_id_single = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

$b_ids = [];
if (!empty($booking_ids_raw)) {
    $b_ids = array_map('intval', explode(',', $booking_ids_raw));
} elseif ($booking_id_single > 0) {
    $b_ids = [$booking_id_single];
}

if (empty($b_ids)) { header("Location: landing.php"); exit(); }

$bookings = [];
$total_price = 0.0;

foreach ($b_ids as $bid) {
    $stmt = $conn->prepare("SELECT b.*, f.name AS facility_name, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=?");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($booking) {
        $pchk = $conn->prepare("SELECT id FROM payments WHERE booking_id=? LIMIT 1");
        $pchk->bind_param("i", $bid);
        $pchk->execute();
        $pchk->store_result();
        $already_paid = $pchk->num_rows > 0;
        $pchk->close();

        if (!$already_paid) {
            $bookings[] = $booking;
            $total_price += floatval($booking['total_price']);
        }
    }
}

if (empty($bookings)) {
    $ids_str = implode(',', $b_ids);
    header("Location: booking_confirmation.php?booking_ids=$ids_str");
    exit();
}

$error = '';

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_paid  = floatval($_POST['amount_paid'] ?? 0);
    $reference_no = trim($_POST['reference_number'] ?? '');

    if ($amount_paid <= 0) {
        $error = 'Please enter a valid amount.';
    } elseif ($amount_paid > $total_price) {
        $error = 'Amount cannot exceed the total of ₱' . number_format($total_price, 2) . '.';
    } elseif ($amount_paid < ($total_price / 2 - 0.01)) {
        $error = 'You can\'t pay downpayment less than half of the total price.';
    } elseif (empty($reference_no)) {
        $error = 'Please enter the GCash reference number.';
    } elseif (!preg_match('/^[A-Za-z0-9\-]{8,20}$/', $reference_no)) {
        $error = 'Reference number must be 8–20 alphanumeric characters.';
    } else {
        $proof_filename = null;
        if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/receipts/';
            if (!file_exists($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['proof_of_payment']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            if (in_array($ext, $allowed)) {
                $proof_filename = 'receipt_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $upload_dir . $proof_filename);
            }
        }

        $success = true;
        $total_cart_price = 0.0;
        foreach ($bookings as $b) {
            $total_cart_price += floatval($b['total_price']);
        }

        foreach ($bookings as $b) {
            $bid = $b['id'];
            $b_price = floatval($b['total_price']);
            $amount_per_booking = ($total_cart_price > 0) ? round($amount_paid * ($b_price / $total_cart_price), 2) : 0;

            $ins = $conn->prepare("INSERT INTO payments (booking_id, amount_paid, method, reference_number, proof_of_payment, status, paid_at) VALUES (?, ?, 'online', ?, ?, 'pending', NOW())");
            $ins->bind_param("idss", $bid, $amount_per_booking, $reference_no, $proof_filename);
            if (!$ins->execute()) { $success = false; }
            $ins->close();

            // Update booking status from 'unpaid' to 'pending'
            $conn->query("UPDATE bookings SET status = 'pending' WHERE id = $bid AND status = 'unpaid'");
        }

        if ($success) {
            $ids_str = implode(',', array_column($bookings, 'id'));
            header("Location: booking_confirmation.php?booking_ids=$ids_str");
            exit();
        } else {
            $error = 'Error saving payment. Please try again.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GCash Payment — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --gd:#1a3d2b;--gm:#2d5a3d;--gl:#4a7c59;
  --cream:#f5f0e8;--gold:#d4a017;
  --gcash:#0070e0;--gcash-light:#e8f2fd;
  --txt:#1a1a1a;--muted:#6b7280;
  --radius:24px;
}
html{scroll-behavior:smooth}
body{
  font-family:'Inter',sans-serif;
  background:linear-gradient(135deg,#0d2b1e 0%,#1a3d2b 40%,#0a4a6b 100%);
  min-height:100vh;
  display:flex;flex-direction:column;
  overflow-x:hidden;
}

/* animated bg particles */
body::before{
  content:'';position:fixed;inset:0;
  background:radial-gradient(ellipse at 20% 50%,rgba(74,124,89,.15) 0%,transparent 60%),
             radial-gradient(ellipse at 80% 20%,rgba(0,112,224,.12) 0%,transparent 50%),
             radial-gradient(ellipse at 60% 80%,rgba(212,160,23,.08) 0%,transparent 40%);
  pointer-events:none;z-index:0;
}

/* NAVBAR */
.nb{
  position:relative;z-index:10;
  background:rgba(255,255,255,.06);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(255,255,255,.1);
  padding:14px 40px;
  display:flex;align-items:center;justify-content:space-between;
}
.nb-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
.nb-brand img{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.25);box-shadow:0 2px 12px rgba(0,0,0,.3);}
.nb-brand-txt strong{display:block;font-size:.9rem;font-weight:700;color:#fff;line-height:1.2;}
.nb-brand-txt span{font-size:.7rem;color:rgba(255,255,255,.55);}
.nb-back{display:flex;align-items:center;gap:7px;color:rgba(255,255,255,.65);font-size:.82rem;font-weight:600;text-decoration:none;transition:color .2s;}
.nb-back:hover{color:#fff;}

/* MAIN */
.main{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:48px 24px 64px;
  position:relative;z-index:1;
}

/* STEP BAR */
.steps-outer{width:100%;max-width:520px;margin:0 auto 36px;}
.steps{display:flex;align-items:center;justify-content:center;}
.step{display:flex;flex-direction:column;align-items:center;gap:6px;flex:0 0 auto;}
.step-circle{
  width:38px;height:38px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:.82rem;font-weight:700;
  transition:all .3s;
}
.step.done .step-circle{background:var(--gl);color:#fff;box-shadow:0 0 0 3px rgba(74,124,89,.3);}
.step.active .step-circle{
  background:#fff;color:var(--gd);
  box-shadow:0 0 0 4px rgba(255,255,255,.25),0 4px 16px rgba(0,0,0,.2);
  font-weight:800;
}
.step.pending .step-circle{background:rgba(255,255,255,.1);color:rgba(255,255,255,.35);border:1.5px solid rgba(255,255,255,.15);}
.step-label{font-size:.62rem;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.5);}
.step.active .step-label,.step.done .step-label{color:rgba(255,255,255,.85);}
.step-line{flex:1;height:2px;background:rgba(255,255,255,.1);margin:0 8px 22px;border-radius:2px;max-width:80px;}
.step-line.done{background:var(--gl);}

/* CARD WRAPPER */
.card-wrap{
  width:100%;max-width:440px;
  display:flex;flex-direction:column;align-items:center;gap:0;
}

/* MAIN QR CARD */
.qr-card{
  width:100%;
  background:rgba(255,255,255,.97);
  border-radius:var(--radius);
  overflow:hidden;
  box-shadow:0 32px 80px rgba(0,0,0,.35),0 0 0 1px rgba(255,255,255,.15);
  animation:cardIn .5s cubic-bezier(.22,1,.36,1);
}
@keyframes cardIn{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}

/* card top gradient header */
.card-header{
  background:linear-gradient(135deg,var(--gd) 0%,var(--gm) 60%,#0a5c7a 100%);
  padding:28px 32px 24px;
  text-align:center;
  position:relative;overflow:hidden;
}
.card-header::before{
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events:none;
}
.card-header-eyebrow{
  font-size:.62rem;font-weight:700;letter-spacing:4px;text-transform:uppercase;
  color:rgba(255,255,255,.6);margin-bottom:8px;
  display:flex;align-items:center;justify-content:center;gap:10px;
}
.card-header-eyebrow::before,.card-header-eyebrow::after{
  content:'';width:24px;height:1px;background:rgba(255,255,255,.3);
}
.card-header-title{
  font-family:'Playfair Display',serif;
  font-size:1.6rem;font-weight:800;color:#fff;
  text-shadow:0 2px 12px rgba(0,0,0,.2);
  margin-bottom:4px;
}
.card-header-sub{font-size:.78rem;color:rgba(255,255,255,.65);}

/* card body */
.card-body{
  padding:28px 32px 32px;
  display: flex;
  flex-direction: column;
  gap: 32px;
}
@media (min-width: 768px) {
  .card-body { flex-direction: row; align-items: stretch; }
  .card-body-left { flex: 0 0 320px; }
  .card-body-right { flex: 1; min-width: 0; }
}
.card-body-left { display: flex; flex-direction: column; }
.card-body-right { display: flex; flex-direction: column; }

/* QR image area */
.qr-image-wrap{
  background:linear-gradient(135deg,#f0faf4,#e8f5fe);
  border:2px solid #d1e9ff;
  border-radius:18px;
  padding:28px 24px;
  margin-bottom:20px;
  display:flex;flex-direction:column;align-items:center;
  position:relative;
  overflow:hidden;
}
.qr-image-wrap::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(74,124,89,.04),rgba(0,112,224,.06));
  pointer-events:none;
}
.qr-corner{
  position:absolute;width:22px;height:22px;
  border-color:var(--gl);border-style:solid;
  border-width:0;
}
.qr-corner.tl{top:12px;left:12px;border-top-width:3px;border-left-width:3px;border-radius:4px 0 0 0;}
.qr-corner.tr{top:12px;right:12px;border-top-width:3px;border-right-width:3px;border-radius:0 4px 0 0;}
.qr-corner.bl{bottom:12px;left:12px;border-bottom-width:3px;border-left-width:3px;border-radius:0 0 0 4px;}
.qr-corner.br{bottom:12px;right:12px;border-bottom-width:3px;border-right-width:3px;border-radius:0 0 4px 0;}
.qr-image-wrap img{
  width:100%;max-width:320px;
  border-radius:14px;
  box-shadow:0 12px 36px rgba(0,0,0,.18);
  position:relative;z-index:1;
  display:block;
}
.qr-scan-hint{
  margin-top:12px;
  font-size:.75rem;color:var(--muted);
  display:flex;align-items:center;gap:6px;
  position:relative;z-index:1;
}
.qr-scan-hint i{color:var(--gl);}

/* gcash account info */
.gcash-info{
  background:linear-gradient(135deg,#e8f2fd,#f0f7ff);
  border:1.5px solid #bfdbfe;
  border-radius:14px;
  padding:14px 18px;
  margin-bottom:20px;
  display:flex;align-items:center;gap:14px;
}
.gcash-logo-badge{
  width:42px;height:42px;border-radius:12px;
  background:linear-gradient(135deg,#0070e0,#00b4d8);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
  box-shadow:0 4px 12px rgba(0,112,224,.3);
}
.gcash-logo-badge i{color:#fff;font-size:1rem;}
.gcash-details{flex:1;min-width:0;}
.gcash-name{font-size:.88rem;font-weight:700;color:var(--gcash);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.gcash-number{font-size:.75rem;color:var(--muted);margin-top:1px;}
.gcash-fee{font-size:.68rem;color:#9ca3af;margin-top:2px;}

/* booking summary strip */
.summary-strip{
  background:#f9fafb;
  border:1.5px solid #f0f0f0;
  border-radius:14px;
  overflow:hidden;
  margin-bottom:20px;
}
.summary-header{
  background:linear-gradient(90deg,var(--gd),var(--gm));
  padding:8px 16px;
  font-size:.65rem;font-weight:700;letter-spacing:2px;
  text-transform:uppercase;color:rgba(255,255,255,.85);
  display:flex;align-items:center;gap:7px;
}
.summary-body{padding:12px 16px;}
.s-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.8rem;}
.s-row .sl{color:var(--muted);}
.s-row .sv{font-weight:600;color:var(--txt);}
.s-divider{height:1px;background:#f0f0f0;margin:6px 0;}
.s-total{
  background:linear-gradient(135deg,#e8f5e9,#f0fdf4);
  border-top:1.5px solid #c8e6c9;
  padding:12px 16px;
  display:flex;justify-content:space-between;align-items:center;
}
.s-total-lbl{font-size:.78rem;font-weight:700;color:var(--gd);}
.s-total-val{font-size:1.15rem;font-weight:800;color:var(--gd);}

/* steps to pay */
.how-to{margin-bottom:24px;}
.how-to-title{font-size:.68rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;}
.how-steps{display:flex;flex-direction:column;gap:8px;}
.how-step{display:flex;align-items:center;gap:10px;font-size:.78rem;color:#374151;}
.how-num{
  width:22px;height:22px;border-radius:50%;
  background:var(--gd);color:#fff;
  font-size:.65rem;font-weight:700;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}

/* reference form */
.ref-form-title{
  font-size:.68rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
  color:var(--muted);margin-bottom:14px;
  display:flex;align-items:center;gap:8px;
}
.ref-form-title::after{content:'';flex:1;height:1px;background:#e5e7eb;}

.form-group{margin-bottom:14px;}
.form-label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.input-wrap{position:relative;}
.input-prefix{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-weight:700;color:var(--gl);font-size:.9rem;pointer-events:none;}
.form-input{
  width:100%;padding:11px 14px;padding-left:13px;
  border:2px solid #e5e7eb;border-radius:11px;
  font-size:.87rem;font-family:'Inter',sans-serif;
  color:var(--txt);outline:none;
  transition:border-color .2s,box-shadow .2s;
  background:#fff;
}
.form-input.has-prefix{padding-left:30px;}
.form-input:focus{border-color:var(--gl);box-shadow:0 0 0 3px rgba(74,124,89,.1);}
.input-hint{font-size:.72rem;color:#9ca3af;margin-top:4px;}

.pay-options{display:flex;gap:10px;margin-bottom:16px;}
.pay-opt-btn{
  flex:1;padding:12px 10px;
  border:2px solid #e5e7eb;border-radius:11px;
  text-align:center;cursor:pointer;
  transition:all .2s;background:#fff;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
}
.pay-opt-btn .opt-title{font-size:.82rem;font-weight:700;color:#374151;margin-bottom:4px;}
.pay-opt-btn .opt-val{font-size:.75rem;color:var(--muted);}
.pay-opt-btn.active{border-color:var(--gl);background:#f0fdf4;}
.pay-opt-btn.active .opt-title{color:var(--gd);}
.pay-opt-btn:hover:not(.active){border-color:#d1d5db;background:#f9fafb;}

.alert-err{
  background:#fef2f2;border:1.5px solid #fca5a5;
  border-radius:11px;padding:11px 14px;
  color:#dc2626;font-size:.82rem;
  margin-bottom:14px;display:flex;gap:8px;align-items:center;
}
.alert-info{
  background:#eff6ff;border:1.5px solid #bfdbfe;
  border-radius:11px;padding:11px 14px;
  color:#1d4ed8;font-size:.82rem;
  margin-bottom:14px;display:flex;gap:8px;align-items:flex-start;
  line-height:1.4;
}

/* submit button */
.btn-submit{
  width:100%;
  background:linear-gradient(135deg,var(--gd),#2d6a4f);
  color:#fff;border:none;border-radius:50px;
  padding:15px;font-size:.95rem;font-weight:700;
  cursor:pointer;transition:all .3s;
  box-shadow:0 6px 24px rgba(26,61,43,.35);
  display:flex;align-items:center;justify-content:center;gap:9px;
  font-family:'Inter',sans-serif;
  letter-spacing:.3px;
}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 32px rgba(26,61,43,.4);}
.btn-submit:active{transform:translateY(0);}
.btn-submit:disabled{opacity:.7;cursor:not-allowed;transform:none;}

/* bottom note */
.card-footer-note{
  margin-top:16px;
  text-align:center;font-size:.72rem;color:rgba(255,255,255,.45);
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.card-footer-note i{color:rgba(255,255,255,.3);}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="nb">
  <a href="landing.php" class="nb-brand">
    <img src="images/logo.jpg" alt="Logo">
    <div class="nb-brand-txt">
      <strong>Sinulom &amp; Bolao</strong>
      <span>Cold Spring Resort</span>
    </div>
  </a>
  <a href="public_booking.php" class="nb-back"><i class="fas fa-arrow-left"></i> Back to Booking</a>
</nav>

<div class="main">
  <div style="width:100%;max-width:900px;display:flex;flex-direction:column;align-items:center;">

    <!-- Step Indicator -->
    <div class="steps-outer">
      <div class="steps">
        <div class="step done">
          <div class="step-circle"><i class="fas fa-check"></i></div>
          <span class="step-label">Booking</span>
        </div>
        <div class="step-line done"></div>
        <div class="step active">
          <div class="step-circle">2</div>
          <span class="step-label">Payment</span>
        </div>
        <div class="step-line"></div>
        <div class="step pending">
          <div class="step-circle">3</div>
          <span class="step-label">Confirm</span>
        </div>
      </div>
    </div>

    <!-- Main Card -->
    <div class="qr-card" style="width:100%;">

      <!-- Header -->
      <div class="card-header">
        <div class="card-header-eyebrow">GCash Payment</div>
        <div class="card-header-title">Scan &amp; Pay</div>
        <div class="card-header-sub">Scan the QR code with your GCash app to pay</div>
      </div>

      <!-- Body -->
      <div class="card-body">
        
        <div class="card-body-left">
          <!-- QR Image -->
        <div class="qr-image-wrap">
          <div class="qr-corner tl"></div>
          <div class="qr-corner tr"></div>
          <div class="qr-corner bl"></div>
          <div class="qr-corner br"></div>
          <img src="images/gcash_qr.png" alt="GCash QR Code" id="qrImg">
          <div class="qr-scan-hint">
            <i class="fas fa-qrcode"></i>
            Point your GCash app at this code
          </div>
        </div>

        <!-- GCash Account Info -->
        <div class="gcash-info">
          <div class="gcash-logo-badge"><i class="fas fa-mobile-alt"></i></div>
          <div class="gcash-details">
            <div class="gcash-name">DH**** R L.</div>
            <div class="gcash-number"><i class="fas fa-phone" style="font-size:.65rem;opacity:.6;margin-right:3px;"></i>+63 947 147 4410</div>
            <div class="gcash-fee">Transfer fees may apply</div>
          </div>
          <span style="font-size:.62rem;font-weight:700;padding:3px 9px;background:#e8f2fd;color:var(--gcash);border-radius:50px;white-space:nowrap;">GCash Enabled</span>
        </div>

        </div><!-- /card-body-left -->
        
        <div class="card-body-right">
          <!-- Booking Summary -->
          <div class="summary-strip">
          <div class="summary-header"><i class="fas fa-receipt" style="font-size:.75rem;"></i> Booking Summary</div>
          <div class="summary-body">
            <?php foreach ($bookings as $b): ?>
            <div class="s-row">
              <span class="sl">Booking Ref</span>
              <span class="sv">#<?php echo str_pad($b['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="s-row">
              <span class="sl">Facility</span>
              <span class="sv"><?php echo htmlspecialchars($b['facility_name'] ?? '—'); ?></span>
            </div>
            <div class="s-row">
              <span class="sl">Check-in</span>
              <span class="sv"><?php echo date('M d, Y', strtotime($b['check_in_date'])); ?></span>
            </div>
            <?php if (!empty($b['check_out_date'])): ?>
            <div class="s-row">
              <span class="sl">Check-out</span>
              <span class="sv"><?php echo date('M d, Y', strtotime($b['check_out_date'])); ?></span>
            </div>
            <?php endif; ?>
            <div class="s-divider"></div>
            <?php endforeach; ?>
          </div>
          <div class="s-total">
            <span class="s-total-lbl"><i class="fas fa-peso-sign" style="font-size:.75rem;margin-right:2px;"></i> Total Amount Due</span>
            <span class="s-total-val">₱<?php echo number_format($total_price, 2); ?></span>
          </div>
        </div>

        <!-- How to Pay steps -->
        <div class="how-to">
          <div class="how-to-title">How to Pay</div>
          <div class="how-steps">
            <div class="how-step"><div class="how-num">1</div> Open your <strong style="margin:0 3px;">GCash</strong> app and tap <strong style="margin:0 3px;">Pay QR</strong></div>
            <div class="how-step"><div class="how-num">2</div> Scan the QR code above &amp; enter the amount</div>
            <div class="how-step"><div class="how-num">3</div> Copy the <strong style="margin:0 3px;">Reference Number</strong> from the receipt</div>
            <div class="how-step"><div class="how-num">4</div> Enter the reference number below &amp; submit</div>
          </div>
        </div>

        <!-- Reference Number Form -->
        <div class="ref-form-title"><i class="fas fa-file-invoice" style="color:var(--gl);"></i> Submit Your Payment</div>

        <div class="alert-info">
          <i class="fas fa-info-circle" style="margin-top:2px;"></i>
          <div><strong>Note:</strong> You must pay at least half (50%) of the total price as a downpayment to secure your booking.</div>
        </div>

        <?php if ($error): ?>
        <div class="alert-err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="payForm" enctype="multipart/form-data">
          <div class="pay-options">
            <div class="pay-opt-btn active" id="btnFullPay">
              <div class="opt-title">Full Payment</div>
              <div class="opt-val">₱<?php echo number_format($total_price, 2); ?></div>
            </div>
            <div class="pay-opt-btn" id="btnHalfPay">
              <div class="opt-title">Half Downpayment</div>
              <div class="opt-val">₱<?php echo number_format($total_price / 2, 2); ?></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="amount_paid">Amount Sent</label>
              <div class="input-wrap">
                <span class="input-prefix">₱</span>
                <input type="number" id="amount_paid" name="amount_paid" class="form-input has-prefix"
                  min="1" max="<?php echo $total_price; ?>" step="0.01"
                  placeholder="<?php echo number_format($total_price, 2); ?>"
                  value="<?php echo isset($_POST['amount_paid']) ? htmlspecialchars($_POST['amount_paid']) : ''; ?>"
                  required>
              </div>
              <div class="input-hint" id="balanceHint"></div>
            </div>
            <div class="form-group">
              <label class="form-label" for="reference_number">Reference No.</label>
              <input type="text" id="reference_number" name="reference_number" class="form-input"
                placeholder="e.g. 1234567890"
                maxlength="20"
                value="<?php echo isset($_POST['reference_number']) ? htmlspecialchars($_POST['reference_number']) : ''; ?>"
                required>
              <div class="input-hint">From GCash receipt</div>
            </div>
          </div>

          <button type="submit" class="btn-submit" id="payBtn">
            <i class="fas fa-paper-plane"></i> Confirm Payment
          </button>
        </form>
        
        </div><!-- /card-body-right -->

      </div><!-- /card-body -->
    </div><!-- /qr-card -->

    <div class="card-footer-note">
      <i class="fas fa-lock"></i> Secure payment powered by GCash
    </div>

  </div>
</div>

<script>
const total = <?php echo $total_price; ?>;
const amtInput = document.getElementById('amount_paid');
const hint = document.getElementById('balanceHint');

function updateHint() {
  const v = parseFloat(amtInput.value) || 0;
  if (v > 0 && v <= total) {
    const bal = total - v;
    hint.style.color = bal > 0 ? '#e65100' : '#059669';
    hint.innerHTML = bal > 0
      ? `Balance: <strong>₱${bal.toFixed(2)}</strong>`
      : `<strong style="color:#059669">✓ Full payment</strong>`;
  } else if (v > total) {
    hint.style.color = '#dc2626';
    hint.innerHTML = `Exceeds total`;
  } else {
    hint.innerHTML = '';
  }
}
const btnFull = document.getElementById('btnFullPay');
const btnHalf = document.getElementById('btnHalfPay');

btnFull.addEventListener('click', () => {
  btnFull.classList.add('active');
  btnHalf.classList.remove('active');
  amtInput.value = total.toFixed(2);
  updateHint();
});

btnHalf.addEventListener('click', () => {
  btnHalf.classList.add('active');
  btnFull.classList.remove('active');
  amtInput.value = (total / 2).toFixed(2);
  updateHint();
});

amtInput.addEventListener('input', () => {
  const v = parseFloat(amtInput.value) || 0;
  if (Math.abs(v - total) < 0.01) {
    btnFull.classList.add('active'); btnHalf.classList.remove('active');
  } else if (Math.abs(v - (total / 2)) < 0.01) {
    btnHalf.classList.add('active'); btnFull.classList.remove('active');
  } else {
    btnFull.classList.remove('active'); btnHalf.classList.remove('active');
  }
  updateHint();
});

// Initialize state
const initialAmt = parseFloat(amtInput.value);
if (!isNaN(initialAmt)) {
  if (Math.abs(initialAmt - (total / 2)) < 0.01) {
    btnHalf.classList.add('active'); btnFull.classList.remove('active');
  } else if (Math.abs(initialAmt - total) < 0.01) {
    btnFull.classList.add('active'); btnHalf.classList.remove('active');
  } else {
    btnFull.classList.remove('active'); btnHalf.classList.remove('active');
  }
} else {
  amtInput.value = total.toFixed(2);
}
updateHint();

document.getElementById('payForm').addEventListener('submit', function(e) {
  const amtVal = parseFloat(amtInput.value) || 0;
  const minRequired = total / 2;
  if (amtVal < minRequired) {
    e.preventDefault();
    alert("You can't pay downpayment less than half of the total price.");
    return false;
  }
  const btn = document.getElementById('payBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
});
</script>
</body>
</html>
