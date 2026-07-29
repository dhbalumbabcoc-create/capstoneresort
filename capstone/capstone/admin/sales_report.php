<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('admin');

$user = get_user_info($_SESSION['user_id'], $conn);
$today = date('Y-m-d');

// ── Filter parameters ────────────────────────────────────────────────────────
$filter_period = isset($_GET['period']) ? trim($_GET['period']) : 'all';
$filter_type   = isset($_GET['booking_type']) ? trim($_GET['booking_type']) : 'all';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'confirmed';
$start_date    = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date      = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';

// ── Build SQL WHERE clauses ──────────────────────────────────────────────────
$where_clauses = [];

// Status filter
if ($filter_status === 'confirmed') {
    $where_clauses[] = "b.status IN ('confirmed', 'completed')";
} elseif ($filter_status !== 'all' && !empty($filter_status)) {
    $safe_status = $conn->real_escape_string($filter_status);
    $where_clauses[] = "b.status = '$safe_status'";
}

// Booking type filter
if ($filter_type === 'walkin') {
    $where_clauses[] = "b.booking_type IN ('walkin', 'walk-in')";
} elseif ($filter_type === 'online') {
    $where_clauses[] = "b.booking_type = 'online'";
}

// Date period filter
if ($filter_period === 'today') {
    $where_clauses[] = "DATE(b.created_at) = '$today'";
} elseif ($filter_period === 'month') {
    $where_clauses[] = "MONTH(b.created_at) = MONTH(CURDATE()) AND YEAR(b.created_at) = YEAR(CURDATE())";
} elseif ($filter_period === 'year') {
    $where_clauses[] = "YEAR(b.created_at) = YEAR(CURDATE())";
} elseif ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $s = $conn->real_escape_string($start_date);
    $e = $conn->real_escape_string($end_date);
    $where_clauses[] = "DATE(b.created_at) BETWEEN '$s' AND '$e'";
}

// Search filter
if (!empty($search)) {
    $s_term = $conn->real_escape_string($search);
    $where_clauses[] = "(b.id LIKE '%$s_term%' OR b.guest_name LIKE '%$s_term%' OR b.guest_email LIKE '%$s_term%' OR b.guest_phone LIKE '%$s_term%')";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// ── Export CSV Request ───────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Sales_Report_' . date('Y-m-d_His') . '.csv');
    $output = fopen('php://output', 'w');

    fputcsv($output, ['Sales ID', 'Date Created', 'Guest Name', 'Email', 'Phone', 'Booking Type', 'Facility', 'Area', 'Mode', 'Adults', 'Children', 'Discounted', 'Status', 'Total Price (PHP)']);

    $export_query = "SELECT b.*, f.name AS facility_name, a.name AS area_name
                    FROM bookings b
                    LEFT JOIN facilities f ON b.facility_id = f.id
                    LEFT JOIN areas a ON b.area_id = a.id
                    $where_sql
                    ORDER BY b.created_at DESC";
    $export_res = $conn->query($export_query);

    if ($export_res && $export_res->num_rows > 0) {
        while ($row = $export_res->fetch_assoc()) {
            fputcsv($output, [
                '#' . $row['id'],
                date('Y-m-d H:i', strtotime($row['created_at'])),
                $row['guest_name'],
                $row['guest_email'] ?? 'N/A',
                $row['guest_phone'] ?? 'N/A',
                strtoupper($row['booking_type']),
                $row['facility_name'] ?? 'N/A',
                $row['area_name'] ?? 'N/A',
                ucfirst($row['mode'] ?? 'daytour'),
                $row['num_adults'] ?? 0,
                $row['num_children'] ?? 0,
                $row['num_discounted'] ?? 0,
                strtoupper($row['status']),
                number_format((float)($row['total_price'] ?? 0), 2, '.', '')
            ]);
        }
    }
    fclose($output);
    exit();
}

// ── KPI Metrics ─────────────────────────────────────────────────────────────
$kpi_query = "SELECT
                SUM(total_price) AS grand_total,
                COUNT(*) AS total_count,
                SUM(CASE WHEN booking_type IN ('walkin','walk-in') THEN total_price ELSE 0 END) AS walkin_total,
                SUM(CASE WHEN booking_type = 'online' THEN total_price ELSE 0 END) AS online_total
              FROM bookings b
              $where_sql";
$kpi_res = $conn->query($kpi_query);
$kpi = $kpi_res ? $kpi_res->fetch_assoc() : [];

