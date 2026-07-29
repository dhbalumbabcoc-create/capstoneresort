<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('frontdesk');

$user   = get_user_info($_SESSION['user_id'], $conn);
$today  = date('Y-m-d');

// ── Filter parameters ────────────────────────────────────────────────────────
$filter_period = isset($_GET['period'])       ? trim($_GET['period'])       : 'all';
$filter_type   = isset($_GET['booking_type']) ? trim($_GET['booking_type']) : 'all';
$filter_status = isset($_GET['status'])       ? trim($_GET['status'])       : 'confirmed';
$start_date    = isset($_GET['start_date'])   ? trim($_GET['start_date'])   : '';
$end_date      = isset($_GET['end_date'])     ? trim($_GET['end_date'])     : '';
$search        = isset($_GET['search'])       ? trim($_GET['search'])       : '';

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

// ── Helper: fetch payments for a booking, split into downpayment & fullpayment
function get_payments_for_booking($conn, $booking_id, $total_price) {
    $res = $conn->query(
        "SELECT id, amount_paid, reference_number, paid_at, status, method
         FROM payments
         WHERE booking_id = " . intval($booking_id) . "
         ORDER BY paid_at ASC"
    );
    $payments = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $payments[] = $row;
        }
    }

    $downpayment  = null;
    $fullpayment  = null;
    $total_paid   = 0;

    foreach ($payments as $p) {
        $total_paid += floatval($p['amount_paid']);
    }

    if (count($payments) === 1) {
        $p = $payments[0];
        $amt = floatval($p['amount_paid']);
        if ($amt >= floatval($total_price) - 0.01) {
            $fullpayment = $p;
        } else {
            $downpayment = $p;
        }
    } elseif (count($payments) >= 2) {
        // First payment = downpayment, last completed payment = full/balance
        $downpayment = $payments[0];
        // Find the last payment
        $fullpayment = $payments[count($payments) - 1];
    }

    return [
        'downpayment'  => $downpayment,
        'fullpayment'  => $fullpayment,
        'total_paid'   => $total_paid,
        'all_payments' => $payments,
    ];
}

// ── Export CSV ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Sales_Report_' . date('Y-m-d_His') . '.csv');
    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'Booking #', 'Transaction Name', 'Date Created', 'Booking Type', 'Facility', 'Area', 'Mode', 'Status',
        'Total Amount (PHP)',
        'Downpayment Ref #', 'Downpayment Date', 'Downpayment Amount (PHP)',
        'Full Payment Ref #', 'Full Payment Date', 'Full Payment Amount (PHP)',
        'Total Paid (PHP)', 'Balance (PHP)'
    ]);

    $export_query = "SELECT b.*, f.name AS facility_name, a.name AS area_name
                     FROM bookings b
                     LEFT JOIN facilities f ON b.facility_id = f.id
                     LEFT JOIN areas a ON b.area_id = a.id
                     $where_sql
                     ORDER BY b.created_at DESC";
    $export_res = $conn->query($export_query);
    if ($export_res && $export_res->num_rows > 0) {
        while ($row = $export_res->fetch_assoc()) {
            $pdata = get_payments_for_booking($conn, $row['id'], $row['total_price']);
            $dp    = $pdata['downpayment'];
            $fp    = $pdata['fullpayment'];
            $total_price = floatval($row['total_price']);
            $total_paid  = floatval($pdata['total_paid']);
            fputcsv($output, [
                '#' . $row['id'],
                $row['guest_name'],
                date('Y-m-d H:i', strtotime($row['created_at'])),
                strtoupper($row['booking_type']),
                $row['facility_name'] ?? 'N/A',
                $row['area_name'] ?? 'N/A',
                ucfirst($row['mode'] ?? 'daytour'),
                strtoupper($row['status']),
                number_format($total_price, 2, '.', ''),
                $dp ? ($dp['reference_number'] ?? '-') : '-',
                $dp ? date('Y-m-d H:i', strtotime($dp['paid_at'])) : '-',
                $dp ? number_format(floatval($dp['amount_paid']), 2, '.', '') : '-',
                $fp ? ($fp['reference_number'] ?? '-') : '-',
                $fp ? date('Y-m-d H:i', strtotime($fp['paid_at'])) : '-',
                $fp ? number_format(floatval($fp['amount_paid']), 2, '.', '') : '-',
                number_format($total_paid, 2, '.', ''),
                number_format(max(0, $total_price - $total_paid), 2, '.', ''),
            ]);
        }
    }
    fclose($output);
    exit();
}

