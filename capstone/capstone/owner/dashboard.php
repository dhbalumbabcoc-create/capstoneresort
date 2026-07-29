<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);

// Date range filter
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'monthly';
$start_date = '';
$end_date = date('Y-m-d');

switch ($filter_type) {
    case 'daily':
        $start_date = date('Y-m-d');
        $end_date   = date('Y-m-d');
        break;
    case 'monthly':
        $start_date = date('Y-m-01');
        $end_date   = date('Y-m-t');
        break;
    case 'yearly':
        $start_date = date('Y-01-01');
        $end_date   = date('Y-12-31');
        break;
    default:
        $filter_type = 'monthly';
        $start_date  = date('Y-m-01');
        $end_date    = date('Y-m-t');
}

// Build date WHERE clause for queries
$date_filter = '';
if ($start_date) {
    $date_filter = " AND DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'";
}

// Total bookings
$total_bookings_query = "SELECT COUNT(*) as count FROM bookings b WHERE 1=1" . $date_filter;
$total_bookings_result = $conn->query($total_bookings_query);
$total_bookings = $total_bookings_result->fetch_assoc()['count'];

// Total revenue
$revenue_query = "SELECT SUM(b.total_price) as total_revenue FROM bookings b WHERE b.status IN ('completed', 'pending', 'confirmed')" . $date_filter;
$revenue_result = $conn->query($revenue_query);
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'] ?: 0;

// Occupancy rate
$facilities_result = $conn->query("SELECT COUNT(*) as count FROM facilities WHERE status = 'available'");
$total_facilities = $facilities_result->fetch_assoc()['count'] ?: 1;

$occupied_result = $conn->query("SELECT COUNT(DISTINCT f.id) as count FROM bookings b JOIN facilities f ON b.facility_id = f.id WHERE f.status != 'archived' AND b.status IN ('pending', 'confirmed') AND b.check_out_date >= CURDATE()");
$occupied_facilities = $occupied_result->fetch_assoc()['count'] ?: 0;
$occupancy_rate = ($total_facilities > 0) ? round(($occupied_facilities / $total_facilities) * 100, 1) : 0;

// Total staff
$staff_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'frontdesk', 'supervisor')");
$total_staff = $staff_result->fetch_assoc()['count'];

// Total facilities (excluding archived)
$total_facilities_count_result = $conn->query("SELECT COUNT(*) as count FROM facilities WHERE status != 'archived'");
$total_facilities_count = $total_facilities_count_result->fetch_assoc()['count'];

// Total areas — only count active ones
$total_areas_result = $conn->query("SELECT COUNT(*) as count FROM areas WHERE status = 'active'");
$total_areas = $total_areas_result->fetch_assoc()['count'];

// --- Modal Data Queries ---
// 1. Revenue Modal Data
$revenue_modal_query = "SELECT b.id, b.guest_name, b.check_in_date, b.check_out_date, b.mode, b.status, b.total_price, b.created_at,
                               f.name as facility_name, a.name as area_name,
                               u.first_name, u.last_name, u.role as staff_role
                        FROM bookings b
                        LEFT JOIN facilities f ON b.facility_id = f.id
                        LEFT JOIN areas a ON b.area_id = a.id
                        LEFT JOIN users u ON b.created_by = u.id
                        WHERE b.status IN ('completed', 'pending', 'confirmed')" . $date_filter . "
                        ORDER BY b.created_at DESC";
$revenue_modal_res = $conn->query($revenue_modal_query);

// 2. Bookings Modal Data
$bookings_modal_query = "SELECT b.id, b.guest_name, b.num_guests, b.check_in_date, b.check_out_date, b.mode, b.status, b.total_price, b.created_at,
                                f.name as facility_name, a.name as area_name,
                                u.first_name, u.last_name, u.role as staff_role
                         FROM bookings b
                         LEFT JOIN facilities f ON b.facility_id = f.id
                         LEFT JOIN areas a ON b.area_id = a.id
                         LEFT JOIN users u ON b.created_by = u.id
                         WHERE 1=1" . $date_filter . "
                         ORDER BY b.created_at DESC";
$bookings_modal_res = $conn->query($bookings_modal_query);

// Status counts for Bookings Modal
$bk_counts_query = "SELECT b.status, COUNT(*) as cnt FROM bookings b WHERE 1=1" . $date_filter . " GROUP BY b.status";
$bk_counts_res = $conn->query($bk_counts_query);
$bk_status_map = ['confirmed' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0];
if ($bk_counts_res) {
    while ($row = $bk_counts_res->fetch_assoc()) {
        $st = strtolower($row['status']);
        if (isset($bk_status_map[$st])) {
            $bk_status_map[$st] = (int)$row['cnt'];
        }
    }
}

// 3. Occupancy / Facilities Status Modal Data (excluding archived)
$occupancy_modal_query = "SELECT f.*, a.name as area_name,
                                 (SELECT COUNT(*) FROM bookings b WHERE b.facility_id = f.id AND b.status IN ('pending', 'confirmed') AND b.check_out_date >= CURDATE()) as active_bks
                          FROM facilities f
                          LEFT JOIN areas a ON f.area_id = a.id
                          WHERE f.status != 'archived'
                          ORDER BY f.name";
$occupancy_modal_res = $conn->query($occupancy_modal_query);

