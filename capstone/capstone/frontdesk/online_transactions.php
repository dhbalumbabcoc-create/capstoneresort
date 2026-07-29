<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !in_array($_SESSION['user_role'], ['admin','frontdesk'])) {
    header("Location: " . BASE_URL . "unauthorized.php");
    exit();
}

$user = get_user_info($_SESSION['user_id'], $conn);
$is_frontdesk = ($_SESSION['user_role'] === 'frontdesk');

// Handle verify / mark-paid / edit-payment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pay_id = intval($_POST['payment_id'] ?? 0);

    // ── Edit Payment Amount ──
    if ($_POST['action'] === 'edit_payment' && $pay_id > 0) {
        $new_amount = floatval($_POST['new_amount'] ?? 0);
        if ($new_amount <= 0) {
            set_error_message('Please enter a valid amount greater than zero.');
        } else {
            // Get old amount and booking info before updating
            $old_stmt = $conn->prepare("SELECT p.*, b.guest_name, b.guest_email, b.total_price, b.check_in_date, b.check_out_date, b.mode, f.name as facility_name, a.name as area_name FROM payments p JOIN bookings b ON p.booking_id = b.id LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id WHERE p.id = ?");
            $old_stmt->bind_param("i", $pay_id);
            $old_stmt->execute();
            $old_data = $old_stmt->get_result()->fetch_assoc();
            $old_stmt->close();

            if ($old_data) {
                $old_amount = floatval($old_data['amount_paid']);
                // Update the payment amount
                $upd = $conn->prepare("UPDATE payments SET amount_paid = ?, status = 'completed' WHERE id = ?");
                $upd->bind_param("di", $new_amount, $pay_id);
                if ($upd->execute()) {
                    // Send payment correction email
                    if (!empty($old_data['guest_email'])) {
                        require_once '../includes/send_status_email.php';
                        $booking_data_edit = [
                            'id'            => $old_data['booking_id'],
                            'guest_name'    => $old_data['guest_name'],
                            'guest_email'   => $old_data['guest_email'],
                            'total_price'   => $old_data['total_price'],
                            'check_in_date' => $old_data['check_in_date'],
                            'facility_name' => $old_data['facility_name'] ?? 'N/A',
                            'area_name'     => $old_data['area_name'] ?? 'N/A',
                        ];
                        $payment_data_edit = [
                            'old_amount'       => $old_amount,
                            'new_amount'       => $new_amount,
                            'reference_number' => $old_data['reference_number'],
                        ];
                        sendPaymentCorrectionEmail($booking_data_edit, $payment_data_edit);
                    }
                    set_success_message('Payment amount updated to ₱' . number_format($new_amount, 2) . ' and notification email sent to guest.');
                } else {
                    set_error_message('Failed to update payment amount. Please try again.');
                }
                $upd->close();
            } else {
                set_error_message('Payment record not found.');
            }
        }
        header("Location: online_transactions.php"); exit();
    }

    if ($_POST['action'] === 'verify' && $pay_id > 0) {
        // Fetch payment and booking details first
        $pay_stmt = $conn->prepare("SELECT p.booking_id, p.reference_number, b.guest_email, b.created_at FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE p.id = ?");
        $pay_stmt->bind_param("i", $pay_id);
        $pay_stmt->execute();
        $pay_res = $pay_stmt->get_result()->fetch_assoc();
        $pay_stmt->close();
        
        if ($pay_res) {
            $booking_id = intval($pay_res['booking_id']);
            $ref_number = trim($pay_res['reference_number'] ?? '');
            $guest_email = trim($pay_res['guest_email'] ?? '');
            $created_at = $pay_res['created_at'];
            $approved_by = $_SESSION['user_id'];

            // Find ALL sibling booking IDs that belong to the same transaction group
            $sibling_ids = [$booking_id];
            if ($guest_email !== '' && $created_at) {
                $sib_stmt = $conn->prepare(
                    "SELECT id FROM bookings
                     WHERE guest_email = ? AND created_at = ? AND booking_type = 'online'"
                );
                $sib_stmt->bind_param("ss", $guest_email, $created_at);
                $sib_stmt->execute();
                $sib_res = $sib_stmt->get_result();
                while ($sr = $sib_res->fetch_assoc()) {
                    $sbid = intval($sr['id']);
                    if (!in_array($sbid, $sibling_ids)) $sibling_ids[] = $sbid;
                }
                $sib_stmt->close();
            }

            // Sort sibling IDs to keep lowest first
            sort($sibling_ids);

            // Update status of relevant payments sharing this reference number inside this transaction group
            if ($ref_number !== '') {
                $placeholders = implode(',', array_fill(0, count($sibling_ids), '?'));
                $upd_pay = $conn->prepare("UPDATE payments SET status = 'completed' WHERE reference_number = ? AND booking_id IN ($placeholders)");
                $types = 's' . str_repeat('i', count($sibling_ids));
                $upd_pay->bind_param($types, $ref_number, ...$sibling_ids);
                $upd_pay->execute();
                $upd_pay->close();
            } else {
                $conn->query("UPDATE payments SET status='completed' WHERE id=$pay_id");
            }

            // Confirm ALL sibling bookings and collect details
            $sibling_bookings = [];
            foreach ($sibling_ids as $sbid) {
                $upd = $conn->prepare("UPDATE bookings SET status = 'confirmed', created_by = ? WHERE id = ? AND booking_type = 'online'");
                $upd->bind_param("ii", $approved_by, $sbid);
                $upd->execute();
                $upd->close();

                // Fetch details for email
                $bk_stmt = $conn->prepare("SELECT b.*, f.name as facility_name, f.price as facility_price, a.name as area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id WHERE b.id = ?");
                $bk_stmt->bind_param("i", $sbid);
                $bk_stmt->execute();
                $bk_row = $bk_stmt->get_result()->fetch_assoc();
                $bk_stmt->close();
                if ($bk_row) {
                    $sibling_bookings[] = $bk_row;
                }
            }

            if (!empty($sibling_bookings)) {
                $primary_bk = $sibling_bookings[0];
                $total_price_group = 0.0;
                $facility_names = [];
                $area_names = [];
                $total_adults = 0;
                $total_children = 0;
                $total_pwd = 0;
                $total_below5 = 0;
                $notes_arr = [];

                foreach ($sibling_bookings as $sb_row) {
                    $total_price_group += floatval($sb_row['total_price']);
                    $facility_names[] = $sb_row['facility_name'];
                    if (!empty($sb_row['area_name'])) {
                        $area_names[] = $sb_row['area_name'];
                    }
                    $total_adults += intval($sb_row['num_adults'] ?? 0);
                    $total_children += intval($sb_row['num_children'] ?? 0);
                    $total_pwd += intval($sb_row['num_discounted'] ?? 0);

                    $sb_notes = $sb_row['notes'] ?? '';
                    if (!empty($sb_notes)) {
                        $notes_arr[] = $sb_notes;
                        if (preg_match('/Below5:\s*(\d+)/i', $sb_notes, $m)) {
                            $total_below5 += intval($m[1]);
                        }
                    }
                }

                $booking_data = [
                    'id'             => $primary_bk['id'],
                    'booking_ids'    => $sibling_ids,
                    'guest_name'     => $primary_bk['guest_name'],
                    'guest_email'    => $primary_bk['guest_email'],
                    'guest_phone'    => $primary_bk['guest_phone'],
                    'facility_name'  => implode(', ', $facility_names),
                    'area_name'      => implode(', ', array_unique($area_names)),
                    'check_in_date'  => $primary_bk['check_in_date'],
                    'check_out_date' => $primary_bk['check_out_date'],
                    'num_adults'     => $total_adults,
                    'num_children'   => $total_children,
                    'num_pwd'        => $total_pwd,
                    'num_below5'     => $total_below5,
                    'mode'           => $primary_bk['mode'],
                    'notes'          => implode(' | ', $notes_arr),
                    'total_price'    => $total_price_group,
                    'status'         => 'confirmed',
                ];

                if (!empty($booking_data['guest_email'])) {
                    require_once '../includes/send_status_email.php';
                    sendBookingStatusEmail($booking_data, 'confirmed');
                }
            }
        }
        
        set_success_message('Payment verified successfully. Booking status updated to confirmed and confirmation email sent.');
    } elseif ($_POST['action'] === 'mark_paid' && $pay_id > 0) {
        $amount  = floatval($_POST['extra_amount'] ?? 0);
        $ref     = trim($_POST['extra_ref'] ?? '');
        $method  = in_array($_POST['extra_method'] ?? '', ['online','walkin']) ? $_POST['extra_method'] : 'walkin';
        if ($amount > 0) {
            if ($method === 'online' && empty($ref)) {
                set_error_message('GCash Reference Number is required for online payment.');
            } else {
                $ref_val = $ref !== '' ? $ref : 'WALKIN-CASH';

                // Find payment and booking details
                $pay_stmt = $conn->prepare("SELECT p.booking_id, b.guest_email, b.created_at FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE p.id = ?");
                $pay_stmt->bind_param("i", $pay_id);
                $pay_stmt->execute();
                $pay_info = $pay_stmt->get_result()->fetch_assoc();
                $pay_stmt->close();

                if ($pay_info) {
                    $b_email = trim($pay_info['guest_email'] ?? '');
                    $b_created = $pay_info['created_at'];
                    $sibling_bookings = [];

                    if (!empty($b_email) && !empty($b_created)) {
                        $sib_stmt = $conn->prepare("SELECT id, total_price FROM bookings WHERE guest_email = ? AND created_at = ? AND booking_type = 'online'");
                        $sib_stmt->bind_param("ss", $b_email, $b_created);
                        $sib_stmt->execute();
                        $sres = $sib_stmt->get_result();
                        while ($sr = $sres->fetch_assoc()) $sibling_bookings[] = $sr;
                        $sib_stmt->close();
                    }

                    if (count($sibling_bookings) > 1) {
                        $group_rem_total = 0.0;
                        $rem_balances = [];

                        foreach ($sibling_bookings as $sb) {
                            $sbid = intval($sb['id']);
                            $sb_tot = floatval($sb['total_price']);
                            $paid_res = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM payments WHERE booking_id = $sbid AND status = 'completed'");
                            $sb_paid = floatval($paid_res->fetch_assoc()['total_paid'] ?? 0);
                            $sb_rem = max(0.0, $sb_tot - $sb_paid);
                            $rem_balances[$sbid] = $sb_rem;
                            $group_rem_total += $sb_rem;
                        }

                        if ($group_rem_total > 0) {
                            foreach ($sibling_bookings as $sb) {
                                $sbid = intval($sb['id']);
                                $sb_rem = $rem_balances[$sbid];
                                if ($sb_rem > 0) {
                                    $alloc = round($amount * ($sb_rem / $group_rem_total), 2);
                                    if ($alloc > 0) {
                                        $s = $conn->prepare("INSERT INTO payments (booking_id, amount_paid, method, reference_number, status, paid_at) VALUES (?, ?, ?, ?, 'completed', NOW())");
                                        $s->bind_param("idss", $sbid, $alloc, $method, $ref_val);
                                        $s->execute(); $s->close();
                                    }
                                }
                            }
                        } else {
                            $tot_price_sum = array_sum(array_column($sibling_bookings, 'total_price'));
                            foreach ($sibling_bookings as $sb) {
                                $sbid = intval($sb['id']);
                                $alloc = ($tot_price_sum > 0) ? round($amount * (floatval($sb['total_price']) / $tot_price_sum), 2) : 0;
                                if ($alloc > 0) {
                                    $s = $conn->prepare("INSERT INTO payments (booking_id, amount_paid, method, reference_number, status, paid_at) VALUES (?, ?, ?, ?, 'completed', NOW())");
                                    $s->bind_param("idss", $sbid, $alloc, $method, $ref_val);
                                    $s->execute(); $s->close();
                                }
                            }
                        }
                    } else {
                        $s = $conn->prepare("INSERT INTO payments (booking_id, amount_paid, method, reference_number, status, paid_at) SELECT booking_id, ?, ?, ?, 'completed', NOW() FROM payments WHERE id=?");
                        $s->bind_param("dssi", $amount, $method, $ref_val, $pay_id);
                        $s->execute(); $s->close();
                    }
                }
                set_success_message('Payment recorded successfully.');
            }
        } else {
            set_error_message('Please enter a valid amount.');
        }
    }
    header("Location: online_transactions.php"); exit();
}

