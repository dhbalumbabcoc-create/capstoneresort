<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('supervisor');

$user = get_user_info($_SESSION['user_id'], $conn);

// Get maintenance history
$maintenance_result = $conn->query("SELECT m.*, f.name as facility_name FROM maintenance m JOIN facilities f ON m.facility_id = f.id ORDER BY m.completed_date DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance History - Supervisor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/supervisor_page_styles.php'; ?>
</head>
<body>
<div class="main-container" style="display:flex;min-height:100vh;">
    <div class="sidebar-col" id="sidebarCol"><?php require_once '../includes/supervisor_sidebar.php'; ?></div>
    <div class="content" style="flex:1;min-width:0;">
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-history me-2" style="color:#1B7D3A;"></i>Maintenance History</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-hard-hat me-1"></i>Supervisor</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>All Maintenance Records</h5><p>Last 100 records ordered by completion date</p></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Facility</th><th>Type</th><th>Priority</th><th>Status</th><th>Scheduled</th><th>Completed</th></tr></thead>
                        <tbody>
                        <?php if ($maintenance_result && $maintenance_result->num_rows > 0): while ($m=$maintenance_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['facility_name']) ?></strong></td>
                            <td><?= htmlspecialchars($m['maintenance_type']) ?></td>
                            <td><?php $p=$m['priority']; $pc=$p==='high'?'pill-red':($p==='medium'?'pill-yellow':'pill-green'); ?><span class="pill <?= $pc ?>"><?= ucfirst($p) ?></span></td>
                            <td><?php $s=$m['status']; $sc=$s==='completed'?'pill-green':($s==='in_progress'?'pill-yellow':'pill-grey'); ?><span class="pill <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$s)) ?></span></td>
                            <td><?= date('M d, Y', strtotime($m['scheduled_date'])) ?></td>
                            <td><?= $m['completed_date'] ? date('M d, Y H:i', strtotime($m['completed_date'])) : '—' ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No maintenance records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>