<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('supervisor');

$user = get_user_info($_SESSION['user_id'], $conn);

// Get today's date
$today = date('Y-m-d');

// Total facilities
$total_facilities_query = "SELECT COUNT(*) as count FROM facilities";
$total_facilities_result = $conn->query($total_facilities_query);
$total_facilities = $total_facilities_result->fetch_assoc()['count'];

// Available facilities
$available_query = "SELECT COUNT(*) as count FROM facilities WHERE status = 'available'";
$available_result = $conn->query($available_query);
$available_facilities = $available_result->fetch_assoc()['count'];

// Under maintenance
$maintenance_query = "SELECT COUNT(*) as count FROM facilities WHERE status = 'maintenance'";
$maintenance_result = $conn->query($maintenance_query);
$under_maintenance = $maintenance_result->fetch_assoc()['count'];

// Unavailable facilities
$unavailable_query = "SELECT COUNT(*) as count FROM facilities WHERE status = 'unavailable'";
$unavailable_result = $conn->query($unavailable_query);
$unavailable_facilities = $unavailable_result->fetch_assoc()['count'];

// Pending maintenance requests
$pending_maintenance_query = "SELECT COUNT(*) as count FROM maintenance WHERE status = 'pending'";
$pending_maintenance_result = $conn->query($pending_maintenance_query);
$pending_maintenance = $pending_maintenance_result->fetch_assoc()['count'];

// In-progress maintenance
$inprogress_maintenance_query = "SELECT COUNT(*) as count FROM maintenance WHERE status = 'in_progress'";
$inprogress_maintenance_result = $conn->query($inprogress_maintenance_query);
$inprogress_maintenance = $inprogress_maintenance_result->fetch_assoc()['count'];

// Completed maintenance this month
$completed_month_query = "SELECT COUNT(*) as count FROM maintenance 
                          WHERE status = 'completed' 
                          AND MONTH(completed_date) = MONTH(CURDATE()) 
                          AND YEAR(completed_date) = YEAR(CURDATE())";
$completed_month_result = $conn->query($completed_month_query);
$completed_this_month = $completed_month_result->fetch_assoc()['count'];

// Completed repairs today
$completed_today_query = "SELECT COUNT(*) as count FROM maintenance 
                          WHERE status = 'completed' 
                          AND DATE(completed_date) = '$today'";
$completed_today_result = $conn->query($completed_today_query);
$completed_today = $completed_today_result->fetch_assoc()['count'];

// Most frequently repaired facilities
$frequent_repairs_query = "SELECT f.name as facility_name, COUNT(m.id) as repair_count
                           FROM maintenance m
                           JOIN facilities f ON m.facility_id = f.id
                           GROUP BY m.facility_id
                           ORDER BY repair_count DESC
                           LIMIT 5";
$frequent_repairs_result = $conn->query($frequent_repairs_query);

// Recent maintenance requests
$recent_maintenance_query = "SELECT mr.*, f.name as facility_name 
                             FROM maintenance mr 
                             JOIN facilities f ON mr.facility_id = f.id 
                             ORDER BY mr.created_at DESC 
                             LIMIT 8";
$recent_maintenance_result = $conn->query($recent_maintenance_query);

// Facilities status breakdown
$facilities_status_query = "SELECT f.*, a.name as area_name 
                            FROM facilities f 
                            LEFT JOIN areas a ON f.area_id = a.id 
                            ORDER BY f.status ASC, f.name ASC
                            LIMIT 10";
$facilities_status_result = $conn->query($facilities_status_query);

// Maintenance by priority
$priority_query = "SELECT priority, COUNT(*) as count 
                   FROM maintenance 
                   WHERE status IN ('pending', 'in_progress')
                   GROUP BY priority";
$priority_result = $conn->query($priority_query);
$priority_counts = ['low' => 0, 'medium' => 0, 'high' => 0];
while ($row = $priority_result->fetch_assoc()) {
    $priority_counts[$row['priority']] = $row['count'];
}

