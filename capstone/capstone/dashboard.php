<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

require_login();

$user = get_user_info($_SESSION['user_id'], $conn);

// Check if user exists
if (!$user) {
    session_destroy();
    header("Location: " . BASE_URL . "login.php?error=user_not_found");
    exit();
}

// Redirect to role-specific dashboards
if (isset($user['role'])) {
    if ($user['role'] === 'owner') {
        header("Location: " . BASE_URL . "owner/dashboard.php");
        exit();
    } elseif ($user['role'] === 'admin') {
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit();
    } elseif ($user['role'] === 'frontdesk') {
        header("Location: " . BASE_URL . "frontdesk/dashboard.php");
        exit();
    } elseif ($user['role'] === 'supervisor') {
        header("Location: " . BASE_URL . "supervisor/dashboard.php");
        exit();
    }
}

// Date range filter
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = '';
$end_date = date('Y-m-d');

switch ($filter_type) {
    case 'today':
        $start_date = date('Y-m-d');
        break;
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case '3months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        break;
    case '6months':
        $start_date = date('Y-m-d', strtotime('-6 months'));
        break;
    case '1year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        break;
    default:
        $filter_type = 'all';
        $start_date = '';
}

// Build date WHERE clause for queries
$date_filter = '';
if ($start_date) {
    $date_filter = " AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Resort Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .navbar {
            background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1041;
            height: 70px;
            flex-shrink: 0;
        }
        .navbar {
            background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 24px;
        }
        * {
            box-sizing: border-box;
        }
        body {
            display: flex;
            flex-direction: column;
            height: 100vh;
            margin: 0;
            padding: 0;
        }
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        .sidebar {
            background: white;
            padding: 20px;
            height: 100%;
        }
        .sidebar-col {
            width: 250px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1039;
            transition: width 0.3s ease;
            overflow-y: auto;
            flex-shrink: 0;
        }
        .sidebar-col.collapsed {
            width: 60px;
        }
        .sidebar-toggle-btn {
            position: fixed;
            top: 15px;
            left: 265px;
            z-index: 1050;
            background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
            border: none;
            color: white;
            font-size: 18px;
            padding: 10px 12px;
            cursor: pointer;
            border-radius: 0 6px 6px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .sidebar-toggle-btn:hover {
            background: linear-gradient(135deg, #27A457 0%, #1B7D3A 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            padding-right: 14px;
        }
        .sidebar-toggle-btn.collapsed {
            left: 75px;
        }
        .sidebar .nav-link {
            color: #333;
            margin-bottom: 10px;
            border-radius: 5px;
            padding: 10px 15px;
            transition: all 0.3s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar .nav-link:hover {
            background: #f0f0f0;
            color: #1B7D3A;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
            color: white;
        }
        .sidebar-col.collapsed .nav-link span,
        .sidebar-col.collapsed .sidebar > div:not(:first-child) {
            display: none;
        }
        .sidebar-col.collapsed .nav-link {
            padding: 10px 8px;
            text-align: center;
        }
        .sidebar-col.collapsed .nav-link i {
            margin: 0;
        }
        .content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: #f5f5f5;
        }
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .dashboard-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #1B7D3A;
        }
        .stat-label {
            color: #666;
            margin-top: 10px;
        }
            text-transform: capitalize;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .role-owner {
            background: #1B7D3A;
            color: white;
        }
        .role-admin {
            background: #f093fb;
            color: white;
        }
        .role-frontdesk {
            background: #4facfe;
            color: white;
        }
        .role-supervisor {
            background: #43e97b;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <div class="ms-auto">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center text-white" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle" style="font-size: 24px; margin-right: 8px;"></i>
                        <span><?php echo htmlspecialchars($_SESSION['user_first_name'] ?? 'User'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toggle Button -->
    <button class="sidebar-toggle-btn" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="main-container">
        <div class="sidebar-col" id="sidebarCol">
                <div class="sidebar">
                    <?php if (isset($user['role']) && $user['role'] === 'owner'): ?>
                        <div class="text-center mb-4">
                            <h4 class="text-success"><i class="fas fa-water"></i> Resort Owner</h4>
                        </div>
                        <nav class="nav flex-column">
                            <a class="nav-link active" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                            <a class="nav-link" href="owner/manage_staff.php"><i class="fas fa-users"></i> Manage Staff</a>
                            <a class="nav-link" href="owner/manage_areas.php"><i class="fas fa-map-marker-alt"></i> Manage Area</a>
                            <a class="nav-link" href="owner/manage_facilities.php"><i class="fas fa-door-open"></i> Manage Facilities</a>
                            <a class="nav-link" href="owner/bookings.php"><i class="fas fa-calendar"></i> Bookings</a>
                            <a class="nav-link" href="owner/archive_facilities.php"><i class="fas fa-archive"></i> Archives</a>
                        </nav>
                    <?php else: ?>
                    <h5 class="mb-4">Menu</h5>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                        
                        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                            <hr>
                            <h6 class="mb-3 mt-3">Admin Functions</h6>
                            <a class="nav-link" href="admin/walkin_booking.php"><i class="fas fa-plus-circle"></i> Walk-in Booking</a>
                            <a class="nav-link" href="admin/online_bookings.php"><i class="fas fa-check-circle"></i> Online Bookings</a>
                            <a class="nav-link" href="admin/bookings_history.php"><i class="fas fa-history"></i> Booking History</a>
                        <?php elseif (isset($user['role']) && $user['role'] === 'frontdesk'): ?>
                            <hr>
                            <h6 class="mb-3 mt-3">Front Desk Functions</h6>
                            <a class="nav-link" href="frontdesk/walkin_booking.php"><i class="fas fa-plus-circle"></i> Walk-in Booking</a>
                            <a class="nav-link" href="frontdesk/online_bookings.php"><i class="fas fa-check-circle"></i> Online Bookings</a>
                            <a class="nav-link" href="frontdesk/bookings_history.php"><i class="fas fa-history"></i> Booking History</a>
                        <?php elseif (isset($user['role']) && $user['role'] === 'supervisor'): ?>
                            <hr>
                            <h6 class="mb-3 mt-3">Supervisor Functions</h6>
                            <a class="nav-link" href="supervisor/maintenance.php"><i class="fas fa-tools"></i> Maintenance</a>
                            <a class="nav-link" href="supervisor/maintenance_history.php"><i class="fas fa-history"></i> Maintenance History</a>
                            <a class="nav-link" href="supervisor/facilities.php"><i class="fas fa-door-open"></i> Facilities Status</a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content" id="contentArea">
                    <?php display_success_message(); ?>
                    <?php display_error_message(); ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="dashboard-card stat-card">
                                <div class="stat-number">
                                    <?php
                                    $query = "SELECT COUNT(*) as count FROM bookings WHERE 1=1" . $date_filter;
                                    $result = $conn->query($query);
                                    $row = $result->fetch_assoc();
                                    echo $row['count'];
                                    ?>
                                </div>
                                <div class="stat-label"><i class="fas fa-calendar"></i> Total Bookings</div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="dashboard-card stat-card">
                                <div class="stat-number">
                                    <?php
                                    $query = "SELECT SUM(total_price) as total_revenue FROM bookings WHERE status IN ('completed', 'pending', 'confirmed')" . $date_filter;
                                    $result = $conn->query($query);
                                    $row = $result->fetch_assoc();
                                    $revenue = $row['total_revenue'] ?: 0;
                                    echo 'â‚±' . number_format($revenue, 0);
                                    ?>
                                </div>
                                <div class="stat-label"><i class="fas fa-money-bill-wave"></i> Total Revenue</div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="dashboard-card stat-card">
                                <div class="stat-number">
                                    <?php
                                    // Calculate occupancy rate
                                    $facilities_result = $conn->query("SELECT COUNT(*) as count FROM facilities WHERE status = 'available'");
                                    $facilities_row = $facilities_result->fetch_assoc();
                                    $total_facilities = $facilities_row['count'] ?: 1;
                                    
                                    $occupied_result = $conn->query("SELECT COUNT(DISTINCT facility_id) as count FROM bookings WHERE status IN ('pending', 'confirmed') AND check_out_date >= CURDATE()");
                                    $occupied_row = $occupied_result->fetch_assoc();
                                    $occupied_facilities = $occupied_row['count'] ?: 0;
                                    
                                    $occupancy_rate = ($total_facilities > 0) ? round(($occupied_facilities / $total_facilities) * 100, 1) : 0;
                                    echo $occupancy_rate . '%';
                                    ?>
                                </div>
                                <div class="stat-label"><i class="fas fa-chart-pie"></i> Occupancy Rate</div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($user['role']) && $user['role'] === 'owner'): ?>
                    <!-- Date Range Filter -->
                    <div class="dashboard-card mb-4">
                        <div class="row align-items-end">
                            <div class="col-md-8">
                                <h5 class="mb-3"><i class="fas fa-filter"></i> Analytics Filter</h5>
                                <div class="btn-group" role="group">
                                    <a href="?filter=all" class="btn btn-sm <?php echo $filter_type === 'all' ? 'btn-success' : 'btn-outline-secondary'; ?>">All Time</a>
                                    <a href="?filter=today" class="btn btn-sm <?php echo $filter_type === 'today' ? 'btn-success' : 'btn-outline-secondary'; ?>">Today</a>
                                    <a href="?filter=7days" class="btn btn-sm <?php echo $filter_type === '7days' ? 'btn-success' : 'btn-outline-secondary'; ?>">Last 7 Days</a>
                                    <a href="?filter=30days" class="btn btn-sm <?php echo $filter_type === '30days' ? 'btn-success' : 'btn-outline-secondary'; ?>">Last 30 Days</a>
                                    <a href="?filter=3months" class="btn btn-sm <?php echo $filter_type === '3months' ? 'btn-success' : 'btn-outline-secondary'; ?>">Last 3 Months</a>
                                    <a href="?filter=6months" class="btn btn-sm <?php echo $filter_type === '6months' ? 'btn-success' : 'btn-outline-secondary'; ?>">Last 6 Months</a>
                                    <a href="?filter=1year" class="btn btn-sm <?php echo $filter_type === '1year' ? 'btn-success' : 'btn-outline-secondary'; ?>">Last Year</a>
                                    <button type="button" class="btn btn-sm <?php echo $filter_type === 'custom' ? 'btn-success' : 'btn-outline-secondary'; ?>" data-bs-toggle="modal" data-bs-target="#customDateModal">Custom Range</button>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php if ($start_date): ?>
                                    <small class="text-muted"><strong>Showing:</strong> <?php echo date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></small>
                                <?php else: ?>
                                    <small class="text-muted"><strong>Showing:</strong> All Time</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Booking Analytics -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-chart-line"></i> Total Bookings (Daily / Monthly / Yearly)</h5>
                                <hr>
                                <div style="display:flex;flex-direction:column;gap:12px;">
                                    <canvas id="bookingsDailyLine" height="80"></canvas>
                                    <canvas id="bookingsMonthlyLine" height="80"></canvas>
                                    <canvas id="bookingsYearlyLine" height="80"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-chart-pie"></i> Walk-in vs Online</h5>
                                <hr>
                                <canvas id="bookingTypeDonut" height="140"></canvas>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-chart-bar"></i> Peak Booking Days</h5>
                                <hr>
                                <canvas id="weekendWeekdayBar" height="140"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Analytics -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-dollar-sign"></i> Monthly Revenue (Last 12 Months)</h5>
                                <hr>
                                <canvas id="monthlyRevenueChart" height="120"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-building"></i> Revenue per Facility</h5>
                                <hr>
                                <canvas id="revenuePerFacilityBar" height="120"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Facility Analytics -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-door-open"></i> Most Used Facilities</h5>
                                <hr>
                                <canvas id="facilityUsageBar" height="120"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-sort-amount-down"></i> Least vs Most Used (Sorted)</h5>
                                <hr>
                                <canvas id="sortedFacilityUsage" height="120"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Summary -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-tools"></i> Maintenance Reports (Last 12 Months)</h5>
                                <hr>
                                <canvas id="maintenanceLineChart" height="120"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5><i class="fas fa-wrench"></i> Most Frequently Maintained Facility</h5>
                                <hr>
                                <canvas id="maintenanceFacilityBar" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Custom Date Range Modal -->
    <div class="modal fade" id="customDateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Custom Date Range</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET" action="dashboard.php">
                    <div class="modal-body">
                        <input type="hidden" name="filter" value="custom">
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $filter_type === 'custom' ? $start_date : date('Y-m-d', strtotime('-30 days')); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $filter_type === 'custom' ? $end_date : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (isset($user['role']) && $user['role'] === 'owner'): ?>
        /* ------------------- Booking datasets ------------------- */
        <?php
        // Daily bookings (last 7 days)
        $bd_labels = [];
        $bd_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $res = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE DATE(created_at) = '$d'");
            $r = $res->fetch_assoc();
            $bd_labels[] = date('M d', strtotime($d));
            $bd_data[] = intval($r['cnt'] ?: 0);
        }

        // Monthly bookings (last 12 months)
        $bm_labels = [];
        $bm_data = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = date('Y-m-01', strtotime("-$i months"));
            $y = date('Y', strtotime($m));
            $mo = date('m', strtotime($m));
            $res = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE YEAR(created_at) = $y AND MONTH(created_at) = $mo");
            $r = $res->fetch_assoc();
            $bm_labels[] = date('M Y', strtotime($m));
            $bm_data[] = intval($r['cnt'] ?: 0);
        }

        // Yearly bookings (last 5 years)
        $by_labels = [];
        $by_data = [];
        for ($i = 4; $i >= 0; $i--) {
            $yr = date('Y', strtotime("-$i years"));
            $res = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE YEAR(created_at) = $yr");
            $r = $res->fetch_assoc();
            $by_labels[] = $yr;
            $by_data[] = intval($r['cnt'] ?: 0);
        }

        // Walk-in vs Online
        $wt_query = "SELECT booking_type, COUNT(*) as cnt FROM bookings WHERE 1=1" . $date_filter . " GROUP BY booking_type";
        $wt_res = $conn->query($wt_query);
        $wt_map = ['online' => 0, 'walkin' => 0, 'walk-in' => 0];
        while ($row = $wt_res->fetch_assoc()) {
            $k = strtolower($row['booking_type'] ?: 'other');
            $wt_map[$k] = intval($row['cnt']);
        }
        $walkin_count = ($wt_map['walkin'] + $wt_map['walk-in']);
        $online_count = $wt_map['online'];

        // Weekend vs Weekday
        $wk_date_filter = $start_date ? " AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'" : " AND DATE(created_at) >= '" . date('Y-m-d', strtotime('-29 days')) . "'";
        $wk_res_weekend = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE 1=1" . $wk_date_filter . " AND DAYOFWEEK(created_at) IN (1,7)");
        $wk_row_w = $wk_res_weekend->fetch_assoc();
        $wk_res_weekday = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE 1=1" . $wk_date_filter . " AND DAYOFWEEK(created_at) NOT IN (1,7)");
        $wk_row_d = $wk_res_weekday->fetch_assoc();

        // Revenue per facility
        $rf_labels = [];
        $rf_data = [];
        $rf_query = "SELECT f.name, COALESCE(SUM(b.total_price),0) as revenue FROM facilities f LEFT JOIN bookings b ON b.facility_id = f.id WHERE 1=1" . str_replace('created_at', 'b.created_at', $date_filter) . " GROUP BY f.id ORDER BY revenue DESC LIMIT 12";
        $rf_q = $conn->query($rf_query);
        while ($r = $rf_q->fetch_assoc()) {
            $rf_labels[] = addslashes($r['name']);
            $rf_data[] = floatval($r['revenue']);
        }

        // Facility usage (most used)
        $fu_labels = [];
        $fu_data = [];
        $fu_query = "SELECT f.name, COUNT(b.id) as cnt FROM facilities f LEFT JOIN bookings b ON b.facility_id = f.id WHERE 1=1" . str_replace('created_at', 'b.created_at', $date_filter) . " GROUP BY f.id ORDER BY cnt DESC LIMIT 10";
        $fu_q = $conn->query($fu_query);
        while ($r = $fu_q->fetch_assoc()) {
            $fu_labels[] = addslashes($r['name']);
            $fu_data[] = intval($r['cnt']);
        }

        // Sorted facility usage (asc)
        $sfu_labels = [];
        $sfu_data = [];
        $sfu_query = "SELECT f.name, COUNT(b.id) as cnt FROM facilities f LEFT JOIN bookings b ON b.facility_id = f.id WHERE 1=1" . str_replace('created_at', 'b.created_at', $date_filter) . " GROUP BY f.id ORDER BY cnt ASC LIMIT 10";
        $sfu_q = $conn->query($sfu_query);
        while ($r = $sfu_q->fetch_assoc()) {
            $sfu_labels[] = addslashes($r['name']);
            $sfu_data[] = intval($r['cnt']);
        }

        // Maintenance: reports per month (last 12 months) using scheduled_date
        $m_labels = [];
        $m_data = [];
        for ($i = 11; $i >= 0; $i--) {
            $dt = date('Y-m-01', strtotime("-$i months"));
            $y = date('Y', strtotime($dt));
            $mo = date('m', strtotime($dt));
            $res = $conn->query("SELECT COUNT(*) as cnt FROM maintenance WHERE YEAR(scheduled_date) = $y AND MONTH(scheduled_date) = $mo");
            $r = $res->fetch_assoc();
            $m_labels[] = date('M Y', strtotime($dt));
            $m_data[] = intval($r['cnt'] ?: 0);
        }

        // Maintenance per facility (top 10)
        $mf_labels = [];
        $mf_data = [];
        $mf_q = $conn->query("SELECT f.name, COUNT(m.id) as cnt FROM maintenance m JOIN facilities f ON m.facility_id = f.id GROUP BY f.id ORDER BY cnt DESC LIMIT 10");
        while ($r = $mf_q->fetch_assoc()) {
            $mf_labels[] = addslashes($r['name']);
            $mf_data[] = intval($r['cnt']);
        }
        ?>

        // Helper to parse PHP arrays into JS
        const php = {
            bd_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $bd_labels)); ?>],
            bd_data: [<?php echo implode(', ', $bd_data); ?>],
            bm_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $bm_labels)); ?>],
            bm_data: [<?php echo implode(', ', $bm_data); ?>],
            by_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $by_labels)); ?>],
            by_data: [<?php echo implode(', ', $by_data); ?>],
            walkin: <?php echo $walkin_count; ?>,
            online: <?php echo $online_count; ?>,
            weekend: <?php echo intval($wk_row_w['cnt'] ?: 0); ?>,
            weekday: <?php echo intval($wk_row_d['cnt'] ?: 0); ?>,
            rf_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $rf_labels)); ?>],
            rf_data: [<?php echo implode(', ', $rf_data); ?>],
            fu_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $fu_labels)); ?>],
            fu_data: [<?php echo implode(', ', $fu_data); ?>],
            sfu_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $sfu_labels)); ?>],
            sfu_data: [<?php echo implode(', ', $sfu_data); ?>],
            m_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $m_labels)); ?>],
            m_data: [<?php echo implode(', ', $m_data); ?>],
            mf_labels: [<?php echo implode(', ', array_map(fn($l) => "'".addslashes($l)."'", $mf_labels)); ?>],
            mf_data: [<?php echo implode(', ', $mf_data); ?>]
        };

        // Bookings charts
        new Chart(document.getElementById('bookingsDailyLine').getContext('2d'), {
            type: 'line',
            data: { labels: php.bd_labels, datasets: [{ label: 'Bookings (Daily)', data: php.bd_data, borderColor: '#1B7D3A', backgroundColor: 'rgba(39,164,87,0.08)', fill: true }] },
            options: { responsive:true, plugins:{legend:{display:false}} }
        });

        new Chart(document.getElementById('bookingsMonthlyLine').getContext('2d'), {
            type: 'line',
            data: { labels: php.bm_labels, datasets: [{ label: 'Bookings (Monthly)', data: php.bm_data, borderColor: '#27A457', backgroundColor: 'rgba(27,125,58,0.08)', fill: true }] },
            options: { responsive:true, plugins:{legend:{display:false}} }
        });

        new Chart(document.getElementById('bookingsYearlyLine').getContext('2d'), {
            type: 'line',
            data: { labels: php.by_labels, datasets: [{ label: 'Bookings (Yearly)', data: php.by_data, borderColor: '#145a2a', backgroundColor: 'rgba(20,90,42,0.08)', fill: true }] },
            options: { responsive:true, plugins:{legend:{display:false}} }
        });

        // Walk-in vs Online donut
        new Chart(document.getElementById('bookingTypeDonut').getContext('2d'), {
            type: 'doughnut',
            data: { labels: ['Walk-in','Online'], datasets:[{ data:[php.walkin, php.online], backgroundColor:['#27A457','#1B7D3A'] }] ,
            },
            options:{ responsive:true }
        });

        // Weekend vs Weekday bar
        new Chart(document.getElementById('weekendWeekdayBar').getContext('2d'), {
            type: 'bar',
            data: { labels:['Weekend','Weekday'], datasets:[{ label:'Bookings (last 30 days)', data:[php.weekend, php.weekday], backgroundColor:['#27A457','#1B7D3A'] }] },
            options:{ responsive:true }
        });

        // Monthly revenue (reuse bm labels) - fetch revenue per month
        <?php
        $mr_data = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = date('Y-m-01', strtotime("-$i months"));
            $y = date('Y', strtotime($m));
            $mo = date('m', strtotime($m));
            $res = $conn->query("SELECT COALESCE(SUM(total_price),0) as revenue FROM bookings WHERE YEAR(created_at) = $y AND MONTH(created_at) = $mo");
            $r = $res->fetch_assoc();
            $mr_data[] = floatval($r['revenue']);
        }
        ?>
        new Chart(document.getElementById('monthlyRevenueChart').getContext('2d'), {
            type: 'line',
            data: { labels: php.bm_labels, datasets:[{ label:'Revenue (â‚±)', data: [<?php echo implode(', ', $mr_data); ?>], borderColor:'#1B7D3A', backgroundColor:'rgba(27,125,58,0.08)', fill:true }] },
            options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback: function(v){ return 'â‚±'+Number(v).toLocaleString(); } } } } }
        });

        // Revenue per facility
        new Chart(document.getElementById('revenuePerFacilityBar').getContext('2d'), {
            type: 'bar',
            data: { labels: php.rf_labels, datasets:[{ label:'Revenue (â‚±)', data: php.rf_data, backgroundColor:'#27A457' }] },
            options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback: function(v){ return 'â‚±'+Number(v).toLocaleString(); } } } } }
        });

        // Facility usage
        new Chart(document.getElementById('facilityUsageBar').getContext('2d'), {
            type: 'bar',
            data: { labels: php.fu_labels, datasets:[{ label:'Bookings', data: php.fu_data, backgroundColor:'#1B7D3A' }] },
            options:{ responsive:true, plugins:{legend:{display:false}} }
        });

        // Sorted facility usage (least -> most)
        new Chart(document.getElementById('sortedFacilityUsage').getContext('2d'), {
            type: 'bar',
            data: { labels: php.sfu_labels, datasets:[{ label:'Bookings', data: php.sfu_data, backgroundColor:'#27A457' }] },
            options:{ responsive:true, plugins:{legend:{display:false}} }
        });

        // Maintenance line (last 12 months)
        new Chart(document.getElementById('maintenanceLineChart').getContext('2d'), {
            type: 'line',
            data: { labels: php.m_labels, datasets:[{ label:'Reports', data: php.m_data, borderColor:'#ff6b6b', backgroundColor:'rgba(255,107,107,0.08)', fill:true }] },
            options:{ responsive:true, plugins:{legend:{display:false}} }
        });

        // Maintenance per facility
        new Chart(document.getElementById('maintenanceFacilityBar').getContext('2d'), {
            type: 'bar',
            data: { labels: php.mf_labels, datasets:[{ label:'Reports', data: php.mf_data, backgroundColor:'#ffd89b' }] },
            options:{ responsive:true, plugins:{legend:{display:false}} }
        });

        <?php endif; ?>
    </script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                const sidebarCol = document.getElementById('sidebarCol');
                sidebarCol.classList.toggle('collapsed');
                sidebarToggle.classList.toggle('collapsed');
            });
        }
    </script>
</body>
</html>


