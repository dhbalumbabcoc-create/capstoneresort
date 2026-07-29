<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('admin');

$user  = get_user_info($_SESSION['user_id'], $conn);
$today = date('Y-m-d');

// KPI queries
$daily_revenue    = $conn->query("SELECT SUM(total_price) as v FROM bookings WHERE DATE(created_at)='$today' AND status IN ('completed','pending','confirmed')")->fetch_assoc()['v'] ?: 0;
$daily_bookings   = $conn->query("SELECT COUNT(*) as v FROM bookings WHERE DATE(created_at)='$today'")->fetch_assoc()['v'];
$pending_bookings = $conn->query("SELECT COUNT(*) as v FROM bookings WHERE booking_type='online' AND status='pending'")->fetch_assoc()['v'];
$today_checkins   = $conn->query("SELECT COUNT(*) as v FROM bookings WHERE DATE(check_in_date)='$today' AND status IN ('pending','confirmed')")->fetch_assoc()['v'];
$today_checkouts  = $conn->query("SELECT COUNT(*) as v FROM bookings WHERE DATE(check_out_date)='$today' AND status IN ('pending','confirmed')")->fetch_assoc()['v'];
$avail_facilities = $conn->query("SELECT COUNT(*) as v FROM facilities WHERE status='available'")->fetch_assoc()['v'];
$occup_facilities = $conn->query("SELECT COUNT(DISTINCT facility_id) as v FROM bookings WHERE status IN ('pending','confirmed') AND check_out_date>='$today'")->fetch_assoc()['v'];
$total_facilities = $conn->query("SELECT COUNT(*) as v FROM facilities")->fetch_assoc()['v'] ?: 1;
$occupancy_pct    = round(($occup_facilities / $total_facilities) * 100, 1);

// Hourly data
$hourly_data = array_fill(0, 24, 0);
$hr = $conn->query("SELECT HOUR(created_at) as h, COUNT(*) as c FROM bookings WHERE DATE(created_at)='$today' GROUP BY h");
while ($r = $hr->fetch_assoc()) $hourly_data[$r['h']] = $r['c'];

// 7-day revenue trend
$revenue_dates = []; $revenue_values = [];
$rt = $conn->query("SELECT DATE(created_at) as d, SUM(total_price) as v FROM bookings WHERE DATE(created_at)>=DATE_SUB('$today',INTERVAL 6 DAY) AND status IN ('completed','pending','confirmed') GROUP BY d ORDER BY d");
while ($r = $rt->fetch_assoc()) { $revenue_dates[] = date('M d', strtotime($r['d'])); $revenue_values[] = floatval($r['v']); }

