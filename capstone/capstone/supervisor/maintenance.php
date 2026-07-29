<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('supervisor');
$user = get_user_info($_SESSION['user_id'], $conn);
// Handle add maintenance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_maintenance') {
    $facility_id = intval($_POST['facility_id']);
    $maintenance_type = escape_input($_POST['maintenance_type'], $conn);
    $description = escape_input($_POST['description'], $conn);
    $priority = escape_input($_POST['priority'], $conn);
    $scheduled_date = escape_input($_POST['scheduled_date'], $conn);
    $supervisor_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO maintenance (facility_id, maintenance_type, description, priority, scheduled_date, status, supervisor_id) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
    $stmt->bind_param("issssi", $facility_id, $maintenance_type, $description, $priority, $scheduled_date, $supervisor_id);

    if ($stmt->execute()) {
        set_success_message('Maintenance task created successfully');
    } else {
        set_error_message('Error creating maintenance task: ' . $conn->error);
    }
    $stmt->close();
}

// Handle update maintenance status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $maintenance_id = intval($_POST['maintenance_id']);
    $status = escape_input($_POST['status'], $conn);
    $completed_date = null;

    if ($status === 'completed') {
        $completed_date = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE maintenance SET status = ?, completed_date = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $completed_date, $maintenance_id);
    } else {
        $stmt = $conn->prepare("UPDATE maintenance SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $maintenance_id);
    }

    if ($stmt->execute()) {
        set_success_message('Maintenance status updated successfully');
    } else {
        set_error_message('Error updating maintenance: ' . $conn->error);
    }
    $stmt->close();
}

// Get pending maintenance
$maintenance_result = $conn->query("SELECT m.*, f.name as facility_name FROM maintenance m JOIN facilities f ON m.facility_id = f.id WHERE m.status != 'completed' ORDER BY m.priority DESC, m.scheduled_date ASC");

// Get all facilities
$facilities_result = $conn->query("SELECT * FROM facilities ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Maintenance - Supervisor</title>
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
                <div class="dash-topbar-title"><i class="fas fa-tools me-2" style="color:#1B7D3A;"></i>Facility Maintenance</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-hard-hat me-1"></i>Supervisor</span>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal"><i class="fas fa-plus"></i> Add Task</button>
            </div>
        </div>
        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>
            <div class="table-card">
                <div class="section-hdr mb-3"><h5>Active Maintenance Tasks</h5><p>Pending and in-progress requests</p></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Facility</th><th>Type</th><th>Description</th><th>Priority</th><th>Status</th><th>Scheduled</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php while ($maintenance = $maintenance_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($maintenance['facility_name']) ?></strong></td>
                            <td><?= htmlspecialchars($maintenance['maintenance_type']) ?></td>
                            <td style="font-size:.82rem;color:#888;"><?= htmlspecialchars(substr($maintenance['description']??'',0,40)).(strlen($maintenance['description']??'')>40?'…':'') ?></td>
                            <td>
                                <?php $p=$maintenance['priority']; $pc=$p==='high'?'pill-red':($p==='medium'?'pill-yellow':'pill-green'); ?>
                                <span class="pill <?= $pc ?>"><?= strtoupper($p) ?></span>
                            </td>
                            <td>
                                <?php $s=$maintenance['status']; $sc=$s==='completed'?'pill-green':($s==='in_progress'?'pill-yellow':'pill-grey'); ?>
                                <span class="pill <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$s)) ?></span>
                            </td>
                            <td><?= date('M d, Y', strtotime($maintenance['scheduled_date'])) ?></td>
                            <td>
                                <?php if ($maintenance['status'] !== 'completed'): ?>
                                <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#statusModal<?= $maintenance['id'] ?>"><i class="fas fa-edit"></i> Update</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <!-- Status Modal -->
                        <div class="modal fade" id="statusModal<?= $maintenance['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="maintenance_id" value="<?= $maintenance['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Facility</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($maintenance['facility_name']) ?>" readonly style="background:#f8fffe;">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status *</label>
                                                <select class="form-control" name="status" required>
                                                    <option value="pending" <?= $maintenance['status']==='pending'?'selected':'' ?>>Pending</option>
                                                    <option value="in_progress" <?= $maintenance['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                                                    <option value="completed" <?= $maintenance['status']==='completed'?'selected':'' ?>>Completed</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-add">Update</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add Maintenance Modal -->
<div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Maintenance Task</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_maintenance">
                    <div class="mb-3"><label class="form-label">Facility *</label>
                        <select class="form-control" name="facility_id" required>
                            <option value="">Select Facility</option>
                            <?php $facilities_result->data_seek(0); while ($f=$facilities_result->fetch_assoc()): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Maintenance Type *</label><input type="text" class="form-control" name="maintenance_type" required placeholder="e.g., AC Repair, Plumbing"></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Priority *</label>
                            <select class="form-control" name="priority" required>
                                <option value="">Select Priority</option>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Scheduled Date *</label><input type="date" class="form-control" name="scheduled_date" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Task</button></div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>