$grand_total  = floatval($kpi['grand_total'] ?? 0);
$total_count  = intval($kpi['total_count'] ?? 0);
$walkin_total = floatval($kpi['walkin_total'] ?? 0);
$online_total = floatval($kpi['online_total'] ?? 0);
$avg_sale     = $total_count > 0 ? ($grand_total / $total_count) : 0;

// ── Fetch Records ────────────────────────────────────────────────────────────
$sales_query = "SELECT b.*, f.name AS facility_name, a.name AS area_name
                FROM bookings b
                LEFT JOIN facilities f ON b.facility_id = f.id
                LEFT JOIN areas a ON b.area_id = a.id
                $where_sql
                ORDER BY b.created_at DESC";
$sales_result  = $conn->query($sales_query);
$sales_records = [];
if ($sales_result && $sales_result->num_rows > 0) {
    while ($row = $sales_result->fetch_assoc()) $sales_records[] = $row;
}

// Prepare export URL keeping current params
$export_params          = $_GET;
$export_params['export'] = 'csv';
$export_url = 'sales_report.php?' . http_build_query($export_params);

$staff_name = isset($user) ? htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) : 'Admin';

// Period label map (for print header)
$period_titles = [
    'all'    => 'All Records',
    'half_1' => '1st Half of Month (Day 1-15)',
    'half_2' => '2nd Half of Month (Day 16-End)',
    'month'  => 'Monthly (' . date('F Y') . ')',
    'year'   => 'Yearly (' . date('Y') . ')',
    'today'  => 'Today (' . date('M d, Y') . ')',
    'custom' => 'Custom Range (' . $start_date . ' to ' . $end_date . ')'
];
$display_period_title = $period_titles[$filter_period] ?? ucfirst($filter_period);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <?php require_once '../includes/admin_page_styles.php'; ?>
    <style>
        .filter-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            margin-bottom: 24px;
        }
        .period-btn {
            padding: 7px 18px;
            border-radius: 20px;
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
            border: 1.5px solid #e0e0e0;
            background: #fff;
            color: #555;
            transition: all 0.2s ease;
            display: inline-block;
        }
        .period-btn.active, .period-btn:hover {
            background: linear-gradient(135deg, #1B7D3A, #27A457);
            color: #fff;
            border-color: transparent;
        }
        .print-header { display: none; }
        .badge-status-confirmed { background-color: #e8f5e9; color: #1B7D3A; border: 1px solid #c8e6c9; }
        .badge-status-completed { background-color: #e8f5e9; color: #1B7D3A; border: 1px solid #c8e6c9; }
        .badge-status-pending   { background-color: #fff8e1; color: #f57f17; border: 1px solid #ffe0b2; }
        .badge-status-cancelled { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        @media print {
            body { background: #fff !important; color: #000 !important; font-size: 12pt; }
            .sidebar-col, .dash-topbar, .filter-card, .btn, .no-print, nav, .kpi-card { display: none !important; }
            .content, .main-container { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .print-header {
                display: block !important;
                text-align: center;
                border-bottom: 2px solid #1B7D3A;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .print-header h2 { font-size: 18pt; font-weight: bold; color: #1B7D3A; margin: 0 0 4px 0; }
            .print-header p  { margin: 2px 0; font-size: 10pt; color: #444; }
            .print-summary-box {
                display: flex !important;
                justify-content: space-between;
                margin-bottom: 20px;
                background: #f9f9f9;
                padding: 12px 18px;
                border: 1px solid #ddd;
                border-radius: 6px;
            }
            .print-summary-item { text-align: center; }
            .print-summary-item strong { display: block; font-size: 11pt; color: #1B7D3A; }
            .table-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
            table.table { width: 100% !important; border-collapse: collapse !important; font-size: 9.5pt !important; }
            table.table th, table.table td { border: 1px solid #999 !important; padding: 6px 8px !important; }
            table.table th { background-color: #f0f0f0 !important; color: #000 !important; }
            .print-footer { display: flex !important; justify-content: space-between; margin-top: 40px; padding-top: 20px; }
            .signature-line { width: 220px; border-top: 1px solid #000; text-align: center; padding-top: 5px; font-size: 10pt; }
        }
    </style>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/admin_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-file-invoice-dollar me-2" style="color:#1B7D3A;"></i>Sales Report</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-outline-success btn-sm fw-bold dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download me-1"></i> Download Records
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                        <li><h6 class="dropdown-header text-uppercase fw-bold text-success" style="font-size:.75rem;">Quick Download CSV</h6></li>
                        <li><a class="dropdown-item py-2" href="<?php echo htmlspecialchars($export_url); ?>"><i class="fas fa-filter me-2 text-success"></i>Download Current Filtered View</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-1" href="sales_report.php?period=all&status=<?= urlencode($filter_status) ?>&export=csv"><i class="fas fa-database me-2 text-secondary"></i>Download All Records</a></li>
                        <li><a class="dropdown-item py-1" href="sales_report.php?period=month&status=<?= urlencode($filter_status) ?>&export=csv"><i class="fas fa-calendar-check me-2 text-secondary"></i>Download Monthly (This Month)</a></li>
                        <li><a class="dropdown-item py-1" href="sales_report.php?period=year&status=<?= urlencode($filter_status) ?>&export=csv"><i class="fas fa-calendar me-2 text-secondary"></i>Download Yearly (This Year)</a></li>
                    </ul>
                </div>
                <button onclick="window.print()" class="btn btn-success btn-sm fw-bold">
                    <i class="fas fa-print me-1"></i> Print / Save PDF
                </button>
                <span class="dash-topbar-badge"><i class="fas fa-shield-alt me-1"></i>Admin</span>
            </div>
        </div>

        <div class="dash-body">

            <!-- Print Header -->
            <div class="print-header">
                <h2>Sinulom &amp; Bolao Cold Spring Resort</h2>
                <p><strong>OFFICIAL SALES REPORT</strong></p>
                <p>Filter Period: <strong><?php echo $display_period_title; ?></strong> |
                   Booking Type: <strong><?php echo ucfirst($filter_type); ?></strong> |
                   Status: <strong><?php echo ucfirst($filter_status); ?></strong>
                </p>
                <p>Report Generated: <?php echo date('F j, Y h:i A'); ?> | Generated By: <?php echo $staff_name; ?> (Admin)</p>
            </div>

            <!-- Filter Controls -->
            <div class="filter-card no-print">
                <form method="GET" action="sales_report.php" class="row g-3 align-items-end">

                    <!-- Quick Period Buttons -->
                    <div class="col-12 mb-2 d-flex gap-2 flex-wrap align-items-center">
                        <span class="text-muted fw-bold me-2" style="font-size:0.85rem;">Period:</span>
                        <?php
                        $periods = [
                            'all'    => 'All Records',
                            'today'  => 'Today',
                            'month'  => 'Monthly',
                            'year'   => 'Yearly',
                            'custom' => 'Custom Range'
                        ];
                        foreach ($periods as $pkey => $plabel):
                            $act = ($filter_period === $pkey) ? 'active' : '';
                        ?>
                            <a href="?period=<?= $pkey ?>&booking_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>" class="period-btn <?= $act ?>"><?= $plabel ?></a>
                        <?php endforeach; ?>
                    </div>

                    <input type="hidden" name="period" value="<?= htmlspecialchars($filter_period) ?>">

                    <?php if ($filter_period === 'custom'): ?>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-bold" style="font-size:.8rem;">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-bold" style="font-size:.8rem;">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <?php endif; ?>

                    <div class="col-6 col-md-3">
                        <label class="form-label fw-bold" style="font-size:.8rem;">Booking Type</label>
                        <select name="booking_type" class="form-select form-select-sm">
                            <option value="all"    <?= $filter_type === 'all'    ? 'selected' : '' ?>>All Types (Walk-in &amp; Online)</option>
                            <option value="walkin" <?= $filter_type === 'walkin' ? 'selected' : '' ?>>Walk-in Only</option>
                            <option value="online" <?= $filter_type === 'online' ? 'selected' : '' ?>>Online Only</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label fw-bold" style="font-size:.8rem;">Payment Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="confirmed" <?= $filter_status === 'confirmed' ? 'selected' : '' ?>>Confirmed / Paid Sales</option>
                            <option value="all"       <?= $filter_status === 'all'       ? 'selected' : '' ?>>All Statuses</option>
                            <option value="pending"   <?= $filter_status === 'pending'   ? 'selected' : '' ?>>Pending Only</option>
                        </select>
                    </div>

                    <div class="col-8 col-md-3">
                        <label class="form-label fw-bold" style="font-size:.8rem;">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Guest name, email, ID..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="col-4 col-md-1">
                        <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                    </div>
                </form>
            </div>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4 no-print">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-peso-sign"></i></div>
                        <div>
                            <div class="kpi-num">&#8369;<?= number_format($grand_total, 2) ?></div>
                            <div class="kpi-lbl">Total Sales</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-receipt"></i></div>
                        <div>
                            <div class="kpi-num"><?= $total_count ?></div>
                            <div class="kpi-lbl">Sales Records</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon teal"><i class="fas fa-walking"></i></div>
                        <div>
                            <div class="kpi-num">&#8369;<?= number_format($walkin_total, 2) ?></div>
                            <div class="kpi-lbl">Walk-in Sales</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-globe"></i></div>
                        <div>
                            <div class="kpi-num">&#8369;<?= number_format($online_total, 2) ?></div>
                            <div class="kpi-lbl">Online Sales</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Summary Box -->
            <div class="print-summary-box" style="display:none;">
                <div class="print-summary-item"><span>Total Revenue</span><strong>&#8369;<?= number_format($grand_total, 2) ?></strong></div>
                <div class="print-summary-item"><span>Total Records</span><strong><?= $total_count ?></strong></div>
                <div class="print-summary-item"><span>Walk-in Revenue</span><strong>&#8369;<?= number_format($walkin_total, 2) ?></strong></div>
                <div class="print-summary-item"><span>Online Revenue</span><strong>&#8369;<?= number_format($online_total, 2) ?></strong></div>
                <div class="print-summary-item"><span>Avg Sale Value</span><strong>&#8369;<?= number_format($avg_sale, 2) ?></strong></div>
            </div>

            <!-- Sales Table -->
            <div class="section-hdr d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0"><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Sales Records</h5>
                    <p class="text-muted small mb-0">Displaying <?= count($sales_records) ?> transaction(s)</p>
                </div>
            </div>

            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sales #</th>
                                <th>Date &amp; Time</th>
                                <th>Guest Name</th>
                                <th>Type</th>
                                <th>Facility &amp; Area</th>
                                <th>Mode</th>
                                <th>Guests</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sales_records) > 0): ?>
                                <?php foreach ($sales_records as $rec):
                                    $is_walkin    = in_array(strtolower($rec['booking_type']), ['walkin', 'walk-in']);
                                    $status_class = 'badge-status-' . strtolower($rec['status']);
                                    $num_adults   = intval($rec['num_adults']);
                                    $num_disc     = intval($rec['num_discounted']);
                                    $num_children = intval($rec['num_children']);
                                    $guest_cnt    = $num_adults + $num_disc + $num_children;
                                ?>
                                <tr>
                                    <td><strong>#<?= $rec['id'] ?></strong></td>
                                    <td>
                                        <div style="font-size:.85rem;font-weight:600;"><?= date('M d, Y', strtotime($rec['created_at'])) ?></div>
                                        <div class="text-muted" style="font-size:.75rem;"><?= date('h:i A', strtotime($rec['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($rec['guest_name']) ?></div>
                                        <?php if (!empty($rec['guest_phone'])): ?>
                                            <div class="text-muted small"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($rec['guest_phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_walkin): ?>
                                            <span class="badge bg-success"><i class="fas fa-walking me-1"></i>Walk-in</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary"><i class="fas fa-globe me-1"></i>Online</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($rec['facility_name'] ?? 'N/A') ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($rec['area_name'] ?? 'General') ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= ucfirst($rec['mode'] ?? 'daytour') ?></span></td>
                                    <td class="text-center">
                                        <span class="fw-bold"><?= $guest_cnt ?></span>
                                    </td>
                                    <td>
                                        <span class="px-2 py-1 rounded-pill text-uppercase fw-bold <?= $status_class ?>" style="font-size:.7rem;">
                                            <?= htmlspecialchars($rec['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success" style="font-size:.95rem;">&#8369;<?= number_format(floatval($rec['total_price']), 2) ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle fa-2x mb-2 d-block opacity-50"></i>
                                        No sales records found matching the filter criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Print Signature Block -->
            <div class="print-footer" style="display:none;">
                <div class="signature-line">
                    <p class="mb-0"><strong><?php echo $staff_name; ?></strong></p>
                    <p class="text-muted mb-0">Admin</p>
                </div>
                <div class="signature-line">
                    <p class="mb-0"><strong>Owner / Manager Signature</strong></p>
                    <p class="text-muted mb-0">Verified &amp; Approved</p>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
