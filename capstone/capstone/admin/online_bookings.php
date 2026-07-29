<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !in_array($_SESSION['user_role'], ['admin','frontdesk'])) {
    header("Location: " . BASE_URL . "unauthorized.php");
    exit();
}

$user = get_user_info($_SESSION['user_id'], $conn);

// Handle approve/decline/checkout booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id']);
    $action = $_POST['action'];

    // Get all sibling bookings in the same transaction
    $stmt = $conn->prepare("SELECT guest_email, check_in_date, check_out_date, created_at FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $group_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($group_row) {
        $email = $group_row['guest_email'];
        $ci = $group_row['check_in_date'];
        $co = $group_row['check_out_date'];
        $ca = $group_row['created_at'];

        $sibling_stmt = $conn->prepare("SELECT id, facility_id, mode FROM bookings WHERE guest_email = ? AND check_in_date = ? AND check_out_date = ? AND created_at = ?");
        $sibling_stmt->bind_param("ssss", $email, $ci, $co, $ca);
        $sibling_stmt->execute();
        $sibling_res = $sibling_stmt->get_result();
        $sibling_bookings = [];
        while ($srow = $sibling_res->fetch_assoc()) {
            $sibling_bookings[] = $srow;
        }
        $sibling_stmt->close();

        $sibling_ids = array_column($sibling_bookings, 'id');

        // ── Check Out ──
        if ($action === 'checkout') {
            $placeholders = implode(',', array_fill(0, count($sibling_ids), '?'));
            $stmt = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id IN ($placeholders) AND status = 'confirmed'");
            $types = str_repeat('i', count($sibling_ids));
            $stmt->bind_param($types, ...$sibling_ids);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                set_success_message('Guest checked out successfully. Facilities are now available.');
                // Send thank-you + feedback email
                $first_id = $sibling_ids[0];
                $bs = $conn->prepare("SELECT b.*, f.name as facility_name, a.name as area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=?");
                $bs->bind_param("i", $first_id); $bs->execute();
                $bd = $bs->get_result()->fetch_assoc(); $bs->close();
                if ($bd && !empty($bd['guest_email'])) {
                    require_once '../includes/send_status_email.php';
                    sendCheckoutThankYouEmail($bd);
                }
            } else {
                set_error_message('Could not check out this booking.');
            }
            $stmt->close();

        // ── Approve / Decline ──
        } elseif (in_array($action, ['approve', 'decline'])) {
            $status = ($action === 'approve') ? 'confirmed' : 'declined';
            $approved_by = $_SESSION['user_id'];

            // Before approving, check availability to prevent double-booking for ALL sibling facilities
            if ($action === 'approve') {
                $overlap_found = false;
                foreach ($sibling_bookings as $sb) {
                    $fid = $sb['facility_id'];
                    $bmode = $sb['mode'];
                    $sb_id = $sb['id'];

                    if ($bmode === 'overnight') {
                        $av = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND id != ? AND status IN ('confirmed') AND check_in_date < ? AND check_out_date > ? LIMIT 1");
                        $av->bind_param("iiss", $fid, $sb_id, $co, $ci);
                    } else {
                        $av = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND id != ? AND status IN ('confirmed') AND mode='overnight' AND check_in_date <= ? AND check_out_date > ? LIMIT 1");
                        $av->bind_param("iiss", $fid, $sb_id, $ci, $ci);
                    }
                    $av->execute(); $av->store_result();
                    if ($av->num_rows > 0) {
                        $overlap_found = true;
                        $av->close();
                        break;
                    }
                    $av->close();
                }

                if ($overlap_found) {
                    set_error_message('Cannot approve: one or more facilities in this request are already confirmed for overlapping dates.');
                    header("Location: " . BASE_URL . "admin/online_bookings.php");
                    exit();
                }
            }

            // Update status for all siblings
            $placeholders = implode(',', array_fill(0, count($sibling_ids), '?'));
            $stmt = $conn->prepare("UPDATE bookings SET status = ?, created_by = ? WHERE id IN ($placeholders) AND booking_type = 'online'");
            $types = 'si' . str_repeat('i', count($sibling_ids));
            $stmt->bind_param($types, $status, $approved_by, ...$sibling_ids);

            if ($stmt->execute()) {
                // Get booking details for email using the first sibling ID
                $first_id = $sibling_ids[0];
                $booking_stmt = $conn->prepare("SELECT b.*, f.name as facility_name, a.name as area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id WHERE b.id = ?");
                $booking_stmt->bind_param("i", $first_id);
                $booking_stmt->execute();
                $booking_data = $booking_stmt->get_result()->fetch_assoc();
                $booking_stmt->close();

                if ($booking_data && $booking_data['guest_email']) {
                    // Combine facility names
                    $all_fac_names = [];
                    foreach ($sibling_bookings as $sb) {
                        $fn_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
                        $fn_stmt->bind_param("i", $sb['facility_id']);
                        $fn_stmt->execute();
                        $fn_row = $fn_stmt->get_result()->fetch_assoc();
                        if ($fn_row) $all_fac_names[] = $fn_row['name'];
                        $fn_stmt->close();
                    }
                    $booking_data['facility_name'] = implode(', ', $all_fac_names);

                    require_once '../includes/send_status_email.php';
                    sendBookingStatusEmail($booking_data, $status);
                }
                set_success_message('Bookings ' . $status . ' successfully and email sent to guest');
            } else {
                set_error_message('Error updating bookings: ' . $conn->error);
            }
            $stmt->close();
        }
    }
}