// Recent bookings
$recent = $conn->query("SELECT 
                            GROUP_CONCAT(b.id ORDER BY b.id ASC SEPARATOR ', ') as id,
                            b.guest_name,
                            b.booking_type,
                            a.name as area_name,
                            GROUP_CONCAT(f.name ORDER BY f.name ASC SEPARATOR ', ') as facility_name,
                            b.check_in_date,
                            b.check_out_date,
                            SUM(b.total_price) as total_price,
                            b.status,
                            b.created_at
                        FROM bookings b
                        JOIN facilities f ON b.facility_id=f.id
                        LEFT JOIN areas a ON b.area_id=a.id
                        GROUP BY b.guest_name, b.check_in_date, b.check_out_date, b.created_at, b.booking_type, b.status, a.name
                        ORDER BY b.created_at DESC
                        LIMIT 10");

// Today check-in/out lists
$ci_list = $conn->query("SELECT GROUP_CONCAT(DISTINCT b.id ORDER BY b.id ASC SEPARATOR ', ') as id, b.guest_name, b.status, GROUP_CONCAT(DISTINCT f.name ORDER BY f.name ASC SEPARATOR ', ') as facility_name FROM bookings b JOIN facilities f ON b.facility_id=f.id WHERE DATE(b.check_in_date)='$today' AND b.status IN ('pending','confirmed') GROUP BY b.guest_name, COALESCE(b.guest_email, ''), b.check_in_date, b.check_out_date, b.created_at, b.booking_type, b.status ORDER BY b.created_at DESC");
$co_list = $conn->query("SELECT GROUP_CONCAT(DISTINCT b.id ORDER BY b.id ASC SEPARATOR ', ') as id, b.guest_name, b.status, GROUP_CONCAT(DISTINCT f.name ORDER BY f.name ASC SEPARATOR ', ') as facility_name FROM bookings b JOIN facilities f ON b.facility_id=f.id WHERE DATE(b.check_out_date)='$today' AND b.status IN ('pending','confirmed') GROUP BY b.guest_name, COALESCE(b.guest_email, ''), b.check_in_date, b.check_out_date, b.created_at, b.booking_type, b.status ORDER BY b.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <?php require_once '../includes/admin_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/admin_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-chart-line me-2" style="color:#1B7D3A;"></i>Admin Dashboard</div>
                <div class="dash-topbar-sub"><?= date('l, F j, Y') ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-shield-alt me-1"></i>Admin</span>
                <span style="font-size:.85rem;color:#888;"><?= htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??'')) ?></span>
            </div>
        </div>

        <div class="dash-body">
            <?php display_messages(); ?>

            <?php if ($pending_bookings > 0): ?>
            <div class="alert-pending mb-4">
                <i class="fas fa-bell"></i>
                <div><strong><?= $pending_bookings ?> pending online booking<?= $pending_bookings>1?'s':'' ?></strong> waiting for approval.
                    <a href="../frontdesk/online_bookings.php" style="color:#e65100;font-weight:700;margin-left:8px;">Review Now &rarr;</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- KPI Row 1 -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon purple"><i class="fas fa-peso-sign"></i></div>
                        <div><div class="kpi-num">&#8369;<?= number_format($daily_revenue, 0) ?></div><div class="kpi-lbl">Today's Revenue</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-calendar-check"></i></div>
                        <div><div class="kpi-num" data-count="<?= $daily_bookings ?>">0</div><div class="kpi-lbl">Today's Bookings</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                        <div><div class="kpi-num" data-count="<?= $pending_bookings ?>">0</div><div class="kpi-lbl">Pending Online</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-chart-pie"></i></div>
                        <div><div class="kpi-num"><?= $occupancy_pct ?>%</div><div class="kpi-lbl">Occupancy Rate</div></div>
                    </div>
                </div>
            </div>

            <!-- KPI Row 2 -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-sign-in-alt"></i></div>
                        <div><div class="kpi-num" data-count="<?= $today_checkins ?>">0</div><div class="kpi-lbl">Today's Check-ins</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-sign-out-alt"></i></div>
                        <div><div class="kpi-num" data-count="<?= $today_checkouts ?>">0</div><div class="kpi-lbl">Today's Check-outs</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon teal"><i class="fas fa-door-open"></i></div>
                        <div><div class="kpi-num" data-count="<?= $avail_facilities ?>">0</div><div class="kpi-lbl">Available Facilities</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon red"><i class="fas fa-door-closed"></i></div>
                        <div><div class="kpi-num" data-count="<?= $occup_facilities ?>">0</div><div class="kpi-lbl">Occupied Facilities</div></div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="chart-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6><i class="fas fa-chart-bar me-2" style="color:#1B7D3A;"></i>Hourly Bookings (Today)</h6>
                        </div>
                        <div class="chart-wrap"><canvas id="hourlyChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6><i class="fas fa-chart-line me-2" style="color:#1B7D3A;"></i>7-Day Revenue</h6>
                        </div>
                        <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- Check-in / Check-out Tables -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="section-hdr"><h5><i class="fas fa-sign-in-alt me-2" style="color:#1565c0;"></i>Today's Check-ins</h5></div>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>#</th><th>Guest</th><th>Facility</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php if ($ci_list && $ci_list->num_rows > 0): while ($ci = $ci_list->fetch_assoc()): ?>
                                <tr>
                                    <td><strong style="color:#1B7D3A;">#<?= htmlspecialchars(str_replace(', ', ', #', $ci['id'])) ?></strong></td>
                                    <td><?= htmlspecialchars($ci['guest_name']) ?></td>
                                    <td><?= htmlspecialchars($ci['facility_name']) ?></td>
                                    <td><span class="pill <?= $ci['status']==='completed'?'pill-green':($ci['status']==='confirmed'?'pill-blue':($ci['status']==='pending'?'pill-yellow':'pill-red')) ?>"><?= ucfirst($ci['status']) ?></span></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No check-ins today</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="section-hdr"><h5><i class="fas fa-sign-out-alt me-2" style="color:#e65100;"></i>Today's Check-outs</h5></div>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>#</th><th>Guest</th><th>Facility</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php if ($co_list && $co_list->num_rows > 0): while ($co = $co_list->fetch_assoc()): ?>
                                <tr>
                                    <td><strong style="color:#1B7D3A;">#<?= htmlspecialchars(str_replace(', ', ', #', $co['id'])) ?></strong></td>
                                    <td><?= htmlspecialchars($co['guest_name']) ?></td>
                                    <td><?= htmlspecialchars($co['facility_name']) ?></td>
                                    <td><span class="pill <?= $co['status']==='completed'?'pill-green':($co['status']==='confirmed'?'pill-blue':($co['status']==='pending'?'pill-yellow':'pill-red')) ?>"><?= ucfirst($co['status']) ?></span></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No check-outs today</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="section-hdr">
                <h5><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Recent Bookings</h5>
                <p>Last 10 bookings across all staff</p>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>#</th><th>Guest</th><th>Type</th><th>Location</th><th>Facility</th><th>Check-in</th><th>Check-out</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($recent && $recent->num_rows > 0): while ($bk = $recent->fetch_assoc()):
                            $pc = $bk['status']==='completed'?'pill-green':($bk['status']==='confirmed'?'pill-blue':($bk['status']==='pending'?'pill-yellow':'pill-red'));
                        ?>
                        <tr>
                            <td><strong style="color:#1B7D3A;">#<?= str_replace(', ', ', #', $bk['id']) ?></strong></td>
                            <td><?= htmlspecialchars($bk['guest_name']) ?></td>
                            <td><?= ucfirst(str_replace('_',' ',$bk['booking_type']??'N/A')) ?></td>
                            <td><?= htmlspecialchars($bk['area_name']??'—') ?></td>
                            <td><?= htmlspecialchars($bk['facility_name']) ?></td>
                            <td><?= date('M d', strtotime($bk['check_in_date'])) ?></td>
                            <td><?= date('M d', strtotime($bk['check_out_date'])) ?></td>
                            <td><strong>&#8369;<?= number_format($bk['total_price'],0) ?></strong></td>
                            <td><span class="pill <?= $pc ?>"><?= ucfirst($bk['status']) ?></span></td>
                            <td style="font-size:.8rem;color:#888;"><?= date('M d, h:i A', strtotime($bk['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No bookings yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /dash-body -->
    </div><!-- /content -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animated counters
    document.querySelectorAll('.kpi-num[data-count]').forEach((el, i) => {
        const t = parseInt(el.getAttribute('data-count'), 10);
        setTimeout(() => {
            const s = performance.now();
            const u = (n) => {
                const p = Math.min((n - s) / 800, 1);
                el.textContent = Math.round((1 - Math.pow(1 - p, 3)) * t);
                if (p < 1) requestAnimationFrame(u);
            };
            requestAnimationFrame(u);
        }, i * 80);
    });

    // Hourly chart
    new Chart(document.getElementById('hourlyChart'), {
        type: 'bar',
        data: {
            labels: ['12a','1a','2a','3a','4a','5a','6a','7a','8a','9a','10a','11a','12p','1p','2p','3p','4p','5p','6p','7p','8p','9p','10p','11p'],
            datasets: [{ label:'Bookings', data: <?= json_encode($hourly_data) ?>, backgroundColor:'rgba(27,125,58,.7)', borderColor:'#1B7D3A', borderWidth:2, borderRadius:6 }]
        },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
    });

    // Revenue trend chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($revenue_dates) ?>,
            datasets: [{ label:'Revenue', data: <?= json_encode($revenue_values) ?>, backgroundColor:'rgba(27,125,58,.15)', borderColor:'#1B7D3A', borderWidth:2.5, fill:true, tension:.4, pointBackgroundColor:'#1B7D3A' }]
        },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{callback:v=>'₱'+v.toLocaleString()}}} }
    });
});

// Auto-refresh every 3 minutes
setTimeout(() => location.reload(), 180000);
</script>
</body>
</html>
