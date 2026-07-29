<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);

// Handle add staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $first_name = escape_input($_POST['first_name'], $conn);
    $last_name = escape_input($_POST['last_name'], $conn);
    $username = escape_input($_POST['username'], $conn);
    $email = escape_input($_POST['email'], $conn);
    $phone = escape_input($_POST['phone'], $conn);
    $role = escape_input($_POST['role'], $conn);
    $password = $_POST['password'];

    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($role)) {
        set_error_message('Please fill in all required fields');
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            set_error_message('Email already exists. Please use a different email address.');
        } else {
            // Check if username already exists
            $check_stmt2 = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt2->bind_param("s", $username);
            $check_stmt2->execute();
            $check_result2 = $check_stmt2->get_result();
            
            if ($check_result2->num_rows > 0) {
                set_error_message('Username already exists. Please use a different username.');
            } else {
                $password_to_store = hash_password($password);
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, phone, role, password, must_change_password, email_verified, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, 'pending')");
                $stmt->bind_param("sssssss", $first_name, $last_name, $username, $email, $phone, $role, $password_to_store);

                if ($stmt->execute()) {
                    set_success_message('Staff member added successfully. They will be prompted to set a new password and verify their email on first login.');
                } else {
                    set_error_message('Error adding staff: ' . $stmt->error);
                }
                $stmt->close();
            }
            $check_stmt2->close();
        }
        $check_stmt->close();
    }
}

// Handle deactivate staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deactivate_staff') {
    $staff_id = intval($_POST['staff_id']);
    
    // First, update any bookings created by this staff member
    $update_bookings = $conn->prepare("UPDATE bookings SET created_by = NULL WHERE created_by = ?");
    $update_bookings->bind_param("i", $staff_id);
    
    if (!$update_bookings->execute()) {
        set_error_message('Error updating bookings: ' . $conn->error);
    } else {
        // Then mark the staff as inactive
        $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role != 'owner'");
        $stmt->bind_param("i", $staff_id);

        if ($stmt->execute()) {
            set_success_message('Staff member deactivated successfully');
        } else {
            set_error_message('Error deactivating staff: ' . $conn->error);
        }
        $stmt->close();
    }
    $update_bookings->close();
}

// Handle activate staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_staff') {
    $staff_id = intval($_POST['staff_id']);
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role != 'owner'");
    $stmt->bind_param("i", $staff_id);
    if ($stmt->execute()) {
        set_success_message('Staff member activated successfully');
    } else {
        set_error_message('Error activating staff: ' . $stmt->error);
    }
    $stmt->close();
}

// Handle delete staff — permanently removes the record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_staff') {
    $staff_id = intval($_POST['staff_id']);

    // Nullify foreign key references before deleting
    $conn->prepare("UPDATE bookings SET created_by = NULL WHERE created_by = ?")->bind_param("i", $staff_id);
    $s1 = $conn->prepare("UPDATE bookings SET created_by = NULL WHERE created_by = ?");
    $s1->bind_param("i", $staff_id); $s1->execute(); $s1->close();

    $s2 = $conn->prepare("UPDATE maintenance SET supervisor_id = NULL WHERE supervisor_id = ?");
    $s2->bind_param("i", $staff_id); $s2->execute(); $s2->close();

    // Delete the user (only non-owner)
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'owner'");
    $stmt->bind_param("i", $staff_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        set_success_message('Staff member permanently deleted.');
    } else {
        set_error_message('Could not delete staff. They may be an owner account.');
    }
    $stmt->close();
}

// Add Edit Staff Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_staff') {
    $staff_id = intval($_POST['staff_id']);
    $first_name = escape_input($_POST['first_name'], $conn);
    $last_name = escape_input($_POST['last_name'], $conn);
    $email = escape_input($_POST['email'], $conn);
    $phone = escape_input($_POST['phone'], $conn);
    $role = escape_input($_POST['role'], $conn);

    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=? WHERE id=? AND role != 'owner'");
    $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $role, $staff_id);
    if ($stmt->execute()) {
        set_success_message('Staff member updated successfully');
    } else {
        set_error_message('Error updating staff: ' . $stmt->error);
    }
    $stmt->close();
}

// Get active (including pending activation) and inactive staff separately
$active_staff_result = $conn->query("SELECT * FROM users WHERE role != 'owner' AND status IN ('active', 'pending') ORDER BY created_at DESC");
$inactive_staff_result = $conn->query("SELECT * FROM users WHERE role != 'owner' AND status = 'inactive' ORDER BY created_at DESC");
$active_staff_rows = $active_staff_result ? $active_staff_result->fetch_all(MYSQLI_ASSOC) : [];
$inactive_staff_rows = $inactive_staff_result ? $inactive_staff_result->fetch_all(MYSQLI_ASSOC) : [];

