<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('admin');
$user  = get_user_info($_SESSION['user_id'], $conn);
$today = date('Y-m-d');

$filter_period = $_GET['period'] ?? 'all';
$date_where = '';
switch ($filter_period) {
    case 'today': $date_where = "AND DATE(b.created_at)='$today'"; break;
    case 'week':  $date_where = "AND DATE(b.created_at)>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)"; break;
    case 'month': $date_where = "AND MONTH(b.created_at)=MONTH(CURDATE()) AND YEAR(b.created_at)=YEAR(CURDATE())"; break;
}

$confirmed_statuses = "'confirmed','completed'";
$rev_row     = $conn->query("SELECT SUM(total_price) AS total, COUNT(*) AS cnt FROM bookings b WHERE status IN ($confirmed_statuses) $date_where")->fetch_assoc();
$total_revenue   = floatval($rev_row['total'] ?? 0);
$total_confirmed = intval($rev_row['cnt']   ?? 0);
$today_rev   = $conn->query("SELECT SUM(total_price) AS total FROM bookings WHERE status IN ($confirmed_statuses) AND DATE(created_at)='$today'")->fetch_assoc()['total'] ?? 0;
$pending_cnt = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE status='pending' $date_where")->fetch_assoc()['c'] ?? 0;
$online_row  = $conn->query("SELECT SUM(total_price) AS total, COUNT(*) AS cnt FROM bookings b WHERE booking_type='online' AND status IN ($confirmed_statuses) $date_where")->fetch_assoc();
$walkin_row  = $conn->query("SELECT SUM(total_price) AS total, COUNT(*) AS cnt FROM bookings b WHERE booking_type IN ('walkin','walk_in','walk-in') AND status IN ($confirmed_statuses) $date_where")->fetch_assoc();
$online_total = floatval($online_row['total'] ?? 0); $online_cnt = intval($online_row['cnt'] ?? 0);
$walkin_total = floatval($walkin_row['total'] ?? 0); $walkin_cnt = intval($walkin_row['cnt'] ?? 0);

$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $m_start = date('Y-m-01', strtotime("-$i months"));
    $m_end   = date('Y-m-t',  strtotime("-$i months"));
    $r = $conn->query("SELECT SUM(total_price) AS rev, COUNT(*) AS cnt FROM bookings WHERE status IN ($confirmed_statuses) AND created_at BETWEEN '$m_start' AND '$m_end 23:59:59'");
    $row = $r->fetch_assoc();
    $monthly[] = ['label'=>date('M Y',strtotime($m_start)),'rev'=>floatval($row['rev']??0),'cnt'=>intval($row['cnt']??0)];
}

