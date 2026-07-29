<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_role('owner');
$user = get_user_info($_SESSION['user_id'], $conn);

// Metrics: Usage, Revenue, Rankings
// Usage: Count per amenity
$usage_result = $conn->query("SELECT a.id, a.name, COUNT(ba.id) AS usage_count FROM amenities a LEFT JOIN booking_addons ba ON a.id = ba.amenity_id GROUP BY a.id ORDER BY usage_count DESC, a.name");

// Revenue: Sum per amenity
$revenue_result = $conn->query("SELECT a.id, a.name, SUM(a.price) * COUNT(ba.id) AS revenue FROM amenities a LEFT JOIN booking_addons ba ON a.id = ba.amenity_id GROUP BY a.id ORDER BY revenue DESC, a.name");

// Top amenities (by usage)
$top_amenities = [];
if ($usage_result && $usage_result->num_rows > 0) {
    while ($row = $usage_result->fetch_assoc()) {
        $top_amenities[] = $row;
    }
}

// Revenue breakdown
$revenue_data = [];
if ($revenue_result && $revenue_result->num_rows > 0) {
    while ($row = $revenue_result->fetch_assoc()) {
        $revenue_data[] = $row;
    }
}

// Total revenue
$total_revenue = 0;
foreach ($revenue_data as $r) {
    $total_revenue += floatval($r['revenue']);
}

// Chart data
$chart_labels = [];
$chart_usage = [];
$chart_revenue = [];
foreach ($top_amenities as $a) {
    $chart_labels[] = $a['name'];
    $chart_usage[] = $a['usage_count'];
}
foreach ($revenue_data as $r) {
    $chart_revenue[] = floatval($r['revenue']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Summary - Resort Management</title>
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
                <div class="dash-topbar-title"><i class="fas fa-tools me-2" style="color:#1B7D3A;"></i>Maintenance Summary</div>
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
                        <div class="kpi-icon teal"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo count($top_amenities); ?>"><?php echo count($top_amenities); ?></div>
                            <div class="kpi-lbl">Total Amenities</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-chart-pie"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo number_format($total_revenue, 2); ?>" data-prefix="₱"><?php echo '₱' . number_format($total_revenue, 2); ?></div>
                            <div class="kpi-lbl">Total Revenue</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-umbrella-beach"></i></div>
                        <div>
                            <div class="kpi-num"><?php echo isset($top_amenities[0]) ? htmlspecialchars($top_amenities[0]['name']) : 'N/A'; ?></div>
                            <div class="kpi-lbl">Most Used Amenity</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Usage Statistics</h6>
                            <span class="chart-badge green">Count</span>
                        </div>
                        <div class="chart-wrap"><canvas id="amenityUsageChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Revenue Breakdown</h6>
                            <span class="chart-badge blue">₱</span>
                        </div>
                        <div class="chart-wrap"><canvas id="amenityRevenueChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- Amenity Rankings Table -->
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>Amenity Rankings</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Name</th>
                                <th>Usage Count</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_amenities as $idx => $a): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php echo $idx === 0 ? 'rank-1' : ($idx === 1 ? 'rank-2' : ($idx === 2 ? 'rank-3' : 'rank-n')); ?>"><?php echo $idx + 1; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['name']); ?></td>
                                    <td><?php echo $a['usage_count']; ?></td>
                                    <td>₱<?php echo isset($revenue_data[$idx]) ? number_format($revenue_data[$idx]['revenue'], 2) : '0.00'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($top_amenities)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No amenity data found.</td></tr>
                            <?php endif; ?>
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

    new Chart(document.getElementById('amenityUsageChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{ label: 'Usage Count', data: <?php echo json_encode($chart_usage); ?>, backgroundColor: '#ace9c0' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    new Chart(document.getElementById('amenityRevenueChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{ label: 'Revenue (₱)', data: <?php echo json_encode($chart_revenue); ?>, backgroundColor: '#95c2e7' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
});
initOwnerSidebar('ownerSidebarCollapsed');
</script>
</body></html>
