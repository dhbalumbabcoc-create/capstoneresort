<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);

// Show all bookings
$online_result = $conn->query("SELECT b.*, f.name as facility_name, a.name as area_name, u.first_name, u.last_name, u.role FROM bookings b JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id LEFT JOIN users u ON b.created_by = u.id WHERE b.booking_type = 'online' ORDER BY b.created_at DESC");
$walkin_result = $conn->query("SELECT b.*, f.name as facility_name, a.name as area_name, u.first_name, u.last_name, u.role FROM bookings b JOIN facilities f ON b.facility_id = f.id LEFT JOIN areas a ON b.area_id = a.id LEFT JOIN users u ON b.created_by = u.id WHERE b.booking_type IN ('walkin', 'walk_in', 'walk-in') ORDER BY b.created_at DESC");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $search_sql = "%" . $conn->real_escape_string($search) . "%";
    $staff_history_result = $conn->query(
        "SELECT * FROM users WHERE role != 'owner' AND (
            CONCAT(first_name, ' ', last_name) LIKE '$search_sql' OR
            username LIKE '$search_sql' OR
            email LIKE '$search_sql' OR
            role LIKE '$search_sql'
        ) ORDER BY created_at DESC"
    );
} else {
    $staff_history_result = $conn->query("SELECT * FROM users WHERE role != 'owner' ORDER BY created_at DESC");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/owner_page_styles.php'; ?>
    <style>
    /* ── Booking History: fit 10-column table without horizontal scroll ── */
    .table-card { padding: 14px; }
    .table-card .table thead th {
        padding: 6px 8px;
        font-size: .69rem;
        white-space: nowrap;
    }
    .table-card .table tbody td {
        padding: 5px 8px;
        font-size: .79rem;
        vertical-align: middle;
        white-space: nowrap;
    }
    /* Fixed column widths across all 10 columns */
    .table-card .table th:nth-child(1),
    .table-card .table td:nth-child(1)  { width: 44px; }   /* ID */
    .table-card .table th:nth-child(2),
    .table-card .table td:nth-child(2)  { width: 110px; }  /* Facility */
    .table-card .table th:nth-child(3),
    .table-card .table td:nth-child(3)  { width: 115px; }  /* Guest Name */
    .table-card .table th:nth-child(4),
    .table-card .table td:nth-child(4)  { width: 82px; }   /* Check-in */
    .table-card .table th:nth-child(5),
    .table-card .table td:nth-child(5)  { width: 82px; }   /* Check-out */
    .table-card .table th:nth-child(6),
    .table-card .table td:nth-child(6)  { width: 70px; }   /* Mode */
    .table-card .table th:nth-child(7),
    .table-card .table td:nth-child(7)  { width: 78px; }   /* Status */
    .table-card .table th:nth-child(8),
    .table-card .table td:nth-child(8)  { width: 82px; }   /* Total Price */
    .table-card .table th:nth-child(9),
    .table-card .table td:nth-child(9)  { min-width: 140px; } /* Created By */
    .table-card .table th:nth-child(10),
    .table-card .table td:nth-child(10) { width: 115px; }  /* Date Created */
    /* Disable inner scroll on large screens */
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
                <div class="dash-topbar-title"><i class="fas fa-history me-2" style="color:#1B7D3A;"></i>Booking History</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_messages(); ?>

            <!-- Search -->
            <div class="d-flex justify-content-end mb-4">
                <form method="GET" class="search-bar">
                    <input type="text" class="form-control" name="search" placeholder="Search by name or type..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-add"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="?" class="btn-del"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Online Bookings -->
            <div class="table-card mb-4">
                <div class="section-hdr mb-3">
                    <h5><i class="fas fa-globe me-2" style="color:#1B7D3A;"></i>Online Bookings</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Facility</th>
                                <th>Guest Name</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Total Price</th>
                                <th>Created By</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($online_result && $online_result->num_rows > 0): ?>
                                <?php while ($row = $online_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['facility_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['check_in_date']); ?></td>
                                        <td><?php echo htmlspecialchars($row['check_out_date']); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($row['mode'])); ?></td>
                                        <td>
                                            <?php
                                            $st = $row['status'];
                                            $pc = $st === 'completed' ? 'pill-green' : ($st === 'confirmed' ? 'pill-blue' : ($st === 'pending' ? 'pill-yellow' : 'pill-red'));
                                            ?>
                                            <span class="pill <?php echo $pc; ?>"><?php echo ucfirst($st); ?></span>
                                        </td>
                                        <td>₱<?php echo number_format($row['total_price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?> <?php if ($row['role']): ?>(<?php echo htmlspecialchars($row['role']); ?>)<?php endif; ?></td>
                                        <td><?php echo isset($row['created_at']) ? date('Y-m-d H:i', strtotime($row['created_at'])) : '-'; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-3">No online bookings found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Walk-in Bookings -->
            <div class="table-card mb-4">
                <div class="section-hdr mb-3">
                    <h5><i class="fas fa-walking me-2" style="color:#1B7D3A;"></i>Walk-in Bookings</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Facility</th>
                                <th>Guest Name</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Total Price</th>
                                <th>Created By</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($walkin_result && $walkin_result->num_rows > 0): ?>
                                <?php while ($row = $walkin_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['facility_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['check_in_date']); ?></td>
                                        <td><?php echo htmlspecialchars($row['check_out_date']); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($row['mode'])); ?></td>
                                        <td>
                                            <?php
                                            $st = $row['status'];
                                            $pc = $st === 'completed' ? 'pill-green' : ($st === 'confirmed' ? 'pill-blue' : ($st === 'pending' ? 'pill-yellow' : 'pill-red'));
                                            ?>
                                            <span class="pill <?php echo $pc; ?>"><?php echo ucfirst($st); ?></span>
                                        </td>
                                        <td>₱<?php echo number_format($row['total_price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?> <?php if ($row['role']): ?>(<?php echo htmlspecialchars($row['role']); ?>)<?php endif; ?></td>
                                        <td><?php echo isset($row['created_at']) ? date('Y-m-d H:i', strtotime($row['created_at'])) : '-'; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-3">No walk-in bookings found.</td></tr>
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
});
initOwnerSidebar('ownerSidebarCollapsed');
</script>
</body></html>