// Count pending activation (status='pending' or must_change_password = 1)
$pending_activation_count = count(array_filter($active_staff_rows, fn($s) => $s['status'] === 'pending' || !empty($s['must_change_password'])));
$fully_active_count = count($active_staff_rows) - $pending_activation_count;
$active_count = count($active_staff_rows);
$inactive_count = count($inactive_staff_rows);

$search = '';
if (isset($_GET['search'])) {
    $search = escape_input($_GET['search'], $conn);
}


// Apply search filter to both active/pending and inactive staff queries
if (!empty($search)) {
    $search_like = "%{$search}%";
    $active_staff_result = $conn->query("SELECT * FROM users WHERE role != 'owner' AND status IN ('active', 'pending') AND (username LIKE '$search_like' OR first_name LIKE '$search_like' OR last_name LIKE '$search_like' OR email LIKE '$search_like' OR phone LIKE '$search_like' OR role LIKE '$search_like') ORDER BY created_at DESC");
    $inactive_staff_result = $conn->query("SELECT * FROM users WHERE role != 'owner' AND status = 'inactive' AND (username LIKE '$search_like' OR first_name LIKE '$search_like' OR last_name LIKE '$search_like' OR email LIKE '$search_like' OR phone LIKE '$search_like' OR role LIKE '$search_like') ORDER BY created_at DESC");
    $active_staff_rows = $active_staff_result ? $active_staff_result->fetch_all(MYSQLI_ASSOC) : [];
    $inactive_staff_rows = $inactive_staff_result ? $inactive_staff_result->fetch_all(MYSQLI_ASSOC) : [];
    $active_count = count($active_staff_rows);
    $inactive_count = count($inactive_staff_rows);
    $pending_activation_count = count(array_filter($active_staff_rows, fn($s) => $s['status'] === 'pending' || !empty($s['must_change_password'])));
    $fully_active_count = $active_count - $pending_activation_count;
}?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/owner_page_styles.php'; ?>
    <style>
    /* ── Manage Staff: fit full table without horizontal scroll ── */
    .table-card { padding: 12px; }
    .table-card .table thead th {
        padding: 7px 8px;
        font-size: .70rem;
        white-space: nowrap;
    }
    .table-card .table tbody td {
        padding: 6px 8px;
        font-size: .80rem;
        vertical-align: middle;
        white-space: nowrap;
    }
    /* Compress action buttons */
    .actions-cell { display: flex; gap: 4px; align-items: center; flex-wrap: nowrap; }
    .actions-cell .btn-view,
    .actions-cell .btn-del,
    .actions-cell .btn-ok {
        padding: 4px 9px;
        font-size: .75rem;
        border-radius: 7px;
        white-space: nowrap;
    }
    /* Keep username / email columns from being too greedy */
    .col-username { width: 90px; }
    .col-contact  { width: 100px; }
    .col-role     { width: 85px; }
    .col-status   { width: 115px; }
    .col-actions  { width: 210px; }
    /* Remove inner horizontal scroll — table fills card naturally */
    .table-responsive { overflow-x: visible; }
    @media (max-width: 1200px) {
        .table-responsive { overflow-x: auto; }
    }
    </style>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/owner_sidebar.php'; ?>
    <div class="content">
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-users me-2" style="color:#1B7D3A;"></i>Manage Staff</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>
            <!-- KPI + Actions row -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div class="d-flex gap-3">
                    <div class="kpi-card" style="min-width:160px;">
                        <div class="kpi-icon green"><i class="fas fa-user-check"></i></div>
                        <div><div class="kpi-num" data-count="<?= $fully_active_count ?>">0</div><div class="kpi-lbl">Active Staff</div></div>
                    </div>
                    <div class="kpi-card" style="min-width:160px;">
                        <div class="kpi-icon yellow"><i class="fas fa-user-clock"></i></div>
                        <div><div class="kpi-num" data-count="<?= $pending_activation_count ?>">0</div><div class="kpi-lbl">Pending Activation</div></div>
                    </div>
                    <div class="kpi-card" style="min-width:160px;">
                        <div class="kpi-icon red"><i class="fas fa-user-slash"></i></div>
                        <div><div class="kpi-num" data-count="<?= $inactive_count ?>">0</div><div class="kpi-lbl">Inactive Staff</div></div>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <form method="GET" class="search-bar">
                        <input type="text" class="form-control" name="search" placeholder="Search staff..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search)): ?><a href="?" class="btn-clear"><i class="fas fa-times"></i></a><?php endif; ?>
                    </form>
                    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="fas fa-plus"></i> Add Staff
                    </button>
                </div>
            </div>
            <!-- Active Staff -->
            <div class="section-hdr"><h5><i class="fas fa-circle me-2" style="color:#1B7D3A;font-size:.6rem;"></i>Active Staff</h5></div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th class="col-username">Username</th><th>Full Name</th><th class="col-contact">Contact</th><th>Email</th><th class="col-role">Role</th><th class="col-status">Status</th><th class="col-actions">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($active_staff_rows as $staff): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($staff['username']) ?></strong></td>
                            <td><?= htmlspecialchars($staff['first_name'].' '.$staff['last_name']) ?></td>
                            <td><?= htmlspecialchars($staff['phone'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($staff['email']) ?></td>
                            <td><span class="pill pill-blue"><?= ucfirst(str_replace('_',' ',$staff['role'])) ?></span></td>
                            <td>
                                <?php if ($staff['status'] === 'pending' || !empty($staff['must_change_password'])): ?>
                                    <span class="pill pill-yellow"><i class="fas fa-clock me-1"></i>Pending Activation</span>
                                <?php else: ?>
                                    <span class="pill pill-green"><i class="fas fa-check-circle me-1"></i>Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-view" data-bs-toggle="modal" data-bs-target="#editStaffModal<?= $staff['id'] ?>"><i class="fas fa-edit"></i> Edit</button>
                                    <form method="POST" style="display:contents;">
                                        <input type="hidden" name="action" value="deactivate_staff">
                                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                        <button type="submit" class="btn-del" onclick="return confirm('Deactivate this staff?')"><i class="fas fa-user-slash"></i> Deactivate</button>
                                    </form>
                                    <button class="btn-del" style="background:#c62828;color:#fff;border:none;border-radius:7px;padding:4px 9px;font-size:.75rem;cursor:pointer;"
                                        onclick="confirmDelete(<?= $staff['id'] ?>, '<?= htmlspecialchars(addslashes($staff['first_name'].' '.$staff['last_name'])) ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($active_staff_rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">No active staff found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($inactive_count > 0): ?>
            <div class="section-hdr"><h5><i class="fas fa-circle me-2" style="color:#c62828;font-size:.6rem;"></i>Inactive Staff</h5></div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th class="col-username">Username</th><th>Full Name</th><th class="col-contact">Contact</th><th>Email</th><th class="col-role">Role</th><th class="col-status">Status</th><th class="col-actions">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($inactive_staff_rows as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['username']) ?></strong></td>
                            <td><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
                            <td><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td><span class="pill pill-blue"><?= ucfirst(str_replace('_',' ',$s['role'])) ?></span></td>
                            <td><span class="pill pill-red">Inactive</span></td>
                            <td>
                                <div class="actions-cell">
                                    <form method="POST" style="display:contents;">
                                        <input type="hidden" name="action" value="activate_staff">
                                        <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn-ok"><i class="fas fa-user-check"></i> Activate</button>
                                    </form>
                                    <button class="btn-del" style="background:#c62828;color:#fff;border:none;border-radius:7px;padding:4px 9px;font-size:.75rem;cursor:pointer;"
                                        onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['first_name'].' '.$s['last_name'])) ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_staff">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">First Name *</label><input type="text" class="form-control" name="first_name" id="add_first_name" required autocomplete="off" oninput="capitalizeFirst(this)"></div>
                        <div class="col-6"><label class="form-label">Last Name *</label><input type="text" class="form-control" name="last_name" id="add_last_name" required autocomplete="off" oninput="capitalizeFirst(this)"></div>
                        <div class="col-6"><label class="form-label">Contact Number</label><input type="text" class="form-control" name="phone"></div>
                        <div class="col-6"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" required></div>
                        <div class="col-12"><label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin Staff</option>
                                <option value="frontdesk">Front Desk Staff</option>
                                <option value="supervisor">Supervisor</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Username *</label><input type="text" class="form-control" name="username" required pattern="[a-zA-Z0-9_]{3,}" onchange="validateUsername(this)"><small class="text-muted">At least 3 characters. Cannot use: owner, admin</small></div>
                        <div class="col-12"><label class="form-label">Password *</label><input type="password" class="form-control" id="add_password" name="password" required></div>
                        <div class="col-12">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="add_confirm_password" name="confirm_password" required oninput="checkPasswordMatch()">
                            <div id="pw_match_msg" style="font-size:.78rem;margin-top:4px;display:none;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-add" id="addStaffSubmitBtn">Add Staff</button></div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Staff Modals -->