$recent_q = $conn->query("SELECT b.id, b.guest_name, b.booking_type, b.mode, b.total_price, b.status, b.created_at, f.name AS facility_name, a.name AS area_name FROM bookings b JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.status IN ($confirmed_statuses) $date_where ORDER BY b.created_at DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Payment Summary - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <?php require_once '../includes/admin_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/admin_sidebar.php'; ?>
    <div class="content">
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-receipt me-2" style="color:#1B7D3A;"></i>Payment Summary</div>
                <div class="dash-topbar-sub"><?= date('l, F j, Y') ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-shield-alt me-1"></i>Admin</span>
                <span style="font-size:.85rem;color:#888;"><?= htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??'')) ?></span>
            </div>
        </div>
        <div class="dash-body">
            <div class="d-flex gap-2 mb-4 flex-wrap">
                <?php foreach (['all'=>'All Time','today'=>'Today','week'=>'Last 7 Days','month'=>'This Month'] as $k=>$lbl): ?>
                <a href="?period=<?= $k ?>" style="padding:7px 18px;border-radius:20px;font-size:.82rem;font-weight:700;text-decoration:none;border:1.5px solid <?= $filter_period===$k?'transparent':'#e0e0e0' ?>;background:<?= $filter_period===$k?'linear-gradient(135deg,#1B7D3A,#27A457)':'#fff' ?>;color:<?= $filter_period===$k?'#fff':'#555' ?>;"><?= $lbl ?></a>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon green"><i class="fas fa-peso-sign"></i></div><div><div class="kpi-num">&#8369;<?= number_format($total_revenue,2) ?></div><div class="kpi-lbl">Total Revenue</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon blue"><i class="fas fa-check-circle"></i></div><div><div class="kpi-num" data-count="<?= $total_confirmed ?>"><?= $total_confirmed ?></div><div class="kpi-lbl">Confirmed Bookings</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon yellow"><i class="fas fa-hourglass-half"></i></div><div><div class="kpi-num" data-count="<?= $pending_cnt ?>"><?= $pending_cnt ?></div><div class="kpi-lbl">Pending Payments</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon teal"><i class="fas fa-calendar-day"></i></div><div><div class="kpi-num">&#8369;<?= number_format($today_rev,2) ?></div><div class="kpi-lbl">Today's Revenue</div></div></div></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="section-hdr"><h5><i class="fas fa-globe me-2" style="color:#1565c0;"></i>Online Bookings (Confirmed)</h5></div>
                    <div class="table-card d-flex align-items-center gap-4">
                        <div style="width:64px;height:64px;border-radius:16px;background:#e3f2fd;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#1565c0;flex-shrink:0;"><i class="fas fa-globe"></i></div>
                        <div><div style="font-size:2rem;font-weight:900;color:#1565c0;">&#8369;<?= number_format($online_total,2) ?></div><div style="font-size:.85rem;color:#888;"><?= $online_cnt ?> confirmed booking<?= $online_cnt!=1?'s':'' ?></div></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="section-hdr"><h5><i class="fas fa-walking me-2" style="color:#1B7D3A;"></i>Walk-in Bookings (Confirmed)</h5></div>
                    <div class="table-card d-flex align-items-center gap-4">
                        <div style="width:64px;height:64px;border-radius:16px;background:#e8f5e9;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#1B7D3A;flex-shrink:0;"><i class="fas fa-walking"></i></div>
                        <div><div style="font-size:2rem;font-weight:900;color:#1B7D3A;">&#8369;<?= number_format($walkin_total,2) ?></div><div style="font-size:.85rem;color:#888;"><?= $walkin_cnt ?> confirmed booking<?= $walkin_cnt!=1?'s':'' ?></div></div>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="section-hdr"><h5><i class="fas fa-chart-pie me-2" style="color:#6a1b9a;"></i>Revenue by Type</h5></div>
                    <div class="chart-card"><div class="chart-wrap" style="height:220px;"><canvas id="typeChart"></canvas></div></div>
                </div>
                <div class="col-lg-8">
                    <div class="section-hdr"><h5><i class="fas fa-chart-bar me-2" style="color:#1B7D3A;"></i>Monthly Revenue Trend</h5></div>
                    <div class="chart-card"><div class="chart-wrap" style="height:220px;"><canvas id="trendChart"></canvas></div></div>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="section-hdr"><h5><i class="fas fa-table me-2" style="color:#1B7D3A;"></i>Breakdown by Booking Type</h5></div>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Type</th><th>Confirmed</th><th>Total Amount</th><th>Share</th></tr></thead>
                                <tbody>
                                <?php
                                $grand = $online_total + $walkin_total;
                                foreach ([['Online',$online_cnt,$online_total,'#1565c0','pill-blue'],['Walk-in',$walkin_cnt,$walkin_total,'#1B7D3A','pill-green']] as $r):
                                    $pct = $grand > 0 ? round(($r[2]/$grand)*100,1) : 0;
                                ?>
                                <tr>
                                    <td><span class="pill <?= $r[4] ?>"><?= $r[0] ?></span></td>
                                    <td><?= $r[1] ?></td>
                                    <td><strong>&#8369;<?= number_format($r[2],2) ?></strong></td>
                                    <td><div style="display:flex;align-items:center;gap:8px;"><div style="flex:1;height:6px;background:#f0f0f0;border-radius:3px;overflow:hidden;"><div style="width:<?= $pct ?>%;height:100%;background:<?= $r[3] ?>;border-radius:3px;"></div></div><span style="font-size:.78rem;color:#888;white-space:nowrap;"><?= $pct ?>%</span></div></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background:#f8fffe;"><td><strong>Total</strong></td><td><strong><?= $online_cnt+$walkin_cnt ?></strong></td><td><strong style="color:#1B7D3A;">&#8369;<?= number_format($grand,2) ?></strong></td><td><strong>100%</strong></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section-hdr"><h5><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Recent Confirmed Bookings</h5><p>Latest 15 confirmed bookings<?= $filter_period!=='all'?' for selected period':'' ?></p></div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>Guest Name</th><th>Type</th><th>Mode</th><th>Location</th><th>Facility</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php if ($recent_q && $recent_q->num_rows > 0): while ($bk = $recent_q->fetch_assoc()): ?>
                        <tr>
                            <td><strong style="color:#1B7D3A;">#<?= $bk['id'] ?></strong></td>
                            <td><?= htmlspecialchars($bk['guest_name']) ?></td>
                            <td><span class="pill <?= $bk['booking_type']==='online'?'pill-blue':'pill-green' ?>"><?= ucfirst(str_replace(['_','-'],' ',$bk['booking_type'])) ?></span></td>
                            <td><?= ucfirst($bk['mode']??'—') ?></td>
                            <td><?= htmlspecialchars($bk['area_name']??'—') ?></td>
                            <td><?= htmlspecialchars($bk['facility_name']) ?></td>
                            <td><strong style="color:#1B7D3A;">&#8369;<?= number_format($bk['total_price'],2) ?></strong></td>
                            <td><span class="pill pill-green"><?= ucfirst($bk['status']) ?></span></td>
                            <td style="font-size:.8rem;color:#888;"><?= date('M d, Y h:i A', strtotime($bk['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No confirmed bookings found.</td></tr>
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
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.kpi-num[data-count]').forEach((el,i)=>{const t=parseInt(el.getAttribute('data-count'),10);setTimeout(()=>{const s=performance.now();const u=(n)=>{const p=Math.min((n-s)/800,1);el.textContent=Math.round((1-Math.pow(1-p,3))*t);if(p<1)requestAnimationFrame(u);};requestAnimationFrame(u);},i*80);});
    new Chart(document.getElementById('typeChart').getContext('2d'),{type:'doughnut',data:{labels:['Online','Walk-in'],datasets:[{data:[<?= $online_total ?>,<?= $walkin_total ?>],backgroundColor:['#e3f2fd','#e8f5e9'],borderColor:['#1565c0','#1B7D3A'],borderWidth:2,hoverOffset:8}]},options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{font:{size:12},padding:14,usePointStyle:true}}}}});
    const monthLabels=<?= json_encode(array_column($monthly,'label')) ?>;
    const monthRev=<?= json_encode(array_column($monthly,'rev')) ?>;
    const ctx2=document.getElementById('trendChart').getContext('2d');
    new Chart(ctx2,{type:'bar',data:{labels:monthLabels,datasets:[{label:'Revenue',data:monthRev,backgroundColor:monthRev.map((v,i)=>{const max=Math.max(...monthRev);return v===max?'#1B7D3A':'rgba(27,125,58,.45)';}),borderRadius:8,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'#1B7D3A',callbacks:{label:c=>' ₱'+c.parsed.y.toLocaleString('en-PH',{minimumFractionDigits:2})}}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'₱'+(v>=1000?(v/1000).toFixed(0)+'k':v),font:{size:11},color:'#888'},grid:{color:'rgba(0,0,0,.04)'},border:{display:false}},x:{ticks:{font:{size:11},color:'#888'},grid:{display:false},border:{display:false}}}}});
});
</script>
</body>
</html>