// Fetch all online payments with booking & balance info
$sql = "SELECT p.*, b.guest_name, b.guest_email, b.guest_phone,
               b.total_price, b.check_in_date, b.check_out_date, b.status AS booking_status,
               b.mode, b.created_at AS booking_created_at,
               f.name AS facility_name, a.name AS area_name
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN facilities f ON b.facility_id = f.id
        LEFT JOIN areas a ON b.area_id = a.id
        WHERE b.booking_type = 'online'
        ORDER BY p.paid_at DESC";
$res = $conn->query($sql);
$payments_raw = [];
if ($res) { while ($r = $res->fetch_assoc()) $payments_raw[] = $r; }

// ── Group online transactions & payments by guest checkout transaction ─────────
// Every online checkout group is identified by guest_email + booking_created_at.
// All sibling bookings (#82, #83, #84) created in the same checkout share the same
// guest_email and created_at. Any payments (GCash or top-up WALKIN-CASH) added to
// any of these bookings are grouped together.
// ─────────────────────────────────────────────────────────────────────────────
$groups     = []; // tx_key => representative payment row
$group_bids = []; // tx_key => [booking_id, ...] (unique, for total_due calculation)
$group_pids = []; // tx_key => [payment_id, ...] (unique, for total_paid calculation)
$group_due  = []; // tx_key => sum of total_price across distinct booking IDs
$group_paid = []; // tx_key => sum of amount_paid across distinct payment IDs