<?php foreach ($active_staff_rows as $staff): ?>
<div class="modal fade" id="editStaffModal<?= $staff['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_staff">
                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">First Name *</label><input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($staff['first_name']) ?>" required></div>
                        <div class="col-6"><label class="form-label">Last Name *</label><input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($staff['last_name']) ?>" required></div>
                        <div class="col-6"><label class="form-label">Contact</label><input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($staff['phone']) ?>"></div>
                        <div class="col-6"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($staff['email']) ?>" required></div>
                        <div class="col-12"><label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="admin" <?= $staff['role']=='admin'?'selected':'' ?>>Admin Staff</option>
                                <option value="frontdesk" <?= $staff['role']=='frontdesk'?'selected':'' ?>>Front Desk Staff</option>
                                <option value="supervisor" <?= $staff['role']=='supervisor'?'selected':'' ?>>Supervisor</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-add">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function validateUsername(input) {
    const reserved = ['owner','admin','root','system'];
    if (reserved.includes(input.value.toLowerCase())) {
        input.setCustomValidity('This username is reserved.');
        input.classList.add('is-invalid');
    } else { input.setCustomValidity(''); input.classList.remove('is-invalid'); }
}

// Auto-capitalize first letter of each word
function capitalizeFirst(input) {
    const pos = input.selectionStart;
    input.value = input.value.replace(/\b\w/g, c => c.toUpperCase());
    input.setSelectionRange(pos, pos);
}

