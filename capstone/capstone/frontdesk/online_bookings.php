<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_role'] !== 'frontdesk') {
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
                    header("Location: " . BASE_URL . "frontdesk/online_bookings.php");
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
    <?php require_once '../includes/frontdesk_page_styles.php'; ?>
    <style>
        /* ── Facility Availability Modal ── */
        #facility-type-tabs .nav-link {
            color: #4a5568;
            background: #e2e8f0;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        #facility-type-tabs .nav-link.active {
            background: #1B7D3A !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(27,125,58,0.25);
        }
        .avail-card {
            background: white;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            height: 100%;
        }
        .avail-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .avail-card.status-available  { border-left: 5px solid #28a745; }
        .avail-card.status-occupied   { border-left: 5px solid #dc3545; background: #fffcfc; }
        .avail-card.status-maintenance{ border-left: 5px solid #ffc107; background: #fffdf8; }
        .avail-card.status-unavailable{ border-left: 5px solid #6c757d; }
        .avail-badge-available   { background:#d1fae5; color:#065f46; font-weight:700; font-size:0.75rem; padding:4px 10px; border-radius:20px; display:inline-flex; align-items:center; }
        .avail-badge-occupied    { background:#fdecea; color:#c62828; font-weight:700; font-size:0.75rem; padding:4px 10px; border-radius:20px; display:inline-flex; align-items:center; }
        .avail-badge-maintenance { background:#fef9c3; color:#854d0e; font-weight:700; font-size:0.75rem; padding:4px 10px; border-radius:20px; display:inline-flex; align-items:center; }
        .avail-badge-unavailable { background:#f3f4f6; color:#374151; font-weight:700; font-size:0.75rem; padding:4px 10px; border-radius:20px; display:inline-flex; align-items:center; }

        /* ── Facility Schedule Calendar ── */
        .fsc-calendar { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; margin-top:8px; }
        .fsc-day-header { text-align:center; font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; padding:4px 0; letter-spacing:0.5px; }
        .fsc-day { border-radius:10px; padding:6px 4px; text-align:center; font-size:0.72rem; font-weight:700; cursor:pointer; transition:transform 0.15s,box-shadow 0.15s; border:1.5px solid transparent; position:relative; min-height:54px; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; gap:2px; }
        .fsc-day:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.12); }
        .fsc-day.fsc-empty     { background:transparent; border-color:transparent; pointer-events:none; }
        .fsc-day.fsc-available { background:#d1fae5; border-color:#6ee7b7; color:#065f46; }
        .fsc-day.fsc-available:hover { background:#a7f3d0; border-color:#34d399; }
        .fsc-day.fsc-partial   { background:#fef9c3; border-color:#fde68a; color:#78350f; }
        .fsc-day.fsc-partial:hover   { background:#fde68a; }
        .fsc-day.fsc-booked    { background:#fdecea; border-color:#fca5a5; color:#991b1b; }
        .fsc-day.fsc-booked:hover    { background:#fca5a5; }
        .fsc-day.fsc-today-marker    { box-shadow:0 0 0 2px #1B7D3A; }
        .fsc-day .fsc-date-num { font-size:0.85rem; font-weight:900; line-height:1; }
        .fsc-day .fsc-slots    { display:flex; flex-direction:column; gap:2px; width:100%; }
        .fsc-slot-dot          { display:flex; align-items:center; justify-content:center; font-size:0.6rem; font-weight:800; padding:1px 4px; border-radius:4px; white-space:nowrap; width:100%; }
        .fsc-slot-dot.avail    { background:rgba(6,95,70,0.15); color:#065f46; }
        .fsc-slot-dot.booked   { background:rgba(153,27,27,0.15); color:#991b1b; }
        .fsc-legend            { display:flex; gap:16px; flex-wrap:wrap; align-items:center; font-size:0.78rem; font-weight:600; color:#4a5568; }
        .fsc-legend-dot        { width:14px; height:14px; border-radius:4px; display:inline-block; margin-right:5px; }
        .fsc-month-hdr         { font-size:1rem; font-weight:800; color:#1e293b; margin:18px 0 6px; padding-bottom:6px; border-bottom:2px solid #e2e8f0; }
        .fsc-day-detail        { background:white; border-radius:12px; border:1.5px solid #e2e8f0; padding:14px 18px; margin-top:16px; display:none; }
        .fsc-day-detail.show   { display:block; }
        .fsc-booking-row       { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:6px; font-size:0.8rem; }
    </style>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/frontdesk_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-globe me-2" style="color:#1B7D3A;"></i>Online Booking Requests</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#facilitiesAvailabilityModal"
                    style="background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;font-weight:700;border:none;border-radius:8px;padding:7px 16px;font-size:0.82rem;">
                    <i class="fas fa-door-open me-1"></i> Check Availability
                </button>
                <span class="dash-topbar-badge"><i class="fas fa-concierge-bell me-1"></i>Front Desk</span>
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
                        <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                        <div><div class="kpi-num"><?= $completed_cnt ?></div><div class="kpi-lbl">Completed</div></div>
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
                <div class="section-hdr mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">All Online Booking Requests</h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Status Filter -->
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto;font-weight:600;border-radius:8px;border:1.5px solid #e2e8f0;font-size:0.82rem;">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="declined">Declined</option>
                        </select>
                        <!-- Search -->
                        <div class="input-group input-group-sm" style="width:220px;">
                            <span class="input-group-text" style="background:#f1f5f9;border-right:none;border-radius:8px 0 0 8px;"><i class="fas fa-search" style="color:#64748b;"></i></span>
                            <input type="text" id="bookingSearch" class="form-control" placeholder="Search name, facility…" style="border-left:none;border-radius:0 8px 8px 0;font-size:0.82rem;">
                        </div>
                        <!-- Entries per page -->
                        <select id="perPageSelect" class="form-select form-select-sm" style="width:auto;font-weight:600;border-radius:8px;border:1.5px solid #e2e8f0;font-size:0.82rem;">
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                            <option value="9999">All</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="bookingsTable">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>Guest Name</th>
                                <th>Phone</th>
                                <th>Facility</th>
                                <th>Check-in / Check-out</th>
                                <th style="width:60px">Guests</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsBody">
                        <?php if (count($grouped_bookings) > 0):
                            foreach ($grouped_bookings as $booking):
                                $st = $booking['status'];
                                $pillClass = ($st==='completed') ? 'pill-green' : (($st==='confirmed') ? 'pill-blue' : (($st==='pending' || $st==='unpaid') ? 'pill-yellow' : 'pill-red'));
                                $display_status = ($st==='unpaid') ? 'Pending' : ucfirst($st);
                                $data_status = ($st==='unpaid') ? 'pending' : $st;
                        ?>
                        <tr data-status="<?= $data_status ?>">
                            <td><strong style="color:#1B7D3A;">#<?= $booking['id'] ?></strong></td>
                            <td><?= htmlspecialchars($booking['guest_name']) ?></td>
                            <td style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($booking['guest_phone'] ?: '—') ?></td>
                            <td><?= htmlspecialchars(implode(', ', $booking['facility_names'])) ?></td>
                            <td style="font-size:.78rem;"><?= date('M d, Y', strtotime($booking['check_in_date'])) ?> → <?= date('M d, Y', strtotime($booking['check_out_date'])) ?></td>
                            <td style="text-align:center;"><?= $booking['num_guests'] ?></td>
                            <td><strong>&#8369;<?= number_format($booking['total_price'],2) ?></strong></td>
                            <td><span class="pill <?= $pillClass ?>"><?= $display_status ?></span></td>
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
                        <tr id="noDataRow"><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No online bookings found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div class="d-flex justify-content-between align-items-center mt-3 px-1 flex-wrap gap-2" id="paginationBar">
                    <div id="paginationInfo" style="font-size:0.82rem;color:#64748b;font-weight:600;"></div>
                    <nav><ul class="pagination pagination-sm mb-0" id="paginationLinks"></ul></nav>
                </div>
            </div>

            <script>
            (function () {
                const tbody      = document.getElementById('bookingsBody');
                const searchInput= document.getElementById('bookingSearch');
                const statusSel  = document.getElementById('statusFilter');
                const perPageSel = document.getElementById('perPageSelect');
                const info       = document.getElementById('paginationInfo');
                const links      = document.getElementById('paginationLinks');

                let allRows   = Array.from(tbody.querySelectorAll('tr[data-status]'));
                let filtered  = [...allRows];
                let currentPage = 1;

                function applyFilters() {
                    const q  = searchInput.value.toLowerCase().trim();
                    const st = statusSel.value;
                    filtered = allRows.filter(row => {
                        const text = row.textContent.toLowerCase();
                        const matchSearch = !q || text.includes(q);
                        const matchStatus = !st || row.dataset.status === st;
                        return matchSearch && matchStatus;
                    });
                    currentPage = 1;
                    render();
                }

                function render() {
                    const perPage = parseInt(perPageSel.value);
                    const total   = filtered.length;
                    const pages   = perPage >= 9999 ? 1 : Math.max(1, Math.ceil(total / perPage));
                    if (currentPage > pages) currentPage = pages;

                    const start = perPage >= 9999 ? 0 : (currentPage - 1) * perPage;
                    const end   = perPage >= 9999 ? total : Math.min(start + perPage, total);

                    // Hide all rows first
                    allRows.forEach(r => r.style.display = 'none');

                    // Show noDataRow if nothing matches
                    const noDataRow = document.getElementById('noDataRow');
                    if (noDataRow) noDataRow.style.display = 'none';

                    if (total === 0) {
                        // Show a "no results" message
                        if (noDataRow) {
                            noDataRow.style.display = '';
                            noDataRow.querySelector('td').innerHTML = '<i class="fas fa-search me-2"></i>No bookings match your search.';
                        } else {
                            // Insert temporary no-results row
                            let tmp = tbody.querySelector('#tmpNoResults');
                            if (!tmp) {
                                tmp = document.createElement('tr');
                                tmp.id = 'tmpNoResults';
                                tmp.innerHTML = '<td colspan="9" class="text-center text-muted py-4"><i class="fas fa-search me-2"></i>No bookings match your search.</td>';
                                tbody.appendChild(tmp);
                            }
                            tmp.style.display = '';
                        }
                        info.textContent = 'Showing 0 results';
                        links.innerHTML  = '';
                        return;
                    }

                    // Remove tmp no-results row if present
                    const tmp = tbody.querySelector('#tmpNoResults');
                    if (tmp) tmp.style.display = 'none';

                    filtered.slice(start, end).forEach(r => r.style.display = '');

                    // Info text
                    info.textContent = `Showing ${start + 1}–${end} of ${total} booking${total !== 1 ? 's' : ''}`;

                    // Pagination links
                    links.innerHTML = '';
                    if (pages <= 1) return;

                    const maxVisible = 5;
                    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                    let endPage   = Math.min(pages, startPage + maxVisible - 1);
                    if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

                    // Prev
                    const prevLi = document.createElement('li');
                    prevLi.className = 'page-item' + (currentPage === 1 ? ' disabled' : '');
                    prevLi.innerHTML = '<a class="page-link" href="#">&laquo;</a>';
                    prevLi.addEventListener('click', e => { e.preventDefault(); if (currentPage > 1) { currentPage--; render(); } });
                    links.appendChild(prevLi);

                    for (let p = startPage; p <= endPage; p++) {
                        const li = document.createElement('li');
                        li.className = 'page-item' + (p === currentPage ? ' active' : '');
                        li.innerHTML = `<a class="page-link" href="#">${p}</a>`;
                        const pg = p;
                        li.addEventListener('click', e => { e.preventDefault(); currentPage = pg; render(); });
                        links.appendChild(li);
                    }

                    // Next
                    const nextLi = document.createElement('li');
                    nextLi.className = 'page-item' + (currentPage === pages ? ' disabled' : '');
                    nextLi.innerHTML = '<a class="page-link" href="#">&raquo;</a>';
                    nextLi.addEventListener('click', e => { e.preventDefault(); if (currentPage < pages) { currentPage++; render(); } });
                    links.appendChild(nextLi);
                }

                searchInput.addEventListener('input',  applyFilters);
                statusSel.addEventListener('change',   applyFilters);
                perPageSel.addEventListener('change',  () => { currentPage = 1; render(); });

                // Initial render
                render();
            })();
            </script>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Facility Availability & Schedule Viewer Modal
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="facilitiesAvailabilityModal" tabindex="-1" aria-labelledby="facilitiesAvailabilityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#1B7D3A,#27A457);color:white;border-top-left-radius:16px;border-top-right-radius:16px;padding:20px 24px;">
                <h5 class="modal-title" id="facilitiesAvailabilityModalLabel" style="font-weight:800;display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-door-open"></i> Facility Availability &amp; Schedule Viewer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="background:#f8fafc;padding:24px;">
                <!-- Filter Bar -->
                <div class="row g-3 align-items-end mb-4 p-3" style="background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                    <div class="col-md-5">
                        <label class="form-label" style="font-weight:700;color:#4a5568;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px;">Check-in Date</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:#f1f5f9;border-right:none;color:#64748b;"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" id="avail-filter-date" class="form-control" style="border-left:none;font-weight:600;color:#1e293b;" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" style="font-weight:700;color:#4a5568;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px;">Time Slot / Mode</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:#f1f5f9;border-right:none;color:#64748b;"><i class="fas fa-clock"></i></span>
                            <select id="avail-filter-slot" class="form-select" style="border-left:none;font-weight:600;color:#1e293b;">
                                <option value="8am-12pm">Daytour: Morning (8:00 AM - 12:00 PM)</option>
                                <option value="12pm-5pm">Daytour: Afternoon (12:00 PM - 5:00 PM)</option>
                                <option value="full_day" selected>Daytour: Full Day (8:00 AM - 5:00 PM)</option>
                                <option value="overnight">Overnight Booking</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="btn-refresh-avail" class="btn w-100" style="background:#1B7D3A;color:white;font-weight:700;border-radius:6px;padding:8px 16px;border:none;transition:background 0.2s;">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Loader -->
                <div id="avail-loader" class="text-center py-5" style="display:none;">
                    <div class="spinner-border" role="status" style="color:#1B7D3A;width:3rem;height:3rem;"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-3 text-muted" style="font-weight:600;">Checking live availability...</p>
                </div>

                <!-- Content Grid -->
                <div id="avail-content" style="display:none;">
                    <ul class="nav nav-pills mb-4 d-flex justify-content-center gap-2" id="facility-type-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="ob-tab-all-btn" data-bs-toggle="pill" data-bs-target="#ob-tab-all" type="button" role="tab" style="font-weight:700;border-radius:30px;padding:8px 20px;">All Types</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ob-tab-room-btn" data-bs-toggle="pill" data-bs-target="#ob-tab-room" type="button" role="tab" style="font-weight:700;border-radius:30px;padding:8px 20px;"><i class="fas fa-bed me-1"></i> Rooms</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ob-tab-cottage-btn" data-bs-toggle="pill" data-bs-target="#ob-tab-cottage" type="button" role="tab" style="font-weight:700;border-radius:30px;padding:8px 20px;"><i class="fas fa-home me-1"></i> Cottages</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ob-tab-hall-btn" data-bs-toggle="pill" data-bs-target="#ob-tab-hall" type="button" role="tab" style="font-weight:700;border-radius:30px;padding:8px 20px;"><i class="fas fa-building me-1"></i> Halls</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="ob-facility-tab-content">
                        <div class="tab-pane fade show active" id="ob-tab-all" role="tabpanel"><div class="row g-3" id="avail-list-all"></div></div>
                        <div class="tab-pane fade" id="ob-tab-room"    role="tabpanel"><div class="row g-3" id="avail-list-room"></div></div>
                        <div class="tab-pane fade" id="ob-tab-cottage" role="tabpanel"><div class="row g-3" id="avail-list-cottage"></div></div>
                        <div class="tab-pane fade" id="ob-tab-hall"    role="tabpanel"><div class="row g-3" id="avail-list-hall"></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 24px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-weight:700;border-radius:6px;padding:8px 20px;">Close</button>
                <a href="walkin_booking.php" class="btn text-white" style="background:linear-gradient(135deg,#1B7D3A,#27A457);font-weight:700;border-radius:6px;padding:8px 20px;border:none;">
                    <i class="fas fa-plus me-1"></i> Create Booking
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Facility Schedule Calendar Modal
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="facilityScheduleModal" tabindex="-1" aria-labelledby="facilityScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#1B7D3A,#27A457);color:white;border-top-left-radius:16px;border-top-right-radius:16px;padding:20px 24px;">
                <div>
                    <h5 class="modal-title mb-0" id="facilityScheduleModalLabel" style="font-weight:800;">
                        <i class="fas fa-calendar-alt me-2"></i><span id="fsc-title">Facility Schedule</span>
                    </h5>
                    <div id="fsc-subtitle" style="font-size:0.8rem;opacity:0.85;margin-top:3px;"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="background:#f8fafc;padding:24px;">
                <!-- Legend -->
                <div class="fsc-legend mb-3 p-3" style="background:white;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                    <span><span class="fsc-legend-dot" style="background:#d1fae5;border:1.5px solid #6ee7b7;"></span>Available</span>
                    <span><span class="fsc-legend-dot" style="background:#fef9c3;border:1.5px solid #fde68a;"></span>Partially Booked</span>
                    <span><span class="fsc-legend-dot" style="background:#fdecea;border:1.5px solid #fca5a5;"></span>Fully Booked</span>
                    <span style="margin-left:auto;font-size:0.72rem;color:#94a3b8;"><i class="fas fa-mouse-pointer me-1"></i>Click a date for details</span>
                </div>
                <!-- Loader -->
                <div id="fsc-loader" class="text-center py-5">
                    <div class="spinner-border" role="status" style="color:#1B7D3A;width:3rem;height:3rem;"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-3 text-muted" style="font-weight:600;">Loading schedule...</p>
                </div>
                <!-- Calendar content -->
                <div id="fsc-content" style="display:none;">
                    <div id="fsc-calendar-wrap"></div>
                    <div class="fsc-day-detail" id="fsc-day-detail">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong id="fsc-detail-date" style="color:#1e293b;font-size:0.95rem;"></strong>
                            <span id="fsc-detail-badge"></span>
                        </div>
                        <div id="fsc-detail-slots" class="mb-2"></div>
                        <div id="fsc-detail-bookings"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 24px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-weight:700;border-radius:6px;padding:8px 20px;">Close</button>
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
// ─── Facility Availability & Schedule Viewer ────────────────────────────────
(function () {
    const modalEl    = document.getElementById('facilitiesAvailabilityModal');
    const dateInput  = document.getElementById('avail-filter-date');
    const slotSelect = document.getElementById('avail-filter-slot');
    const btnRefresh = document.getElementById('btn-refresh-avail');
    const loader     = document.getElementById('avail-loader');
    const content    = document.getElementById('avail-content');
    const listAll    = document.getElementById('avail-list-all');
    const listRoom   = document.getElementById('avail-list-room');
    const listCottage= document.getElementById('avail-list-cottage');
    const listHall   = document.getElementById('avail-list-hall');

    function fetchAvailability() {
        const date = dateInput.value;
        const slot = slotSelect.value;
        loader.style.display  = 'block';
        content.style.display = 'none';
        fetch(`get_facilities_availability.php?date=${date}&slot=${slot}`)
            .then(r => r.json())
            .then(data => {
                loader.style.display  = 'none';
                content.style.display = 'block';
                if (!data.success) { alert('Error: ' + data.error); return; }
                renderFacilities(data.facilities);
            })
            .catch(err => {
                loader.style.display = 'none';
                alert('Failed to load facility availability.');
                console.error(err);
            });
    }

    function renderFacilities(facilities) {
        listAll.innerHTML = listRoom.innerHTML = listCottage.innerHTML = listHall.innerHTML = '';
        if (facilities.length === 0) {
            const noData = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3"></i><p>No facilities registered.</p></div>`;
            listAll.innerHTML = noData;
            return;
        }
        let roomsCount = 0, cottagesCount = 0, hallsCount = 0;
        facilities.forEach(fac => {
            const typeLower = fac.type.toLowerCase();
            const isAvail   = fac.is_available;
            let statusClass = 'status-available';
            let badgeHtml   = '<span class="avail-badge-available"><i class="fas fa-check-circle me-1"></i>Available</span>';
            if (!isAvail) {
                if (fac.status === 'maintenance') {
                    statusClass = 'status-maintenance';
                    badgeHtml   = '<span class="avail-badge-maintenance"><i class="fas fa-tools me-1"></i>Maintenance</span>';
                } else if (fac.status === 'unavailable') {
                    statusClass = 'status-unavailable';
                    badgeHtml   = '<span class="avail-badge-unavailable"><i class="fas fa-times-circle me-1"></i>Unavailable</span>';
                } else {
                    statusClass = 'status-occupied';
                    badgeHtml   = `<span class="avail-badge-occupied" title="${fac.conflict_reason}"><i class="fas fa-door-closed me-1"></i>Occupied</span>`;
                }
            }
            const priceFormatted = parseFloat(fac.price).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
            const schedCnt       = fac.schedules ? fac.schedules.length : 0;
            const scheduleHtml   = `
                <div class="mt-3">
                    <button class="btn btn-sm w-100 py-1 fsc-open-btn" type="button"
                        onclick="openFacilitySchedule(${fac.id})"
                        style="font-size:0.75rem;font-weight:700;background:linear-gradient(135deg,#1B7D3A,#27A457);color:white;border:none;border-radius:8px;">
                        <i class="fas fa-calendar-alt me-1"></i> View Schedule${schedCnt > 0 ? ' (' + schedCnt + ' bookings)' : ''}
                    </button>
                </div>`;
            const cardHtml = `
                <div class="col-md-6 col-lg-4">
                    <div class="avail-card ${statusClass} p-3 d-flex flex-column justify-content-between h-100">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 style="font-weight:800;color:#1e293b;margin:0;font-size:1rem;">${fac.name}</h6>
                                ${badgeHtml}
                            </div>
                            <div class="text-muted mb-2" style="font-size:0.78rem;font-weight:600;">
                                <i class="fas fa-map-marker-alt me-1" style="color:#1B7D3A;"></i>Area: ${fac.area_name}
                            </div>
                            <div class="d-flex justify-content-between text-muted mb-2" style="font-size:0.8rem;">
                                <span><i class="fas fa-users me-1"></i>Capacity: <strong>${fac.capacity} pax</strong></span>
                                <span><i class="fas fa-tag me-1"></i>Price: <strong style="color:#1B7D3A;">&#8369;${priceFormatted}</strong></span>
                            </div>
                            ${!isAvail && fac.conflict_reason ? `<div class="alert alert-danger py-2 px-3 mb-2" style="font-size:0.75rem;font-weight:600;border-radius:8px;"><i class="fas fa-exclamation-circle me-1"></i>${fac.conflict_reason}</div>` : ''}
                        </div>
                        ${scheduleHtml}
                    </div>
                </div>`;
            listAll.insertAdjacentHTML('beforeend', cardHtml);
            if (typeLower === 'room' || typeLower === 'rooms')                          { listRoom.insertAdjacentHTML('beforeend', cardHtml); roomsCount++; }
            else if (typeLower === 'cottage' || typeLower === 'cottages')               { listCottage.insertAdjacentHTML('beforeend', cardHtml); cottagesCount++; }
            else if (['function_hall','hall','halls','function hall'].includes(typeLower)){ listHall.insertAdjacentHTML('beforeend', cardHtml); hallsCount++; }
        });
        if (roomsCount    === 0) listRoom.innerHTML    = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-bed fa-3x mb-3"></i><p>No rooms found.</p></div>`;
        if (cottagesCount === 0) listCottage.innerHTML = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-home fa-3x mb-3"></i><p>No cottages found.</p></div>`;
        if (hallsCount    === 0) listHall.innerHTML    = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-building fa-3x mb-3"></i><p>No function halls found.</p></div>`;
    }

    modalEl.addEventListener('show.bs.modal', fetchAvailability);
    dateInput.addEventListener('change', fetchAvailability);
    slotSelect.addEventListener('change', fetchAvailability);
    btnRefresh.addEventListener('click', fetchAvailability);

    // ─── Facility Schedule Calendar ──────────────────────────────────────────
    const fscModal      = document.getElementById('facilityScheduleModal');
    const fscLoader     = document.getElementById('fsc-loader');
    const fscContent    = document.getElementById('fsc-content');
    const fscCalWrap    = document.getElementById('fsc-calendar-wrap');
    const fscTitle      = document.getElementById('fsc-title');
    const fscSubtitle   = document.getElementById('fsc-subtitle');
    const fscDayDetail  = document.getElementById('fsc-day-detail');
    const fscDetailDate = document.getElementById('fsc-detail-date');
    const fscDetailBadge= document.getElementById('fsc-detail-badge');
    const fscDetailSlots= document.getElementById('fsc-detail-slots');
    const fscDetailBk   = document.getElementById('fsc-detail-bookings');
    let   fscBsModal    = null;
    if (fscModal) fscBsModal = new bootstrap.Modal(fscModal);

    window.openFacilitySchedule = function(facilityId) {
        if (!fscBsModal) return;
        fscLoader.style.display  = 'block';
        fscContent.style.display = 'none';
        fscTitle.textContent     = 'Loading...';
        fscSubtitle.textContent  = '';
        fscCalWrap.innerHTML     = '';
        fscDayDetail.classList.remove('show');
        fscBsModal.show();
        fetch(`get_facility_schedule.php?facility_id=${facilityId}&days=60`)
            .then(r => r.json())
            .then(data => {
                fscLoader.style.display = 'none';
                if (!data.success) {
                    fscCalWrap.innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
                    fscContent.style.display = 'block';
                    return;
                }
                const fac      = data.facility;
                const priceStr = parseFloat(fac.price).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
                fscTitle.textContent    = fac.name + ' — Schedule';
                fscSubtitle.textContent = `Capacity: ${fac.capacity} pax  ·  &#8369;${priceStr}  ·  Area: ${fac.area_name}`;
                renderFscCalendar(data.days, data.today);
                fscContent.style.display = 'block';
            })
            .catch(err => {
                fscLoader.style.display = 'none';
                fscCalWrap.innerHTML    = `<div class="alert alert-danger">Failed to load schedule.</div>`;
                fscContent.style.display = 'block';
                console.error(err);
            });
    };

    function renderFscCalendar(days, today) {
        fscCalWrap.innerHTML = '';
        fscDayDetail.classList.remove('show');
        if (!days || days.length === 0) return;
        const months = {};
        days.forEach(d => {
            const dt  = new Date(d.date + 'T00:00:00');
            const key = dt.getFullYear() + '-' + dt.getMonth();
            if (!months[key]) months[key] = { year: dt.getFullYear(), month: dt.getMonth(), days: [] };
            months[key].days.push(d);
        });
        const DAY_NAMES = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        Object.values(months).forEach(mObj => {
            const mLabel = new Date(mObj.year, mObj.month, 1).toLocaleDateString('en-US',{month:'long',year:'numeric'});
            const hdr = document.createElement('div');
            hdr.className   = 'fsc-month-hdr';
            hdr.textContent = mLabel;
            fscCalWrap.appendChild(hdr);
            const grid = document.createElement('div');
            grid.className  = 'fsc-calendar';
            DAY_NAMES.forEach(n => {
                const dh = document.createElement('div');
                dh.className   = 'fsc-day-header';
                dh.textContent = n;
                grid.appendChild(dh);
            });
            const firstDow = new Date(mObj.year, mObj.month, parseInt(mObj.days[0].date.split('-')[2])).getDay();
            for (let e = 0; e < firstDow; e++) {
                const em = document.createElement('div');
                em.className = 'fsc-day fsc-empty';
                grid.appendChild(em);
            }
            mObj.days.forEach(dayInfo => {
                const dt     = new Date(dayInfo.date + 'T00:00:00');
                const dayNum = dt.getDate();
                const isToday= dayInfo.date === today;
                let cls      = 'fsc-available';
                if (dayInfo.fully_booked)           cls = 'fsc-booked';
                else if (dayInfo.bookings.length > 0) cls = 'fsc-partial';
                const cell = document.createElement('div');
                cell.className = `fsc-day ${cls}${isToday ? ' fsc-today-marker' : ''}`;
                cell.setAttribute('data-date', dayInfo.date);
                const numEl = document.createElement('div');
                numEl.className   = 'fsc-date-num';
                numEl.textContent = dayNum;
                cell.appendChild(numEl);
                if (!dayInfo.fully_booked) {
                    const slotsEl = document.createElement('div');
                    slotsEl.className = 'fsc-slots';
                    [{key:'morning_available',label:'AM'},{key:'afternoon_available',label:'PM'},{key:'overnight_available',label:'ON'}].forEach(s => {
                        const dot = document.createElement('div');
                        dot.className   = `fsc-slot-dot ${dayInfo[s.key] ? 'avail' : 'booked'}`;
                        dot.textContent = s.label;
                        slotsEl.appendChild(dot);
                    });
                    cell.appendChild(slotsEl);
                } else {
                    const fb = document.createElement('div');
                    fb.style.cssText = 'font-size:0.6rem;font-weight:800;margin-top:2px;';
                    fb.textContent   = 'BOOKED';
                    cell.appendChild(fb);
                }
                cell.addEventListener('click', () => showDayDetail(dayInfo));
                grid.appendChild(cell);
            });
            fscCalWrap.appendChild(grid);
        });
    }

    function showDayDetail(dayInfo) {
        const dt        = new Date(dayInfo.date + 'T00:00:00');
        const dateLabel = dt.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
        fscDetailDate.textContent = dateLabel;
        let badgeHtml = '';
        if (dayInfo.fully_booked)           badgeHtml = '<span style="background:#fdecea;color:#991b1b;padding:4px 12px;border-radius:20px;font-weight:800;font-size:0.75rem;">Fully Booked</span>';
        else if (dayInfo.bookings.length > 0) badgeHtml = '<span style="background:#fef9c3;color:#78350f;padding:4px 12px;border-radius:20px;font-weight:800;font-size:0.75rem;">Partially Available</span>';
        else                                badgeHtml = '<span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:20px;font-weight:800;font-size:0.75rem;">Fully Available</span>';
        fscDetailBadge.innerHTML = badgeHtml;
        const slotDefs = [
            {key:'morning_available',   label:'\uD83C\uDF05 Morning (8AM\u201312PM)'},
            {key:'afternoon_available', label:'\u2600\uFE0F Afternoon (12PM\u20135PM)'},
            {key:'overnight_available', label:'\uD83C\uDF19 Overnight'},
        ];
        let slotsHtml = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">';
        slotDefs.forEach(s => {
            const avail = dayInfo[s.key];
            const bg    = avail ? '#d1fae5' : '#fdecea';
            const col   = avail ? '#065f46' : '#991b1b';
            const icon  = avail ? '✓' : '✗';
            slotsHtml += `<span style="background:${bg};color:${col};padding:5px 12px;border-radius:20px;font-weight:700;font-size:0.75rem;">${icon} ${s.label}</span>`;
        });
        slotsHtml += '</div>';
        fscDetailSlots.innerHTML = slotsHtml;
        if (dayInfo.bookings.length === 0) {
            fscDetailBk.innerHTML = '<div class="text-muted" style="font-size:0.82rem;"><i class="fas fa-calendar-check me-1" style="color:#28a745;"></i>No bookings on this date.</div>';
        } else {
            let bkHtml = `<div style="font-size:0.8rem;font-weight:700;color:#4a5568;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Active Bookings</div>`;
            dayInfo.bookings.forEach(bk => {
                const statusColor = bk.status === 'confirmed' ? '#1B7D3A' : '#e65100';
                bkHtml += `
                    <div class="fsc-booking-row">
                        <i class="fas fa-user-circle" style="color:#64748b;font-size:1.2rem;flex-shrink:0;"></i>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;color:#1e293b;">${bk.guest_name}</div>
                            <div class="text-muted" style="font-size:0.75rem;">${bk.slot_label} · Booking #${bk.id}</div>
                        </div>
                        <span style="background:${bk.status==='confirmed'?'#e8f5e9':'#fff8e1'};color:${statusColor};padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:800;white-space:nowrap;">
                            ${bk.status.charAt(0).toUpperCase()+bk.status.slice(1)}
                        </span>
                    </div>`;
            });
            fscDetailBk.innerHTML = bkHtml;
        }
        fscDayDetail.classList.add('show');
        fscDayDetail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}());
</script>
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