foreach ($payments_raw as $p) {
    $ref   = trim($p['reference_number'] ?? '');
    $email = trim($p['guest_email'] ?? '');
    $cat   = trim($p['booking_created_at'] ?? '');
    $bid   = intval($p['booking_id']);
    $pid   = intval($p['id']);

    // Canonical key for the transaction group
    if (!empty($email) && !empty($cat)) {
        $tx_key = 'grp_' . md5($email . '_' . $cat);
    } elseif (!empty($ref) && $ref !== 'WALKIN-CASH') {
        $tx_key = 'ref_' . $ref;
    } else {
        $tx_key = 'bid_' . $bid;
    }

    if (!isset($groups[$tx_key])) {
        $groups[$tx_key]     = $p; // first row seen = representative
        $group_bids[$tx_key] = [];
        $group_pids[$tx_key] = [];
        $group_due[$tx_key]  = 0.0;
        $group_paid[$tx_key] = 0.0;
    }

    // Preserve receipt_image if available
    if (empty($groups[$tx_key]['receipt_image']) && !empty($p['receipt_image'])) {
        $groups[$tx_key]['receipt_image'] = $p['receipt_image'];
    }

    // Keep the lowest booking_id as primary display booking ID
    if ($bid < intval($groups[$tx_key]['booking_id'])) {
        $groups[$tx_key]['booking_id']     = $bid;
        $groups[$tx_key]['guest_name']     = $p['guest_name'];
        $groups[$tx_key]['guest_email']    = $p['guest_email'];
        $groups[$tx_key]['guest_phone']    = $p['guest_phone'];
        $groups[$tx_key]['check_in_date']  = $p['check_in_date'];
        $groups[$tx_key]['booking_status'] = $p['booking_status'];
    }

    // Ensure we keep/prefer the real GCash reference number over 'WALKIN-CASH'
    $cur_ref = trim($groups[$tx_key]['reference_number'] ?? '');
    if (($cur_ref === '' || $cur_ref === 'WALKIN-CASH') && $ref !== '' && $ref !== 'WALKIN-CASH') {
        $groups[$tx_key]['reference_number'] = $ref;
    }

    // Use latest paid_at date for sorting
    if (strtotime($p['paid_at']) > strtotime($groups[$tx_key]['paid_at'])) {
        $groups[$tx_key]['paid_at'] = $p['paid_at'];
    }

    // If any payment is pending, mark group as pending
    if ($p['status'] === 'pending') {
        $groups[$tx_key]['status'] = 'pending';
    }

    // Sum total_price once per unique booking_id
    if (!in_array($bid, $group_bids[$tx_key])) {
        $group_bids[$tx_key][] = $bid;
        $group_due[$tx_key]   += floatval($p['total_price']);
    }

    // Sum amount_paid once per unique payment id
    if (!in_array($pid, $group_pids[$tx_key])) {
        $group_pids[$tx_key][] = $pid;
        $group_paid[$tx_key]  += floatval($p['amount_paid']);
    }
}

