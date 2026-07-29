<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_role('owner');

// Ensure $user is defined for sidebar
if (isset($_SESSION['user_id'])) {
    $user = get_user_info($_SESSION['user_id'], $conn);
} else {
    $user = ['first_name' => 'Owner', 'last_name' => '', 'role' => 'owner'];
}

// Fetch area metrics
$areas_result = $conn->query("SELECT id, name FROM areas ORDER BY name");
$areas = [];
while ($row = $areas_result->fetch_assoc()) {
    $areas[$row['id']] = $row['name'];
}

$metrics = [];
foreach ($areas as $area_id => $area_name) {
    // Revenue
    $revenue = $conn->query("SELECT SUM(total_price) AS revenue FROM bookings WHERE area_id = $area_id AND status = 'confirmed'")->fetch_assoc()['revenue'] ?? 0;
    // Bookings
    $bookings = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE area_id = $area_id")->fetch_assoc()['cnt'] ?? 0;
    // Cancellations
    $cancellations = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE area_id = $area_id AND status = 'cancelled'")->fetch_assoc()['cnt'] ?? 0;
    // Maintenance cost (not available, set to 0)
    $maintenance = 0;
    // Expenses (not available, set to 0)
    $expenses = 0;
    // Net profit
    $net_profit = $revenue - ($expenses + $maintenance);
    // Occupancy rate
    $days = $conn->query("SELECT SUM(DATEDIFF(check_out_date, check_in_date)) AS days FROM bookings WHERE area_id = $area_id AND status = 'confirmed'")->fetch_assoc()['days'] ?? 0;
    $total_days = 365; // Assume 1 year for simplicity
    $occupancy_rate = $total_days > 0 ? round(($days / $total_days) * 100, 2) : 0;
    // Avg daily bookings
    $avg_daily = $total_days > 0 ? round($bookings / $total_days, 2) : 0;
    // Customer satisfaction
    // Find all facility IDs for this area
    $facility_ids = [];
    $facility_result = $conn->query("SELECT id FROM facilities WHERE area_id = $area_id");
    while ($frow = $facility_result->fetch_assoc()) {
        $facility_ids[] = $frow['id'];
    }
    $satisfaction = 0;
    if (count($facility_ids) > 0) {
        $facility_ids_str = implode(',', $facility_ids);
        $feedback_result = $conn->query("SELECT AVG(rating) AS avg_rating FROM feedback WHERE facility_id IN ($facility_ids_str)");
        if ($feedback_result && $feedback_result->num_rows > 0) {
            $satisfaction = $feedback_result->fetch_assoc()['avg_rating'] ?? 0;
        }
    }
    $metrics[] = [
        'name' => $area_name,
        'revenue' => $revenue,
        'bookings' => $bookings,
        'avg_daily' => $avg_daily,
        'occupancy' => $occupancy_rate,
        'expenses' => $expenses,
        'net_profit' => $net_profit,
        'satisfaction' => $satisfaction,
        'maintenance' => $maintenance,
        'cancellations' => $cancellations
    ];
}

