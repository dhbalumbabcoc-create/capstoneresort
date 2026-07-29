<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');
$user = get_user_info($_SESSION['user_id'], $conn);

// Search handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT m.*, f.name as facility_name FROM maintenance m JOIN facilities f ON m.facility_id = f.id";
if (!empty($search)) {
    $search_sql = $conn->real_escape_string($search);
    $query .= " WHERE f.name LIKE '%$search_sql%' OR m.maintenance_type LIKE '%$search_sql%'";
}
$query .= " ORDER BY m.completed_date DESC LIMIT 100";
$maintenance_result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance History - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/owner_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/owner_sidebar.php'; ?>
    <div class="content">
        <!-- topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-wrench me-2" style="color:#1B7D3A;"></i>Maintenance History</div>
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
                    <input type="text" class="form-control" name="search" placeholder="Search by facility or type..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-add"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="?" class="btn-del"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Maintenance Table -->
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>All Maintenance Records</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Facility</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Scheduled Date</th>
                                <th>Completed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($maintenance_result && $maintenance_result->num_rows > 0): ?>
                                <?php while ($maintenance = $maintenance_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($maintenance['facility_name']); ?></td>
                                        <td><?php echo htmlspecialchars($maintenance['maintenance_type']); ?></td>
                                        <td>
                                            <?php
                                            $pri = $maintenance['priority'];
                                            $priClass = $pri === 'high' ? 'pill-red' : ($pri === 'medium' ? 'pill-yellow' : 'pill-green');
                                            ?>
                                            <span class="pill <?php echo $priClass; ?>"><?php echo ucfirst($pri); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $st = $maintenance['status'];
                                            $stClass = $st === 'completed' ? 'pill-green' : ($st === 'in_progress' ? 'pill-yellow' : 'pill-grey');
                                            ?>
                                            <span class="pill <?php echo $stClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $st)); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($maintenance['scheduled_date'])); ?></td>
                                        <td><?php echo $maintenance['completed_date'] ? date('M d, Y H:i', strtotime($maintenance['completed_date'])) : '-'; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No maintenance records found.</td></tr>
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
