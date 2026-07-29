<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);
// Handle add booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_booking') {
    $facility_id = intval($_POST['facility_id']);
    $guest_name = escape_input($_POST['guest_name'], $conn);
    $guest_email = escape_input($_POST['guest_email'], $conn);
    $guest_phone = escape_input($_POST['guest_phone'], $conn);
    $check_in = escape_input($_POST['check_in_date'], $conn);
    $check_out = escape_input($_POST['check_out_date'], $conn);
    $num_guests = intval($_POST['num_guests']);
    $mode = escape_input($_POST['mode'] ?? 'overnight', $conn);
    $total_price = floatval($_POST['total_price']);
    $stmt = $conn->prepare("INSERT INTO bookings (facility_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, num_guests, mode, booking_type, status, total_price, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'walk_in', 'confirmed', ?, ?)");
    $created_by = $_SESSION['user_id'];
    $stmt->bind_param("isssssisdi", $facility_id, $guest_name, $guest_email, $guest_phone, $check_in, $check_out, $num_guests, $mode, $total_price, $created_by);
    if ($stmt->execute()) {
        $booking_id = $stmt->insert_id;
        header("Location: " . BASE_URL . "receipt.php?booking_id=" . $booking_id);
        exit();
    } else {
        set_error_message('Error creating booking: ' . $conn->error);
    }
    $stmt->close();
}

// Get all unique users who have approved/created bookings for filter dropdown
$users_result = $conn->query("SELECT DISTINCT u.id, u.first_name, u.last_name, u.role FROM bookings b LEFT JOIN users u ON b.created_by = u.id WHERE u.id IS NOT NULL ORDER BY u.first_name, u.last_name");

// Search handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$approved_by = isset($_GET['approved_by']) ? intval($_GET['approved_by']) : '';
$bookings_query = "SELECT b.*, f.name as facility_name, a.name as area_name, u.first_name, u.last_name, u.role FROM bookings b JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id LEFT JOIN users u ON b.created_by = u.id";
$where = [];
if ($approved_by) {
    $where[] = "b.created_by = $approved_by";
}
if (!empty($search)) {
    $search_sql = $conn->real_escape_string($search);
    $where[] = "(b.guest_name LIKE '%$search_sql%' OR f.name LIKE '%$search_sql%' OR b.mode LIKE '%$search_sql%')";
}
if (count($where) > 0) {
    $bookings_query .= " WHERE " . implode(' AND ', $where);
}
$bookings_query .= " ORDER BY b.created_at DESC";
$bookings_result = $conn->query($bookings_query);

// Get facilities for dropdown
$facilities_result = $conn->query("SELECT * FROM facilities ORDER BY name");
// Booking analytics
$total_bookings = $conn->query("SELECT COUNT(*) AS cnt FROM bookings")->fetch_assoc()['cnt'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total_price) AS revenue FROM bookings WHERE status = 'confirmed'")->fetch_assoc()['revenue'] ?? 0;

// Monthly booking chart data
$monthly_result = $conn->query("SELECT MONTH(check_in_date) AS month, COUNT(*) AS cnt FROM bookings WHERE YEAR(check_in_date) = YEAR(CURDATE()) GROUP BY month ORDER BY month");
$monthly_labels = [];
$monthly_counts = [];
for ($i = 1; $i <= 12; $i++) { $monthly_labels[] = date('M', mktime(0,0,0,$i,1)); $monthly_counts[] = 0; }
while ($row = $monthly_result->fetch_assoc()) { $monthly_counts[$row['month']-1] = $row['cnt']; }

// Booking status breakdown
$status_result = $conn->query("SELECT status, COUNT(*) AS cnt FROM bookings GROUP BY status");
$status_labels = [];
$status_counts = [];
while ($row = $status_result->fetch_assoc()) { $status_labels[] = ucfirst($row['status']); $status_counts[] = $row['cnt']; }

// Bookings per location
$location_result = $conn->query("SELECT a.name AS area_name, COUNT(b.id) AS cnt FROM bookings b LEFT JOIN areas a ON b.area_id = a.id GROUP BY b.area_id ORDER BY cnt DESC, area_name");
$location_labels = [];
$location_counts = [];
while ($row = $location_result->fetch_assoc()) { $location_labels[] = $row['area_name'] ?? '-'; $location_counts[] = $row['cnt']; }

