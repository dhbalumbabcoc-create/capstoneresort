<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);
// Handle restore facility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_facility') {
    $facility_id = intval($_POST['facility_id']);
    $stmt = $conn->prepare("UPDATE facilities SET status = 'available' WHERE id = ?");
    $stmt->bind_param("i", $facility_id);

    if ($stmt->execute()) {
        set_success_message('Facility restored successfully');
    } else {
        set_error_message('Error restoring facility: ' . $conn->error);
    }
    $stmt->close();
}

// Handle permanently delete facility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'permanent_delete') {
    $facility_id = intval($_POST['facility_id']);
    $stmt = $conn->prepare("DELETE FROM facilities WHERE id = ?");
    $stmt->bind_param("i", $facility_id);

    if ($stmt->execute()) {
        set_success_message('Facility permanently deleted');
    } else {
        set_error_message('Error deleting facility: ' . $conn->error);
    }
    $stmt->close();
}

// Get all archived facilities with search filter
$search = '';
if (isset($_GET['search'])) {
    $search = escape_input($_GET['search'], $conn);
}

if (!empty($search)) {
    $query = "SELECT * FROM facilities WHERE status = 'archived' AND (name LIKE '%" . $conn->real_escape_string($search) . "%' OR type LIKE '%" . $conn->real_escape_string($search) . "%') ORDER BY type, name";
} else {
    $query = "SELECT * FROM facilities WHERE status = 'archived' ORDER BY type, name";
}

$facilities_result = $conn->query($query);