// ── KPI Metrics ───────────────────────────────────────────────────────────────
$kpi_query = "SELECT
                SUM(total_price) AS grand_total,
                COUNT(*) AS total_count,
                SUM(CASE WHEN booking_type IN ('walkin','walk-in') THEN total_price ELSE 0 END) AS walkin_total,
                SUM(CASE WHEN booking_type = 'online' THEN total_price ELSE 0 END) AS online_total
              FROM bookings b
              $where_sql";
$kpi_res = $conn->query($kpi_query);
$kpi     = $kpi_res ? $kpi_res->fetch_assoc() : [];

$grand_total  = floatval($kpi['grand_total']  ?? 0);
$total_count  = intval($kpi['total_count']    ?? 0);
$walkin_total = floatval($kpi['walkin_total'] ?? 0);
$online_total = floatval($kpi['online_total'] ?? 0);
$avg_sale     = $total_count > 0 ? ($grand_total / $total_count) : 0;

// ── Total actually collected (from payments table) ────────────────────────────
// Build safely:
$pay_where_parts = [];
if ($filter_status === 'confirmed') $pay_where_parts[] = "b.status IN ('confirmed','completed')";
elseif ($filter_status !== 'all' && !empty($filter_status)) $pay_where_parts[] = "b.status = '" . $conn->real_escape_string($filter_status) . "'";
if ($filter_type === 'walkin') $pay_where_parts[] = "b.booking_type IN ('walkin','walk-in')";
elseif ($filter_type === 'online') $pay_where_parts[] = "b.booking_type = 'online'";
if ($filter_period === 'today') $pay_where_parts[] = "DATE(b.created_at) = '$today'";
elseif ($filter_period === 'month') $pay_where_parts[] = "MONTH(b.created_at) = MONTH(CURDATE()) AND YEAR(b.created_at) = YEAR(CURDATE())";
elseif ($filter_period === 'year') $pay_where_parts[] = "YEAR(b.created_at) = YEAR(CURDATE())";
elseif ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $s = $conn->real_escape_string($start_date); $e = $conn->real_escape_string($end_date);
    $pay_where_parts[] = "DATE(b.created_at) BETWEEN '$s' AND '$e'";
}
if (!empty($search)) {
    $s_term = $conn->real_escape_string($search);
    $pay_where_parts[] = "(b.id LIKE '%$s_term%' OR b.guest_name LIKE '%$s_term%' OR b.guest_email LIKE '%$s_term%' OR b.guest_phone LIKE '%$s_term%')";
}
$pay_where_sql = count($pay_where_parts) > 0 ? "AND " . implode(" AND ", $pay_where_parts) : "";

$collected_res = $conn->query("SELECT COALESCE(SUM(p.amount_paid),0) AS total_collected FROM payments p JOIN bookings b ON p.booking_id=b.id WHERE p.status='completed' $pay_where_sql");
$total_collected = $collected_res ? floatval($collected_res->fetch_assoc()['total_collected'] ?? 0) : 0;

// ── Downpayment count (bookings with 2+ payments = had a downpayment phase) ──
$dp_count_res = $conn->query("SELECT COUNT(*) as cnt FROM (SELECT p.booking_id FROM payments p JOIN bookings b ON p.booking_id=b.id WHERE p.status='completed' $pay_where_sql GROUP BY p.booking_id HAVING COUNT(p.id)>=2) AS t");
$dp_count = $dp_count_res ? intval($dp_count_res->fetch_assoc()['cnt'] ?? 0) : 0;

// ── Fetch Detailed Records ────────────────────────────────────────────────────
$sales_query = "SELECT b.*, f.name AS facility_name, a.name AS area_name
                FROM bookings b
                LEFT JOIN facilities f ON b.facility_id = f.id
                LEFT JOIN areas a ON b.area_id = a.id
                $where_sql
                ORDER BY b.created_at DESC";
