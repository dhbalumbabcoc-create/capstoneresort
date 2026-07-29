<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('frontdesk');

$user = get_user_info($_SESSION['user_id'], $conn);

// Get today's date
$today = date('Y-m-d');

// Today's bookings
$today_bookings_query = "SELECT COUNT(*) as count FROM bookings WHERE DATE(created_at) = '$today'";
$today_bookings_result = $conn->query($today_bookings_query);
$today_bookings = $today_bookings_result->fetch_assoc()['count'];

// Pending online bookings
$pending_query = "SELECT COUNT(*) as count FROM bookings WHERE booking_type = 'online' AND status = 'pending'";
$pending_result = $conn->query($pending_query);
$pending_bookings = $pending_result->fetch_assoc()['count'];

// Today's check-ins
$checkin_query = "SELECT COUNT(*) as count FROM bookings WHERE DATE(check_in_date) = '$today' AND status IN ('pending', 'confirmed')";
$checkin_result = $conn->query($checkin_query);
$today_checkins = $checkin_result->fetch_assoc()['count'];

// Today's check-outs
$checkout_query = "SELECT COUNT(*) as count FROM bookings WHERE DATE(check_out_date) = '$today' AND status IN ('pending', 'confirmed')";
$checkout_result = $conn->query($checkout_query);
$today_checkouts = $checkout_result->fetch_assoc()['count'];

// Available facilities
$available_query = "SELECT COUNT(*) as count FROM facilities WHERE status = 'available'";
$available_result = $conn->query($available_query);
$available_facilities = $available_result->fetch_assoc()['count'];

// Occupied facilities
$occupied_query = "SELECT COUNT(DISTINCT facility_id) as count FROM bookings WHERE status IN ('pending', 'confirmed') AND check_out_date >= '$today'";
$occupied_result = $conn->query($occupied_query);
$occupied_facilities = $occupied_result->fetch_assoc()['count'];

// Today's revenue
$revenue_query = "SELECT SUM(total_price) as revenue FROM bookings WHERE DATE(created_at) = '$today' AND status IN ('completed', 'pending', 'confirmed')";
$revenue_result = $conn->query($revenue_query);
$today_revenue = $revenue_result->fetch_assoc()['revenue'] ?: 0;

// Recent bookings (last 10) — group multi-facility bookings by guest transaction
$recent_bookings_sql = "
    SELECT 
        GROUP_CONCAT(DISTINCT b.id ORDER BY b.id ASC SEPARATOR ', ') AS id,
        b.guest_name,
        b.guest_email,
        b.booking_type,
        GROUP_CONCAT(DISTINCT COALESCE(f.name, 'N/A') ORDER BY b.id ASC SEPARATOR ', ') AS facility_name,
        b.check_in_date,
        b.check_out_date,
        SUM(b.total_price) AS total_price,
        COALESCE(SUM(pay.total_paid), 0) AS total_paid,
        b.status,
        b.created_at
    FROM bookings b
    LEFT JOIN facilities f ON b.facility_id = f.id
    LEFT JOIN (
        SELECT booking_id, SUM(amount_paid) AS total_paid
        FROM payments
        WHERE status = 'completed'
        GROUP BY booking_id
    ) pay ON b.id = pay.booking_id
    GROUP BY b.guest_name, COALESCE(b.guest_email, ''), b.check_in_date, b.check_out_date, b.created_at, b.booking_type, b.status
    ORDER BY b.created_at DESC
    LIMIT 10
";
$recent_bookings_result = $conn->query($recent_bookings_sql);
$payment_counts = ['all' => 0, 'paid' => 0, 'unpaid' => 0, 'pending' => 0];
$recent_bookings_data = [];

if ($recent_bookings_result && $recent_bookings_result->num_rows > 0) {
    while ($bk = $recent_bookings_result->fetch_assoc()) {
        $bk_paid   = floatval($bk['total_paid']);
        $bk_total  = floatval($bk['total_price']);
        $b_type    = strtolower($bk['booking_type'] ?? '');

        // For walk-in bookings that are confirmed/completed and have no payments recorded, assume fully paid
        if (($b_type === 'walkin' || $b_type === 'walk-in') && $bk_paid == 0 && in_array($bk['status'], ['confirmed', 'completed'])) {
            $bk_paid = $bk_total;
        }

        $bk_balance = max(0.0, $bk_total - $bk_paid);
        $bk['calc_paid']    = $bk_paid;
        $bk['calc_balance'] = $bk_balance;

        // Determine payment category
        if ($bk['status'] === 'pending') {
            $pay_cat = 'pending';
        } elseif ($bk_balance <= 0) {
            $pay_cat = 'paid';
        } else {
            $pay_cat = 'unpaid';
        }

        $bk['pay_cat'] = $pay_cat;
        $payment_counts['all']++;
        $payment_counts[$pay_cat]++;
        $recent_bookings_data[] = $bk;
    }
}

// Today's check-in list — group multi-facility bookings
$checkin_list_query = "SELECT 
                           GROUP_CONCAT(DISTINCT b.id ORDER BY b.id ASC SEPARATOR ', ') AS id,
                           b.guest_name,
                           b.status,
                           GROUP_CONCAT(DISTINCT f.name ORDER BY f.name ASC SEPARATOR ', ') AS facility_name
                       FROM bookings b
                       JOIN facilities f ON b.facility_id = f.id
                       LEFT JOIN areas a ON b.area_id = a.id
                       WHERE DATE(b.check_in_date) = '$today' AND b.status IN ('pending', 'confirmed')
                       GROUP BY b.guest_name, COALESCE(b.guest_email, ''), b.check_in_date, b.check_out_date, b.created_at, b.booking_type, b.status
                       ORDER BY b.created_at DESC";
$checkin_list_result = $conn->query($checkin_list_query);

// Today's check-out list — group multi-facility bookings
$checkout_list_query = "SELECT 
                            GROUP_CONCAT(DISTINCT b.id ORDER BY b.id ASC SEPARATOR ', ') AS id,
                            b.guest_name,
                            b.status,
                            GROUP_CONCAT(DISTINCT f.name ORDER BY f.name ASC SEPARATOR ', ') AS facility_name
                        FROM bookings b
                        JOIN facilities f ON b.facility_id = f.id
                        LEFT JOIN areas a ON b.area_id = a.id
                        WHERE DATE(b.check_out_date) = '$today' AND b.status IN ('pending', 'confirmed')
                        GROUP BY b.guest_name, COALESCE(b.guest_email, ''), b.check_in_date, b.check_out_date, b.created_at, b.booking_type, b.status
                        ORDER BY b.created_at DESC";
