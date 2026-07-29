<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// require_role('owner'); // Removed for refactoring

$user = get_user_info($_SESSION['user_id'], $conn);



// Handle search
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
    <title>Staff History - Resort Management</title>
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
                <div class="dash-topbar-title"><i class="fas fa-users-cog me-2" style="color:#1B7D3A;"></i>Staff History</div>
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
                    <input type="text" class="form-control" name="search" placeholder="Search by name, username, email or role..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-add"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="?" class="btn-del"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Staff Table -->
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>All Staff</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Complete Name</th>
                                <th>Contact Number</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($staff_history_result && $staff_history_result->num_rows > 0): ?>
                                <?php while ($staff = $staff_history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['username']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['phone'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                        <td><span class="pill pill-blue"><?php echo ucfirst(str_replace('_', ' ', $staff['role'])); ?></span></td>
                                        <td>
                                            <span class="pill <?php echo $staff['status'] === 'active' ? 'pill-green' : 'pill-red'; ?>">
                                                <?php echo ucfirst($staff['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($staff['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">No staff records found.</td></tr>
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