// Facility analytics
$total_facilities = $conn->query("SELECT COUNT(*) AS cnt FROM facilities")->fetch_assoc()['cnt'] ?? 0;
$active_facilities = $conn->query("SELECT COUNT(*) AS cnt FROM facilities WHERE status = 'available'")->fetch_assoc()['cnt'] ?? 0;
$archived_facilities = $conn->query("SELECT COUNT(*) AS cnt FROM facilities WHERE status = 'archived'")->fetch_assoc()['cnt'] ?? 0;
$most_booked_result = $conn->query("SELECT f.name, COUNT(b.id) AS cnt FROM facilities f LEFT JOIN bookings b ON f.id = b.facility_id GROUP BY f.id ORDER BY cnt DESC, f.name LIMIT 1");
$most_booked = $most_booked_result && $most_booked_result->num_rows > 0 ? $most_booked_result->fetch_assoc()['name'] : '-';
// Bookings per facility
$bookings_result = $conn->query("SELECT f.name, COUNT(b.id) AS cnt FROM facilities f LEFT JOIN bookings b ON f.id = b.facility_id GROUP BY f.id ORDER BY f.name");
$bookings_labels = [];
$bookings_counts = [];
while ($row = $bookings_result->fetch_assoc()) { $bookings_labels[] = $row['name']; $bookings_counts[] = $row['cnt']; }
// Revenue per facility
$revenue_result = $conn->query("SELECT f.name, SUM(b.total_price) AS revenue FROM facilities f LEFT JOIN bookings b ON f.id = b.facility_id AND b.status = 'confirmed' GROUP BY f.id ORDER BY f.name");
$revenue_labels = [];
$revenue_counts = [];
while ($row = $revenue_result->fetch_assoc()) { $revenue_labels[] = $row['name']; $revenue_counts[] = floatval($row['revenue']); }
// Status distribution
$status_result = $conn->query("SELECT status, COUNT(*) AS cnt FROM facilities GROUP BY status");
$status_labels = [];
$status_counts = [];
while ($row = $status_result->fetch_assoc()) { $status_labels[] = ucfirst($row['status']); $status_counts[] = $row['cnt']; }
// Maintenance per facility
$maintenance_result = $conn->query("SELECT f.name, COUNT(m.id) AS cnt FROM facilities f LEFT JOIN maintenance m ON f.id = m.facility_id GROUP BY f.id ORDER BY f.name");
$maintenance_labels = [];
$maintenance_counts = [];
while ($row = $maintenance_result->fetch_assoc()) { $maintenance_labels[] = $row['name']; $maintenance_counts[] = $row['cnt']; }
// Ratings per facility
// Facility rating analytics removed due to missing feedback schema
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Utilization - Resort Management</title>
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

        /* KPI cards */
        .kpi-card { background:#fff; border-radius:16px; padding:22px 20px; box-shadow:0 2px 12px rgba(0,0,0,.06); display:flex; align-items:center; gap:16px; transition:transform .25s,box-shadow .25s; border:none; margin-bottom:0; }
        .kpi-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.1); }
        .kpi-icon { width:54px; height:54px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
        .kpi-icon.green  { background:#e8f5e9; color:#1B7D3A; }
        .kpi-icon.blue   { background:#e3f2fd; color:#1565c0; }
        .kpi-icon.orange { background:#fff3e0; color:#e65100; }
        .kpi-icon.yellow { background:#fffde7; color:#f9a825; }
        .kpi-num { font-size:1.7rem; font-weight:900; color:#1a1a1a; line-height:1; }
        .kpi-lbl { font-size:.8rem; color:#888; margin-top:3px; }

        /* Section header */
        .section-hdr { margin-bottom:16px; }
        .section-hdr h5 { font-weight:800; color:#1a1a1a; margin:0; }
        .section-hdr p  { color:#888; font-size:.85rem; margin:2px 0 0; }

        /* Chart card */
        .chart-card { background:#fff; border-radius:16px; padding:22px; box-shadow:0 2px 12px rgba(0,0,0,.06); height:100%; }
        .chart-card h6 { font-weight:700; color:#1a1a1a; margin-bottom:0; }
        .chart-wrap { position:relative; height:260px; width:100%; }
        .chart-badge { font-size:.72rem; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
        .chart-badge.green  { background:#e8f5e9; color:#1B7D3A; }
        .chart-badge.blue   { background:#e3f2fd; color:#1565c0; }
        .chart-badge.orange { background:#fff3e0; color:#e65100; }
        .chart-badge.purple { background:#f3e5f5; color:#6a1b9a; }

        /* Table card */
        .table-card { background:#fff; border-radius:16px; padding:22px; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .table-card .table thead th { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; font-size:.78rem; text-transform:uppercase; letter-spacing:.6px; border:none; padding:12px 14px; }
        .table-card .table tbody td { padding:11px 14px; font-size:.88rem; vertical-align:middle; border-color:#f5f5f5; }
        .table-card .table tbody tr:hover { background:#f8fffe; }

        /* Rank badge */
        .rank-badge { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:.82rem; }
        .rank-1 { background:linear-gradient(135deg,#ffd700,#ffb300); color:#fff; }
        .rank-2 { background:linear-gradient(135deg,#b0bec5,#90a4ae); color:#fff; }
        .rank-3 { background:linear-gradient(135deg,#cd7f32,#a0522d); color:#fff; }
        .rank-n { background:#f0f0f0; color:#888; }

        /* Progress bar */
        .util-bar { height:8px; border-radius:4px; background:#e8f5e9; overflow:hidden; margin-top:4px; }
        .util-bar-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#1B7D3A,#27A457); transition:width .8s ease; }
    </style>
</head>
<body>
    <div class="main-container">
        <?php require_once '../includes/owner_sidebar.php'; ?>
        <div class="content">

            <!-- Topbar -->
            <div class="dash-topbar">
                <div>
                    <div class="dash-topbar-title"><i class="fas fa-chart-bar me-2" style="color:#1B7D3A;"></i>Facility Utilization</div>
                    <div class="dash-topbar-date"><?php echo date('l, F j, Y'); ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
                    <span style="font-size:.85rem;color:#888;"><?php echo isset($user) ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : ''; ?></span>
                </div>
            </div>

            <div class="dash-body">
                <?php display_messages(); ?>

                <!-- KPI Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon blue"><i class="fas fa-building"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $total_facilities ?>">0</div>
                                <div class="kpi-lbl">Total Facilities</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $active_facilities ?>">0</div>
                                <div class="kpi-lbl">Active Facilities</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon orange"><i class="fas fa-archive"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?= $archived_facilities ?>">0</div>
                                <div class="kpi-lbl">Archived</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card">
                            <div class="kpi-icon yellow"><i class="fas fa-star"></i></div>
                            <div>
                                <div class="kpi-num" style="font-size:1rem;line-height:1.3;"><?= htmlspecialchars($most_booked) ?></div>
                                <div class="kpi-lbl">Most Booked</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1: Bookings + Revenue per Facility -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0"><i class="fas fa-calendar-check me-2" style="color:#1B7D3A;"></i>Bookings per Facility</h6>
                                <span class="chart-badge green">All Time</span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="bookingsFacilityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2" style="color:#e65100;"></i>Revenue per Facility</h6>
                                <span class="chart-badge orange">Confirmed Only</span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="revenueFacilityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2: Status Distribution + Maintenance -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0"><i class="fas fa-chart-pie me-2" style="color:#1565c0;"></i>Facility Status Distribution</h6>
                                <span class="chart-badge blue">Current</span>
                            </div>
                            <div class="chart-wrap" style="height:220px;">
                                <canvas id="statusFacilityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0"><i class="fas fa-wrench me-2" style="color:#6a1b9a;"></i>Maintenance per Facility</h6>
                                <span class="chart-badge purple">All Time</span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="maintenanceFacilityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Facility Ranking Table -->
                <div class="section-hdr">
                    <h5><i class="fas fa-trophy me-2" style="color:#f9a825;"></i>Facility Ranking by Bookings</h5>
                    <p>All facilities ranked from most to least booked</p>
                </div>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:60px;">Rank</th>
                                    <th>Facility Name</th>
                                    <th>Type</th>
                                    <th>Bookings</th>
                                    <th style="width:200px;">Utilization</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ranking_result = $conn->query("SELECT f.name, f.type, f.status, COUNT(b.id) AS bookings FROM facilities f LEFT JOIN bookings b ON f.id = b.facility_id GROUP BY f.id ORDER BY bookings DESC, f.name");
                                $rank = 1;
                                $max_bookings = 1;
                                // Get max for progress bar
                                $all_ranks = [];
                                while ($r = $ranking_result->fetch_assoc()) { $all_ranks[] = $r; }
                                if (!empty($all_ranks)) $max_bookings = max(array_column($all_ranks, 'bookings')) ?: 1;
                                foreach ($all_ranks as $row):
                                    $pct = round(($row['bookings'] / $max_bookings) * 100);
                                    $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-n'));
                                    $statusColor = $row['status']==='available' ? '#1B7D3A' : ($row['status']==='maintenance' ? '#f9a825' : '#c62828');
                                    $statusBg    = $row['status']==='available' ? '#e8f5e9' : ($row['status']==='maintenance' ? '#fffde7' : '#fdecea');
                                ?>
                                <tr>
                                    <td class="text-center"><span class="rank-badge <?= $rankClass ?>"><?= $rank++ ?></span></td>
                                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                    <td><span style="background:#e3f2fd;color:#1565c0;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;"><?= ucfirst(str_replace('_',' ',$row['type'])) ?></span></td>
                                    <td><strong style="color:#1B7D3A;"><?= $row['bookings'] ?></strong></td>
                                    <td>
                                        <div style="font-size:.75rem;color:#888;margin-bottom:3px;"><?= $pct ?>%</div>
                                        <div class="util-bar"><div class="util-bar-fill" style="width:<?= $pct ?>%;"></div></div>
                                    </td>
                                    <td><span style="background:<?= $statusBg ?>;color:<?= $statusColor ?>;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;"><?= ucfirst($row['status']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /.dash-body -->
        </div><!-- /.content -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const GREEN_PALETTE  = ['#1B7D3A','#27A457','#4caf50','#81c784','#a5d6a7','#c8e6c9','#e8f5e9'];
    const ORANGE_PALETTE = ['#e65100','#f57c00','#fb8c00','#ffa726','#ffb74d','#ffcc80','#ffe0b2'];
    const BLUE_PALETTE   = ['#1565c0','#1976d2','#1e88e5','#42a5f5','#64b5f6','#90caf9','#bbdefb'];
    const PURPLE_PALETTE = ['#6a1b9a','#7b1fa2','#8e24aa','#ab47bc','#ce93d8','#e1bee7'];

    const bookingsLabels  = <?= json_encode($bookings_labels) ?>;
    const bookingsCounts  = <?= json_encode($bookings_counts) ?>;
    const revenueLabels   = <?= json_encode($revenue_labels) ?>;
    const revenueCounts   = <?= json_encode($revenue_counts) ?>;
    const statusLabels    = <?= json_encode($status_labels) ?>;
    const statusCounts    = <?= json_encode($status_counts) ?>;
    const maintLabels     = <?= json_encode($maintenance_labels) ?>;
    const maintCounts     = <?= json_encode($maintenance_counts) ?>;

    const commonBarOpts = (palette, prefix='', suffix='') => ({
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                padding: 10,
                callbacks: { label: c => ' ' + prefix + c.parsed.y.toLocaleString() + suffix }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { font: { size: 11 }, color: '#888', callback: v => prefix + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) }, grid: { color: 'rgba(0,0,0,.04)' }, border: { display: false } },
            x: { ticks: { font: { size: 10 }, color: '#888', maxRotation: 30 }, grid: { display: false }, border: { display: false } }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Animated counters
        document.querySelectorAll('.kpi-num[data-count]').forEach((el, i) => {
            const target = parseInt(el.getAttribute('data-count'), 10);
            setTimeout(() => {
                const start = performance.now();
                const update = (now) => {
                    const p = Math.min((now - start) / 900, 1);
                    el.textContent = Math.round((1 - Math.pow(1 - p, 3)) * target);
                    if (p < 1) requestAnimationFrame(update);
                };
                requestAnimationFrame(update);
            }, i * 80);
        });

        // Bookings per Facility
        new Chart(document.getElementById('bookingsFacilityChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: bookingsLabels,
                datasets: [{
                    label: 'Bookings',
                    data: bookingsCounts,
                    backgroundColor: bookingsCounts.map((v, i) => GREEN_PALETTE[i % GREEN_PALETTE.length]),
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: commonBarOpts(GREEN_PALETTE)
        });

        // Revenue per Facility
        new Chart(document.getElementById('revenueFacilityChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Revenue',
                    data: revenueCounts,
                    backgroundColor: revenueCounts.map((v, i) => ORANGE_PALETTE[i % ORANGE_PALETTE.length]),
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: commonBarOpts(ORANGE_PALETTE, '₱')
        });

        // Status Distribution (doughnut)
        const statusColors = statusLabels.map(l => {
            const s = l.toLowerCase();
            return s === 'available' ? '#1B7D3A' : s === 'maintenance' ? '#f9a825' : s === 'archived' ? '#90a4ae' : '#c62828';
        });
        new Chart(document.getElementById('statusFacilityChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{ data: statusCounts, backgroundColor: statusColors, borderWidth: 3, borderColor: '#fff', hoverOffset: 8 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 16, usePointStyle: true } },
                    tooltip: { padding: 10 }
                }
            }
        });

        // Maintenance per Facility
        new Chart(document.getElementById('maintenanceFacilityChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: maintLabels,
                datasets: [{
                    label: 'Maintenance',
                    data: maintCounts,
                    backgroundColor: maintCounts.map((v, i) => PURPLE_PALETTE[i % PURPLE_PALETTE.length]),
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: commonBarOpts(PURPLE_PALETTE)
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
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarMenu = document.getElementById('ownerSidebarMenu');
            if (!sidebarMenu) return;
            sidebarMenu.querySelectorAll('.sidebar-group').forEach(function(group) {
                const parent = group.querySelector('.sidebar-parent');
                const collapse = group.querySelector('.collapse');
                if (!parent || !collapse) return;
                if (collapse.querySelector('.nav-link.active')) {
                    collapse.classList.add('show');
                    parent.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }
    </script>
</body>
</html>