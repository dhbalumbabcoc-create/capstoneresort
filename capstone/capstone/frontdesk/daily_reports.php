<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('frontdesk');

$user = get_user_info($_SESSION['user_id'], $conn);
$today = date('Y-m-d');

// 1. Today’s Bookings
$bookings_today_query = "SELECT b.*, f.name as facility_name FROM bookings b JOIN facilities f ON b.facility_id = f.id WHERE DATE(b.created_at) = '$today' ORDER BY b.created_at DESC";
$bookings_today_result = $conn->query($bookings_today_query);
$walkin_count = $online_count = $approved_count = $pending_count = 0;
$todays_bookings = [];
if ($bookings_today_result && $bookings_today_result->num_rows > 0) {
    while ($b = $bookings_today_result->fetch_assoc()) {
        $todays_bookings[] = $b;
        if ($b['booking_type'] === 'walkin') $walkin_count++;
        if ($b['booking_type'] === 'online') $online_count++;
        if ($b['status'] === 'confirmed') $approved_count++;
        if ($b['status'] === 'pending') $pending_count++;
    }
}

// 2. Guest Check-ins & Check-outs
$checkin_query = "SELECT COUNT(*) as checkin_count FROM bookings WHERE DATE(check_in_date) = '$today' AND status IN ('pending', 'confirmed')";
$checkin_result = $conn->query($checkin_query);
$today_checkins = $checkin_result->fetch_assoc()['checkin_count'];
$checkout_query = "SELECT COUNT(*) as checkout_count FROM bookings WHERE DATE(check_out_date) = '$today' AND status IN ('pending', 'confirmed')";
$checkout_result = $conn->query($checkout_query);
$today_checkouts = $checkout_result->fetch_assoc()['checkout_count'];

$upcoming_query = "SELECT guest_name FROM bookings WHERE check_in_date = '$today' AND status IN ('pending', 'confirmed') ORDER BY guest_name ASC LIMIT 5";
$upcoming_result = $conn->query($upcoming_query);

// 3. Facility Usage Report
$most_booked_query = "SELECT f.name, COUNT(b.id) as cnt FROM bookings b JOIN facilities f ON b.facility_id = f.id WHERE DATE(b.created_at) = '$today' GROUP BY b.facility_id ORDER BY cnt DESC LIMIT 1";
$most_booked_result = $conn->query($most_booked_query);
$most_booked = $most_booked_result && $most_booked_result->num_rows > 0 ? $most_booked_result->fetch_assoc() : null;
$available_query = "SELECT COUNT(*) as cnt FROM facilities WHERE status = 'available'";
$occupied_query = "SELECT COUNT(*) as cnt FROM facilities WHERE status = 'occupied'";
$maintenance_query = "SELECT COUNT(*) as cnt FROM facilities WHERE status = 'maintenance'";
$available = $conn->query($available_query)->fetch_assoc()['cnt'];
$occupied = $conn->query($occupied_query)->fetch_assoc()['cnt'];
$under_maintenance = $conn->query($maintenance_query)->fetch_assoc()['cnt'];