// Get online bookings — use LEFT JOIN so bookings with missing facility still appear
$bookings_result = $conn->query("SELECT b.*, f.name as facility_name, a.name as area_name, u.first_name, u.last_name, u.role FROM bookings b LEFT JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id LEFT JOIN users u ON b.created_by = u.id WHERE b.booking_type = 'online' ORDER BY b.created_at DESC");

// Pre-load all rows into array so we can count without data_seek issues
$all_bookings_arr = [];
if ($bookings_result) {
    while ($r = $bookings_result->fetch_assoc()) $all_bookings_arr[] = $r;
}

// Group by guest_email, check_in_date, check_out_date, created_at
$grouped_bookings = [];
foreach ($all_bookings_arr as $booking) {
    $group_key = $booking['guest_email'] . '_' . $booking['check_in_date'] . '_' . $booking['check_out_date'] . '_' . $booking['created_at'];
    if (!isset($grouped_bookings[$group_key])) {
        $grouped_bookings[$group_key] = [
            'id' => $booking['id'], // anchor ID
            'ids' => [],
            'facility_names' => [],
            'total_price' => 0.0,
            'status' => $booking['status'],
            'guest_name' => $booking['guest_name'],
            'guest_phone' => $booking['guest_phone'],
            'guest_email' => $booking['guest_email'],
            'check_in_date' => $booking['check_in_date'],
            'check_out_date' => $booking['check_out_date'],
            'num_guests' => 0,
            'num_adults' => 0,
            'num_children' => 0,
            'num_discounted' => 0,
            'first_name' => $booking['first_name'],
            'last_name' => $booking['last_name'],
            'role' => $booking['role'],
        ];
    }
    $grouped_bookings[$group_key]['ids'][] = $booking['id'];
    if ($booking['facility_name']) {
        $grouped_bookings[$group_key]['facility_names'][] = $booking['facility_name'];
    }
    $grouped_bookings[$group_key]['total_price'] += floatval($booking['total_price']);
    $grouped_bookings[$group_key]['num_guests'] += intval($booking['num_guests']);
    $grouped_bookings[$group_key]['num_adults'] += intval($booking['num_adults']);
    $grouped_bookings[$group_key]['num_children'] += intval($booking['num_children']);
    $grouped_bookings[$group_key]['num_discounted'] += intval($booking['num_discounted']);
}