// Top 5 most booked amenities
$amenity_result = $conn->query("SELECT am.name, COUNT(ba.id) AS cnt FROM booking_addons ba LEFT JOIN amenities am ON ba.amenity_id = am.id GROUP BY ba.amenity_id ORDER BY cnt DESC, am.name LIMIT 5");
$amenity_labels = [];
$amenity_counts = [];
while ($row = $amenity_result->fetch_assoc()) { $amenity_labels[] = $row['name']; $amenity_counts[] = $row['cnt']; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Analytics - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <?php require_once '../includes/owner_page_styles.php'; ?>
    <style>
    /* ── Booking Analytics: fit 12-column table without scroll ── */
    .table-card { padding: 14px; }
    .table-card .table thead th {
        padding: 6px 7px;
        font-size: .68rem;
        white-space: nowrap;
    }
    .table-card .table tbody td {
        padding: 5px 7px;
        font-size: .78rem;
        vertical-align: middle;
        white-space: nowrap;
    }
    /* Fixed column widths to distribute space evenly */
    .table-card .table th:nth-child(1),
    .table-card .table td:nth-child(1)  { width: 42px; }   /* ID */
    .table-card .table th:nth-child(2),
    .table-card .table td:nth-child(2)  { width: 110px; }  /* Guest Name */
    .table-card .table th:nth-child(3),
    .table-card .table td:nth-child(3)  { width: 72px; }   /* Mode */
    .table-card .table th:nth-child(4),
    .table-card .table td:nth-child(4)  { width: 80px; }   /* Location */
    .table-card .table th:nth-child(5),
    .table-card .table td:nth-child(5)  { width: 100px; }  /* Facility */
    .table-card .table th:nth-child(6),
    .table-card .table td:nth-child(6)  { width: 82px; }   /* Check-in */
    .table-card .table th:nth-child(7),
    .table-card .table td:nth-child(7)  { width: 82px; }   /* Check-out */
    .table-card .table th:nth-child(8),
    .table-card .table td:nth-child(8)  { width: 50px; }   /* Guests */
    .table-card .table th:nth-child(9),
    .table-card .table td:nth-child(9)  { width: 85px; }   /* Total Price */
    .table-card .table th:nth-child(10),
    .table-card .table td:nth-child(10) { width: 76px; }   /* Status */
    .table-card .table th:nth-child(11),
    .table-card .table td:nth-child(11) { width: 68px; }   /* Type */
    .table-card .table th:nth-child(12),
    .table-card .table td:nth-child(12) { min-width: 100px; } /* Approved By */
    /* Approved By dropdown in header */
    .table-card .table th .form-select-sm {
        font-size: .68rem;
        padding: 2px 4px;
        min-width: 70px !important;
    }
    /* Allow overflow-x only at small viewports */
    .table-responsive { overflow-x: visible; }
    @media (max-width: 1280px) {
        .table-responsive { overflow-x: auto; }
    }
    </style>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/owner_sidebar.php'; ?>
    <div class="content">
        <!-- topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-chart-bar me-2" style="color:#1B7D3A;"></i>Booking Analytics</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_messages(); ?>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $total_bookings; ?>"><?php echo $total_bookings; ?></div>
                            <div class="kpi-lbl">Total Bookings</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-peso-sign"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo number_format($total_revenue, 2); ?>" data-prefix="₱"><?php echo '₱' . number_format($total_revenue, 2); ?></div>
                            <div class="kpi-lbl">Total Revenue</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts 2x2 -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Monthly Bookings</h6>
                            <span class="chart-badge green">This Year</span>
                        </div>
                        <div class="chart-wrap"><canvas id="monthlyBookingChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Status Breakdown</h6>
                            <span class="chart-badge blue">All Time</span>
                        </div>
                        <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Bookings per Location</h6>
                            <span class="chart-badge orange">All Time</span>
                        </div>
                        <div class="chart-wrap"><canvas id="locationChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Top 5 Most Booked Amenities</h6>
                            <span class="chart-badge purple">All Time</span>
                        </div>
                        <div class="chart-wrap"><canvas id="amenityChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- All Bookings Table -->
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="section-hdr mb-0"><h5>All Bookings</h5></div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <form method="GET" class="search-bar">
                            <input type="text" class="form-control" name="search" placeholder="Search by name or type..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn-add"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn-del"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Guest Name</th>
                                <th>Mode</th>
                                <th>Location</th>
                                <th>Facility</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Guests</th>
                                <th>Total Price</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>
                                    <form method="GET" class="d-inline-flex align-items-center" style="gap:4px;">
                                        <span style="font-weight:400;">Approved By</span>
                                        <select name="approved_by" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:90px;">
                                            <option value="">All</option>
                                            <?php if ($users_result && $users_result->num_rows > 0): ?>
                                                <?php mysqli_data_seek($users_result, 0); while ($u = $users_result->fetch_assoc()): ?>
                                                    <?php if (in_array($u['role'], ['admin', 'frontdesk'])): ?>
                                                        <option value="<?php echo $u['id']; ?>" <?php if ($approved_by == $u['id']) echo 'selected'; ?>>
                                                            <?php echo htmlspecialchars(ucfirst($u['role'])); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                        <?php if ($approved_by): ?>
                                            <a href="?" class="btn-del ms-1" title="Clear filter" style="padding:2px 6px;"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </form>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                    <td><?php echo ucfirst($booking['mode'] ?? 'overnight'); ?></td>
                                    <td><?php echo htmlspecialchars($booking['area_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($booking['facility_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                                    <td><?php echo $booking['num_guests']; ?></td>
                                    <td>₱<?php echo number_format($booking['total_price'], 2); ?></td>
                                    <td>
                                        <?php
                                        $st = $booking['status'];
                                        $pc = $st === 'completed' ? 'pill-green' : ($st === 'confirmed' ? 'pill-blue' : ($st === 'pending' ? 'pill-yellow' : 'pill-red'));
                                        ?>
                                        <span class="pill <?php echo $pc; ?>"><?php echo ucfirst($st); ?></span>
                                    </td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $booking['booking_type'])); ?></td>
                                    <td><?php echo ($booking['first_name'] && $booking['last_name']) ? htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) . ' (' . ucfirst($booking['role']) . ')' : '-'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.kpi-num[data-count]').forEach((el,i) => {
        const t = parseFloat(el.getAttribute('data-count'));
        const pfx = el.getAttribute('data-prefix')||'';
        const sfx = el.getAttribute('data-suffix')||'';
        setTimeout(() => { const s=performance.now(); const u=(n)=>{ const p=Math.min((n-s)/800,1); const v=(1-Math.pow(1-p,3))*t; el.textContent=pfx+(Number.isInteger(t)?Math.round(v).toLocaleString():v.toFixed(1))+sfx; if(p<1)requestAnimationFrame(u); }; requestAnimationFrame(u); }, i*80);
    });

    // Monthly Bookings
    new Chart(document.getElementById('monthlyBookingChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [{ label: 'Bookings', data: <?php echo json_encode($monthly_counts); ?>, backgroundColor: '#43e97b' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    // Status Breakdown
    new Chart(document.getElementById('statusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($status_labels); ?>,
            datasets: [{ data: <?php echo json_encode($status_counts); ?>, backgroundColor: ['#43e97b','#ffd89b','#ff6b6b','#4facfe','#a3d9a5'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom' } } }
    });
    // Bookings per Location
    new Chart(document.getElementById('locationChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($location_labels); ?>,
            datasets: [{ label: 'Bookings', data: <?php echo json_encode($location_counts); ?>, backgroundColor: '#1B7D3A' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    // Top Amenities
    new Chart(document.getElementById('amenityChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($amenity_labels); ?>,
            datasets: [{ label: 'Bookings', data: <?php echo json_encode($amenity_counts); ?>, backgroundColor: '#95c2e7' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
});
initOwnerSidebar('ownerSidebarCollapsed');
</script>
</body></html>