// Ensure all sibling bookings from `bookings` table are included for total_due calculation
foreach ($groups as $tx_key => &$row) {
    $email = trim($row['guest_email'] ?? '');
    $cat   = trim($row['booking_created_at'] ?? '');
    if (!empty($email) && !empty($cat)) {
        $sib_stmt = $conn->prepare("SELECT id, total_price FROM bookings WHERE guest_email = ? AND created_at = ? AND booking_type = 'online'");
        $sib_stmt->bind_param("ss", $email, $cat);
        $sib_stmt->execute();
        $sres = $sib_stmt->get_result();
        while ($srow = $sres->fetch_assoc()) {
            $sbid = intval($srow['id']);
            if (!in_array($sbid, $group_bids[$tx_key])) {
                $group_bids[$tx_key][] = $sbid;
                $group_due[$tx_key]   += floatval($srow['total_price']);
            }
        }
        $sib_stmt->close();
    }

    $row['tx_total_due']   = $group_due[$tx_key];
    $row['tx_total_paid']  = $group_paid[$tx_key];
    $rem = $group_due[$tx_key] - $group_paid[$tx_key];
    $row['tx_remaining']   = $rem > 0.001 ? $rem : 0.0;
    $row['tx_booking_ids'] = $group_bids[$tx_key];
}
unset($row);