// 4. Staff Modal Data
$staff_modal_query = "SELECT * FROM users WHERE role IN ('admin', 'frontdesk', 'supervisor', 'owner') ORDER BY role, first_name";
$staff_modal_res = $conn->query($staff_modal_query);

// 5. Facilities Modal Data (excluding archived)
$facilities_modal_query = "SELECT f.*, a.name as area_name FROM facilities f LEFT JOIN areas a ON f.area_id = a.id WHERE f.status != 'archived' ORDER BY f.name";
$facilities_modal_res = $conn->query($facilities_modal_query);

// 6. Areas Modal Data (only active facilities count)
$areas_modal_query = "SELECT a.*, (SELECT COUNT(*) FROM facilities f WHERE f.area_id = a.id AND f.status != 'archived') as facility_count FROM areas a ORDER BY a.name";
$areas_modal_res = $conn->query($areas_modal_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        /* ── Dashboard improvements ── */
        body { background: #f4f6f9; }
        .content { padding: 0 !important; }

        /* Topbar */
        .dash-topbar {
            background: #fff;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e8ecf0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .dash-topbar-title { font-size: 1.25rem; font-weight: 800; color: #1a1a1a; }
        .dash-topbar-date { font-size: .85rem; color: #888; }
        .dash-topbar-badge {
            background: linear-gradient(135deg, #1B7D3A, #27A457);
            color: #fff; border-radius: 20px; padding: 5px 14px;
            font-size: .78rem; font-weight: 700;
        }

        /* Inner content padding */
        .dash-body { padding: 28px 32px; }

        /* Stat cards */
        .kpi-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all .25s ease;
            border: 2px solid transparent;
            margin-bottom: 0;
            cursor: pointer;
            position: relative;
            user-select: none;
        }
        .kpi-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 8px 24px rgba(27,125,58,.15);
            border-color: #1B7D3A; 
        }
        .kpi-icon {
            width: 54px; height: 54px; border-radius: 14px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .kpi-icon.green  { background: #e8f5e9; color: #1B7D3A; }
        .kpi-icon.blue   { background: #e3f2fd; color: #1565c0; }
        .kpi-icon.orange { background: #fff3e0; color: #e65100; }
        .kpi-icon.purple { background: #f3e5f5; color: #6a1b9a; }
        .kpi-icon.teal   { background: #e0f2f1; color: #00695c; }
        .kpi-icon.red    { background: #fdecea; color: #c62828; }
        .kpi-num  { font-size: 1.65rem; font-weight: 900; color: #1a1a1a; line-height: 1; }
        .kpi-lbl  { font-size: .8rem; color: #888; margin-top: 3px; }
        .kpi-hint {
            font-size: .72rem;
            color: #1B7D3A;
            font-weight: 700;
            margin-top: 4px;
            display: flex;
            align-items: center;
            opacity: 0.7;
            transition: opacity .2s;
        }
        .kpi-card:hover .kpi-hint {
            opacity: 1;
            text-decoration: underline;
        }

        /* Section header */
        .section-hdr { margin-bottom: 16px; }
        .section-hdr h5 { font-weight: 800; color: #1a1a1a; margin: 0; }
        .section-hdr p  { color: #888; font-size: .85rem; margin: 2px 0 0; }

        /* Chart cards */
        .chart-card {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            height: 100%;
        }
        .chart-card h6 { font-weight: 700; color: #1a1a1a; margin-bottom: 16px; }
        .chart-wrap {
            position: relative;
            height: 260px;
            width: 100%;
        }
        .chart-badge {
            font-size: .72rem; font-weight: 700; padding: 3px 10px;
            border-radius: 20px; white-space: nowrap;
        }
        .chart-badge.green  { background: #e8f5e9; color: #1B7D3A; }
        .chart-badge.blue   { background: #e3f2fd; color: #1565c0; }
        .chart-badge.orange { background: #fff3e0; color: #e65100; }
        .chart-badge.purple { background: #f3e5f5; color: #6a1b9a; }

        /* Table card */
        .table-card {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .table-card .table thead th {
            background: linear-gradient(135deg, #1B7D3A, #27A457);
            color: #fff; font-size: .78rem; text-transform: uppercase;
            letter-spacing: .6px; border: none; padding: 12px 14px;
        }
        .table-card .table tbody td { padding: 11px 14px; font-size: .88rem; vertical-align: middle; border-color: #f5f5f5; }
        .table-card .table tbody tr:hover { background: #f8fffe; }

        /* Status badges */
        .badge-confirmed { background: #e8f5e9; color: #1B7D3A; padding: 4px 10px; border-radius: 20px; font-size: .75rem; font-weight: 700; }
        .badge-pending   { background: #fff8e1; color: #e65100; padding: 4px 10px; border-radius: 20px; font-size: .75rem; font-weight: 700; }
        .badge-cancelled { background: #fdecea; color: #c62828; padding: 4px 10px; border-radius: 20px; font-size: .75rem; font-weight: 700; }

        /* Filter bar */
        .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 24px; }
        .filter-btn {
            padding: 7px 16px; border-radius: 20px; font-size: .82rem; font-weight: 600;
            border: 1.5px solid #e0e0e0; background: #fff; color: #555; cursor: pointer; transition: all .2s;
            text-decoration: none;
        }
        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(135deg, #1B7D3A, #27A457);
            color: #fff; border-color: transparent;
        }

        /* Counter animation */
        .kpi-num[data-count] { transition: color .3s; }
    </style>
</head>
<body>
    <div class="main-container">
        <?php require_once '../includes/owner_sidebar.php'; ?>
        <div class="content">

            <!-- Topbar -->
            <div class="dash-topbar">
                <div>
                    <div class="dash-topbar-title"><i class="fas fa-chart-line me-2" style="color:#1B7D3A;"></i>Owner Dashboard</div>
                    <div class="dash-topbar-date"><?php echo date('l, F j, Y'); ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
                    <span style="font-size:.85rem;color:#888;">
                        <?php echo isset($user) ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : ''; ?>
                    </span>
                </div>
            </div>

            <div class="dash-body">
                <?php display_messages(); ?>

                <!-- Filter bar -->
                <div class="filter-bar">
                    <span style="font-size:.82rem;color:#888;font-weight:600;">Period:</span>
                    <?php
                    $filters = ['daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];
                    foreach ($filters as $key => $label):
                    ?>
                    <a href="?filter=<?= $key ?>" class="filter-btn <?= $filter_type===$key?'active':'' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                    <span style="font-size:.78rem;color:#aaa;margin-left:6px;">
                        <?php
                        if ($filter_type === 'daily')   echo '(' . date('F j, Y') . ')';
                        if ($filter_type === 'monthly') echo '(' . date('F Y') . ')';
                        if ($filter_type === 'yearly')  echo '(' . date('Y') . ')';
                        ?>
                    </span>
                </div>

                <!-- KPI Cards (Interactive - Click to View Results) -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" data-bs-toggle="modal" data-bs-target="#modalRevenue" title="Click to view total revenue details">
                            <div class="kpi-icon green"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="flex-grow-1">
                                <div class="kpi-num" data-count="<?= intval($total_revenue) ?>" data-prefix="₱">₱0</div>
                                <div class="kpi-lbl">Total Revenue</div>
                                <div class="kpi-hint"><i class="fas fa-arrow-right me-1"></i>View Results</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" data-bs-toggle="modal" data-bs-target="#modalBookings" title="Click to view total bookings details">
                            <div class="kpi-icon blue"><i class="fas fa-calendar-check"></i></div>
                            <div class="flex-grow-1">
                                <div class="kpi-num" data-count="<?= $total_bookings ?>">0</div>
                                <div class="kpi-lbl">Total Bookings</div>
                                <div class="kpi-hint"><i class="fas fa-arrow-right me-1"></i>View Results</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" data-bs-toggle="modal" data-bs-target="#modalOccupancy" title="Click to view occupancy details">
                            <div class="kpi-icon orange"><i class="fas fa-chart-pie"></i></div>
                            <div class="flex-grow-1">
                                <div class="kpi-num" data-count="<?= $occupancy_rate ?>" data-suffix="%">0%</div>
                                <div class="kpi-lbl">Occupancy Rate</div>
                                <div class="kpi-hint"><i class="fas fa-arrow-right me-1"></i>View Results</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" data-bs-toggle="modal" data-bs-target="#modalStaff" title="Click to view staff list">
                            <div class="kpi-icon purple"><i class="fas fa-users"></i></div>
                            <div class="flex-grow-1">
                                <div class="kpi-num" data-count="<?= $total_staff ?>">0</div>
                                <div class="kpi-lbl">Total Staff</div>
                                <div class="kpi-hint"><i class="fas fa-arrow-right me-1"></i>View Results</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" data-bs-toggle="modal" data-bs-target="#modalFacilities" title="Click to view facilities list">
                            <div class="kpi-icon teal"><i class="fas fa-building"></i></div>
                            <div class="flex-grow-1">
                                <div class="kpi-num" data-count="<?= $total_facilities_count ?>">0</div>
                                <div class="kpi-lbl">Total Facilities</div>
                                <div class="kpi-hint"><i class="fas fa-arrow-right me-1"></i>View Results</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card" data-bs-toggle="modal" data-bs-target="#modalAreas" title="Click to view areas list">
                            <div class="kpi-icon red"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="flex-grow-1">
                                <div class="kpi-num" data-count="<?= $total_areas ?>">0</div>
                                <div class="kpi-lbl">Total Areas</div>
                                <div class="kpi-hint"><i class="fas fa-arrow-right me-1"></i>View Results</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0"><i class="fas fa-chart-line me-2" style="color:#1B7D3A;"></i><?= htmlspecialchars($chart_label_rev ?? 'Revenue') ?></h6>
                                <span class="chart-badge green"><?= htmlspecialchars($chart_badge_rev ?? '') ?></span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="revenueTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0"><i class="fas fa-chart-bar me-2" style="color:#1565c0;"></i><?= htmlspecialchars($chart_label_bk ?? 'Bookings') ?></h6>
                                <span class="chart-badge blue"><?= htmlspecialchars($chart_badge_bk ?? '') ?></span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="bookingTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings Table -->
                <div class="section-hdr">
                    <h5><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Recent Booking Transactions</h5>
                    <p>Latest 10 bookings across all channels</p>
                </div>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Guest Name</th>
                                    <th>Mode</th>
                                    <th>Location</th>
                                    <th>Facility</th>
                                    <th>Guests</th>
                                    <th>Total Price</th>
                                    <th>Approved By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_bookings_result = $conn->query("SELECT 
                                                                            GROUP_CONCAT(b.id ORDER BY b.id ASC SEPARATOR ', ') as id, 
                                                                            b.guest_name, 
                                                                            b.booking_type, 
                                                                            b.mode, 
                                                                            a.name AS area_name, 
                                                                            GROUP_CONCAT(f.name ORDER BY f.name ASC SEPARATOR ', ') AS facility_name, 
                                                                            SUM(b.num_guests) as num_guests, 
                                                                            SUM(b.total_price) as total_price, 
                                                                            u.first_name, 
                                                                            u.last_name, 
                                                                            u.role AS staff_role 
                                                                        FROM bookings b 
                                                                        JOIN facilities f ON b.facility_id = f.id 
                                                                        LEFT JOIN areas a ON b.area_id = a.id 
                                                                        LEFT JOIN users u ON b.created_by = u.id 
                                                                        GROUP BY b.guest_name, b.check_in_date, b.check_out_date, b.created_at, b.booking_type, b.mode, a.name, u.first_name, u.last_name, u.role
                                                                        ORDER BY b.created_at DESC 
                                                                        LIMIT 10");
                                while ($booking = $recent_bookings_result->fetch_assoc()): ?>
                                <tr>
                                    <td><span style="font-weight:700;color:#1B7D3A;">#<?= str_replace(', ', ', #', $booking['id']) ?></span></td>
                                    <td><?= htmlspecialchars($booking['guest_name']) ?></td>
                                    <td><span class="badge-pending"><?= ucfirst($booking['mode']) ?></span></td>
                                    <td><?= htmlspecialchars($booking['area_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($booking['facility_name']) ?></td>
                                    <td><?= $booking['num_guests'] ?></td>
                                    <td><strong>₱<?= number_format($booking['total_price'], 2) ?></strong></td>
                                    <td>
                                        <?php
                                        if (!empty($booking['first_name']) && !empty($booking['last_name'])) {
                                            echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']);
                                            if (!empty($booking['staff_role'])) {
                                                echo ' <span style="font-size:.72rem;color:#888;">(' . ucfirst(htmlspecialchars($booking['staff_role'])) . ')</span>';
                                            }
                                        } else {
                                            echo '<span style="color:#aaa;">—</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /.dash-body -->
        </div><!-- /.content -->
    </div><!-- /.main-container -->

    <!-- ==================== MODALS FOR KPI CARDS ==================== -->

    <!-- 1. Total Revenue Modal -->
    <div class="modal fade" id="modalRevenue" tabindex="-1" aria-labelledby="modalRevenueLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #1B7D3A, #27A457);">
                    <h5 class="modal-title fw-bold" id="modalRevenueLabel">
                        <i class="fas fa-money-bill-wave me-2"></i>Total Revenue Detailed Results
                        <span class="fs-6 fw-normal text-white-50 ms-2">
                            (<?= ucfirst($filter_type) ?>: <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?>)
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-success fs-6 p-2"><i class="fas fa-coins me-1"></i> Total Revenue: ₱<?= number_format($total_revenue, 2) ?></span>
                            <span class="badge bg-light text-dark border fs-6 p-2"><i class="fas fa-receipt me-1"></i> Total Transactions: <?= $revenue_modal_res ? $revenue_modal_res->num_rows : 0 ?></span>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" class="form-control form-control-sm" placeholder="🔍 Search revenue records..." onkeyup="filterModalTable(this, 'tblRevenue')">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tblRevenue">
                            <thead class="table-dark">
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Guest Name</th>
                                    <th>Facility</th>
                                    <th>Area</th>
                                    <th>Mode</th>
                                    <th>Dates</th>
                                    <th>Status</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($revenue_modal_res && $revenue_modal_res->num_rows > 0): 
                                    while ($r = $revenue_modal_res->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><strong class="text-success">#<?= $r['id'] ?></strong></td>
                                    <td class="fw-bold"><?= htmlspecialchars($r['guest_name']) ?></td>
                                    <td><?= htmlspecialchars($r['facility_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($r['area_name'] ?? '—') ?></td>
                                    <td><span class="badge bg-info text-dark"><?= ucfirst($r['mode']) ?></span></td>
                                    <td><small><?= date('M d', strtotime($r['check_in_date'])) ?> - <?= date('M d, Y', strtotime($r['check_out_date'])) ?></small></td>
                                    <td>
                                        <span class="badge <?= $r['status'] === 'completed' ? 'bg-success' : ($r['status'] === 'confirmed' ? 'bg-primary' : 'bg-warning text-dark') ?>">
                                            <?= ucfirst($r['status']) ?>
                                        </span>
                                    </td>
                                    <td><strong class="text-success">₱<?= number_format($r['total_price'], 2) ?></strong></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No revenue transactions found for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="location.php" class="btn btn-outline-success btn-sm"><i class="fas fa-chart-line me-1"></i>Revenue Analytics</a>
                    <a href="booking_history.php" class="btn btn-success btn-sm"><i class="fas fa-history me-1"></i>View Full Booking History</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Total Bookings Modal -->
    <div class="modal fade" id="modalBookings" tabindex="-1" aria-labelledby="modalBookingsLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalBookingsLabel">
                        <i class="fas fa-calendar-check me-2"></i>Total Bookings Detailed Results
                        <span class="fs-6 fw-normal text-white-50 ms-2">
                            (<?= ucfirst($filter_type) ?>: <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?>)
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                        <div class="d-flex flex-wrap gap-2" id="bkStatusFilters">
                            <button class="bk-filter-btn active" data-status="all" onclick="filterBookingsByStatus('all')" style="background:#0d6efd;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;">Total: <?= $total_bookings ?></button>
                            <button class="bk-filter-btn" data-status="confirmed" onclick="filterBookingsByStatus('confirmed')" style="background:#198754;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;">Confirmed: <?= $bk_status_map['confirmed'] ?></button>
                            <button class="bk-filter-btn" data-status="pending" onclick="filterBookingsByStatus('pending')" style="background:#ffc107;color:#212529;border:none;border-radius:6px;padding:5px 12px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;">Pending: <?= $bk_status_map['pending'] ?></button>
                            <button class="bk-filter-btn" data-status="completed" onclick="filterBookingsByStatus('completed')" style="background:#0dcaf0;color:#212529;border:none;border-radius:6px;padding:5px 12px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;">Completed: <?= $bk_status_map['completed'] ?></button>
                            <button class="bk-filter-btn" data-status="cancelled" onclick="filterBookingsByStatus('cancelled')" style="background:#dc3545;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;">Cancelled: <?= $bk_status_map['cancelled'] ?></button>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" id="bkSearchInput" class="form-control form-control-sm" placeholder="🔍 Search bookings..." onkeyup="filterBookingsSearch(this)">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tblBookings">
                            <thead class="table-dark">
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Guest Name</th>
                                    <th>Facility</th>
                                    <th>Guests</th>
                                    <th>Mode</th>
                                    <th>Check-in / Check-out</th>
                                    <th>Status</th>
                                    <th>Total Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($bookings_modal_res && $bookings_modal_res->num_rows > 0): 
                                    while ($b = $bookings_modal_res->fetch_assoc()): 
                                ?>
                                <tr data-status="<?= htmlspecialchars($b['status']) ?>">
                                    <td><strong class="text-primary">#<?= $b['id'] ?></strong></td>
                                    <td class="fw-bold"><?= htmlspecialchars($b['guest_name']) ?></td>
                                    <td><?= htmlspecialchars($b['facility_name'] ?? '—') ?></td>
                                    <td><i class="fas fa-users me-1 text-secondary"></i><?= $b['num_guests'] ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= ucfirst($b['mode']) ?></span></td>
                                    <td><small><?= date('M d, Y', strtotime($b['check_in_date'])) ?> &rarr; <?= date('M d, Y', strtotime($b['check_out_date'])) ?></small></td>
                                    <td>
                                        <span class="badge <?= $b['status'] === 'confirmed' ? 'bg-success' : ($b['status'] === 'pending' ? 'bg-warning text-dark' : ($b['status'] === 'completed' ? 'bg-info text-dark' : 'bg-danger')) ?>">
                                            <?= ucfirst($b['status']) ?>
                                        </span>
                                    </td>
                                    <td><strong>₱<?= number_format($b['total_price'], 2) ?></strong></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No bookings found for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="booking.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar me-1"></i>Booking Analytics</a>
                    <a href="booking_history.php" class="btn btn-primary btn-sm"><i class="fas fa-history me-1"></i>Full Booking History</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Occupancy Rate Modal -->
    <div class="modal fade" id="modalOccupancy" tabindex="-1" aria-labelledby="modalOccupancyLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="modalOccupancyLabel">
                        <i class="fas fa-chart-pie me-2"></i>Occupancy Rate & Facility Status Results
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                        <div class="d-flex align-items-center gap-2" id="occFilters">
                            <span class="badge bg-warning text-dark fs-6 p-2" style="cursor:default;"><i class="fas fa-percentage me-1"></i> Occupancy: <?= $occupancy_rate ?>%</span>
                            <button class="occ-filter-btn" data-occ="all" onclick="filterOccupancy('all')" style="background:#6c757d;color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;">All: <?= $occupancy_modal_res ? $occupancy_modal_res->num_rows : 0 ?></button>
                            <button class="occ-filter-btn" data-occ="occupied" onclick="filterOccupancy('occupied')" style="background:#dc3545;color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;"><i class="fas fa-user-check me-1"></i>Occupied: <?= $occupied_facilities ?></button>
                            <button class="occ-filter-btn" data-occ="available" onclick="filterOccupancy('available')" style="background:#198754;color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;"><i class="fas fa-check-circle me-1"></i>Available Total: <?= $total_facilities ?></button>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" id="occSearchInput" class="form-control form-control-sm" placeholder="🔍 Search facilities..." onkeyup="filterOccupancySearch(this)">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tblOccupancy">
                            <thead class="table-dark">
                                <tr>
                                    <th>Facility Name</th>
                                    <th>Type</th>
                                    <th>Area / Location</th>
                                    <th>Capacity</th>
                                    <th>Price</th>
                                    <th>Current Occupancy</th>
                                    <th>Facility Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($occupancy_modal_res && $occupancy_modal_res->num_rows > 0): 
                                    while ($f = $occupancy_modal_res->fetch_assoc()): 
                                        $is_occ = $f['active_bks'] > 0;
                                ?>
                                <tr data-occ="<?= $is_occ ? 'occupied' : 'available' ?>">
                                    <td class="fw-bold"><?= htmlspecialchars($f['name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= ucfirst(htmlspecialchars($f['type'])) ?></span></td>
                                    <td><?= htmlspecialchars($f['area_name'] ?? '—') ?></td>
                                    <td><?= $f['capacity'] ?> guests</td>
                                    <td>₱<?= number_format($f['price'], 2) ?></td>
                                    <td>
                                        <?php if ($is_occ): ?>
                                            <span class="badge bg-danger"><i class="fas fa-user-check me-1"></i>Occupied (<?= $f['active_bks'] ?> Active)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Vacant / Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $f['status'] === 'available' ? 'bg-success' : ($f['status'] === 'maintenance' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                            <?= ucfirst($f['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No facilities data available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="facilities_status.php" class="btn btn-outline-warning text-dark btn-sm"><i class="fas fa-toggle-on me-1"></i>Facility Status Management</a>
                    <a href="facilities.php" class="btn btn-warning text-dark btn-sm"><i class="fas fa-chart-pie me-1"></i>Facility Utilization</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Total Staff Modal -->
    <div class="modal fade" id="modalStaff" tabindex="-1" aria-labelledby="modalStaffLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header text-white" style="background-color: #6a1b9a;">
                    <h5 class="modal-title fw-bold" id="modalStaffLabel">
                        <i class="fas fa-users me-2"></i>Total Staff Members List
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                        <div>
                            <span class="badge fs-6 p-2 text-white" style="background-color: #6a1b9a;">Total Staff: <?= $total_staff ?></span>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" class="form-control form-control-sm" placeholder="🔍 Search staff members..." onkeyup="filterModalTable(this, 'tblStaff')">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tblStaff">
                            <thead class="table-dark">
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($staff_modal_res && $staff_modal_res->num_rows > 0): 
                                    while ($s = $staff_modal_res->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                    <td><code><?= htmlspecialchars($s['username']) ?></code></td>
                                    <td>
                                        <span class="badge text-white" style="background-color: #6a1b9a;">
                                            <?= ucfirst(htmlspecialchars($s['role'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No staff members found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="staff_history.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user-clock me-1"></i>Staff History</a>
                    <a href="manage_staff.php" class="btn text-white btn-sm" style="background-color: #6a1b9a;"><i class="fas fa-user-gear me-1"></i>Manage Staff</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Total Facilities Modal -->
    <div class="modal fade" id="modalFacilities" tabindex="-1" aria-labelledby="modalFacilitiesLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header text-white" style="background-color: #00695c;">
                    <h5 class="modal-title fw-bold" id="modalFacilitiesLabel">
                        <i class="fas fa-building me-2"></i>Total Facilities List & Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                        <div>
                            <span class="badge fs-6 p-2 text-white" style="background-color: #00695c;">Total Facilities: <?= $total_facilities_count ?></span>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" class="form-control form-control-sm" placeholder="🔍 Search facilities..." onkeyup="filterModalTable(this, 'tblFacilities')">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tblFacilities">
                            <thead class="table-dark">
                                <tr>
                                    <th>Facility Name</th>
                                    <th>Type</th>
                                    <th>Area / Location</th>
                                    <th>Capacity</th>
                                    <th>Price Rate</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($facilities_modal_res && $facilities_modal_res->num_rows > 0): 
                                    while ($fc = $facilities_modal_res->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($fc['name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= ucfirst(htmlspecialchars($fc['type'])) ?></span></td>
                                    <td><?= htmlspecialchars($fc['area_name'] ?? 'Unassigned') ?></td>
                                    <td><?= $fc['capacity'] ?> guests</td>
                                    <td><strong>₱<?= number_format($fc['price'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $fc['status'] === 'available' ? 'bg-success' : ($fc['status'] === 'maintenance' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                            <?= ucfirst($fc['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No facilities registered.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="premises_history.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-history me-1"></i>Premises History</a>
                    <a href="manage_facilities.php" class="btn text-white btn-sm" style="background-color: #00695c;"><i class="fas fa-building me-1"></i>Manage Facilities</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Total Areas Modal -->
    <div class="modal fade" id="modalAreas" tabindex="-1" aria-labelledby="modalAreasLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="modalAreasLabel">
                        <i class="fas fa-map-marker-alt me-2"></i>Total Resort Areas & Locations
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                        <div>
                            <span class="badge bg-danger fs-6 p-2">Total Areas: <?= $total_areas ?></span>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" class="form-control form-control-sm" placeholder="🔍 Search areas..." onkeyup="filterModalTable(this, 'tblAreas')">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tblAreas">
                            <thead class="table-dark">
                                <tr>
                                    <th>Area / Location Name</th>
                                    <th>Regular Price</th>
                                    <th>Discounted Price</th>
                                    <th>Children Price</th>
                                    <th>Facilities Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($areas_modal_res && $areas_modal_res->num_rows > 0): 
                                    while ($ar = $areas_modal_res->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($ar['name']) ?></td>
                                    <td>₱<?= number_format($ar['price_regular'] ?? 0, 2) ?></td>
                                    <td>₱<?= number_format($ar['price_discounted'] ?? 0, 2) ?></td>
                                    <td>₱<?= number_format($ar['price_children'] ?? 0, 2) ?></td>
                                    <td><span class="badge bg-danger"><?= $ar['facility_count'] ?> facilities</span></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No resort areas found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="manage_areas.php" class="btn btn-danger btn-sm"><i class="fas fa-map-marked-alt me-1"></i>Manage Locations</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Generic text search filter for any modal table
    function filterModalTable(input, tableId) {
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }

    // Active status filter for Bookings modal
    let bkActiveStatus = 'all';

    function filterBookingsByStatus(status) {
        bkActiveStatus = status;

        // Update active button styles
        document.querySelectorAll('.bk-filter-btn').forEach(btn => {
            const isActive = btn.getAttribute('data-status') === status;
            btn.style.opacity = isActive ? '1' : '0.55';
            btn.style.transform = isActive ? 'scale(1.07)' : 'scale(1)';
            btn.style.boxShadow = isActive ? '0 3px 10px rgba(0,0,0,.25)' : 'none';
        });

        applyBookingFilters();
    }

    function filterBookingsSearch(input) {
        applyBookingFilters();
    }

    function applyBookingFilters() {
        const searchVal = (document.getElementById('bkSearchInput')?.value || '').toLowerCase();
        const rows = document.querySelectorAll('#tblBookings tbody tr');

        rows.forEach(row => {
            const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            const statusMatch = bkActiveStatus === 'all' || rowStatus === bkActiveStatus;
            const textMatch = row.textContent.toLowerCase().includes(searchVal);
            row.style.display = (statusMatch && textMatch) ? '' : 'none';
        });
    }

    // Reset filters when modal opens
    document.addEventListener('DOMContentLoaded', function() {
        var modalBookings = document.getElementById('modalBookings');
        if (modalBookings) {
            modalBookings.addEventListener('show.bs.modal', function() {
                bkActiveStatus = 'all';
                if (document.getElementById('bkSearchInput')) document.getElementById('bkSearchInput').value = '';
                document.querySelectorAll('.bk-filter-btn').forEach(btn => {
                    btn.style.opacity = btn.getAttribute('data-status') === 'all' ? '1' : '0.55';
                    btn.style.transform = btn.getAttribute('data-status') === 'all' ? 'scale(1.07)' : 'scale(1)';
                    btn.style.boxShadow = btn.getAttribute('data-status') === 'all' ? '0 3px 10px rgba(0,0,0,.25)' : 'none';
                });
                applyBookingFilters();
            });
        }

        // Reset Occupancy modal filters on open
        var modalOccupancy = document.getElementById('modalOccupancy');
        if (modalOccupancy) {
            modalOccupancy.addEventListener('show.bs.modal', function() {
                occActiveFilter = 'all';
                if (document.getElementById('occSearchInput')) document.getElementById('occSearchInput').value = '';
                setOccBtnActive('all');
                applyOccupancyFilters();
            });
        }
    });

    // ── Occupancy modal filter logic ──────────────────────────────────
    let occActiveFilter = 'all';

    function filterOccupancy(filter) {
        occActiveFilter = filter;
        setOccBtnActive(filter);
        applyOccupancyFilters();
    }

    function filterOccupancySearch() {
        applyOccupancyFilters();
    }

    function setOccBtnActive(active) {
        document.querySelectorAll('.occ-filter-btn').forEach(btn => {
            const isActive = btn.getAttribute('data-occ') === active;
            btn.style.opacity      = isActive ? '1'                        : '0.55';
            btn.style.transform    = isActive ? 'scale(1.07)'              : 'scale(1)';
            btn.style.boxShadow    = isActive ? '0 3px 10px rgba(0,0,0,.25)' : 'none';
        });
    }

    function applyOccupancyFilters() {
        const searchVal = (document.getElementById('occSearchInput')?.value || '').toLowerCase();
        const rows = document.querySelectorAll('#tblOccupancy tbody tr');

        rows.forEach(row => {
            const rowOcc    = (row.getAttribute('data-occ') || '').toLowerCase();
            const occMatch  = occActiveFilter === 'all' || rowOcc === occActiveFilter;
            const textMatch = row.textContent.toLowerCase().includes(searchVal);
            row.style.display = (occMatch && textMatch) ? '' : 'none';
        });
    }

    <?php
    $revenue_data = []; $booking_data = []; $labels = [];

    if ($filter_type === 'daily') {
        // 24 hours of today
        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $res  = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE status IN ('completed','pending','confirmed') AND DATE(created_at)='" . date('Y-m-d') . "' AND HOUR(created_at)=$h");
            $revenue_data[] = floatval($res->fetch_assoc()['total'] ?? 0);
            $res2 = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE DATE(created_at)='" . date('Y-m-d') . "' AND HOUR(created_at)=$h");
            $booking_data[] = intval($res2->fetch_assoc()['cnt'] ?? 0);
        }
        $chart_label_rev = 'Today\'s Revenue by Hour';
        $chart_label_bk  = 'Today\'s Bookings by Hour';
        $chart_badge_rev = 'Today';
        $chart_badge_bk  = 'Today';
    } elseif ($filter_type === 'monthly') {
        // Each day of current month
        $days_in_month = (int)date('t');
        $year_month    = date('Y-m');
        for ($d = 1; $d <= $days_in_month; $d++) {
            $day_str  = $year_month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            $labels[] = $d;
            $res  = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE status IN ('completed','pending','confirmed') AND DATE(created_at)='$day_str'");
            $revenue_data[] = floatval($res->fetch_assoc()['total'] ?? 0);
            $res2 = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE DATE(created_at)='$day_str'");
            $booking_data[] = intval($res2->fetch_assoc()['cnt'] ?? 0);
        }
        $chart_label_rev = 'Revenue — ' . date('F Y');
        $chart_label_bk  = 'Bookings — ' . date('F Y');
        $chart_badge_rev = date('F Y');
        $chart_badge_bk  = date('F Y');
    } else {
        // 12 months of current year
        $year = date('Y');
        for ($m = 1; $m <= 12; $m++) {
            $month_str = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
            $labels[]  = date('M', mktime(0,0,0,$m,1,$year));
            $res  = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE status IN ('completed','pending','confirmed') AND DATE_FORMAT(created_at,'%Y-%m')='$month_str'");
            $revenue_data[] = floatval($res->fetch_assoc()['total'] ?? 0);
            $res2 = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE DATE_FORMAT(created_at,'%Y-%m')='$month_str'");
            $booking_data[] = intval($res2->fetch_assoc()['cnt'] ?? 0);
        }
        $chart_label_rev = 'Revenue — ' . date('Y');
        $chart_label_bk  = 'Bookings — ' . date('Y');
        $chart_badge_rev = date('Y');
        $chart_badge_bk  = date('Y');
    }
    ?>
    const chartLabels = <?= json_encode($labels) ?>;
    const revenueData = <?= json_encode($revenue_data) ?>;
    const bookingData = <?= json_encode($booking_data) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Animated counters
        document.querySelectorAll('.kpi-num[data-count]').forEach((el, i) => {
            const target = parseFloat(el.getAttribute('data-count'));
            const prefix = el.getAttribute('data-prefix') || '';
            const suffix = el.getAttribute('data-suffix') || '';
            const isFloat = target % 1 !== 0;
            setTimeout(() => {
                const start = performance.now();
                const dur = 900;
                const update = (now) => {
                    const p = Math.min((now - start) / dur, 1);
                    const eased = 1 - Math.pow(1 - p, 3);
                    const val = eased * target;
                    el.textContent = prefix + (isFloat ? val.toFixed(1) : Math.round(val).toLocaleString()) + suffix;
                    if (p < 1) requestAnimationFrame(update);
                };
                requestAnimationFrame(update);
            }, i * 80);
        });

        // Revenue chart
        const ctx1 = document.getElementById('revenueTrendChart').getContext('2d');
        const grad = ctx1.createLinearGradient(0, 0, 0, 240);
        grad.addColorStop(0, 'rgba(27,125,58,.25)');
        grad.addColorStop(1, 'rgba(27,125,58,.02)');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: revenueData,
                    backgroundColor: grad,
                    borderColor: '#1B7D3A',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.42,
                    pointRadius: 5,
                    pointBackgroundColor: '#1B7D3A',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1B7D3A',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 10,
                        callbacks: {
                            label: c => ' ₱' + c.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v),
                            font: { size: 11 },
                            color: '#888'
                        },
                        grid: { color: 'rgba(0,0,0,.04)' },
                        border: { display: false }
                    },
                    x: {
                        ticks: { font: { size: 11 }, color: '#888' },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });

        // Booking chart
        const ctx2 = document.getElementById('bookingTrendChart').getContext('2d');
        const grad2 = ctx2.createLinearGradient(0, 0, 0, 240);
        grad2.addColorStop(0, 'rgba(21,101,192,.7)');
        grad2.addColorStop(1, 'rgba(21,101,192,.4)');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Bookings',
                    data: bookingData,
                    backgroundColor: bookingData.map((v, i) => {
                        const max = Math.max(...bookingData);
                        return v === max ? '#1B7D3A' : 'rgba(27,125,58,.45)';
                    }),
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: '#1B7D3A'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1565c0',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 10,
                        callbacks: {
                            label: c => ' ' + c.parsed.y + ' booking' + (c.parsed.y !== 1 ? 's' : '')
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 11 }, color: '#888' },
                        grid: { color: 'rgba(0,0,0,.04)' },
                        border: { display: false }
                    },
                    x: {
                        ticks: { font: { size: 11 }, color: '#888' },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });
    });

    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        const sidebarCol = document.getElementById('sidebarCol');
        const navbarBrand = document.getElementById('navbarBrand');
        if (localStorage.getItem('ownerSidebarCollapsed') === 'true') {
            sidebarCol.classList.add('collapsed');
            sidebarToggle.classList.add('collapsed');
            if (navbarBrand) navbarBrand.classList.add('collapsed');
        }
        sidebarToggle.addEventListener('click', function() {
            const c = sidebarCol.classList.toggle('collapsed');
            this.classList.toggle('collapsed');
            if (navbarBrand) navbarBrand.classList.toggle('collapsed');
            localStorage.setItem('ownerSidebarCollapsed', c);
        });
    }
    </script>
</body>
</html>