$total_cnt     = count($grouped_bookings);
$pending_cnt   = count(array_filter($grouped_bookings, fn($r) => $r['status'] === 'pending' || $r['status'] === 'unpaid'));
$confirmed_cnt = count(array_filter($grouped_bookings, fn($r) => $r['status'] === 'confirmed'));
$declined_cnt  = count(array_filter($grouped_bookings, fn($r) => $r['status'] === 'declined'));
$completed_cnt = count(array_filter($grouped_bookings, fn($r) => $r['status'] === 'completed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Bookings - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/admin_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/admin_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-globe me-2" style="color:#1B7D3A;"></i>Online Booking Requests</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-shield-alt me-1"></i>Admin</span>
            </div>
        </div>

        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>

            <?php if ($pending_cnt > 0): ?>
            <div class="alert-pending mb-4">
                <i class="fas fa-bell"></i>
                <div><strong><?= $pending_cnt ?> pending booking<?= $pending_cnt>1?'s':'' ?></strong> waiting for your approval.</div>
            </div>
            <?php endif; ?>

            <!-- KPI row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-list"></i></div>
                        <div><div class="kpi-num"><?= $total_cnt ?></div><div class="kpi-lbl">Total Requests</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                        <div><div class="kpi-num"><?= $pending_cnt ?></div><div class="kpi-lbl">Pending</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                        <div><div class="kpi-num"><?= $confirmed_cnt ?></div><div class="kpi-lbl">Confirmed</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon red"><i class="fas fa-times-circle"></i></div>
                        <div><div class="kpi-num"><?= $declined_cnt ?></div><div class="kpi-lbl">Declined</div></div>
                    </div>
                </div>
            </div>

            <!-- Bookings table -->
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>All Online Booking Requests</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Guest Name</th>
                                <th>Phone</th>
                                <th>Facility</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Guests</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($grouped_bookings) > 0):
                            foreach ($grouped_bookings as $booking):
                                $st = $booking['status'];
                                $pillClass = ($st==='completed') ? 'pill-green' : (($st==='confirmed') ? 'pill-blue' : (($st==='pending' || $st==='unpaid') ? 'pill-yellow' : 'pill-red'));
                                $display_status = ($st==='unpaid') ? 'Pending' : ucfirst($st);
                        ?>
                        <tr>
                            <td><strong style="color:#1B7D3A;">#<?= $booking['id'] ?></strong></td>
                            <td><?= htmlspecialchars($booking['guest_name']) ?></td>
                            <td><?= htmlspecialchars($booking['guest_phone'] ?: '—') ?></td>
                            <td><?= htmlspecialchars(implode(', ', $booking['facility_names'])) ?></td>
                            <td><?= date('M d, Y', strtotime($booking['check_in_date'])) ?></td>
                            <td><?= date('M d, Y', strtotime($booking['check_out_date'])) ?></td>
                            <td><?= $booking['num_guests'] ?></td>
                            <td><strong>₱<?= number_format($booking['total_price'],2) ?></strong></td>
                            <td><span class="pill <?= $pillClass ?>"><?= $display_status ?></span></td>
                            <td style="font-size:.82rem;"><?= ($booking['first_name']&&$booking['last_name']) ? htmlspecialchars($booking['first_name'].' '.$booking['last_name']).' ('.ucfirst($booking['role']).')' : '—' ?></td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <button type="button" class="btn-view" data-bs-toggle="modal" data-bs-target="#receiptModal" onclick="loadReceipt(<?= $booking['id'] ?>)" title="View Receipt">
                                        <i class="fas fa-receipt"></i>
                                    </button>
                                    <?php if ($st === 'pending' || $st === 'unpaid'): ?>
                                    <form method="POST" onsubmit="return confirm('Approve this booking request? This will approve all accommodations in this group.');" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve" title="Approve"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Decline this booking request? This will decline all accommodations in this group.');" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <button type="submit" class="btn-decline" title="Decline"><i class="fas fa-times"></i> Decline</button>
                                    </form>
                                    <?php elseif ($st === 'confirmed'): ?>
                                    <form method="POST" onsubmit="return confirm('Mark this guest as checked out? This will free up all facilities in this group.');" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="action" value="checkout">
                                        <button type="submit" style="background:linear-gradient(135deg,#1565c0,#1976d2);color:#fff;border:none;border-radius:8px;padding:6px 12px;font-size:.8rem;cursor:pointer;font-weight:600;">
                                            <i class="fas fa-sign-out-alt"></i> Check Out
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No online bookings found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Booking Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2 text-muted">Loading receipt...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-add" onclick="printReceipt()"><i class="fas fa-print me-1"></i>Print</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

function loadReceipt(bookingId) {
    const el = document.getElementById('receiptContent');
    el.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>';
    fetch(`../receipt.php?booking_id=${bookingId}`)
        .then(r => r.text())
        .then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            el.innerHTML = (doc.querySelector('.receipt') || doc.body).innerHTML;
        })
        .catch(() => { el.innerHTML = '<div class="alert alert-danger">Error loading receipt.</div>'; });
}

function printReceipt() {
    const w = window.open('','_blank','width=800,height=600');
    w.document.write('<!DOCTYPE html><html><head><title>Receipt</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{padding:20px}</style></head><body>' + document.getElementById('receiptContent').innerHTML + '<script>window.onload=function(){window.print()}<\/script></body></html>