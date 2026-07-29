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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amenities Reports & Analytics - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../assets/css/owner.css">
</head>
<body>
     <div class="main-container">
            <?php require_once '../includes/owner_sidebar.php'; ?>

            <div class="content">

                <?php display_messages(); ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Amenities Reports</h2>
                </div>

                <!-- CENTERED ROW -->
                <div class="row g-3 justify-content-center">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="dashboard-card stat-card" style="height: 200px; border-left: 4px solid #75a6e6;">
                        <div class="stat-icon me-3" style="font-size: 2.2rem;">
                                 <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <h5 class="mb-2">Total Amenities</h5>
                            <div class="display-5 fw-bold"><?php echo count($top_amenities); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card stat-card" style="height: 200px; border-left: 4px solid #75a6e6;">
                        <div class="stat-icon me-3" style="font-size: 2.2rem;">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div>
                            <h5 class="mb-2">Total Revenue</h5>
                            <div class="display-5 fw-bold">₱<?php echo number_format($total_revenue, 2); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card stat-card" style="height: 200px; border-left: 4px solid #75a6e6;">
                        <div class="stat-icon me-3" style="font-size: 2.2rem;">
                            <i class="fas fa-umbrella-beach"></i>
                        </div>
                        <div>
                            <h5 class="mb-2">Most Used Amenity</h5>
                            <div class="display-6 fw-bold"><?php echo isset($top_amenities[0]) ? htmlspecialchars($top_amenities[0]['name']) : 'N/A'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Usage Statistics</h5>
                            <canvas id="amenityUsageChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Revenue Breakdown</h5>
                            <canvas id="amenityRevenueChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Amenity Rankings</h5>
                            <table class="table table-bordered table-hover">
                                <thead class="table-success">
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
                                            <td><?php echo $idx + 1; ?></td>
                                            <td><?php echo htmlspecialchars($a['name']); ?></td>
                                            <td><?php echo $a['usage_count']; ?></td>
                                            <td>₱<?php echo isset($revenue_data[$idx]) ? number_format($revenue_data[$idx]['revenue'], 2) : '0.00'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCol = document.getElementById('sidebarCol');
    const navbarBrand = document.getElementById('navbarBrand');
    if (sidebarToggle && sidebarCol) {
        sidebarToggle.addEventListener('click', function() {
            sidebarCol.classList.toggle('collapsed');
            if (navbarBrand) {
                navbarBrand.classList.toggle('collapsed');
            }
        });
    }

    // Active State Persistence for sidebar
    const sidebarMenu = document.getElementById('ownerSidebarMenu');
    if (!sidebarMenu) return;
    sidebarMenu.querySelectorAll('.sidebar-group').forEach(function(group) {
        const parent = group.querySelector('.sidebar-parent');
        const collapse = group.querySelector('.collapse');
        if (!parent || !collapse) return;
        const activeChild = collapse.querySelector('.nav-link.active');
        if (activeChild) {
            collapse.classList.add('show');
            parent.setAttribute('aria-expanded', 'true');
        }
    });

    // Charts
    const usageCtx = document.getElementById('amenityUsageChart').getContext('2d');
    const revenueCtx = document.getElementById('amenityRevenueChart').getContext('2d');
    const usageChart = new Chart(usageCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Usage Count',
                data: <?php echo json_encode($chart_usage); ?>,
                backgroundColor: '#ace9c0',
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
    const revenueChart = new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?php echo json_encode($chart_revenue); ?>,
                backgroundColor: '#95c2e7',
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>
</html>