$payments = array_values($groups);

// Sort by paid_at DESC (groups may have reordered)
usort($payments, fn($a, $b) => strtotime($b['paid_at']) - strtotime($a['paid_at']));

$total_collected   = array_sum(array_column(array_filter($payments, fn($p) => $p['status']==='completed'), 'tx_total_paid'));
$total_pending_amt = array_sum(array_column(array_filter($payments, fn($p) => $p['status']==='pending'),   'tx_total_paid'));
$pending_count     = count(array_filter($payments, fn($p) => $p['status']==='pending'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Transactions - Resort Management</title>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/' . ($_SESSION['user_role'] === 'frontdesk' ? 'frontdesk' : 'admin') . '_page_styles.php'; ?>
    <style>
        .balance-badge { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:50px; font-size:.75rem; font-weight:700; }
        .balance-badge.zero { background:#d1fae5; color:#065f46; }
        .pay-status-pending  { background:#fff8e1; color:#f59e0b; padding:3px 10px; border-radius:50px; font-size:.75rem; font-weight:700; }
        .pay-status-done     { background:#d1fae5; color:#059669; padding:3px 10px; border-radius:50px; font-size:.75rem; font-weight:700; }
        .ref-code { font-family:monospace; background:#f3f4f6; padding:2px 8px; border-radius:6px; font-size:.82rem; letter-spacing:.5px; }
        .kpi-icon.orange { background:#fff7ed; color:#ea580c; }
    </style>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/' . ($_SESSION['user_role'] === 'frontdesk' ? 'frontdesk' : 'admin') . '_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-money-check-alt me-2" style="color:#1B7D3A;"></i>Online Transactions</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-shield-alt me-1"></i><?php echo ucfirst($_SESSION['user_role']); ?></span>
            </div>
        </div>

        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>

            <?php if ($pending_count > 0): ?>
            <div class="alert-pending mb-4">
                <i class="fas fa-bell"></i>
                <div><strong><?= $pending_count ?> unverified payment<?= $pending_count > 1 ? 's' : '' ?></strong> waiting for GCash confirmation.</div>
            </div>
            <?php endif; ?>

            <!-- KPI cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-list"></i></div>
                        <div><div class="kpi-num"><?= count($payments) ?></div><div class="kpi-lbl">Total Transactions</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                        <div><div class="kpi-num"><?= $pending_count ?></div><div class="kpi-lbl">Pending Verification</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                        <div><div class="kpi-num">₱<?= number_format($total_collected, 0) ?></div><div class="kpi-lbl">Payment Received</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-clock"></i></div>
                        <div><div class="kpi-num">₱<?= number_format($total_pending_amt, 0) ?></div><div class="kpi-lbl">Pending Amount</div></div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>GCash Payment Records</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Guest</th>
                                <th>Check-in</th>
                                <th>Total Due</th>
                                <th>Amount Paid</th>
                                <th>Remaining</th>
                                <th>Reference No.</th>
                                <th>Date Paid</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($payments) > 0):
                            foreach ($payments as $p):
                                $is_pending = $p['status'] === 'pending';
                        ?>
                        <tr>
                            <td>
                            <strong style="color:#1B7D3A;">#<?= str_pad($p['booking_id'], 6, '0', STR_PAD_LEFT) ?></strong>
                            <?php if (count($p['tx_booking_ids']) > 1): ?>
                            <div style="font-size:.7rem;color:#9ca3af;margin-top:2px;">
                                +<?= count($p['tx_booking_ids']) - 1 ?> more
                            </div>
                            <?php endif; ?>
                        </td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($p['guest_name']) ?></div>
                                <div style="font-size:.75rem;color:#9ca3af;"><?= htmlspecialchars($p['guest_phone'] ?? '') ?></div>
                            </td>
                            <td style="font-size:.78rem;"><?= date('M d, y', strtotime($p['check_in_date'])) ?></td>
                            <td><strong>&#8369;<?= number_format($p['tx_total_due'], 2) ?></strong></td>
                            <td><strong style="color:#059669;">&#8369;<?= number_format($p['tx_total_paid'], 2) ?></strong></td>
                            <td>
                                <?php $tx_rem = floatval($p['tx_remaining']); ?>
                                <span class="balance-badge <?= $tx_rem <= 0 ? 'zero' : '' ?>">
                                    <?= $tx_rem <= 0 ? '&#10003; Fully Paid' : '&#8369;' . number_format($tx_rem, 2) ?>
                                </span>
                            </td>
                            <td><span class="ref-code"><?= htmlspecialchars($p['reference_number'] ?? '—') ?></span></td>
                            <td style="font-size:.78rem;"><?= date('M d, y', strtotime($p['paid_at'])) ?></td>
                            <td>
                                <?php if ($is_pending): ?>
                                <span class="pay-status-pending"><i class="fas fa-clock me-1"></i>Pending</span>
                                <?php else: ?>
                                <span class="pay-status-done"><i class="fas fa-check me-1"></i>Verified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <button type="button" class="btn-view" style="background:#fff3e0;color:#e65100;border-color:#ffcc80;"
                                        onclick="openEditPayment(<?= $p['id'] ?>, <?= $p['booking_id'] ?>, <?= $p['amount_paid'] ?>, '<?= htmlspecialchars($p['reference_number'] ?? '', ENT_QUOTES) ?>')"
                                        title="Edit Payment Amount">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($is_pending): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <button type="submit" class="btn-approve" title="Verify Payment">
                                            <i class="fas fa-check-double"></i> Verify
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($p['tx_remaining'] > 0): ?>
                                    <button type="button" class="btn-view"
                                        onclick="openAddPayment(<?= $p['id'] ?>, <?= $p['booking_id'] ?>, <?= $p['tx_remaining'] ?>)"
                                        title="Record Additional Payment">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php
                                    $receipt_ids = implode(',', $p['tx_booking_ids']);
                                    $receipt_url = count($p['tx_booking_ids']) > 1
                                        ? "../booking_confirmation.php?booking_ids={$receipt_ids}"
                                        : "../booking_confirmation.php?booking_id={$p['booking_id']}";
                                    ?>
                                    <a href="<?= $receipt_url ?>" target="_blank" class="btn-view" title="View Receipt">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No online transactions found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#e65100,#f57c00);">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Payment Amount</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editPayForm">
                <input type="hidden" name="action" value="edit_payment">
                <input type="hidden" name="payment_id" id="edit_pay_id">
                <div class="modal-body">
                    <div class="alert" style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;color:#856404;font-size:.85rem;">
                        <i class="fas fa-info-circle me-1"></i> This will update the recorded payment to the <strong>actual amount sent</strong> based on the GCash reference number. A notification email will be sent to the guest.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Booking #</label>
                        <div id="edit_booking_ref" class="fw-bold text-success"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">GCash Reference No.</label>
                        <div id="edit_ref_no" style="font-family:monospace;background:#f3f4f6;padding:8px 12px;border-radius:8px;font-size:.9rem;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Amount</label>
                        <div id="edit_current_amt" style="color:#9ca3af;font-size:.9rem;text-decoration:line-through;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Corrected Amount <span style="color:#e65100;">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">&#8369;</span>
                            <input type="number" name="new_amount" id="edit_new_amount" class="form-control" step="0.01" min="0.01" required placeholder="Enter actual amount sent">
                        </div>
                        <div class="form-text text-muted">Enter the exact amount the guest actually sent via GCash.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background:linear-gradient(135deg,#e65100,#f57c00);color:#fff;font-weight:700;">
                        <i class="fas fa-save me-1"></i>Update & Notify Guest
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color:#1B7D3A;"></i>Record Additional Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addPayForm">
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="payment_id" id="modal_pay_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Booking #</label>
                        <div id="modal_booking_ref" class="fw-bold text-success"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Remaining Balance</label>
                        <div id="modal_remaining" class="fw-bold" style="color:#e65100;"></div>
                    </div>

                    <!-- Payment Method Toggle -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary flex-fill method-btn active" id="btnGcash" onclick="setMethod('online')">
                                <i class="fas fa-mobile-alt me-1"></i> GCash
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-fill method-btn" id="btnWalkin" onclick="setMethod('walkin')">
                                <i class="fas fa-money-bill-wave me-1"></i> Walk-in / Cash
                            </button>
                        </div>
                        <input type="hidden" name="extra_method" id="extra_method" value="online">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount Received</label>
                        <div class="input-group">
                            <span class="input-group-text">&#8369;</span>
                            <input type="number" name="extra_amount" id="extra_amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-text" id="modal_balance_hint"></div>
                    </div>

                    <div class="mb-3" id="refGroup">
                        <label class="form-label fw-semibold">GCash Reference Number</label>
                        <input type="text" name="extra_ref" id="extra_ref" class="form-control" maxlength="20" placeholder="e.g. 1234567890123">
                        <div class="form-text text-muted">Required for GCash. Leave blank for walk-in cash.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Payment</button>
<!-- Proof of Payment Modal -->
<div class="modal fade" id="proofModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#0070e0,#0056b3);color:#fff;">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>GCash Proof of Payment Photo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="mb-3 d-flex justify-content-between align-items-center bg-light p-3 rounded border">
                    <div style="text-align:left;">
                        <span class="text-muted" style="font-size:.78rem;display:block;">Guest Name</span>
                        <strong id="proofGuestName" style="font-size:1rem;color:#111827;"></strong>
                    </div>
                    <div style="text-align:right;">
                        <span class="text-muted" style="font-size:.78rem;display:block;">GCash Ref No.</span>
                        <span id="proofRefNo" class="ref-code" style="font-size:.95rem;font-weight:700;"></span>
                    </div>
                </div>
                <div style="max-height: 65vh; overflow-y: auto; background: #0f172a; border-radius: 12px; padding: 16px; display:flex; align-items:center; justify-content:center;">
                    <img id="proofImg" src="" alt="GCash Receipt Photo" style="max-width: 100%; max-height: 60vh; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); object-fit: contain;">
                </div>
            </div>
            <div class="modal-footer">
                <a id="proofDownloadBtn" href="" target="_blank" class="btn btn-outline-primary" download>
                    <i class="fas fa-external-link-alt me-1"></i> Open Full Image
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modalRemaining = 0;

function openProofModal(imgSrc, guestName, refNo) {
    document.getElementById('proofImg').src = imgSrc;
    document.getElementById('proofDownloadBtn').href = imgSrc;
    document.getElementById('proofGuestName').textContent = guestName;
    document.getElementById('proofRefNo').textContent = refNo || '—';
    var proofModal = new bootstrap.Modal(document.getElementById('proofModal'));
    proofModal.show();
}

function openEditPayment(payId, bookingId, currentAmount, refNo) {
    document.getElementById('edit_pay_id').value = payId;
    document.getElementById('edit_booking_ref').textContent = '#' + String(bookingId).padStart(6, '0');
    document.getElementById('edit_ref_no').textContent = refNo || 'N/A';
    document.getElementById('edit_current_amt').textContent = '\u20b1' + parseFloat(currentAmount).toFixed(2);
    document.getElementById('edit_new_amount').value = parseFloat(currentAmount).toFixed(2);
    new bootstrap.Modal(document.getElementById('editPayModal')).show();
}

function openAddPayment(payId, bookingId, remaining) {
    modalRemaining = parseFloat(remaining) || 0;
    document.getElementById('modal_pay_id').value = payId;
    document.getElementById('modal_booking_ref').textContent = '#' + String(bookingId).padStart(6,'0');
    document.getElementById('modal_remaining').textContent = '\u20b1' + modalRemaining.toFixed(2);
    document.getElementById('extra_amount').value = '';
    document.getElementById('extra_ref').value = '';
    document.getElementById('modal_balance_hint').textContent = '';
    setMethod('online');
    new bootstrap.Modal(document.getElementById('addPayModal')).show();
}

function setMethod(m) {
    document.getElementById('extra_method').value = m;
    const refGroup = document.getElementById('refGroup');
    const refInput = document.getElementById('extra_ref');
    const btnG = document.getElementById('btnGcash');
    const btnW = document.getElementById('btnWalkin');
    if (m === 'online') {
        btnG.classList.add('active','btn-primary'); btnG.classList.remove('btn-outline-primary');
        btnW.classList.remove('active','btn-secondary'); btnW.classList.add('btn-outline-secondary');
        refGroup.style.display = '';
        refInput.setAttribute('required', 'required');
        refGroup.querySelector('label').textContent = 'GCash Reference Number';
    } else {
        btnW.classList.add('active','btn-secondary'); btnW.classList.remove('btn-outline-secondary');
        btnG.classList.remove('active','btn-primary'); btnG.classList.add('btn-outline-primary');
        refGroup.style.display = '';
        refInput.removeAttribute('required');
        refGroup.querySelector('label').textContent = 'Receipt / Note (Optional)';
        refGroup.querySelector('input').placeholder = 'e.g. Cash paid at front desk';
    }
}

document.getElementById('extra_amount').addEventListener('input', function() {
    const v = parseFloat(this.value) || 0;
    const hint = document.getElementById('modal_balance_hint');
    if (v > 0) {
        const bal = modalRemaining - v;
        hint.style.color = bal <= 0 ? '#059669' : '#e65100';
        hint.textContent = bal <= 0 ? '\u2713 Fully settled!' : 'Still remaining: \u20b1' + bal.toFixed(2);
    } else { hint.textContent = ''; }
});
</script>
</body>
</html>