// Check password match in real-time
function checkPasswordMatch() {
    const pw  = document.getElementById('add_password');
    const cpw = document.getElementById('add_confirm_password');
    const msg = document.getElementById('pw_match_msg');
    const btn = document.getElementById('addStaffSubmitBtn');
    if (!cpw.value) { msg.style.display='none'; btn.disabled=false; return; }
    if (pw.value === cpw.value) {
        msg.style.display='block';
        msg.style.color='#1B7D3A';
        msg.innerHTML='<i class="fas fa-check-circle me-1"></i>Passwords match';
        cpw.style.borderColor='#27A457';
        btn.disabled=false;
    } else {
        msg.style.display='block';
        msg.style.color='#c62828';
        msg.innerHTML='<i class="fas fa-times-circle me-1"></i>Passwords do not match';
        cpw.style.borderColor='#e53935';
        btn.disabled=true;
    }
}

// Validate confirm password on form submit
document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.querySelector('#addStaffModal form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const pw  = document.getElementById('add_password').value;
            const cpw = document.getElementById('add_confirm_password').value;
            if (pw !== cpw) {
                e.preventDefault();
                document.getElementById('pw_match_msg').style.display='block';
                document.getElementById('pw_match_msg').style.color='#c62828';
                document.getElementById('pw_match_msg').innerHTML='<i class="fas fa-times-circle me-1"></i>Passwords do not match';
            }
        });
    }

    // Animated KPI counters
    document.querySelectorAll('.kpi-num[data-count]').forEach((el,i) => {
        const t = parseInt(el.getAttribute('data-count'),10);
        setTimeout(() => { const s=performance.now(); const u=(n)=>{ const p=Math.min((n-s)/800,1); el.textContent=Math.round((1-Math.pow(1-p,3))*t); if(p<1)requestAnimationFrame(u); }; requestAnimationFrame(u); }, i*100);
    });
});

function confirmDelete(staffId, staffName) {
    document.getElementById('deleteStaffName').textContent = staffName;
    document.getElementById('deleteStaffId').value = staffId;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

initOwnerSidebar('ownerSidebarCollapsed');
</script>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center px-4 pb-2">
                <div style="width:60px;height:60px;background:#fdecea;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fas fa-trash" style="color:#c62828;font-size:1.4rem;"></i>
                </div>
                <h5 style="font-weight:800;color:#111;margin-bottom:8px;">Delete Staff?</h5>
                <p style="color:#888;font-size:.88rem;margin-bottom:0;">
                    You are about to permanently delete<br>
                    <strong id="deleteStaffName" style="color:#111;"></strong>.<br>
                    <span style="color:#c62828;font-size:.82rem;">This action cannot be undone.</span>
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2 pt-2">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete_staff">
                    <input type="hidden" name="staff_id" id="deleteStaffId" value="">
                    <button type="submit" class="btn btn-sm px-4" style="background:#c62828;color:#fff;border:none;">
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</body></html>