// 4. Guest Feedback
$feedback_today_query = "SELECT rating, comment FROM feedback WHERE DATE(created_at) = '$today' ORDER BY created_at DESC";
$feedback_today_result = $conn->query($feedback_today_query);
$feedback_count = $feedback_today_result ? $feedback_today_result->num_rows : 0;
$ratings = [];
$complaints = 0;
if ($feedback_today_result && $feedback_today_result->num_rows > 0) {
    while ($f = $feedback_today_result->fetch_assoc()) {
        $ratings[] = $f['rating'];
        if (stripos($f['comment'], 'complain') !== false || stripos($f['comment'], 'bad') !== false || stripos($f['comment'], 'poor') !== false) {
            $complaints++;
        }
    }
}
$avg_rating = count($ratings) ? round(array_sum($ratings)/count($ratings), 2) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Reports - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <?php require_once '../includes/frontdesk_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/frontdesk_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-calendar-day me-2" style="color:#1B7D3A;"></i>Daily Reports</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-concierge-bell me-1"></i>Front Desk</span>
                <span style="font-size:.85rem;color:#888;"><?php echo isset($user) ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : ''; ?></span>
            </div>
        </div>

        <div class="dash-body">

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-calendar-check"></i></div>
                        <div><div class="kpi-num" data-count="<?php echo count($todays_bookings); ?>"><?php echo count($todays_bookings); ?></div><div class="kpi-lbl">Today's Bookings</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-walking"></i></div>
                        <div><div class="kpi-num" data-count="<?php echo $walkin_count; ?>"><?php echo $walkin_count; ?></div><div class="kpi-lbl">Walk-in</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-globe"></i></div>
                        <div><div class="kpi-num" data-count="<?php echo $online_count; ?>"><?php echo $online_count; ?></div><div class="kpi-lbl">Online</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                        <div><div class="kpi-num" data-count="<?php echo $pending_count; ?>"><?php echo $pending_count; ?></div><div class="kpi-lbl">Pending</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-sign-in-alt"></i></div>
                        <div><div class="kpi-num" data-count="<?php echo $today_checkins; ?>"><?php echo $today_checkins; ?></div><div class="kpi-lbl">Check-ins Today</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-sign-out-alt"></i></div>
                        <div><div class="kpi-num" data-count="<?php echo $today_checkouts; ?>"><?php echo $today_checkouts; ?></div><div class="kpi-lbl">Check-outs Today</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon teal"><i class="fas fa-door-open"></i></div>
                        <div><div class="kpi-num" data-count="<?php echo $available; ?>"><?php echo $available; ?></div><div class="kpi-lbl">Available Facilities</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon purple"><i class="fas fa-star"></i></div>
                        <div><div class="kpi-num"><?php echo $avg_rating !== 'N/A' ? $avg_rating : 'N/A'; ?></div><div class="kpi-lbl">Avg Rating Today</div></div>
                    </div>
                </div>
            </div>

            <!-- Today's Bookings Table -->
            <div class="section-hdr">
                <h5><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Today's Bookings</h5>
                <p>All bookings created today — <?php echo date('F j, Y'); ?></p>
            </div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Guest Name</th>
                                <th>Facility</th>
                                <th>Check-in</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($todays_bookings) > 0): foreach ($todays_bookings as $b): ?>
                            <tr>
                                <td><strong style="color:#1B7D3A;">#<?php echo $b['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($b['guest_name']); ?></td>
                                <td><?php echo htmlspecialchars($b['facility_name']); ?></td>
                                <td><?php echo date('M d', strtotime($b['check_in_date'])); ?></td>
                                <td><?php echo ucfirst($b['mode'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $st = $b['status'];
                                    $pc = $st==='confirmed' ? 'pill-green' : ($st==='pending' ? 'pill-yellow' : 'pill-red');
                                    ?>
                                    <span class="pill <?php echo $pc; ?>"><?php echo ucfirst($st); ?></span>
                                </td>
                                <td><span class="pill pill-blue"><?php echo ucfirst($b['booking_type']); ?></span></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No bookings today</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Check-ins + Facility Usage -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="section-hdr"><h5><i class="fas fa-sign-in-alt me-2" style="color:#1565c0;"></i>Today's Check-ins</h5></div>
                    <div class="table-card">
                        <div class="d-flex gap-3 mb-3">
                            <div class="kpi-card flex-fill" style="padding:14px;">
                                <div class="kpi-icon green" style="width:38px;height:38px;font-size:1rem;"><i class="fas fa-sign-in-alt"></i></div>
                                <div><div class="kpi-num" style="font-size:1.3rem;"><?php echo $today_checkins; ?></div><div class="kpi-lbl">Checked-in</div></div>
                            </div>
                            <div class="kpi-card flex-fill" style="padding:14px;">
                                <div class="kpi-icon orange" style="width:38px;height:38px;font-size:1rem;"><i class="fas fa-sign-out-alt"></i></div>
                                <div><div class="kpi-num" style="font-size:1.3rem;"><?php echo $today_checkouts; ?></div><div class="kpi-lbl">Checked-out</div></div>
                            </div>
                        </div>
                        <div class="section-hdr mb-2"><h5 style="font-size:.88rem;">Upcoming Check-ins</h5></div>
                        <ul class="list-unstyled mb-0">
                            <?php if ($upcoming_result && $upcoming_result->num_rows > 0): while ($u = $upcoming_result->fetch_assoc()): ?>
                            <li class="d-flex align-items-center gap-2 py-2" style="border-bottom:1px solid #f5f5f5;">
                                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1B7D3A,#27A457);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700;flex-shrink:0;">
                                    <?php echo strtoupper(substr($u['guest_name'],0,1)); ?>
                                </div>
                                <span style="font-size:.88rem;"><?php echo htmlspecialchars($u['guest_name']); ?></span>
                            </li>
                            <?php endwhile; else: ?>
                            <li class="text-muted text-center py-3" style="font-size:.88rem;"><i class="fas fa-inbox me-2"></i>No check-ins found</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="section-hdr"><h5><i class="fas fa-building me-2" style="color:#e65100;"></i>Facility Usage Report</h5></div>
                    <div class="table-card">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div style="background:#e8f5e9;border-radius:12px;padding:14px;text-align:center;">
                                    <div style="font-size:1.5rem;font-weight:900;color:#1B7D3A;"><?php echo $available; ?></div>
                                    <div style="font-size:.78rem;color:#888;">Available</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background:#fdecea;border-radius:12px;padding:14px;text-align:center;">
                                    <div style="font-size:1.5rem;font-weight:900;color:#c62828;"><?php echo $under_maintenance; ?></div>
                                    <div style="font-size:.78rem;color:#888;">Maintenance</div>
                                </div>
                            </div>
                        </div>
                        <div class="section-hdr mb-2"><h5 style="font-size:.88rem;">Most Booked Today</h5></div>
                        <?php if ($most_booked): ?>
                        <div style="background:#f0faf4;border-radius:12px;padding:14px;display:flex;align-items:center;gap:12px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#1B7D3A,#27A457);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;flex-shrink:0;">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:.92rem;"><?php echo htmlspecialchars($most_booked['name']); ?></div>
                                <div style="font-size:.78rem;color:#888;"><?php echo $most_booked['cnt']; ?> booking<?php echo $most_booked['cnt']!=1?'s':''; ?> today</div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-muted text-center py-3" style="font-size:.88rem;"><i class="fas fa-inbox me-2"></i>No bookings today</div>
                        <?php endif; ?>

                        <?php if ($feedback_count > 0): ?>
                        <div class="mt-3">
                            <div class="section-hdr mb-2"><h5 style="font-size:.88rem;">Guest Feedback Today</h5></div>
                            <div style="background:#f8fffe;border-radius:12px;padding:14px;display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-size:1.3rem;font-weight:900;color:#1B7D3A;"><?php echo $avg_rating; ?></div>
                                    <div style="font-size:.78rem;color:#888;"><?php echo $feedback_count; ?> review<?php echo $feedback_count!=1?'s':''; ?></div>
                                </div>
                                <div style="font-size:1.2rem;">
                                    <?php for($i=1;$i<=5;$i++) echo '<i class="fas fa-star" style="color:'.($i<=$avg_rating?'#f9a825':'#ddd').';font-size:.9rem;"></i>'; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.kpi-num[data-count]').forEach((el,i) => {
        const t = parseInt(el.getAttribute('data-count'),10);
        setTimeout(() => { const s=performance.now(); const u=(n)=>{ const p=Math.min((n-s)/800,1); el.textContent=Math.round((1-Math.pow(1-p,3))*t); if(p<1)requestAnimationFrame(u); }; requestAnimationFrame(u); }, i*80);
    });
});
initFrontdeskSidebar();
</script>
</body></html>