// Monthly maintenance trend (last 6 months)
$monthly_maintenance_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $month_query = "SELECT COUNT(*) as count FROM maintenance 
                    WHERE created_at BETWEEN '$month_start' AND '$month_end 23:59:59'";
    $month_result = $conn->query($month_query);
    $monthly_maintenance_data[] = [
        'month' => date('M Y', strtotime($month_start)),
        'count' => $month_result->fetch_assoc()['count']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard - Facility Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        body { background: #f4f6f9; }
        .content { padding: 0 !important; }
        .dash-topbar { background:#fff; padding:16px 32px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e8ecf0; position:sticky; top:0; z-index:50; }
        .dash-topbar-title { font-size:1.25rem; font-weight:800; color:#1a1a1a; }
        .dash-topbar-date  { font-size:.85rem; color:#888; }
        .dash-topbar-badge { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border-radius:20px; padding:5px 14px; font-size:.78rem; font-weight:700; }
        .dash-body { padding:28px 32px; }
        .kpi-card { background:#fff; border-radius:16px; padding:22px 20px; box-shadow:0 2px 12px rgba(0,0,0,.06); display:flex; align-items:center; gap:16px; transition:transform .25s,box-shadow .25s; border:none; margin-bottom:0; }
        .kpi-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.1); }
        .kpi-icon { width:54px; height:54px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
        .kpi-icon.green  { background:#e8f5e9; color:#1B7D3A; }
        .kpi-icon.blue   { background:#e3f2fd; color:#1565c0; }
        .kpi-icon.orange { background:#fff3e0; color:#e65100; }
        .kpi-icon.yellow { background:#fffde7; color:#f9a825; }
        .kpi-icon.red    { background:#fdecea; color:#c62828; }
        .kpi-icon.teal   { background:#e0f2f1; color:#00695c; }
        .kpi-num { font-size:1.7rem; font-weight:900; color:#1a1a1a; line-height:1; }
        .kpi-lbl { font-size:.8rem; color:#888; margin-top:3px; }
        .section-hdr { margin-bottom:16px; }
        .section-hdr h5 { font-weight:800; color:#1a1a1a; margin:0; }
        .section-hdr p  { color:#888; font-size:.85rem; margin:2px 0 0; }
        .chart-card { background:#fff; border-radius:16px; padding:22px; box-shadow:0 2px 12px rgba(0,0,0,.06); height:100%; }
        .chart-card h6 { font-weight:700; color:#1a1a1a; margin-bottom:12px; }
        /* Fixed-height wrapper — required for Chart.js with maintainAspectRatio:false */
        .chart-wrap { position:relative; height:220px; width:100%; }
        .table-card { background:#fff; border-radius:16px; padding:22px; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .table-card .table thead th { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; font-size:.78rem; text-transform:uppercase; letter-spacing:.6px; border:none; padding:12px 14px; }
        .table-card .table tbody td { padding:11px 14px; font-size:.88rem; vertical-align:middle; border-color:#f5f5f5; }
        .table-card .table tbody tr:hover { background:#f8fffe; }
        .priority-high   { background:#fdecea; color:#c62828; padding:3px 10px; border-radius:20px; font-size:.75rem; font-weight:700; }
        .priority-medium { background:#fff8e1; color:#e65100; padding:3px 10px; border-radius:20px; font-size:.75rem; font-weight:700; }
        .priority-low    { background:#e8f5e9; color:#1B7D3A; padding:3px 10px; border-radius:20px; font-size:.75rem; font-weight:700; }
        .alert-banner { background:linear-gradient(135deg,#fdecea,#fff5f5); border:1.5px solid #f5c6cb; border-radius:14px; padding:14px 20px; display:flex; align-items:center; gap:12px; margin-bottom:20px; }
        .alert-banner i { font-size:1.3rem; color:#c62828; }
    </style>
</head>
<body>
    <div class="main-container" style="display:flex; min-height:100vh;">
        <div class="sidebar-col" id="sidebarCol">
            <?php require_once '../includes/supervisor_sidebar.php'; ?>
        </div>
        <div class="content" style="flex:1; min-width:0;">

            <!-- Topbar -->
            <div class="dash-topbar">
                <div>
                    <div class="dash-topbar-title"><i class="fas fa-tools me-2" style="color:#1B7D3A;"></i>Supervisor Dashboard</div>
                    <div class="dash-topbar-date"><?php echo date('l, F j, Y'); ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="dash-topbar-badge"><i class="fas fa-hard-hat me-1"></i>Supervisor</span>
                    <span style="font-size:.85rem;color:#888;"><?php echo isset($user) ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : ''; ?></span>
                </div>
            </div>

            <div class="dash-body">
                <?php display_messages(); ?>

                <!-- High priority alert -->
                <?php if ($priority_counts['high'] > 0): ?>
                <div class="alert-banner">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong><?= $priority_counts['high'] ?> high-priority</strong> maintenance request<?= $priority_counts['high']>1?'s':'' ?> need immediate attention.
                        <a href="maintenance.php" style="color:#c62828;font-weight:700;margin-left:8px;">View Now &rarr;</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- KPI Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon yellow"><i class="fas fa-wrench"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $under_maintenance ?>">0</div>
                                <div class="kpi-lbl">Under Maintenance</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon orange"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $pending_maintenance ?>">0</div>
                                <div class="kpi-lbl">Pending Requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon blue"><i class="fas fa-cogs"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $inprogress_maintenance ?>">0</div>
                                <div class="kpi-lbl">Ongoing Repairs</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $completed_today ?>">0</div>
                                <div class="kpi-lbl">Completed Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon teal"><i class="fas fa-building"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $available_facilities ?>">0</div>
                                <div class="kpi-lbl">Available Facilities</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon red"><i class="fas fa-ban"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $unavailable_facilities ?>">0</div>
                                <div class="kpi-lbl">Unavailable</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon green"><i class="fas fa-calendar-check"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $completed_this_month ?>">0</div>
                                <div class="kpi-lbl">Completed This Month</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <h6><i class="fas fa-chart-line me-2" style="color:#1B7D3A;"></i>Maintenance Trend (6 Mo)</h6>
                            <div class="chart-wrap"><canvas id="maintenanceTrendChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <h6><i class="fas fa-chart-pie me-2" style="color:#c62828;"></i>Priority Breakdown</h6>
                            <div class="chart-wrap"><canvas id="priorityChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <h6><i class="fas fa-chart-pie me-2" style="color:#1565c0;"></i>Facility Status</h6>
                            <div class="chart-wrap"><canvas id="facilityStatusChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- Most Repaired + Facility Status Table -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <h6><i class="fas fa-chart-bar me-2" style="color:#e65100;"></i>Most Frequently Repaired</h6>
                            <div class="chart-wrap"><canvas id="frequentRepairsChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="table-card" style="height:100%;">
                            <div class="section-hdr mb-3">
                                <h5><i class="fas fa-door-open me-2" style="color:#1B7D3A;"></i>Facility Status Overview</h5>
                            </div>
                            <div class="table-responsive" style="max-height:240px;overflow-y:auto;">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>Facility</th><th>Type</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php $facilities_status_result->data_seek(0); while ($f = $facilities_status_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($f['name']) ?></td>
                                        <td><?= ucfirst($f['type']) ?></td>
                                        <td>
                                            <?php
                                            $sc = $f['status']==='available' ? 'green' : ($f['status']==='maintenance' ? 'yellow' : 'red');
                                            echo "<span class='priority-$sc' style='background:".($sc==='yellow'?'#fffde7':'').";color:".($sc==='yellow'?'#f9a825':'')."'>".ucfirst($f['status'])."</span>";
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Maintenance -->
                <div class="section-hdr">
                    <h5><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Recent Maintenance Requests</h5>
                    <p>Latest 8 maintenance requests</p>
                </div>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>ID</th><th>Facility</th><th>Issue</th><th>Priority</th><th>Status</th><th>Created</th></tr></thead>
                            <tbody>
                            <?php $recent_maintenance_result->data_seek(0); while ($m = $recent_maintenance_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong style="color:#1B7D3A;">#<?= $m['id'] ?></strong></td>
                                <td><?= htmlspecialchars($m['facility_name']) ?></td>
                                <td><?= htmlspecialchars(substr($m['description']??'N/A',0,40)).(strlen($m['description']??'')>40?'…':'') ?></td>
                                <td><span class="priority-<?= $m['priority'] ?>"><?= strtoupper($m['priority']) ?></span></td>
                                <td>
                                    <?php
                                    $sc = $m['status']==='completed'?'green':($m['status']==='in_progress'?'yellow':'red');
                                    echo "<span class='priority-$sc'>".ucfirst(str_replace('_',' ',$m['status']))."</span>";
                                    ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /.dash-body -->
        </div><!-- /.content -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animated counters
        document.querySelectorAll('.kpi-num[data-count]').forEach((el, i) => {
            const target = parseInt(el.getAttribute('data-count'), 10);
            setTimeout(() => {
                const start = performance.now();
                const update = (now) => {
                    const p = Math.min((now - start) / 900, 1);
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(eased * target);
                    if (p < 1) requestAnimationFrame(update);
                };
                requestAnimationFrame(update);
            }, i * 80);
        });

        // Maintenance Trend
        new Chart(document.getElementById('maintenanceTrendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_maintenance_data,'month')) ?>,
                datasets: [{
                    label: 'Requests',
                    data: <?= json_encode(array_column($monthly_maintenance_data,'count')) ?>,
                    borderColor: '#1B7D3A',
                    backgroundColor: 'rgba(27,125,58,.15)',
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
                        callbacks: { label: c => ' ' + c.parsed.y + ' request' + (c.parsed.y !== 1 ? 's' : '') }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 }, color: '#888' }, grid: { color: 'rgba(0,0,0,.04)' }, border: { display: false } },
                    x: { ticks: { font: { size: 11 }, color: '#888' }, grid: { display: false }, border: { display: false } }
                }
            }
        });

        // Priority Breakdown
        new Chart(document.getElementById('priorityChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{
                    data: [<?= $priority_counts['high'] ?>, <?= $priority_counts['medium'] ?>, <?= $priority_counts['low'] ?>],
                    backgroundColor: ['#fdecea', '#fff8e1', '#e8f5e9'],
                    borderColor: ['#c62828', '#e65100', '#1B7D3A'],
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 14, usePointStyle: true } },
                    tooltip: { padding: 10 }
                }
            }
        });

        // Facility Status
        new Chart(document.getElementById('facilityStatusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Maintenance', 'Unavailable'],
                datasets: [{
                    data: [<?= $available_facilities ?>, <?= $under_maintenance ?>, <?= $unavailable_facilities ?>],
                    backgroundColor: ['#e8f5e9', '#fffde7', '#fdecea'],
                    borderColor: ['#1B7D3A', '#f9a825', '#c62828'],
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 14, usePointStyle: true } },
                    tooltip: { padding: 10 }
                }
            }
        });

        // Frequent Repairs
        <?php
        $frequent_repairs_result->data_seek(0);
        $fr_labels = []; $fr_data = [];
        while ($row = $frequent_repairs_result->fetch_assoc()) { $fr_labels[] = $row['facility_name']; $fr_data[] = $row['repair_count']; }
        ?>
        new Chart(document.getElementById('frequentRepairsChart').getContext('2d'), {
            type: 'bar',
            data: { labels: <?= json_encode($fr_labels) ?>, datasets:[{ data:<?= json_encode($fr_data) ?>, backgroundColor:'rgba(27,125,58,.75)', borderRadius:6, borderSkipped:false }] },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11}},grid:{color:'rgba(0,0,0,.04)'}},x:{ticks:{font:{size:11}},grid:{display:false}}} }
        });
    });

    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        const sidebarCol = document.getElementById('sidebarCol');
        const navbarBrand = document.getElementById('navbarBrand');
        if (localStorage.getItem('supervisorSidebarCollapsed') === 'true') {
            sidebarCol.classList.add('collapsed');
            sidebarToggle.classList.add('collapsed');
            if (navbarBrand) navbarBrand.classList.add('collapsed');
        }
        sidebarToggle.addEventListener('click', function() {
            const c = sidebarCol.classList.toggle('collapsed');
            this.classList.toggle('collapsed');
            if (navbarBrand) navbarBrand.classList.toggle('collapsed');
            localStorage.setItem('supervisorSidebarCollapsed', c);
        });
    }
    </script>
</body>
</html>