$checkout_list_result = $conn->query($checkout_list_query);
$checkout_list_result = $conn->query($checkout_list_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frontdesk Dashboard - Daily Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .content { padding: 0 !important; min-width: 0; overflow-x: hidden; }
        .dash-topbar { background:#fff; padding:16px 32px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e8ecf0; position:sticky; top:0; z-index:50; }
        .dash-topbar-title { font-size:1.25rem; font-weight:800; color:#1a1a1a; }
        .dash-topbar-date  { font-size:.85rem; color:#888; }
        .dash-topbar-badge { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border-radius:20px; padding:5px 14px; font-size:.78rem; font-weight:700; }
        .dash-body { padding:20px 24px; }
        .kpi-card { background:#fff; border-radius:16px; padding:18px 16px; box-shadow:0 2px 12px rgba(0,0,0,.06); display:flex; align-items:center; gap:14px; transition:transform .25s,box-shadow .25s; border:none; margin-bottom:0; }
        .kpi-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.1); }
        .kpi-icon { width:50px; height:50px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.3rem; }
        .kpi-icon.green  { background:#e8f5e9; color:#1B7D3A; }
        .kpi-icon.blue   { background:#e3f2fd; color:#1565c0; }
        .kpi-icon.orange { background:#fff3e0; color:#e65100; }
        .kpi-icon.yellow { background:#fffde7; color:#f9a825; }
        .kpi-icon.red    { background:#fdecea; color:#c62828; }
        .kpi-icon.teal   { background:#e0f2f1; color:#00695c; }
        .kpi-icon.purple { background:#f3e5f5; color:#6a1b9a; }
        .kpi-num { font-size:1.6rem; font-weight:900; color:#1a1a1a; line-height:1; }
        .kpi-lbl { font-size:.78rem; color:#888; margin-top:3px; }
        .section-hdr { margin-bottom:14px; }
        .section-hdr h5 { font-weight:800; color:#1a1a1a; margin:0; }
        .section-hdr p  { color:#888; font-size:.85rem; margin:2px 0 0; }
        .table-card { background:#fff; border-radius:16px; padding:16px 18px; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .table-card .table { table-layout: auto; width: 100%; margin-bottom: 0; }
        .table-card .table thead th { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; font-size:.7rem; text-transform:uppercase; letter-spacing:.5px; border:none; padding:7px 6px; white-space:nowrap; }
        .table-card .table tbody td { padding:6px 6px; font-size:.78rem; vertical-align:middle; border-color:#f5f5f5; }
        .table-card .table tbody tr:hover { background:#f8fffe; }
        .badge-confirmed { background:#e8f5e9; color:#1B7D3A; padding:4px 10px; border-radius:20px; font-size:.75rem; font-weight:700; display:inline-block; }
        .badge-pending   { background:#fff8e1; color:#e65100; padding:4px 10px; border-radius:20px; font-size:.75rem; font-weight:700; display:inline-block; }
        .badge-cancelled { background:#fdecea; color:#c62828; padding:4px 10px; border-radius:20px; font-size:.75rem; font-weight:700; display:inline-block; }
        .badge-completed { background:#e8f5e9; color:#1B7D3A; padding:4px 10px; border-radius:20px; font-size:.75rem; font-weight:700; display:inline-block; }
        .badge-declined  { background:#fdecea; color:#c62828; padding:4px 10px; border-radius:20px; font-size:.75rem; font-weight:700; display:inline-block; }
        .pending-alert { background:linear-gradient(135deg,#fff8e1,#fffde7); border:1.5px solid #ffe082; border-radius:14px; padding:14px 20px; display:flex; align-items:center; gap:12px; margin-bottom:20px; }
        .pending-alert i { font-size:1.3rem; color:#f9a825; }
        
        /* New Availability Modal Styles */
        #facility-type-tabs .nav-link {
            color: #4a5568;
            background: #e2e8f0;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        #facility-type-tabs .nav-link.active {
            background: #1B7D3A !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(27, 125, 58, 0.25);
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
        .avail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        .avail-card.status-available {
            border-left: 5px solid #28a745;
        }
        .avail-card.status-occupied {
            border-left: 5px solid #dc3545;
            background: #fffcfc;
        }
        .avail-card.status-maintenance {
            border-left: 5px solid #ffc107;
            background: #fffdf8;
        }
        .avail-card.status-unavailable {
            border-left: 5px solid #6c757d;
        }
        .avail-badge-available {
            background: #d1fae5;
            color: #065f46;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
        }
        .avail-badge-occupied {
            background: #fdecea;
            color: #c62828;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
        }
        .avail-badge-maintenance {
            background: #fef9c3;
            color: #854d0e;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
        }
        .avail-badge-unavailable {
            background: #f3f4f6;
            color: #374151;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
        }
        .schedule-list-item {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 6px;
        }

        /* Facility Schedule Calendar Modal */
        .fsc-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-top: 8px;
        }
        .fsc-day-header {
            text-align: center;
            font-size: 0.7rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            padding: 4px 0;
            letter-spacing: 0.5px;
        }
        .fsc-day {
            border-radius: 10px;
            padding: 6px 4px;
            text-align: center;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            border: 1.5px solid transparent;
            position: relative;
            min-height: 54px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 2px;
        }
        .fsc-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .fsc-day.fsc-empty {
            background: transparent;
            border-color: transparent;
            pointer-events: none;
        }
        .fsc-day.fsc-available {
            background: #d1fae5;
            border-color: #6ee7b7;
            color: #065f46;
        }
        .fsc-day.fsc-available:hover {
            background: #a7f3d0;
            border-color: #34d399;
        }
        .fsc-day.fsc-partial {
            background: #fef9c3;
            border-color: #fde68a;
            color: #78350f;
        }
        .fsc-day.fsc-partial:hover {
            background: #fde68a;
        }
        .fsc-day.fsc-booked {
            background: #fdecea;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .fsc-day.fsc-booked:hover {
            background: #fca5a5;
        }
        .fsc-day.fsc-today-marker {
            box-shadow: 0 0 0 2px #1B7D3A;
        }
        .fsc-day .fsc-date-num {
            font-size: 0.85rem;
            font-weight: 900;
            line-height: 1;
        }
        .fsc-day .fsc-slots {
            display: flex;
            flex-direction: column;
            gap: 2px;
            width: 100%;
        }
        .fsc-slot-dot {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 1px 4px;
            border-radius: 4px;
            white-space: nowrap;
            width: 100%;
        }
        .fsc-slot-dot.avail  { background: rgba(6,95,70,0.15); color: #065f46; }
        .fsc-slot-dot.booked { background: rgba(153,27,27,0.15); color: #991b1b; }
        .fsc-legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 600;
            color: #4a5568;
        }
        .fsc-legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 5px;
        }
        .fsc-month-hdr {
            font-size: 1rem;
            font-weight: 800;
            color: #1e293b;
            margin: 18px 0 6px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e2e8f0;
        }
        .fsc-day-detail {
            background: white;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 14px 18px;
            margin-top: 16px;
            display: none;
        }
        .fsc-day-detail.show { display: block; }
        .fsc-booking-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 6px;
            font-size: 0.8rem;
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
                    <div class="dash-topbar-title"><i class="fas fa-calendar-day me-2" style="color:#1B7D3A;"></i>Daily Operations</div>
                    <div class="dash-topbar-date"><?php echo date('l, F j, Y'); ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="dash-topbar-badge"><i class="fas fa-concierge-bell me-1"></i>Front Desk</span>
                    <span style="font-size:.85rem;color:#888;"><?php echo isset($user) ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : ''; ?></span>
                </div>
            </div>

            <div class="dash-body">
                <?php display_messages(); ?>

                <!-- Pending alert -->
                <?php if ($pending_bookings > 0): ?>
                <div class="pending-alert">
                    <i class="fas fa-bell"></i>
                    <div>
                        <strong><?= $pending_bookings ?> pending online booking<?= $pending_bookings>1?'s':'' ?></strong> waiting for approval.
                        <a href="online_bookings.php" style="color:#e65100;font-weight:700;margin-left:8px;">Review Now &rarr;</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- KPI Cards Row 1 -->
                <div class="row g-3 mb-3">
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#dashboardDetailsModal" data-kpi-type="today_bookings" title="Click to view today's bookings details">
                            <div class="kpi-icon green"><i class="fas fa-calendar-check"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $today_bookings ?>">0</div>
                                <div class="kpi-lbl">Today's Bookings</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#dashboardDetailsModal" data-kpi-type="pending_online" title="Click to view pending online bookings details">
                            <div class="kpi-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $pending_bookings ?>">0</div>
                                <div class="kpi-lbl">Pending Online</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#dashboardDetailsModal" data-kpi-type="today_checkins" title="Click to view today's check-ins details">
                            <div class="kpi-icon blue"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $today_checkins ?>">0</div>
                                <div class="kpi-lbl">Today's Check-ins</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#dashboardDetailsModal" data-kpi-type="today_checkouts" title="Click to view today's check-outs details">
                            <div class="kpi-icon orange"><i class="fas fa-sign-out-alt"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $today_checkouts ?>">0</div>
                                <div class="kpi-lbl">Today's Check-outs</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards Row 2 -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-4">
                        <div class="kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#facilitiesAvailabilityModal" title="Click to view availability & schedules">
                            <div class="kpi-icon teal"><i class="fas fa-door-open"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $available_facilities ?>">0</div>
                                <div class="kpi-lbl">Available Facilities</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4">
                        <div class="kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#dashboardDetailsModal" data-kpi-type="occupied_facilities" title="Click to view occupied facilities details">
                            <div class="kpi-icon red"><i class="fas fa-door-closed"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $occupied_facilities ?>">0</div>
                                <div class="kpi-lbl">Occupied Facilities</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4">
                        <div class="kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#dashboardDetailsModal" data-kpi-type="today_revenue" title="Click to view today's revenue details">
                            <div class="kpi-icon purple"><i class="fas fa-peso-sign"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= intval($today_revenue) ?>" data-prefix="₱">₱0</div>
                                <div class="kpi-lbl">Today's Revenue</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Check-in / Check-out Tables -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="section-hdr"><h5><i class="fas fa-sign-in-alt me-2" style="color:#1565c0;"></i>Today's Check-ins</h5></div>
                        <div class="table-card">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>#</th><th>Guest</th><th>Facility</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php if ($checkin_list_result->num_rows > 0): while ($ci = $checkin_list_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong style="color:#1B7D3A;">#<?= htmlspecialchars(str_replace(', ', ', #', $ci['id'])) ?></strong></td>
                                        <td><?= htmlspecialchars($ci['guest_name']) ?></td>
                                        <td><?= htmlspecialchars($ci['facility_name']) ?></td>
                                        <td><span class="badge-<?= $ci['status'] ?>"><?= ucfirst($ci['status']) ?></span></td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No check-ins today</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="section-hdr"><h5><i class="fas fa-sign-out-alt me-2" style="color:#e65100;"></i>Today's Check-outs</h5></div>
                        <div class="table-card">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>#</th><th>Guest</th><th>Facility</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php if ($checkout_list_result->num_rows > 0): while ($co = $checkout_list_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong style="color:#1B7D3A;">#<?= htmlspecialchars(str_replace(', ', ', #', $co['id'])) ?></strong></td>
                                        <td><?= htmlspecialchars($co['guest_name']) ?></td>
                                        <td><?= htmlspecialchars($co['facility_name']) ?></td>
                                        <td><span class="badge-<?= $co['status'] ?>"><?= ucfirst($co['status']) ?></span></td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No check-outs today</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="section-hdr d-flex align-items-start justify-content-between flex-wrap gap-2">
                    <div>
                        <h5><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Recent Bookings</h5>
                        <p>Your last 10 processed bookings</p>
                    </div>
                    <!-- Payment Filter Pills -->
                    <div class="d-flex align-items-center gap-2 flex-wrap" id="payment-filter-bar">
                        <span style="font-size:.78rem;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.4px;"><i class="fas fa-filter me-1"></i>Filter:</span>
                        <button class="btn btn-sm payment-filter-btn active" data-filter="all" id="filter-all"
                            style="border-radius:20px;font-size:.76rem;font-weight:700;padding:4px 14px;border:1.5px solid #1B7D3A;background:#1B7D3A;color:#fff;transition:all .2s;">
                            <i class="fas fa-list me-1"></i>All (<?= $payment_counts['all'] ?>)
                        </button>
                        <button class="btn btn-sm payment-filter-btn" data-filter="paid" id="filter-paid"
                            style="border-radius:20px;font-size:.76rem;font-weight:700;padding:4px 14px;border:1.5px solid #1B7D3A;background:#fff;color:#1B7D3A;transition:all .2s;">
                            <i class="fas fa-check-circle me-1"></i>Paid (<?= $payment_counts['paid'] ?>)
                        </button>
                        <button class="btn btn-sm payment-filter-btn" data-filter="unpaid" id="filter-unpaid"
                            style="border-radius:20px;font-size:.76rem;font-weight:700;padding:4px 14px;border:1.5px solid #c62828;background:#fff;color:#c62828;transition:all .2s;">
                            <i class="fas fa-exclamation-circle me-1"></i>Unpaid (<?= $payment_counts['unpaid'] ?>)
                        </button>
                        <button class="btn btn-sm payment-filter-btn" data-filter="pending" id="filter-pending"
                            style="border-radius:20px;font-size:.76rem;font-weight:700;padding:4px 14px;border:1.5px solid #e65100;background:#fff;color:#e65100;transition:all .2s;">
                            <i class="fas fa-hourglass-half me-1"></i>Pending (<?= $payment_counts['pending'] ?>)
                        </button>
                    </div>
                </div>
                <!-- Filter Summary Badge -->
                <div id="filter-summary" style="display:none;margin-bottom:10px;">
                    <span id="filter-summary-badge" style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:20px;padding:5px 16px;font-size:.78rem;font-weight:700;color:#4a5568;">
                        <i class="fas fa-info-circle me-1 text-muted"></i><span id="filter-summary-text"></span>
                    </span>
                </div>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="recent-bookings-table">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Guest</th>
                                    <th style="width:55px" class="text-nowrap">Mode</th>
                                    <th>Facility</th>
                                    <th class="text-nowrap">Check-in</th>
                                    <th class="text-nowrap">Check-out</th>
                                    <th class="text-nowrap">Amount</th>
                                    <th class="text-nowrap">Payment</th>
                                    <th class="text-nowrap">Status</th>
                                    <th class="text-nowrap">Date</th>
                                </tr>
                            </thead>
                            <tbody id="recent-bookings-tbody">
                            <?php if (!empty($recent_bookings_data)): foreach ($recent_bookings_data as $bk):
                                $bk_paid   = $bk['calc_paid'];
                                $bk_balance= $bk['calc_balance'];
                                $bk_total  = floatval($bk['total_price']);
                                $pay_cat   = $bk['pay_cat'];
                            ?>
                            <tr class="booking-row" data-payment-cat="<?= $pay_cat ?>">
                                <td><strong style="color:#1B7D3A;">#<?= htmlspecialchars(str_replace(', ', ', #', $bk['id'])) ?></strong></td>
                                <td><div style="max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($bk['guest_name']) ?>"><?= htmlspecialchars($bk['guest_name']) ?></div></td>
                                <td class="text-nowrap"><?= ucfirst(str_replace('_',' ',$bk['booking_type']??'N/A')) ?></td>
                                <td><div style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($bk['facility_name']) ?>"><?= htmlspecialchars($bk['facility_name']) ?></div></td>
                                <td class="text-nowrap"><?= date('M d, Y', strtotime($bk['check_in_date'])) ?></td>
                                <td class="text-nowrap"><?= date('M d, Y', strtotime($bk['check_out_date'])) ?></td>
                                <td class="text-nowrap"><strong>&#8369;<?= number_format($bk_total,2) ?></strong></td>
                                <td class="text-nowrap">
                                    <?php if ($pay_cat === 'paid'): ?>
                                        <span style="background:#e8f5e9;color:#1B7D3A;padding:3px 8px;border-radius:20px;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                            <i class="fas fa-check-circle"></i> Paid
                                        </span>
                                    <?php elseif ($pay_cat === 'pending'): ?>
                                        <span style="background:#fff8e1;color:#e65100;padding:3px 8px;border-radius:20px;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                            <i class="fas fa-hourglass-half"></i> Pending
                                        </span>
                                    <?php else: ?>
                                        <span style="background:#fdecea;color:#c62828;padding:3px 8px;border-radius:20px;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;" title="Balance: &#8369;<?= number_format($bk_balance,2) ?>">
                                            <i class="fas fa-exclamation-circle"></i> Unpaid
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php
                                    $st = $bk['status'];
                                    $sc = $st==='completed'?'completed':($st==='confirmed'?'confirmed':($st==='pending'?'pending':($st==='declined'?'declined':'cancelled')));
                                    echo "<span class='badge-$sc'>".ucfirst($st)."</span>";
                                    ?>
                                </td>
                                <td class="text-nowrap" style="font-size:.75rem;color:#888;"><?= date('M d, h:i A', strtotime($bk['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr id="no-bookings-row"><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No bookings yet</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <!-- No results for filter -->
                        <div id="no-filter-results" style="display:none;" class="text-center text-muted py-4">
                            <i class="fas fa-search-minus fa-2x mb-2" style="opacity:.35;"></i>
                            <p style="font-weight:600;margin:0;" id="no-filter-results-text">No bookings match this filter.</p>
                        </div>
                    </div>
                </div>

            </div><!-- /.dash-body -->
        </div><!-- /.content -->
    </div>

    <!-- Facility Availability & Schedule Modal -->
    <div class="modal fade" id="facilitiesAvailabilityModal" tabindex="-1" aria-labelledby="facilitiesAvailabilityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: linear-gradient(135deg, #1B7D3A, #27A457); color: white; border-top-left-radius: 16px; border-top-right-radius: 16px; padding: 20px 24px;">
                    <h5 class="modal-title" id="facilitiesAvailabilityModalLabel" style="font-weight: 800; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-door-open"></i> Facility Availability & Schedule Viewer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background: #f8fafc; padding: 24px;">
                    <!-- Filter Bar -->
                    <div class="row g-3 align-items-end mb-4 p-3" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                        <div class="col-md-5">
                            <label class="form-label" style="font-weight: 700; color: #4a5568; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Check-in Date</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f1f5f9; border-right: none; color: #64748b;"><i class="fas fa-calendar-alt"></i></span>
                                <input type="date" id="avail-filter-date" class="form-control" style="border-left: none; font-weight: 600; color: #1e293b;" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" style="font-weight: 700; color: #4a5568; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Time Slot / Mode</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f1f5f9; border-right: none; color: #64748b;"><i class="fas fa-clock"></i></span>
                                <select id="avail-filter-slot" class="form-select" style="border-left: none; font-weight: 600; color: #1e293b;">
                                    <option value="8am-12pm">Daytour: Morning (8:00 AM - 12:00 PM)</option>
                                    <option value="12pm-5pm">Daytour: Afternoon (12:00 PM - 5:00 PM)</option>
                                    <option value="full_day" selected>Daytour: Full Day (8:00 AM - 5:00 PM)</option>
                                    <option value="overnight">Overnight Booking</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="btn-refresh-avail" class="btn w-100" style="background: #1B7D3A; color: white; font-weight: 700; border-radius: 6px; padding: 8px 16px; border: none; transition: background 0.2s;">
                                <i class="fas fa-sync-alt me-1"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Loader -->
                    <div id="avail-loader" class="text-center py-5" style="display: none;">
                        <div class="spinner-border" role="status" style="color: #1B7D3A; width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted" style="font-weight: 600;">Checking live availability...</p>
                    </div>

                    <!-- Content Grid -->
                    <div id="avail-content" style="display: none;">
                        <!-- Nav tabs for Facility Types -->
                        <ul class="nav nav-pills mb-4 d-flex justify-content-center gap-2" id="facility-type-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-all-btn" data-bs-toggle="pill" data-bs-target="#tab-all" type="button" role="tab" style="font-weight: 700; border-radius: 30px; padding: 8px 20px;">All Types</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-room-btn" data-bs-toggle="pill" data-bs-target="#tab-room" type="button" role="tab" style="font-weight: 700; border-radius: 30px; padding: 8px 20px;"><i class="fas fa-bed me-1"></i> Rooms</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-cottage-btn" data-bs-toggle="pill" data-bs-target="#tab-cottage" type="button" role="tab" style="font-weight: 700; border-radius: 30px; padding: 8px 20px;"><i class="fas fa-home me-1"></i> Cottages</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-hall-btn" data-bs-toggle="pill" data-bs-target="#tab-hall" type="button" role="tab" style="font-weight: 700; border-radius: 30px; padding: 8px 20px;"><i class="fas fa-building me-1"></i> Halls</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="facility-tab-content">
                            <!-- All Tab -->
                            <div class="tab-pane fade show active" id="tab-all" role="tabpanel">
                                <div class="row g-3" id="avail-list-all"></div>
                            </div>
                            <!-- Room Tab -->
                            <div class="tab-pane fade" id="tab-room" role="tabpanel">
                                <div class="row g-3" id="avail-list-room"></div>
                            </div>
                            <!-- Cottage Tab -->
                            <div class="tab-pane fade" id="tab-cottage" role="tabpanel">
                                <div class="row g-3" id="avail-list-cottage"></div>
                            </div>
                            <!-- Hall Tab -->
                            <div class="tab-pane fade" id="tab-hall" role="tabpanel">
                                <div class="row g-3" id="avail-list-hall"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-weight: 700; border-radius: 6px; padding: 8px 20px;">Close</button>
                    <a href="walkin_booking.php" class="btn text-white" style="background: linear-gradient(135deg, #1B7D3A, #27A457); font-weight: 700; border-radius: 6px; padding: 8px 20px; border: none;">
                        <i class="fas fa-plus me-1"></i> Create Booking
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Facility Schedule Calendar Modal -->
    <div class="modal fade" id="facilityScheduleModal" tabindex="-1" aria-labelledby="facilityScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background:linear-gradient(135deg,#1B7D3A,#27A457); color:white; border-top-left-radius:16px; border-top-right-radius:16px; padding:20px 24px;">
                    <div>
                        <h5 class="modal-title mb-0" id="facilityScheduleModalLabel" style="font-weight:800;">
                            <i class="fas fa-calendar-alt me-2"></i><span id="fsc-title">Facility Schedule</span>
                        </h5>
                        <div id="fsc-subtitle" style="font-size:0.8rem; opacity:0.85; margin-top:3px;"></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background:#f8fafc; padding:24px;">
                    <!-- Legend -->
                    <div class="fsc-legend mb-3 p-3" style="background:white; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                        <span><span class="fsc-legend-dot" style="background:#d1fae5; border:1.5px solid #6ee7b7;"></span>Available</span>
                        <span><span class="fsc-legend-dot" style="background:#fef9c3; border:1.5px solid #fde68a;"></span>Partially Booked</span>
                        <span><span class="fsc-legend-dot" style="background:#fdecea; border:1.5px solid #fca5a5;"></span>Fully Booked</span>
                        <span style="margin-left:auto; font-size:0.72rem; color:#94a3b8;"><i class="fas fa-mouse-pointer me-1"></i>Click a date for details</span>
                    </div>

                    <!-- Loader -->
                    <div id="fsc-loader" class="text-center py-5">
                        <div class="spinner-border" role="status" style="color:#1B7D3A; width:3rem; height:3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted" style="font-weight:600;">Loading schedule...</p>
                    </div>

                    <!-- Calendar content -->
                    <div id="fsc-content" style="display:none;">
                        <div id="fsc-calendar-wrap"></div>
                        <!-- Day detail panel -->
                        <div class="fsc-day-detail" id="fsc-day-detail">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong id="fsc-detail-date" style="color:#1e293b; font-size:0.95rem;"></strong>
                                <span id="fsc-detail-badge"></span>
                            </div>
                            <div id="fsc-detail-slots" class="mb-2"></div>
                            <div id="fsc-detail-bookings"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:16px 24px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-weight:700; border-radius:6px; padding:8px 20px;">Close</button>
                </div>
            </div>
        </div>
    </div>

        <!-- Dashboard Details Modal (bookings list details) -->
    <div class="modal fade" id="dashboardDetailsModal" tabindex="-1" aria-labelledby="dashboardDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" id="details-modal-header" style="background: linear-gradient(135deg, #1B7D3A, #27A457); color: white; border-top-left-radius: 16px; border-top-right-radius: 16px; padding: 20px 24px;">
                    <h5 class="modal-title" id="dashboardDetailsModalLabel" style="font-weight: 800; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list-alt"></i> KPI Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background: #f8fafc; padding: 24px;">
                    <!-- Loading Indicator -->
                    <div id="details-loader" class="text-center py-5">
                        <div class="spinner-border" role="status" style="color: #1B7D3A; width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted" style="font-weight: 600;">Fetching list details...</p>
                    </div>

                    <!-- Details Table Container -->
                    <div id="details-content" style="display: none;">
                        <!-- Occupied facilities card grid (shown only for occupied_facilities type) -->
                        <div id="occupied-cards-grid" class="row g-3" style="display: none;"></div>

                        <!-- Generic booking table -->
                        <div id="details-table-wrap" class="table-responsive" style="background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.04); padding: 12px;">
                            <table class="table table-hover align-middle mb-0" id="details-table">
                                <thead>
                                    <tr style="background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; color: #4a5568; letter-spacing: 0.5px;">
                                        <th>#</th>
                                        <th>Guest</th>
                                        <th>Facility</th>
                                        <th>Mode / Slot</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th id="th-amount-header">Amount</th>
                                        <th>Status</th>
                                        <th id="th-date-header">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="details-tbody" style="font-size: 0.85rem; color: #1e293b;">
                                    <!-- Dynamic rows go here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-weight: 700; border-radius: 6px; padding: 8px 20px;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animated counters
        document.querySelectorAll('.kpi-num[data-count]').forEach((el, i) => {
            const target = parseFloat(el.getAttribute('data-count'));
            const prefix = el.getAttribute('data-prefix') || '';
            const isFloat = target % 1 !== 0;
            setTimeout(() => {
                const start = performance.now();
                const update = (now) => {
                    const p = Math.min((now - start) / 900, 1);
                    const eased = 1 - Math.pow(1 - p, 3);
                    const val = eased * target;
                    el.textContent = prefix + (isFloat ? val.toFixed(1) : Math.round(val).toLocaleString());
                    if (p < 1) requestAnimationFrame(update);
                };
                requestAnimationFrame(update);
            }, i * 80);
        });

        // KPI card entrance animation
        document.querySelectorAll('.kpi-card').forEach((card, i) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'opacity .4s ease, transform .4s ease';
                card.style.opacity = '1';
                card.style.transform = 'none';
            }, 80 + i * 60);
        });

        // Dynamic availability checker script
        const modalEl = document.getElementById('facilitiesAvailabilityModal');
        const dateInput = document.getElementById('avail-filter-date');
        const slotSelect = document.getElementById('avail-filter-slot');
        const btnRefresh = document.getElementById('btn-refresh-avail');
        const loader = document.getElementById('avail-loader');
        const content = document.getElementById('avail-content');
        
        const listAll = document.getElementById('avail-list-all');
        const listRoom = document.getElementById('avail-list-room');
        const listCottage = document.getElementById('avail-list-cottage');
        const listHall = document.getElementById('avail-list-hall');

        function fetchAvailability() {
            const date = dateInput.value;
            const slot = slotSelect.value;
            
            loader.style.display = 'block';
            content.style.display = 'none';
            
            fetch(`get_facilities_availability.php?date=${date}&slot=${slot}`)
                .then(res => res.json())
                .then(data => {
                    loader.style.display = 'none';
                    content.style.display = 'block';
                    
                    if (!data.success) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    renderFacilities(data.facilities);
                })
                .catch(err => {
                    loader.style.display = 'none';
                    alert('Failed to load facility availability.');
                    console.error(err);
                });
        }

        function renderFacilities(facilities) {
            listAll.innerHTML = '';
            listRoom.innerHTML = '';
            listCottage.innerHTML = '';
            listHall.innerHTML = '';
            
            if (facilities.length === 0) {
                const noData = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3"></i><p>No facilities registered.</p></div>`;
                listAll.innerHTML = noData;
                return;
            }
            
            let roomsCount = 0;
            let cottagesCount = 0;
            let hallsCount = 0;
            
            facilities.forEach(fac => {
                const typeLower = fac.type.toLowerCase();
                const isAvail = fac.is_available;
                
                let statusClass = 'status-available';
                let badgeHtml = '<span class="avail-badge-available"><i class="fas fa-check-circle me-1"></i>Available</span>';
                
                if (!isAvail) {
                    if (fac.status === 'maintenance') {
                        statusClass = 'status-maintenance';
                        badgeHtml = '<span class="avail-badge-maintenance"><i class="fas fa-tools me-1"></i>Maintenance</span>';
                    } else if (fac.status === 'unavailable') {
                        statusClass = 'status-unavailable';
                        badgeHtml = '<span class="avail-badge-unavailable"><i class="fas fa-times-circle me-1"></i>Unavailable</span>';
                    } else {
                        statusClass = 'status-occupied';
                        badgeHtml = `<span class="avail-badge-occupied" title="${fac.conflict_reason}"><i class="fas fa-door-closed me-1"></i>Occupied</span>`;
                    }
                }
                
                const priceFormatted = parseFloat(fac.price).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                const schedCnt = fac.schedules ? fac.schedules.length : 0;
                let scheduleHtml = `
                    <div class="mt-3">
                        <button class="btn btn-sm w-100 py-1 fsc-open-btn" type="button"
                            onclick="openFacilitySchedule(${fac.id})"
                            style="font-size:0.75rem; font-weight:700; background: linear-gradient(135deg,#1B7D3A,#27A457); color:white; border:none; border-radius:8px;">
                            <i class="fas fa-calendar-alt me-1"></i> View Schedule${schedCnt > 0 ? ' (' + schedCnt + ' bookings)' : ''}
                        </button>
                    </div>`;

                const cardHtml = `
                    <div class="col-md-6 col-lg-4">
                        <div class="avail-card ${statusClass} p-3 d-flex flex-column justify-content-between h-100">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 style="font-weight: 800; color: #1e293b; margin: 0; font-size: 1rem;">${fac.name}</h6>
                                    ${badgeHtml}
                                </div>
                                
                                <div class="text-muted mb-2" style="font-size: 0.78rem; font-weight: 600;">
                                    <i class="fas fa-map-marker-alt me-1" style="color: #1B7D3A;"></i>Area: ${fac.area_name}
                                </div>
                                
                                <div class="d-flex justify-content-between text-muted mb-2" style="font-size: 0.8rem;">
                                    <span><i class="fas fa-users me-1"></i>Capacity: <strong>${fac.capacity} pax</strong></span>
                                    <span><i class="fas fa-tag me-1"></i>Price: <strong style="color: #1B7D3A;">₱${priceFormatted}</strong></span>
                                </div>

                                ${!isAvail && fac.conflict_reason ? `
                                    <div class="alert alert-danger py-2 px-3 mb-2" style="font-size: 0.75rem; font-weight: 600; border-radius: 8px;">
                                        <i class="fas fa-exclamation-circle me-1"></i>${fac.conflict_reason}
                                    </div>
                                ` : ''}
                            </div>
                            
                            ${scheduleHtml}
                        </div>
                    </div>
                `;
                
                listAll.insertAdjacentHTML('beforeend', cardHtml);
                
                if (typeLower === 'room' || typeLower === 'rooms') {
                    listRoom.insertAdjacentHTML('beforeend', cardHtml);
                    roomsCount++;
                } else if (typeLower === 'cottage' || typeLower === 'cottages') {
                    listCottage.insertAdjacentHTML('beforeend', cardHtml);
                    cottagesCount++;
                } else if (typeLower === 'function_hall' || typeLower === 'hall' || typeLower === 'halls' || typeLower === 'function hall') {
                    listHall.insertAdjacentHTML('beforeend', cardHtml);
                    hallsCount++;
                }
            });
            
            if (roomsCount === 0) {
                listRoom.innerHTML = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-bed fa-3x mb-3"></i><p>No rooms found.</p></div>`;
            }
            if (cottagesCount === 0) {
                listCottage.innerHTML = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-home fa-3x mb-3"></i><p>No cottages found.</p></div>`;
            }
            if (hallsCount === 0) {
                listHall.innerHTML = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-building fa-3x mb-3"></i><p>No function halls found.</p></div>`;
            }
        }
        
        modalEl.addEventListener('show.bs.modal', fetchAvailability);
        dateInput.addEventListener('change', fetchAvailability);
        slotSelect.addEventListener('change', fetchAvailability);
        btnRefresh.addEventListener('click', fetchAvailability);

        // Generic KPI details loader
        const detailsModal = document.getElementById('dashboardDetailsModal');
        const detailsLoader = document.getElementById('details-loader');
        const detailsContent = document.getElementById('details-content');
        const detailsTbody = document.getElementById('details-tbody');
        const detailsTitle = document.getElementById('dashboardDetailsModalLabel');
        const detailsHeader = document.getElementById('details-modal-header');

        const kpiTitles = {
            'today_bookings': "Today's Bookings List",
            'pending_online': 'Pending Online Bookings',
            'today_checkins': "Today's Check-in List",
            'today_checkouts': "Today's Check-out List",
            'occupied_facilities': 'Occupied Facilities Schedule',
            'today_revenue': "Today's Revenue Transactions"
        };

        const kpiIcons = {
            'today_bookings': 'fa-calendar-check',
            'pending_online': 'fa-hourglass-half',
            'today_checkins': 'fa-sign-in-alt',
            'today_checkouts': 'fa-sign-out-alt',
            'occupied_facilities': 'fa-door-closed',
            'today_revenue': 'fa-peso-sign'
        };

        const kpiColors = {
            'today_bookings': 'linear-gradient(135deg, #1B7D3A, #27A457)',
            'pending_online': 'linear-gradient(135deg, #f9a825, #fbc02d)',
            'today_checkins': 'linear-gradient(135deg, #1565c0, #1e88e5)',
            'today_checkouts': 'linear-gradient(135deg, #e65100, #f57c00)',
            'occupied_facilities': 'linear-gradient(135deg, #c62828, #e53935)',
            'today_revenue': 'linear-gradient(135deg, #6a1b9a, #8e24aa)'
        };

        detailsModal.addEventListener('show.bs.modal', function(event) {
            const trigger = event.relatedTarget;
            const kpiType = trigger.getAttribute('data-kpi-type');
            
            detailsHeader.style.background = kpiColors[kpiType] || 'linear-gradient(135deg, #1B7D3A, #27A457)';
            detailsTitle.innerHTML = `<i class="fas ${kpiIcons[kpiType] || 'fa-list'} me-2"></i>${kpiTitles[kpiType] || 'KPI Details'}`;
            
            detailsLoader.style.display = 'block';
            detailsContent.style.display = 'none';
            detailsTbody.innerHTML = '';
            
            const thAmount = document.getElementById('th-amount-header');
            const thDate = document.getElementById('th-date-header');
            if (kpiType === 'today_revenue') {
                thAmount.textContent = 'Revenue / Price';
                thDate.textContent = 'Processed Date';
            } else {
                thAmount.textContent = 'Total Price';
                thDate.textContent = 'Booked Date';
            }

            fetch(`get_dashboard_details.php?type=${kpiType}`)
                .then(res => res.json())
                .then(data => {
                    detailsLoader.style.display = 'none';
                    detailsContent.style.display = 'block';
                    
                    if (!data.success) {
                        detailsTbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>Error: ${data.error}</td></tr>`;
                        return;
                    }
                    
                    renderDetailsTable(data.bookings, kpiType);
                })
                .catch(err => {
                    detailsLoader.style.display = 'none';
                    detailsTbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>Failed to fetch dashboard details.</td></tr>`;
                    console.error(err);
                });
        });

        function groupBookings(bookingsList) {
            const groups = {};
            bookingsList.forEach(b => {
                // Group by guest details and dates
                const key = `${b.guest_name}|${b.check_in_date}|${b.check_out_date}|${b.created_at_fmt}`;
                if (!groups[key]) {
                    groups[key] = {
                        id: b.booking_id || b.id,
                        booking_id: b.booking_id || b.id,
                        guest_name: b.guest_name,
                        guest_phone: b.guest_phone,
                        guest_email: b.guest_email,
                        check_in_date: b.check_in_date,
                        check_out_date: b.check_out_date,
                        check_in_fmt: b.check_in_fmt,
                        check_out_fmt: b.check_out_fmt,
                        mode: b.mode,
                        booking_type: b.booking_type,
                        status: b.status,
                        created_at: b.created_at,
                        created_at_fmt: b.created_at_fmt,
                        notes: b.notes || '',
                        days_remaining: b.days_remaining,
                        days_until_checkin: b.days_until_checkin,
                        is_upcoming: b.is_upcoming,
                        time_slot: b.time_slot,
                        num_guests: 0,
                        num_adults: 0,
                        num_discounted: 0,
                        num_children: 0,
                        total_price: 0,
                        total_paid: 0,
                        remaining_balance: 0,
                        facilities: []
                    };
                }

                // Add facility info
                groups[key].facilities.push({
                    booking_id: b.booking_id || b.id,
                    facility_id: b.facility_id,
                    facility_name: b.facility_name,
                    facility_type: b.facility_type,
                    facility_price: b.facility_price,
                    area_name: b.area_name,
                    capacity: b.capacity,
                    amenities: b.amenities,
                    image_path: b.image_path,
                    status: b.status
                });

                // Sum totals
                groups[key].total_price += parseFloat(b.total_price || 0);
                groups[key].total_paid += parseFloat(b.total_paid || 0);

                // Sum guest counts
                groups[key].num_guests += parseInt(b.num_guests || 0);
                groups[key].num_adults += parseInt(b.num_adults || 0);
                groups[key].num_discounted += parseInt(b.num_discounted || 0);
                groups[key].num_children += parseInt(b.num_children || 0);

                // If this booking has notes, append if different
                if (b.notes && b.notes.trim() && groups[key].notes.indexOf(b.notes.trim()) === -1) {
                    if (groups[key].notes) {
                        groups[key].notes += ' | ' + b.notes.trim();
                    } else {
                        groups[key].notes = b.notes.trim();
                    }
                }
            });

            // Populate compatibility fields for each group
            return Object.values(groups).map(g => {
                g.remaining_balance = Math.max(0, g.total_price - g.total_paid);
                
                if (g.facilities.length > 1) {
                    g.facility_name = g.facilities.map(f => f.facility_name).filter(Boolean).join(', ');
                    g.facility_type = 'multiple';
                    
                    const areaNames = [...new Set(g.facilities.map(f => f.area_name).filter(Boolean))];
                    if (areaNames.length > 1) {
                        g.area_name = 'Multiple Areas';
                    } else if (areaNames.length === 1) {
                        g.area_name = areaNames[0];
                    } else {
                        g.area_name = 'N/A';
                    }
                } else if (g.facilities.length === 1) {
                    const f = g.facilities[0];
                    g.facility_name = f.facility_name;
                    g.facility_type = f.facility_type;
                    g.area_name = f.area_name || 'N/A';
                }
                return g;
            });
        }

        function renderDetailsTable(bookings, kpiType) {
            const cardsGrid   = document.getElementById('occupied-cards-grid');
            const tableWrap   = document.getElementById('details-table-wrap');

            const groupedBookings = groupBookings(bookings);

            // Store bookings in a global lookup map so openBookingDetail() can look them up by id
            window._allBookings = {};
            if (groupedBookings && groupedBookings.length > 0) {
                groupedBookings.forEach(b => {
                    window._allBookings[b.booking_id || b.id] = b;
                });
            }

            // --- OCCUPIED FACILITIES: special card layout ---
            if (kpiType === 'occupied_facilities') {
                cardsGrid.style.display = '';
                tableWrap.style.display  = 'none';

                if (groupedBookings.length === 0) {
                    cardsGrid.innerHTML = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-door-open fa-3x mb-3" style="color:#28a745;opacity:.4"></i><p style="font-weight:600;">No facilities are currently occupied.</p></div>`;
                    return;
                }

                const typeIcon = { room:'fa-bed', cottage:'fa-home', function_hall:'fa-building', multiple:'fa-door-closed' };
                const typeLabel = { room:'Room', cottage:'Cottage', function_hall:'Function Hall', multiple:'Multiple Facilities' };

                // Store bookings in a global map so openBookingDetail() can look them up by id
                window._occupiedBookings = {};

                let html = '';
                groupedBookings.forEach(b => {
                    window._occupiedBookings[b.booking_id || b.id] = b;
                    const icon = typeIcon[b.facility_type] || 'fa-door-closed';
                    const typeLbl = typeLabel[b.facility_type] || b.facility_type;
                    const priceFormatted = b.total_price.toLocaleString('en-PH', {minimumFractionDigits:2});
                    const modeSlot = b.mode === 'overnight' ? '<span style="background:#e0f2f1;color:#00695c;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-moon me-1"></i>Overnight</span>'
                                   : `<span style="background:#fff3e0;color:#e65100;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-sun me-1"></i>Daytour (${(b.time_slot||'full_day').replace('_',' ')})</span>`;
                    const statusClass = b.status === 'confirmed' ? 'confirmed' : 'pending';

                    const isUpcoming = b.is_upcoming;
                    const occupantLabel = isUpcoming ? 'Upcoming Guest' : 'Current Occupant';
                    const occupantColor = isUpcoming ? '#1b7d3a' : '#c62828';
                    const occupantBg = isUpcoming ? '#e8f5e9' : '#fdecea';
                    const cardBorderColor = isUpcoming ? '#28a745' : '#dc3545';
                    const headerGradient = isUpcoming ? 'linear-gradient(135deg,#1b7d3a,#27a457)' : 'linear-gradient(135deg,#c62828,#e53935)';

                    // Days remaining label
                    let daysLabel = '';
                    if (isUpcoming) {
                        const daysUntil = b.days_until_checkin;
                        if (daysUntil === 1) {
                            daysLabel = `<span style="background:#fff8e1;color:#f9a825;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-calendar-day me-1"></i>Starts tomorrow</span>`;
                        } else {
                            daysLabel = `<span style="background:#e8f5e9;color:#1b7d3a;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-calendar-alt me-1"></i>Starts in ${daysUntil} days</span>`;
                        }
                    } else {
                        if (b.days_remaining === 0) {
                            daysLabel = `<span style="background:#fdecea;color:#c62828;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-sign-out-alt me-1"></i>Checking out today</span>`;
                        } else if (b.days_remaining === 1) {
                            daysLabel = `<span style="background:#fff8e1;color:#f9a825;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-clock me-1"></i>1 day remaining</span>`;
                        } else {
                            daysLabel = `<span style="background:#e8f5e9;color:#1B7D3A;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-hourglass-half me-1"></i>${b.days_remaining} days remaining</span>`;
                        }
                    }

                    const viewScheduleBtn = `
                    <div class="mt-3">
                      <button class="btn btn-sm w-100 py-1 fsc-open-btn" type="button"
                          onclick="openBookingDetail(${b.booking_id || b.id})"
                          style="font-size:0.75rem; font-weight:700; background: ${isUpcoming ? 'linear-gradient(135deg,#1B7D3A,#27A457)' : 'linear-gradient(135deg,#c62828,#e53935)'}; color:white; border:none; border-radius:8px;">
                          <i class="fas fa-receipt me-1"></i> View Booking
                      </button>
                    </div>`;

                    html += `
                    <div class="col-md-6 col-lg-4">
                      <div style="background:white;border-radius:14px;border:1.5px solid #e2e8f0;border-left:5px solid ${cardBorderColor};box-shadow:0 4px 16px rgba(0,0,0,.06);overflow:hidden;height:100%;">

                        <!-- Card Header: Facility name + type -->
                        <div style="background:${headerGradient};padding:14px 16px;display:flex;align-items:center;gap:10px;">
                          <div style="width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;flex-shrink:0;">
                            <i class="fas ${icon}"></i>
                          </div>
                          <div style="flex:1;min-width:0;">
                            <div style="font-weight:800;color:#fff;font-size:.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${b.facilities.map(f=>f.facility_name).join(', ')}">${b.facility_name}</div>
                            <div style="font-size:.72rem;color:rgba(255,255,255,.8);">${typeLbl} &bull; ${b.area_name || 'N/A'}</div>
                          </div>
                          <span class="badge-${statusClass}" style="flex-shrink:0;white-space:nowrap;">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span>
                        </div>

                        <!-- Card Body -->
                        <div style="padding:14px 16px;">
                          <!-- Occupant Info -->
                          <div style="background:${occupantBg};border-radius:10px;padding:10px 12px;margin-bottom:12px;">
                            <div style="font-size:.72rem;font-weight:700;color:${occupantColor};text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><i class="fas fa-user me-1"></i>${occupantLabel}</div>
                            <div style="font-weight:800;color:#1e293b;font-size:.95rem;">${b.guest_name}</div>
                            <div style="font-size:.75rem;color:#64748b;"><i class="fas fa-phone me-1"></i>${b.guest_phone || 'N/A'}</div>
                          </div>

                          <!-- Booking Info grid -->
                          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
                            <div style="background:#f8fafc;border-radius:8px;padding:8px 10px;">
                              <div style="font-size:.68rem;color:#64748b;font-weight:600;">CHECK-IN</div>
                              <div style="font-weight:700;color:#1e293b;font-size:.82rem;">${b.check_in_fmt}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:8px;padding:8px 10px;">
                              <div style="font-size:.68rem;color:#64748b;font-weight:600;">CHECK-OUT</div>
                              <div style="font-weight:700;color:#1e293b;font-size:.82rem;">${b.check_out_fmt}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:8px;padding:8px 10px;">
                              <div style="font-size:.68rem;color:#64748b;font-weight:600;">BOOKING #</div>
                              <div style="font-weight:700;color:#1B7D3A;font-size:.82rem;">#${b.id}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:8px;padding:8px 10px;">
                              <div style="font-size:.68rem;color:#64748b;font-weight:600;">TOTAL PRICE</div>
                              <div style="font-weight:700;color:#475569;font-size:.82rem;">₱${priceFormatted}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:8px;padding:8px 10px;">
                              <div style="font-size:.68rem;color:#64748b;font-weight:600;">TOTAL PAID</div>
                              <div style="font-weight:700;color:#1B7D3A;font-size:.82rem;">₱${(b.total_paid || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:8px;padding:8px 10px;">
                              <div style="font-size:.68rem;color:#64748b;font-weight:600;">REMAINING BAL</div>
                              <div style="font-weight:700;color:#c62828;font-size:.82rem;">₱${(b.remaining_balance || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                            </div>
                          </div>

                          <!-- Mode + days remaining -->
                          <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            ${modeSlot}
                            ${daysLabel}
                          </div>

                          ${viewScheduleBtn}
                        </div>
                      </div>
                    </div>`;
                });

                cardsGrid.innerHTML = html;
                return;
            }

            // --- DEFAULT: generic table for all other KPI types ---
            cardsGrid.style.display  = 'none';
            tableWrap.style.display  = '';

            if (groupedBookings.length === 0) {
                detailsTbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No records found.</td></tr>`;
                return;
            }

            let html = '';
            let totalSum = 0;

            groupedBookings.forEach(g => {
                totalSum += g.total_price;

                const statusClass = g.status === 'completed' ? 'completed' : (g.status === 'confirmed' ? 'confirmed' : (g.status === 'pending' ? 'pending' : (g.status === 'declined' ? 'declined' : 'cancelled')));
                const priceFormatted = g.total_price.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const modeSlot = g.mode === 'overnight' ? 'Overnight' : `Daytour (${(g.time_slot||'full_day').replace('_', ' ')})`;

                // Build facility tags (all facilities in this group)
                const facilityTags = g.facilities.map(f =>
                    `<span style="display:inline-block;background:#e8f5e9;color:#1B7D3A;border-radius:6px;padding:2px 8px;font-size:0.73rem;font-weight:700;margin:2px 2px 2px 0;border:1px solid #c8e6c9;">${f.facility_name}</span>`
                ).join('');

                html += `
                    <tr>
                        <td>
                            <button class="btn btn-sm btn-link p-0 fw-bold text-decoration-none" onclick="openBookingDetail(${g.id})" style="color:#1B7D3A; font-size: 0.85rem;">
                                <i class="fas fa-eye me-1" style="font-size:0.75rem;"></i>#${g.id}
                            </button>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1e293b;">${g.guest_name}</div>
                            <div class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-phone me-1"></i>${g.guest_phone || 'N/A'}</div>
                        </td>
                        <td style="max-width:180px;">
                            <div style="display:flex;flex-wrap:wrap;gap:0;">${facilityTags}</div>
                        </td>
                        <td><span class="badge bg-light text-dark" style="font-weight:600; border: 1px solid #e2e8f0;">${modeSlot}</span></td>
                        <td>${g.check_in_fmt}</td>
                        <td>${g.check_out_fmt}</td>
                        <td><strong>₱${priceFormatted}</strong></td>
                        <td><span class="badge-${statusClass}">${g.status.charAt(0).toUpperCase() + g.status.slice(1)}</span></td>
                        <td style="font-size: 0.78rem; color:#64748b;">${g.created_at_fmt}</td>
                    </tr>
                `;
            });

            if (kpiType === 'today_revenue') {
                const totalFormatted = totalSum.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                html += `
                    <tr style="background: #f8fafc; border-top: 2px solid #e2e8f0; font-size: 0.95rem;">
                        <td colspan="6" class="text-end"><strong>Total Revenue Today:</strong></td>
                        <td colspan="3"><strong style="color: #6a1b9a; font-size: 1.1rem;">₱${totalFormatted}</strong></td>
                    </tr>
                `;
            }

            detailsTbody.innerHTML = html;
        }
    });

    // ─── Payment Filter for Recent Bookings ──────────────────────────────────
    const filterBtns = document.querySelectorAll('.payment-filter-btn');
    const bookingRows = document.querySelectorAll('#recent-bookings-tbody .booking-row');
    const noFilterResults = document.getElementById('no-filter-results');
    const noFilterResultsText = document.getElementById('no-filter-results-text');
    const filterSummary = document.getElementById('filter-summary');
    const filterSummaryText = document.getElementById('filter-summary-text');

    const filterLabels = { all: 'All Bookings', paid: 'Paid', unpaid: 'Unpaid (Balance Remaining)', pending: 'Pending' };
    const filterColors = {
        all:     { bg: '#1B7D3A', color: '#fff', border: '#1B7D3A' },
        paid:    { bg: '#1B7D3A', color: '#fff', border: '#1B7D3A' },
        unpaid:  { bg: '#c62828', color: '#fff', border: '#c62828' },
        pending: { bg: '#e65100', color: '#fff', border: '#e65100' }
    };

    function applyPaymentFilter(activeFilter) {
        let visibleCount = 0;

        filterBtns.forEach(btn => {
            const f = btn.getAttribute('data-filter');
            const fc = filterColors[f];
            if (f === activeFilter) {
                btn.style.background = fc.bg;
                btn.style.color = fc.color;
                btn.style.borderColor = fc.border;
                btn.classList.add('active');
            } else {
                btn.style.background = '#fff';
                btn.style.color = fc.bg;
                btn.style.borderColor = fc.border;
                btn.classList.remove('active');
            }
        });

        bookingRows.forEach(row => {
            const cat = row.getAttribute('data-payment-cat');
            const show = (activeFilter === 'all' || cat === activeFilter);
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (activeFilter !== 'all') {
            filterSummary.style.display = 'block';
            filterSummaryText.textContent = `Showing ${visibleCount} "${filterLabels[activeFilter]}" booking${visibleCount !== 1 ? 's' : ''}`;
        } else {
            filterSummary.style.display = 'none';
        }

        if (visibleCount === 0 && bookingRows.length > 0) {
            noFilterResults.style.display = 'block';
            noFilterResultsText.textContent = `No "${filterLabels[activeFilter]}" bookings found in your last 10 records.`;
        } else {
            noFilterResults.style.display = 'none';
        }
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            applyPaymentFilter(this.getAttribute('data-filter'));
        });
    });


    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        const sidebarCol = document.getElementById('sidebarCol');
        const navbarBrand = document.getElementById('navbarBrand');
        if (localStorage.getItem('frontdeskSidebarCollapsed') === 'true') {
            sidebarCol.classList.add('collapsed');
            sidebarToggle.classList.add('collapsed');
            if (navbarBrand) navbarBrand.classList.add('collapsed');
        }
        sidebarToggle.addEventListener('click', function() {
            const c = sidebarCol.classList.toggle('collapsed');
            this.classList.toggle('collapsed');
            if (navbarBrand) navbarBrand.classList.toggle('collapsed');
            localStorage.setItem('frontdeskSidebarCollapsed', c);
        });
    }

    // Auto-refresh every 3 minutes
    setTimeout(() => location.reload(), 180000);

    // ─── Facility Schedule Calendar ───────────────────────────────────────────
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

    let fscBsModal = null;
    if (fscModal) {
        fscBsModal = new bootstrap.Modal(fscModal);
    }

    function openFacilitySchedule(facilityId) {
        if (!fscBsModal) return;

        // Reset state
        fscLoader.style.display = 'block';
        fscContent.style.display = 'none';
        fscTitle.textContent = 'Loading...';
        fscSubtitle.textContent = '';
        fscCalWrap.innerHTML = '';
        fscDayDetail.classList.remove('show');

        fscBsModal.show();

        fetch(`get_facility_schedule.php?facility_id=${facilityId}&days=365`)
            .then(r => r.json())
            .then(data => {
                fscLoader.style.display = 'none';
                if (!data.success) {
                    fscCalWrap.innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
                    fscContent.style.display = 'block';
                    return;
                }

                const fac = data.facility;
                const priceStr = parseFloat(fac.price).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2});
                fscTitle.textContent = fac.name + ' — Schedule';
                fscSubtitle.textContent = `Capacity: ${fac.capacity} pax  ·  ₱${priceStr}  ·  Area: ${fac.area_name}`;

                renderFscCalendar(data.days, data.today);
                fscContent.style.display = 'block';
            })
            .catch(err => {
                fscLoader.style.display = 'none';
                fscCalWrap.innerHTML = `<div class="alert alert-danger">Failed to load schedule.</div>`;
                fscContent.style.display = 'block';
                console.error(err);
            });
    }

    function renderFscCalendar(days, today) {
        fscCalWrap.innerHTML = '';
        fscDayDetail.classList.remove('show');

        if (!days || days.length === 0) return;

        // Group days by month
        const months = {};
        days.forEach(d => {
            const dt  = new Date(d.date + 'T00:00:00');
            const key = dt.getFullYear() + '-' + dt.getMonth();
            if (!months[key]) months[key] = { year: dt.getFullYear(), month: dt.getMonth(), days: [] };
            months[key].days.push(d);
        });

        const DAY_NAMES = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

        Object.values(months).forEach(mObj => {
            const mLabel = new Date(mObj.year, mObj.month, 1)
                .toLocaleDateString('en-US', {month:'long', year:'numeric'});

            const hdr = document.createElement('div');
            hdr.className = 'fsc-month-hdr';
            hdr.textContent = mLabel;
            fscCalWrap.appendChild(hdr);

            const grid = document.createElement('div');
            grid.className = 'fsc-calendar';

            // Day-of-week headers
            DAY_NAMES.forEach(n => {
                const dh = document.createElement('div');
                dh.className = 'fsc-day-header';
                dh.textContent = n;
                grid.appendChild(dh);
            });

            // Always anchor grid to the 1st of the month
            const firstOfMonth    = new Date(mObj.year, mObj.month, 1);
            const firstOfMonthDow = firstOfMonth.getDay(); // 0=Sun
            const firstDataDay    = parseInt(mObj.days[0].date.split('-')[2]);

            // Blank spacer cells before day-1
            for (let e = 0; e < firstOfMonthDow; e++) {
                const em = document.createElement('div');
                em.className = 'fsc-day fsc-empty';
                grid.appendChild(em);
            }

            // Greyed-out past-day cells for days before the data starts
            for (let d = 1; d < firstDataDay; d++) {
                const pastCell = document.createElement('div');
                pastCell.className = 'fsc-day fsc-empty';
                pastCell.style.cssText = 'opacity:0.3; cursor:default;';
                const pNum = document.createElement('div');
                pNum.className = 'fsc-date-num';
                pNum.textContent = d;
                pastCell.appendChild(pNum);
                grid.appendChild(pastCell);
            }


            // Day cells
            mObj.days.forEach(dayInfo => {
                const dt = new Date(dayInfo.date + 'T00:00:00');
                const dayNum = dt.getDate();
                const isToday = dayInfo.date === today;

                // Determine class
                let cls = 'fsc-available';
                if (dayInfo.fully_booked) {
                    cls = 'fsc-booked';
                } else if (dayInfo.bookings.length > 0) {
                    cls = 'fsc-partial';
                }

                const cell = document.createElement('div');
                cell.className = `fsc-day ${cls}${isToday ? ' fsc-today-marker' : ''}`;
                cell.setAttribute('data-date', dayInfo.date);

                // Date number
                const numEl = document.createElement('div');
                numEl.className = 'fsc-date-num';
                numEl.textContent = dayNum;
                cell.appendChild(numEl);

                // Slot dots (only if not fully booked)
                if (!dayInfo.fully_booked) {
                    const slotsEl = document.createElement('div');
                    slotsEl.className = 'fsc-slots';

                    const slots = [
                        { key: 'morning_available',   label: 'AM' },
                        { key: 'afternoon_available',  label: 'PM' },
                        { key: 'overnight_available',  label: 'ON' },
                    ];
                    slots.forEach(s => {
                        const dot = document.createElement('div');
                        dot.className = `fsc-slot-dot ${dayInfo[s.key] ? 'avail' : 'booked'}`;
                        dot.textContent = s.label;
                        slotsEl.appendChild(dot);
                    });
                    cell.appendChild(slotsEl);
                } else {
                    const fb = document.createElement('div');
                    fb.style.cssText = 'font-size:0.6rem; font-weight:800; margin-top:2px;';
                    fb.textContent = 'BOOKED';
                    cell.appendChild(fb);
                }

                // Click → detail panel
                cell.addEventListener('click', () => showDayDetail(dayInfo));
                grid.appendChild(cell);
            });

            fscCalWrap.appendChild(grid);
        });
    }

    function showDayDetail(dayInfo) {
        const dt = new Date(dayInfo.date + 'T00:00:00');
        const dateLabel = dt.toLocaleDateString('en-US', {weekday:'long', month:'long', day:'numeric', year:'numeric'});
        fscDetailDate.textContent = dateLabel;

        // Badge
        let badgeHtml = '';
        if (dayInfo.fully_booked) {
            badgeHtml = '<span style="background:#fdecea;color:#991b1b;padding:4px 12px;border-radius:20px;font-weight:800;font-size:0.75rem;">Fully Booked</span>';
        } else if (dayInfo.bookings.length > 0) {
            badgeHtml = '<span style="background:#fef9c3;color:#78350f;padding:4px 12px;border-radius:20px;font-weight:800;font-size:0.75rem;">Partially Available</span>';
        } else {
            badgeHtml = '<span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:20px;font-weight:800;font-size:0.75rem;">Fully Available</span>';
        }
        fscDetailBadge.innerHTML = badgeHtml;

        // Slot availability
        const slotDefs = [
            { key: 'morning_available',   label: '🌅 Morning (8AM–12PM)' },
            { key: 'afternoon_available', label: '☀️ Afternoon (12PM–5PM)' },
            { key: 'overnight_available', label: '🌙 Overnight' },
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

        // Bookings list
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

    // ─── Booking Detail Modal (Occupied Facilities) ──────────────────────────
    // Lazily initialized so the modal HTML (placed after this script block) is in the DOM first.
    let bdBsModal = null;
    function getBdModal() {
        if (!bdBsModal) {
            const el = document.getElementById('bookingDetailModal');
            if (el) bdBsModal = new bootstrap.Modal(el);
        }
        return bdBsModal;
    }

    function openBookingDetail(bookingId) {
        const b = (window._occupiedBookings || {})[bookingId] || (window._allBookings || {})[bookingId];
        if (!b) return;
        const modal = getBdModal();
        if (!modal) return;

        const isUpcoming    = b.is_upcoming;
        const headerGrad    = isUpcoming ? 'linear-gradient(135deg,#1b7d3a,#27a457)' : 'linear-gradient(135deg,#c62828,#e53935)';
        const accentColor   = isUpcoming ? '#1b7d3a' : '#c62828';
        const occupantLabel = isUpcoming ? 'Upcoming Guest' : 'Current Occupant';
        const occupantBg    = isUpcoming ? '#e8f5e9' : '#fdecea';
        const typeIcon      = { room:'fa-bed', cottage:'fa-home', function_hall:'fa-building', multiple:'fa-door-closed' };
        const typeLabel     = { room:'Room', cottage:'Cottage', function_hall:'Function Hall', multiple:'Multiple Facilities' };
        const icon          = typeIcon[b.facility_type] || 'fa-door-closed';
        const typeLbl       = typeLabel[b.facility_type] || b.facility_type;
        const priceFormatted= b.total_price.toLocaleString('en-PH', {minimumFractionDigits:2});
        const statusClass   = b.status === 'confirmed' ? 'confirmed' : 'pending';
        const modeSlot      = b.mode === 'overnight'
            ? '<span style="background:#e0f2f1;color:#00695c;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-moon me-1"></i>Overnight</span>'
            : `<span style="background:#fff3e0;color:#e65100;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-sun me-1"></i>Daytour (${(b.time_slot||'full_day').replace('_',' ')})</span>`;

        let daysLabel = '';
        if (isUpcoming) {
            const d = b.days_until_checkin;
            daysLabel = d === 1
                ? '<span style="background:#fff8e1;color:#f9a825;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-calendar-day me-1"></i>Starts tomorrow</span>'
                : `<span style="background:#e8f5e9;color:#1b7d3a;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-calendar-alt me-1"></i>Starts in ${d} days</span>`;
        } else {
            if (b.days_remaining === 0)      daysLabel = '<span style="background:#fdecea;color:#c62828;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-sign-out-alt me-1"></i>Checking out today</span>';
            else if (b.days_remaining === 1) daysLabel = '<span style="background:#fff8e1;color:#f9a825;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-clock me-1"></i>1 day remaining</span>';
            else                             daysLabel = `<span style="background:#e8f5e9;color:#1B7D3A;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-hourglass-half me-1"></i>${b.days_remaining} days remaining</span>`;
        }

        const notesHtml = (b.notes && b.notes.trim())
            ? `<div style="background:#f8fafc;border-radius:10px;padding:12px 14px;margin-top:4px;font-size:.82rem;color:#475569;border-left:4px solid ${accentColor};"><i class="fas fa-sticky-note me-2" style="color:${accentColor};"></i>${b.notes}</div>`
            : '<div style="color:#94a3b8;font-size:.82rem;"><i class="fas fa-sticky-note me-2"></i>No notes.</div>';

        const totalPaidFormatted = (b.total_paid || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
        const remainingFormatted = (b.remaining_balance || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});

        // --- Facilities Booked section ---
        const facCount = b.facilities ? b.facilities.length : 1;
        document.getElementById('bd-facilities-label').innerHTML = `<i class="fas fa-door-open me-1"></i>Facilities Booked (${facCount})`;

        const facilityTypeIcon  = { room:'fa-bed', cottage:'fa-home', function_hall:'fa-building' };
        const facilityTypeLabel = { room:'Room', cottage:'Cottage', function_hall:'Function Hall' };
        
        let facilitiesHtml = '';
        if (b.facilities && b.facilities.length > 0) {
            facilitiesHtml += `<div style="display: flex; flex-direction: column; gap: 8px;">`;
            b.facilities.forEach(fac => {
                const facIcon  = facilityTypeIcon[fac.facility_type]  || 'fa-door-closed';
                const facLabel = facilityTypeLabel[fac.facility_type] || fac.facility_type;
                const facStatusClass = fac.status === 'confirmed' ? 'confirmed' : 'pending';
                facilitiesHtml += `
                    <div style="display:flex;align-items:center;gap:12px;background:#f8fafc;border-radius:10px;padding:10px 14px;">
                        <div style="width:38px;height:38px;background:${accentColor};border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;">
                            <i class="fas ${facIcon}"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:800;color:#1e293b;font-size:.93rem;">${fac.facility_name}</div>
                            <div style="font-size:.73rem;color:#64748b;margin-top:2px;">${facLabel} &bull; ${fac.area_name || 'N/A'}</div>
                        </div>
                        <span class="badge-${facStatusClass}" style="flex-shrink:0;font-size:.7rem;">${fac.status.charAt(0).toUpperCase()+fac.status.slice(1)}</span>
                    </div>`;
            });
            facilitiesHtml += `</div>`;
        } else {
            const facIcon  = facilityTypeIcon[b.facility_type]  || 'fa-door-closed';
            const facLabel = facilityTypeLabel[b.facility_type] || b.facility_type;
            facilitiesHtml = `
                <div style="display:flex;align-items:center;gap:12px;background:#f8fafc;border-radius:10px;padding:10px 14px;">
                    <div style="width:38px;height:38px;background:${accentColor};border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;">
                        <i class="fas ${facIcon}"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:800;color:#1e293b;font-size:.93rem;">${b.facility_name}</div>
                        <div style="font-size:.73rem;color:#64748b;margin-top:2px;">${facLabel} &bull; ${b.area_name || 'N/A'}</div>
                    </div>
                    <span class="badge-${statusClass}" style="flex-shrink:0;font-size:.7rem;">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span>
                </div>`;
        }
        document.getElementById('bd-facilities-booked').innerHTML = facilitiesHtml;

        // --- Guests breakdown section ---
        const numAdults     = parseInt(b.num_adults     || 0);
        const numDiscounted = parseInt(b.num_discounted || 0);
        const numChildren   = parseInt(b.num_children   || 0);
        const totalGuests   = parseInt(b.num_guests     || 0) || (numAdults + numDiscounted + numChildren);

        const guestPillStyle = (bg, color) =>
            `background:${bg};color:${color};border-radius:20px;padding:5px 14px;font-size:.75rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;`;

        let guestBreakdown = '';
        if (numAdults > 0)
            guestBreakdown += `<span style="${guestPillStyle('#e8f5e9','#1b7d3a')}"><i class="fas fa-user"></i> ${numAdults} Adult${numAdults>1?'s':''}</span>`;
        if (numDiscounted > 0)
            guestBreakdown += `<span style="${guestPillStyle('#e3f2fd','#1565c0')}"><i class="fas fa-id-card"></i> ${numDiscounted} Senior/PWD</span>`;
        if (numChildren > 0)
            guestBreakdown += `<span style="${guestPillStyle('#fff8e1','#f9a825')}"><i class="fas fa-child"></i> ${numChildren} Child${numChildren>1?'ren':''}</span>`;
        if (!guestBreakdown)
            guestBreakdown = `<span style="color:#94a3b8;font-size:.82rem;"><i class="fas fa-users me-1"></i>No breakdown available.</span>`;

        const guestsHtml = `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:38px;height:38px;background:#1b7d3a;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div style="font-weight:800;color:#1e293b;font-size:1.1rem;">${totalGuests} <span style="font-size:.8rem;font-weight:600;color:#64748b;">Total Guest${totalGuests!==1?'s':''}</span></div>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">${guestBreakdown}</div>`;
        document.getElementById('bd-guests-info').innerHTML = guestsHtml;

        document.getElementById('bd-header').style.background = headerGrad;
        document.getElementById('bd-facility-icon').className  = `fas ${icon}`;
        document.getElementById('bd-facility-name').textContent = b.facility_name;
        document.getElementById('bd-facility-sub').textContent  = `${typeLbl} · ${b.area_name || 'N/A'}`;
        document.getElementById('bd-status-badge').className    = `badge-${statusClass}`;
        document.getElementById('bd-status-badge').textContent  = b.status.charAt(0).toUpperCase() + b.status.slice(1);
        document.getElementById('bd-occupant-label').style.color = accentColor;
        document.getElementById('bd-occupant-bg').style.background = occupantBg;
        document.getElementById('bd-occupant-label').innerHTML = `<i class="fas fa-user me-1"></i>${occupantLabel}`;
        document.getElementById('bd-guest-name').textContent   = b.guest_name;
        document.getElementById('bd-guest-phone').textContent  = b.guest_phone || 'N/A';
        document.getElementById('bd-guest-email').textContent  = b.guest_email || 'N/A';
        document.getElementById('bd-booking-id').textContent   = '#' + (b.booking_id || b.id);
        document.getElementById('bd-checkin').textContent      = b.check_in_fmt;
        document.getElementById('bd-checkout').textContent     = b.check_out_fmt;
        
        document.getElementById('bd-total-price').textContent       = '₱' + priceFormatted;
        document.getElementById('bd-total-paid').textContent        = '₱' + totalPaidFormatted;
        document.getElementById('bd-remaining-balance').textContent = '₱' + remainingFormatted;
        
        document.getElementById('bd-mode-slot').innerHTML      = modeSlot;
        document.getElementById('bd-days-label').innerHTML     = daysLabel;
        document.getElementById('bd-notes').innerHTML          = notesHtml;

        modal.show();
    }
    </script>

<!-- ═══════════════════════════════════════════════════════════════
     Booking Detail Modal  (shown when clicking "View Booking")
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="bookingDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
    <div class="modal-content" style="border-radius:18px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.18);">

      <!-- Coloured header -->
      <div id="bd-header" style="padding:20px 22px;display:flex;align-items:center;gap:14px;">
        <div style="width:46px;height:46px;background:rgba(255,255,255,.22);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;flex-shrink:0;">
          <i id="bd-facility-icon" class="fas fa-door-closed"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <div id="bd-facility-name" style="font-weight:900;color:#fff;font-size:1.05rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
          <div id="bd-facility-sub"  style="font-size:.75rem;color:rgba(255,255,255,.78);margin-top:2px;"></div>
        </div>
        <span id="bd-status-badge" class="badge-confirmed" style="flex-shrink:0;"></span>
        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Body -->
      <div class="modal-body" style="padding:20px 22px;background:#fff;">

        <!-- Occupant block -->
        <div id="bd-occupant-bg" style="border-radius:12px;padding:12px 14px;margin-bottom:16px;">
          <div id="bd-occupant-label" style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;"></div>
          <div id="bd-guest-name"  style="font-weight:900;font-size:1.05rem;color:#1e293b;"></div>
          <div style="margin-top:4px;display:flex;gap:16px;flex-wrap:wrap;">
            <span style="font-size:.78rem;color:#64748b;"><i class="fas fa-phone me-1"></i><span id="bd-guest-phone"></span></span>
            <span style="font-size:.78rem;color:#64748b;"><i class="fas fa-envelope me-1"></i><span id="bd-guest-email"></span></span>
          </div>
        </div>

        <!-- Booking meta grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
          <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Booking #</div>
            <div id="bd-booking-id" style="font-weight:800;color:#1b7d3a;font-size:.95rem;"></div>
          </div>
          <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Total Price</div>
            <div id="bd-total-price" style="font-weight:800;color:#475569;font-size:.95rem;"></div>
          </div>
          <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Total Paid</div>
            <div id="bd-total-paid" style="font-weight:800;color:#1b7d3a;font-size:.95rem;"></div>
          </div>
          <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Remaining Balance</div>
            <div id="bd-remaining-balance" style="font-weight:800;color:#c62828;font-size:.95rem;"></div>
          </div>
          <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Check-in</div>
            <div id="bd-checkin" style="font-weight:800;color:#1e293b;font-size:.88rem;"></div>
          </div>
          <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Check-out</div>
            <div id="bd-checkout" style="font-weight:800;color:#1e293b;font-size:.88rem;"></div>
          </div>
        </div>

        <!-- Facilities Booked -->
        <div id="bd-facilities-label" style="font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><i class="fas fa-door-open me-1"></i>Facility Booked</div>
        <div id="bd-facilities-booked" style="margin-bottom:16px;"></div>

        <!-- Guests Breakdown -->
        <div style="font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><i class="fas fa-users me-1"></i>Number of Guests</div>
        <div id="bd-guests-info" style="background:#f8fafc;border-radius:10px;padding:12px 14px;margin-bottom:16px;"></div>

        <!-- Mode + days status badges -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
          <div id="bd-mode-slot"></div>
          <div id="bd-days-label"></div>
        </div>

        <!-- Notes -->
        <div style="font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><i class="fas fa-sticky-note me-1"></i>Notes</div>
        <div id="bd-notes"></div>
      </div>

      <!-- Footer -->
      <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 22px;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-weight:700;border-radius:8px;padding:8px 22px;">Close</button>
      </div>

    </div>
  </div>
</div>

</body>
</html>