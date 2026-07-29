<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('admin');
$user = get_user_info($_SESSION['user_id'], $conn);

$current_user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "1=1";
if (!empty($search)) {
    $sl = $conn->real_escape_string($search);
    $where .= " AND (b.guest_name LIKE '%$sl%' OR f.name LIKE '%$sl%' OR b.status LIKE '%$sl%')";
}

$online_result = $conn->query("SELECT b.*, f.name as facility_name, a.name as area_name, u.first_name, u.last_name, u.role FROM bookings b JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id LEFT JOIN users u ON b.created_by=u.id WHERE $where AND b.booking_type='online' ORDER BY b.created_at DESC LIMIT 100");
$walkin_result = $conn->query("SELECT b.*, f.name as facility_name, a.name as area_name, u.first_name, u.last_name, u.role FROM bookings b JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id LEFT JOIN users u ON b.created_by=u.id WHERE $where AND b.booking_type IN ('walkin','walk_in','walk-in') ORDER BY b.created_at DESC LIMIT 100");

$online_count  = $online_result ? $online_result->num_rows : 0;
$walkin_count  = $walkin_result ? $walkin_result->num_rows : 0;
$total_count   = $online_count + $walkin_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/admin_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/admin_sidebar.php'; ?>
    <div class="content">
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-history me-2" style="color:#1B7D3A;"></i>Booking History</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-user-shield me-1"></i>Admin</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>

            <!-- KPI + Search -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div class="d-flex gap-3">
                    <div class="kpi-card" style="min-width:150px;">
                        <div class="kpi-icon blue"><i class="fas fa-list"></i></div>
                        <div><div class="kpi-num" data-count="<?= $total_count ?>">0</div><div class="kpi-lbl">Total Bookings</div></div>
                    </div>
                    <div class="kpi-card" style="min-width:150px;">
                        <div class="kpi-icon green"><i class="fas fa-globe"></i></div>
                        <div><div class="kpi-num" data-count="<?= $online_count ?>">0</div><div class="kpi-lbl">Online</div></div>
                    </div>
                    <div class="kpi-card" style="min-width:150px;">
                        <div class="kpi-icon orange"><i class="fas fa-walking"></i></div>
                        <div><div class="kpi-num" data-count="<?= $walkin_count ?>">0</div><div class="kpi-lbl">Walk-in</div></div>
                    </div>
                </div>
                <form method="GET" class="search-bar">
                    <input type="text" class="form-control" name="search" placeholder="Search guest, facility..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search)): ?><a href="?" class="btn-clear"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>
            </div>

            <!-- Online Bookings -->
            <div class="section-hdr"><h5><i class="fas fa-globe me-2" style="color:#1565c0;"></i>Online Bookings</h5><p>Bookings you approved from the online channel</p></div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>ID</th><th>Guest Name</th><th>Location</th><th>Facility</th><th>Mode</th><th>Check-in</th><th>Check-out</th><th>Guests</th><th>Total Price</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php if ($online_result && $online_result->num_rows > 0): while ($r=$online_result->fetch_assoc()): $st=$r['status']; $pc=$st==='completed'?'pill-green':($st==='confirmed'?'pill-blue':($st==='pending'?'pill-yellow':'pill-red')); ?>
                        <tr>
                            <td><strong style="color:#1B7D3A;">#<?= $r['id'] ?></strong></td>
                            <td><?= htmlspecialchars($r['guest_name']) ?></td>
                            <td><?= htmlspecialchars($r['area_name']??'—') ?></td>
                            <td><?= htmlspecialchars($r['facility_name']) ?></td>
                            <td><?= ucfirst($r['mode']??'overnight') ?></td>
                            <td><?= date('M d, Y',strtotime($r['check_in_date'])) ?></td>
                            <td><?= date('M d, Y',strtotime($r['check_out_date'])) ?></td>
                            <td><?= $r['num_guests'] ?></td>
                            <td><strong>₱<?= number_format($r['total_price'],2) ?></strong></td>
                            <td><span class="pill <?= $pc ?>"><?= ucfirst($st) ?></span></td>
                            <td style="font-size:.8rem;color:#888;"><?= date('M d, Y',strtotime($r['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No online bookings found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Walk-in Bookings -->
            <div class="section-hdr"><h5><i class="fas fa-walking me-2" style="color:#1B7D3A;"></i>Walk-in Bookings</h5><p>Walk-in bookings you created</p></div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>ID</th><th>Guest Name</th><th>Location</th><th>Facility</th><th>Mode</th><th>Check-in</th><th>Check-out</th><th>Guests</th><th>Total Price</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php if ($walkin_result && $walkin_result->num_rows > 0): while ($r=$walkin_result->fetch_assoc()): $st=$r['status']; $pc=$st==='completed'?'pill-green':($st==='confirmed'?'pill-blue':($st==='pending'?'pill-yellow':'pill-red')); ?>
                        <tr>
                            <td><strong style="color:#1B7D3A;">#<?= $r['id'] ?></strong></td>
                            <td><?= htmlspecialchars($r['guest_name']) ?></td>
                            <td><?= htmlspecialchars($r['area_name']??'—') ?></td>
                            <td><?= htmlspecialchars($r['facility_name']) ?></td>
                            <td><?= ucfirst($r['mode']??'overnight') ?></td>
                            <td><?= date('M d, Y',strtotime($r['check_in_date'])) ?></td>
                            <td><?= date('M d, Y',strtotime($r['check_out_date'])) ?></td>
                            <td><?= $r['num_guests'] ?></td>
                            <td><strong>₱<?= number_format($r['total_price'],2) ?></strong></td>
                            <td><span class="pill <?= $pc ?>"><?= ucfirst($st) ?></span></td>
                            <td style="font-size:.8rem;color:#888;"><?= date('M d, Y',strtotime($r['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No walk-in bookings found.</td></tr>
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
        const t=parseInt(el.getAttribute('data-count'),10);
        setTimeout(()=>{ const s=performance.now(); const u=(n)=>{ const p=Math.min((n-s)/800,1); el.textContent=Math.round((1-Math.pow(1-p,3))*t); if(p<1)requestAnimationFrame(u); }; requestAnimationFrame(u); },i*80);
    });
});
</script>
</body></html>