$sales_result  = $conn->query($sales_query);
$sales_records = [];
if ($sales_result && $sales_result->num_rows > 0) {
    while ($row = $sales_result->fetch_assoc()) {
        $row['_payments'] = get_payments_for_booking($conn, $row['id'], $row['total_price']);
        $sales_records[]  = $row;
    }
}

// Export URL
$export_params         = $_GET;
$export_params['export'] = 'csv';
$export_url            = 'sales_report.php?' . http_build_query($export_params);

$staff_name = isset($user) ? htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) : 'Frontdesk Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <?php require_once '../includes/frontdesk_page_styles.php'; ?>
    <style>
        /* ── Filter Card ── */
        .filter-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0,0,0,.03);
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
            transition: all .2s ease;
            display: inline-block;
        }
        .period-btn.active, .period-btn:hover {
            background: linear-gradient(135deg, #1B7D3A, #27A457);
            color: #fff;
            border-color: transparent;
        }

        /* ── Payment breakdown columns ── */
        .pay-section {
            border-radius: 7px;
            padding: 5px 8px;
            font-size: .72rem;
            border: 1px solid #e0f2ef;
            flex: 1;
        }
        .pay-section.dp { border-color: #ffe082; background: #fffde7; }
        .pay-section.fp { border-color: #a5d6a7; background: #e8f5e9; }
        .pay-section .pay-label {
            font-weight: 700;
            font-size: .63rem;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 2px;
            white-space: nowrap;
        }
        .pay-section.dp .pay-label { color: #f57f17; }
        .pay-section.fp .pay-label { color: #1B7D3A; }
        .pay-section .pay-ref  { color: #444; font-size: .68rem; font-weight: 700; word-break: break-all; }
        .pay-section .pay-date { color: #888; font-size: .63rem; }
        .pay-section .pay-amt  { font-weight: 800; font-size: .78rem; margin-top: 2px; }
        .pay-section.dp .pay-amt { color: #e65100; }
        .pay-section.fp .pay-amt { color: #1B7D3A; }
        .pay-none { color: #bbb; font-size: .68rem; font-style: italic; }
        /* payment method badge */
        .pay-method {
            display: inline-block;
            font-size: .6rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 10px;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .pay-method.gcash   { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .pay-method.walkin  { background: #f3e5f5; color: #6a1b9a; border: 1px solid #ce93d8; }
        /* side-by-side payment pair */
        .pay-pair { display: flex; gap: 5px; min-width: 240px; }

        /* ── Badges ── */
        .badge-status-confirmed { background-color: #e8f5e9; color: #1B7D3A; border: 1px solid #c8e6c9; }
        .badge-status-completed { background-color: #e8f5e9; color: #1B7D3A; border: 1px solid #c8e6c9; }
        .badge-status-pending   { background-color: #fff8e1; color: #f57f17; border: 1px solid #ffe0b2; }
        .badge-status-cancelled { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* ── KPI extra card ── */
        .kpi-tag {
            display: inline-block;
            font-size: .7rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
            margin-top: 3px;
        }
        .kpi-tag.collected { background: #e8f5e9; color: #1B7D3A; }
        .kpi-tag.pending   { background: #fff8e1; color: #f57f17; }

        /* ── Print Header ── */
        .print-header { display: none; }

        /* ── Print Styles ── */
        @media print {
            body { background: #fff !important; color: #000 !important; font-size: 9pt; }
            .sidebar-col, .dash-topbar, .filter-card, .btn, .no-print, nav, .kpi-card { display: none !important; }
            .content, .main-container { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .dash-body { padding: 0 !important; }
            .print-header {
                display: block !important;
                text-align: center;
                border-bottom: 2px solid #1B7D3A;
                padding-bottom: 12px;
                margin-bottom: 14px;
            }
            .print-header h2 { font-size: 16pt; font-weight: bold; color: #1B7D3A; margin: 0 0 4px 0; }
            .print-header p  { margin: 2px 0; font-size: 9pt; color: #444; }
            .print-summary-box {
                display: flex !important;
                justify-content: space-between;
                margin-bottom: 14px;
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 10px 14px;
                border: 1px solid #ccc;
            }
            .print-summary-item { text-align: center; }
            .print-summary-item span { font-size: 7.5pt; color: #555; display: block; }
            .print-summary-item strong { display: block; font-size: 10pt; font-weight: 800; color: #1B7D3A; }

            /* ── Table ── */
            .table-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .section-hdr { margin-bottom: 8px !important; }
            .section-hdr h5 { font-size: 10pt !important; }
            .section-hdr p  { font-size: 7.5pt !important; }

            table.table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 7pt !important;
                table-layout: fixed !important;
            }
            table.table th, table.table td {
                border: 1px solid #bbb !important;
                padding: 4px 5px !important;
                vertical-align: top !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
            }
            table.table th {
                background-color: #e8f5e9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color: #1B7D3A !important;
                font-size: 6.5pt !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
                white-space: nowrap !important;
            }

            /* Column widths for A4 landscape (~277mm usable) */
            table.table th:nth-child(1), table.table td:nth-child(1) { width:  6%; }
            table.table th:nth-child(2), table.table td:nth-child(2) { width: 14%; }
            table.table th:nth-child(3), table.table td:nth-child(3) { width:  9%; }
            table.table th:nth-child(4), table.table td:nth-child(4) { width:  6%; }
            table.table th:nth-child(5), table.table td:nth-child(5) { width: 12%; }
            table.table th:nth-child(6), table.table td:nth-child(6) { width: 35%; }
            table.table th:nth-child(7), table.table td:nth-child(7) { width:  9%; }
            table.table th:nth-child(8), table.table td:nth-child(8) { width:  9%; }

            /* ── Pay pair: two table-cells side by side ── */
            .pay-pair {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
                min-width: unset !important;
                gap: 0 !important;
            }
            .pay-pair .pay-section {
                display: table-cell !important;
                width: 50% !important;
                padding: 3px 4px !important;
                font-size: 6.5pt !important;
                border: 1px solid #ccc !important;
                vertical-align: top !important;
                flex: unset !important;
                border-radius: 0 !important;
            }
            .pay-pair .pay-section.dp {
                background: #fffde7 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-color: #f9a825 !important;
            }
            .pay-pair .pay-section.fp {
                background: #e8f5e9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-color: #43a047 !important;
                border-left: none !important;
            }
            .pay-section .pay-label {
                font-size: 6pt !important;
                font-weight: 800 !important;
                margin-bottom: 2px !important;
                white-space: nowrap !important;
            }
            .pay-section.dp .pay-label { color: #e65100 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .pay-section.fp .pay-label { color: #1B7D3A !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .pay-section .pay-method {
                display: inline-block !important;
                font-size: 5.5pt !important;
                font-weight: 700 !important;
                padding: 0 3px !important;
                border-radius: 3px !important;
                margin-bottom: 2px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .pay-method.gcash  { background:#e3f2fd !important; color:#1565c0 !important; border:1px solid #90caf9 !important; }
            .pay-method.walkin { background:#f3e5f5 !important; color:#6a1b9a !important; border:1px solid #ce93d8 !important; }
            .pay-section .pay-ref  { font-size: 6.5pt !important; font-weight: 700 !important; color: #333 !important; word-break: break-all !important; margin-bottom: 1px !important; }
            .pay-section .pay-date { font-size: 5.8pt !important; color: #666 !important; line-height: 1.4 !important; }
            .pay-section .pay-amt  { font-size: 7.5pt !important; font-weight: 800 !important; margin-top: 2px !important; }
            .pay-section.dp .pay-amt { color: #e65100 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .pay-section.fp .pay-amt { color: #1B7D3A !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .pay-none { font-size: 6pt !important; color: #aaa !important; font-style: italic !important; }

            /* ── Badges ── */
            .badge { font-size: 6pt !important; padding: 1px 4px !important; }
            .badge-status-confirmed, .badge-status-completed {
                background:#e8f5e9 !important; color:#1B7D3A !important;
                -webkit-print-color-adjust:exact; print-color-adjust:exact;
                border:1px solid #a5d6a7 !important;
                border-radius: 8px !important; padding: 1px 5px !important;
                font-size: 6pt !important; display: inline-block !important;
            }
            .badge-status-pending {
                background:#fff8e1 !important; color:#e65100 !important;
                -webkit-print-color-adjust:exact; print-color-adjust:exact;
                border:1px solid #ffe082 !important;
                border-radius: 8px !important; padding: 1px 5px !important;
                font-size: 6pt !important; display: inline-block !important;
            }

            /* ── Grand total ── */
            .table-success {
                background: #e8f5e9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table-success td { font-size: 8pt !important; font-weight: 800 !important; }

            /* ── Signature ── */
            .print-footer {
                display: flex !important;
                justify-content: space-between;
                margin-top: 30px;
                padding-top: 15px;
            }
            .signature-line {
                width: 200px;
                border-top: 1px solid #000;
                text-align: center;
                padding-top: 4px;
                font-size: 8.5pt;
            }

            /* ── A4 Landscape ── */
            @page {
                size: A4 landscape;
                margin: 10mm 12mm;
            }
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
                <div class="dash-topbar-title"><i class="fas fa-file-invoice-dollar me-2" style="color:#1B7D3A;"></i>Sales Report</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-1"></i> Download Records
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                        <li><h6 class="dropdown-header text-uppercase fw-bold text-success" style="font-size:.7rem;">Quick Download CSV</h6></li>
                        <li><a class="dropdown-item py-2" href="<?php echo htmlspecialchars($export_url); ?>"><i class="fas fa-filter me-2 text-success"></i>Download Current Filtered View</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-1" href="sales_report.php?period=all&status=<?= urlencode($filter_status) ?>&export=csv"><i class="fas fa-database me-2 text-secondary"></i>All Records</a></li>
                        <li><a class="dropdown-item py-1" href="sales_report.php?period=month&status=<?= urlencode($filter_status) ?>&export=csv"><i class="fas fa-calendar-check me-2 text-secondary"></i>This Month</a></li>
                        <li><a class="dropdown-item py-1" href="sales_report.php?period=year&status=<?= urlencode($filter_status) ?>&export=csv"><i class="fas fa-calendar me-2 text-secondary"></i>This Year</a></li>
                    </ul>
                </div>
                <button onclick="window.print()" class="btn btn-success btn-sm">
                    <i class="fas fa-print me-1"></i> Print / Save PDF
                </button>
                <span class="dash-topbar-badge"><i class="fas fa-concierge-bell me-1"></i>Front Desk</span>
            </div>
        </div>

        <div class="dash-body">

            <!-- Print Header -->
            <div class="print-header">
                <h2>Sinulom &amp; Bolao Cold Spring Resort</h2>
                <p><strong>OFFICIAL SALES REPORT</strong></p>
                <?php
                $period_titles = [
                    'all'    => 'All Records',
                    'month'  => 'Monthly (' . date('F Y') . ')',
                    'year'   => 'Yearly (' . date('Y') . ')',
                    'today'  => 'Today (' . date('M d, Y') . ')',
                    'custom' => 'Custom Range (' . $start_date . ' to ' . $end_date . ')',
                ];
                $display_period_title = $period_titles[$filter_period] ?? ucfirst($filter_period);
                ?>
                <p>Filter Period: <strong><?php echo $display_period_title; ?></strong> |
                   Booking Type: <strong><?php echo ucfirst($filter_type); ?></strong> |
                   Status: <strong><?php echo ucfirst($filter_status); ?></strong>
                </p>
                <p>Report Generated: <?php echo date('F j, Y h:i A'); ?> | Generated By: <?php echo $staff_name; ?></p>
            </div>

            <!-- Filter Controls -->
            <div class="filter-card no-print">
                <form method="GET" action="sales_report.php" class="row g-3 align-items-end">

                    <!-- Period Buttons -->
                    <div class="col-12 mb-2 d-flex gap-2 flex-wrap align-items-center">
                        <span class="text-muted fw-bold me-2" style="font-size:.85rem;">Period:</span>
                        <?php
                        $periods = [
                            'all'    => 'All Records',
                            'today'  => 'Today',
                            'month'  => 'Monthly',
                            'year'   => 'Yearly',
                            'custom' => 'Custom Range',
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
                        <label class="form-label fw-bold" style="font-size:.82rem;">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-bold" style="font-size:.82rem;">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <?php endif; ?>

                    <div class="col-6 col-md-3">
                        <label class="form-label fw-bold" style="font-size:.82rem;">Booking Type</label>
                        <select name="booking_type" class="form-select form-select-sm">
                            <option value="all"    <?= $filter_type === 'all'    ? 'selected' : '' ?>>All Types</option>
                            <option value="walkin" <?= $filter_type === 'walkin' ? 'selected' : '' ?>>Walk-in Only</option>
                            <option value="online" <?= $filter_type === 'online' ? 'selected' : '' ?>>Online Only</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label fw-bold" style="font-size:.82rem;">Payment Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="confirmed" <?= $filter_status === 'confirmed' ? 'selected' : '' ?>>Confirmed / Paid Sales</option>
                            <option value="all"       <?= $filter_status === 'all'       ? 'selected' : '' ?>>All Statuses</option>
                            <option value="pending"   <?= $filter_status === 'pending'   ? 'selected' : '' ?>>Pending Only</option>
                        </select>
                    </div>

                    <div class="col-8 col-md-3">
                        <label class="form-label fw-bold" style="font-size:.82rem;">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Guest name, email, ref #..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="col-4 col-md-1 d-flex">
                        <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
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
                            <div class="kpi-lbl">Total Sales Amount</div>
                            <span class="kpi-tag collected">Collected: &#8369;<?= number_format($total_collected, 2) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-receipt"></i></div>
                        <div>
                            <div class="kpi-num"><?= $total_count ?></div>
                            <div class="kpi-lbl">Total Transactions</div>
                            <span class="kpi-tag pending">Partial Pay: <?= $dp_count ?></span>
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

            <!-- Printable Summary Box -->
            <div class="print-summary-box" style="display:none;">
                <div class="print-summary-item">
                    <span>Total Sales Amount</span>
                    <strong>&#8369;<?= number_format($grand_total, 2) ?></strong>
                </div>
                <div class="print-summary-item">
                    <span>Total Collected</span>
                    <strong>&#8369;<?= number_format($total_collected, 2) ?></strong>
                </div>
                <div class="print-summary-item">
                    <span>Total Records</span>
                    <strong><?= $total_count ?></strong>
                </div>
                <div class="print-summary-item">
                    <span>Walk-in Revenue</span>
                    <strong>&#8369;<?= number_format($walkin_total, 2) ?></strong>
                </div>
                <div class="print-summary-item">
                    <span>Online Revenue</span>
                    <strong>&#8369;<?= number_format($online_total, 2) ?></strong>
                </div>
                <div class="print-summary-item">
                    <span>Average Sale</span>
                    <strong>&#8369;<?= number_format($avg_sale, 2) ?></strong>
                </div>
            </div>

            <!-- Sales Records Table -->
            <div class="section-hdr d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0"><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Sales Transactions</h5>
                    <p class="text-muted small mb-0">Displaying <?= count($sales_records) ?> transaction(s) — each row shows downpayment & full payment breakdown</p>
                </div>
                <div class="no-print">
                    <span class="badge bg-warning text-dark me-1"><i class="fas fa-circle me-1"></i>Down Payment</span>
                    <span class="badge bg-success me-1"><i class="fas fa-circle me-1"></i>Full Payment</span>
                </div>
            </div>

            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:70px; white-space:nowrap;">#</th>
                                <th style="min-width:120px;">Guest / Transaction</th>
                                <th style="min-width:85px; white-space:nowrap;">Date</th>
                                <th style="min-width:70px;">Type</th>
                                <th style="min-width:110px;">Facility &amp; Area</th>
                                <th style="min-width:250px;">
                                    <span style="color:#e65100; margin-right:6px;"><i class="fas fa-arrow-down"></i> Down</span>
                                    <span style="color:#1B7D3A;"><i class="fas fa-check-circle"></i> Full Payment</span>
                                </th>
                                <th style="min-width:85px;">Status</th>
                                <th class="text-end" style="min-width:90px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sales_records) > 0): ?>
                                <?php foreach ($sales_records as $rec):
                                    $is_walkin    = in_array(strtolower($rec['booking_type']), ['walkin', 'walk-in']);
                                    $status_class = 'badge-status-' . strtolower($rec['status']);
                                    $pdata        = $rec['_payments'];
                                    $dp           = $pdata['downpayment'];
                                    $fp           = $pdata['fullpayment'];
                                    $total_paid   = floatval($pdata['total_paid']);
                                    $total_price  = floatval($rec['total_price']);
                                    $balance      = max(0, $total_price - $total_paid);
                                ?>
                                    <tr>
                                        <!-- Booking # + Guest Name merged -->
                                        <td style="white-space:nowrap;">
                                            <div class="fw-bold text-success" style="font-size:.88rem;">#<?= $rec['id'] ?></div>
                                            <div class="text-muted" style="font-size:.68rem;">TXN-<?= str_pad($rec['id'], 5, '0', STR_PAD_LEFT) ?></div>
                                        </td>

                                        <!-- Guest Name + Phone -->
                                        <td>
                                            <div class="fw-semibold" style="font-size:.82rem; line-height:1.2;"><?= htmlspecialchars($rec['guest_name']) ?></div>
                                            <?php if (!empty($rec['guest_phone'])): ?>
                                                <div class="text-muted" style="font-size:.68rem;"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($rec['guest_phone']) ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Date -->
                                        <td style="white-space:nowrap;">
                                            <div style="font-size:.78rem; font-weight:600;"><?= date('M d, Y', strtotime($rec['created_at'])) ?></div>
                                            <div class="text-muted" style="font-size:.68rem;"><?= date('h:i A', strtotime($rec['created_at'])) ?></div>
                                        </td>

                                        <!-- Booking Type -->
                                        <td>
                                            <?php if ($is_walkin): ?>
                                                <span class="badge bg-success" style="font-size:.67rem;"><i class="fas fa-walking me-1"></i>Walk-in</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary" style="font-size:.67rem;"><i class="fas fa-globe me-1"></i>Online</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Facility & Area -->
                                        <td>
                                            <div class="fw-semibold" style="font-size:.78rem; line-height:1.2;"><?= htmlspecialchars($rec['facility_name'] ?? 'N/A') ?></div>
                                            <div class="text-muted" style="font-size:.68rem;"><?= htmlspecialchars($rec['area_name'] ?? 'General') ?></div>
                                        </td>

                                        <!-- Down Payment + Full Payment side-by-side in ONE cell -->
                                        <td>
                                            <div class="pay-pair">
                                                <!-- Down Payment card -->
                                                <?php if ($dp): ?>
                                                    <?php
                                                        $dp_method = strtolower($dp['method'] ?? 'online');
                                                        $dp_is_gcash = ($dp_method === 'online');
                                                        $dp_ref_raw = $dp['reference_number'] ?? '';
                                                        $dp_ref_clean = preg_replace('/^(GCASH|WALKIN-?CASH|WALKIN)[\s\-]*/i', '', $dp_ref_raw);
                                                        if (empty(trim($dp_ref_clean))) $dp_ref_clean = $dp_ref_raw;
                                                    ?>
                                                    <div class="pay-section dp">
                                                        <div class="pay-label"><i class="fas fa-arrow-down"></i> Down</div>
                                                        <span class="pay-method <?= $dp_is_gcash ? 'gcash' : 'walkin' ?>">
                                                            <?= $dp_is_gcash ? '<i class="fas fa-mobile-alt me-1"></i>GCash' : '<i class="fas fa-money-bill me-1"></i>Walk-in Cash' ?>
                                                        </span>
                                                        <div class="pay-ref">Ref#: <?= htmlspecialchars($dp_ref_clean ?: '—') ?></div>
                                                        <div class="pay-date"><?= date('M d, Y', strtotime($dp['paid_at'])) ?><br><?= date('h:i A', strtotime($dp['paid_at'])) ?></div>
                                                        <div class="pay-amt">&#8369;<?= number_format(floatval($dp['amount_paid']), 2) ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="pay-section dp" style="display:flex;align-items:center;justify-content:center;">
                                                        <span class="pay-none">No downpayment</span>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Full Payment card -->
                                                <?php
                                                    $fp_display = null;
                                                    if ($fp && $fp !== $dp) {
                                                        $fp_display = $fp;
                                                    } elseif ($dp && !$fp) {
                                                        // single payment covering full amount
                                                        $fp_display = $dp;
                                                    }
                                                ?>
                                                <?php if ($fp_display): ?>
                                                    <?php
                                                        $fp_method = strtolower($fp_display['method'] ?? 'online');
                                                        $fp_is_gcash = ($fp_method === 'online');
                                                        $fp_ref_raw = $fp_display['reference_number'] ?? '';
                                                        $fp_ref_clean = preg_replace('/^(GCASH|WALKIN-?CASH|WALKIN)[\s\-]*/i', '', $fp_ref_raw);
                                                        if (empty(trim($fp_ref_clean))) $fp_ref_clean = $fp_ref_raw;
                                                    ?>
                                                    <div class="pay-section fp">
                                                        <div class="pay-label"><i class="fas fa-check-circle"></i> Full Payment</div>
                                                        <span class="pay-method <?= $fp_is_gcash ? 'gcash' : 'walkin' ?>">
                                                            <?= $fp_is_gcash ? '<i class="fas fa-mobile-alt me-1"></i>GCash' : '<i class="fas fa-money-bill me-1"></i>Walk-in Cash' ?>
                                                        </span>
                                                        <div class="pay-ref">Ref#: <?= htmlspecialchars($fp_ref_clean ?: '—') ?></div>
                                                        <div class="pay-date"><?= date('M d, Y', strtotime($fp_display['paid_at'])) ?><br><?= date('h:i A', strtotime($fp_display['paid_at'])) ?></div>
                                                        <div class="pay-amt">&#8369;<?= number_format(floatval($fp_display['amount_paid']), 2) ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="pay-section fp" style="display:flex;align-items:center;justify-content:center;">
                                                        <span class="pay-none">Awaiting payment</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td style="white-space:nowrap;">
                                            <span class="px-2 py-1 rounded-pill text-uppercase fw-bold <?= $status_class ?>" style="font-size:.65rem;">
                                                <?= htmlspecialchars($rec['status']) ?>
                                            </span>
                                            <?php if ($balance > 0.01): ?>
                                                <div class="text-danger mt-1" style="font-size:.65rem; font-weight:600;">
                                                    <i class="fas fa-exclamation-circle"></i> Bal: &#8369;<?= number_format($balance, 2) ?>
                                                </div>
                                            <?php elseif ($total_paid > 0): ?>
                                                <div class="text-success mt-1" style="font-size:.65rem; font-weight:600;">
                                                    <i class="fas fa-check"></i> Fully Paid
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Total Amount -->
                                        <td class="text-end" style="white-space:nowrap;">
                                            <strong class="text-success" style="font-size:.88rem;">&#8369;<?= number_format($total_price, 2) ?></strong>
                                            <?php if ($total_paid > 0 && $total_paid < $total_price - 0.01): ?>
                                                <div class="text-muted" style="font-size:.65rem;">Paid: &#8369;<?= number_format($total_paid, 2) ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Grand Total Row -->
                                <tr class="table-success fw-bold">
                                    <td colspan="5" class="text-end pe-3" style="font-size:.82rem;">
                                        <i class="fas fa-calculator me-1"></i>
                                        Grand Total (<?= count($sales_records) ?> transactions):
                                    </td>
                                    <td class="text-end" colspan="3">
                                        <span style="font-size:1rem; color:#1B7D3A;">&#8369;<?= number_format($grand_total, 2) ?></span>
                                        <div class="text-muted fw-normal" style="font-size:.72rem;">Collected: &#8369;<?= number_format($total_collected, 2) ?></div>
                                    </td>
                                </tr>

                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
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
                    <p class="text-muted mb-0">Frontdesk Staff</p>
                </div>
                <div class="signature-line">
                    <p class="mb-0"><strong>Supervisor / Manager Signature</strong></p>
                    <p class="text-muted mb-0">Verified &amp; Approved</p>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