// Prepare chart data
$chart_labels = array_column($metrics, 'name');
$chart_revenue = array_map('floatval', array_column($metrics, 'revenue'));
$chart_bookings = array_map('intval', array_column($metrics, 'bookings'));
$chart_occupancy = array_map('floatval', array_column($metrics, 'occupancy'));
$chart_expenses = array_map('floatval', array_column($metrics, 'expenses'));
$chart_profit = array_map('floatval', array_column($metrics, 'net_profit'));
$chart_satisfaction = array_map('floatval', array_column($metrics, 'satisfaction'));
$chart_maintenance = array_map('floatval', array_column($metrics, 'maintenance'));
$chart_cancellations = array_map('intval', array_column($metrics, 'cancellations'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Analytics - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <?php require_once '../includes/owner_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/owner_sidebar.php'; ?>
    <div class="content">
        <!-- topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-coins me-2" style="color:#1B7D3A;"></i>Revenue Analytics</div>
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
                <div class="col-md-4">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-coins"></i></div>
                        <div>
                            <div class="kpi-num"><?php echo !empty($metrics) ? htmlspecialchars($metrics[array_search(max($chart_profit), $chart_profit)]['name']) : 'N/A'; ?></div>
                            <div class="kpi-lbl">Most Profitable Location</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div class="kpi-num"><?php echo !empty($metrics) ? htmlspecialchars($metrics[array_search(max($chart_occupancy), $chart_occupancy)]['name']) : 'N/A'; ?></div>
                            <div class="kpi-lbl">Highest Occupancy</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-book"></i></div>
                        <div>
                            <div class="kpi-num"><?php echo !empty($metrics) ? htmlspecialchars($metrics[array_search(max($chart_bookings), $chart_bookings)]['name']) : 'N/A'; ?></div>
                            <div class="kpi-lbl">Most Bookings</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts 2x3 -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Revenue per Area</h6>
                            <span class="chart-badge green">₱</span>
                        </div>
                        <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Bookings per Area</h6>
                            <span class="chart-badge blue">Count</span>
                        </div>
                        <div class="chart-wrap"><canvas id="bookingsChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Occupancy Rate</h6>
                            <span class="chart-badge orange">%</span>
                        </div>
                        <div class="chart-wrap"><canvas id="occupancyChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Expenses &amp; Maintenance</h6>
                            <span class="chart-badge yellow">₱</span>
                        </div>
                        <div class="chart-wrap"><canvas id="expensesChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Net Profit per Area</h6>
                            <span class="chart-badge purple">₱</span>
                        </div>
                        <div class="chart-wrap"><canvas id="profitChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Cancellations per Area</h6>
                            <span class="chart-badge orange">Count</span>
                        </div>
                        <div class="chart-wrap"><canvas id="cancellationsChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- Area Rankings Table -->
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>Area Rankings &amp; Insights</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Revenue</th>
                                <th>Bookings</th>
                                <th>Avg Daily</th>
                                <th>Occupancy (%)</th>
                                <th>Expenses</th>
                                <th>Maintenance</th>
                                <th>Net Profit</th>
                                <th>Satisfaction</th>
                                <th>Cancellations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metrics as $idx => $m): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php echo $idx === 0 ? 'rank-1' : ($idx === 1 ? 'rank-2' : ($idx === 2 ? 'rank-3' : 'rank-n')); ?>"><?php echo $idx + 1; ?></span>
                                        <?php echo htmlspecialchars($m['name']); ?>
                                    </td>
                                    <td>₱<?php echo number_format($m['revenue'], 2); ?></td>
                                    <td><?php echo $m['bookings']; ?></td>
                                    <td><?php echo $m['avg_daily']; ?></td>
                                    <td><?php echo $m['occupancy']; ?>%</td>
                                    <td>₱<?php echo number_format($m['expenses'], 2); ?></td>
                                    <td>₱<?php echo number_format($m['maintenance'], 2); ?></td>
                                    <td>₱<?php echo number_format($m['net_profit'], 2); ?></td>
                                    <td><?php echo $m['satisfaction'] ? number_format($m['satisfaction'], 2) : 'N/A'; ?></td>
                                    <td><?php echo $m['cancellations']; ?></td>
                                </tr>
                            <?php endforeach; ?>
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

    const chartOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };

    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Revenue', data: <?php echo json_encode($chart_revenue); ?>, backgroundColor: '#43d0e9' }] },
        options: chartOpts
    });
    new Chart(document.getElementById('bookingsChart').getContext('2d'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Bookings', data: <?php echo json_encode($chart_bookings); ?>, backgroundColor: '#9edaab' }] },
        options: chartOpts
    });
    new Chart(document.getElementById('occupancyChart').getContext('2d'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Occupancy (%)', data: <?php echo json_encode($chart_occupancy); ?>, backgroundColor: '#4facfe' }] },
        options: chartOpts
    });
    new Chart(document.getElementById('expensesChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                { label: 'Expenses', data: <?php echo json_encode($chart_expenses); ?>, backgroundColor: '#ffd89b' },
                { label: 'Maintenance', data: <?php echo json_encode($chart_maintenance); ?>, backgroundColor: '#ff6b6b' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
    });
    new Chart(document.getElementById('profitChart').getContext('2d'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Net Profit', data: <?php echo json_encode($chart_profit); ?>, backgroundColor: '#5869ca' }] },
        options: chartOpts
    });
    new Chart(document.getElementById('cancellationsChart').getContext('2d'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Cancellations', data: <?php echo json_encode($chart_cancellations); ?>, backgroundColor: '#e5533d' }] },
        options: chartOpts
    });
});
initOwnerSidebar('ownerSidebarCollapsed');
</script>
</body></html>
