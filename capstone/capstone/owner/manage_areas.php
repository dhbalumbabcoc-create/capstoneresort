<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);

// Include shared owner sidebar
require_once '../includes/owner_sidebar.php';

// Handle add area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_area') {
    $name = escape_input($_POST['name'], $conn);
    $price_regular = floatval($_POST['price_regular']);
    $price_discounted = floatval($_POST['price_discounted']);
    $price_children = floatval($_POST['price_children']);
    $free_below_age = intval($_POST['free_below_age']);

    if (empty($name) || $price_regular <= 0) {
        set_error_message('Please provide a valid area name and regular price.');
    } else {
        // Check if area name already exists
        $check_stmt = $conn->prepare("SELECT id FROM areas WHERE name = ?");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            set_error_message('Area name already exists. Please use a different name.');
        } else {
        $stmt = $conn->prepare("INSERT INTO areas (name, price_regular, price_discounted, price_children, free_below_age) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdddi", $name, $price_regular, $price_discounted, $price_children, $free_below_age);
            if ($stmt->execute()) {
                set_success_message('Area added successfully');
            } else {
                set_error_message('Error adding area: ' . $stmt->error);
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle update area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_area') {
    $area_id = intval($_POST['area_id']);
    $name = escape_input($_POST['name'], $conn);
    $price_regular = floatval($_POST['price_regular']);
    $price_discounted = floatval($_POST['price_discounted']);
    $price_children = floatval($_POST['price_children']);
    $free_below_age = intval($_POST['free_below_age']);

    if (empty($name) || $price_regular <= 0) {
        set_error_message('Please provide a valid area name and regular price.');
    } else {
        // Check if area name already exists (excluding current area)
        $check_stmt = $conn->prepare("SELECT id FROM areas WHERE name = ? AND id != ?");
        $check_stmt->bind_param("si", $name, $area_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            set_error_message('Area name already exists. Please use a different name.');
        } else {
            $stmt = $conn->prepare("UPDATE areas SET name = ?, price_regular = ?, price_discounted = ?, price_children = ?, free_below_age = ? WHERE id = ?");
            $stmt->bind_param("sdddii", $name, $price_regular, $price_discounted, $price_children, $free_below_age, $area_id);
            if ($stmt->execute()) {
                set_success_message('Area updated successfully');
            } else {
                set_error_message('Error updating area: ' . $stmt->error);
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle delete area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_area') {
    $area_id = intval($_POST['area_id']);
    $stmt = $conn->prepare("UPDATE areas SET status = 'archived' WHERE id = ?");
    $stmt->bind_param("i", $area_id);
    if ($stmt->execute()) {
        set_success_message('Area archived successfully');
    } else {
        set_error_message('Error archiving area: ' . $stmt->error);
    }
    $stmt->close();
}

// Handle search
$search = '';
if (isset($_GET['search'])) {
    $search = escape_input($_GET['search'], $conn);
}

// Get areas with search filter
$areas_query = "SELECT * FROM areas WHERE status = 'active'";
if (!empty($search)) {
    $search_like = "%{$search}%";
    $areas_query .= " AND (name LIKE '$search_like')";
}
$areas_query .= " ORDER BY id DESC";
$areas_result = $conn->query($areas_query);
$archived_areas_result = $conn->query("SELECT * FROM areas WHERE status = 'archived' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Locations - Resort Management</title>
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
                <div class="dash-topbar-title"><i class="fas fa-map-marker-alt me-2" style="color:#1B7D3A;"></i>Manage Locations</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_messages(); ?>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $areas_result ? $areas_result->num_rows : 0; ?>"><?php echo $areas_result ? $areas_result->num_rows : 0; ?></div>
                            <div class="kpi-lbl">Active Locations</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-archive"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $archived_areas_result ? $archived_areas_result->num_rows : 0; ?>"><?php echo $archived_areas_result ? $archived_areas_result->num_rows : 0; ?></div>
                            <div class="kpi-lbl">Archived Locations</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search + Add -->
            <div class="table-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="section-hdr mb-0"><h5>All Locations</h5></div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <form method="GET" class="search-bar">
                            <input type="text" class="form-control" name="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn-add"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn-del"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </form>
                        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addAreaModal">
                            <i class="fas fa-plus"></i> Add Location
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Adult Price (₱)</th>
                                <th>PWD/Senior Price (₱)</th>
                                <th>Children Price (₱)</th>
                                <th>Free Below Age</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($areas_result && $areas_result->num_rows > 0): ?>
                            <?php $areas_result->data_seek(0); while ($area = $areas_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($area['name']); ?></td>
                                    <td>₱<?php echo number_format($area['price_regular'], 2); ?></td>
                                    <td>₱<?php echo number_format($area['price_discounted'], 2); ?></td>
                                    <td>₱<?php echo number_format($area['price_children'], 2); ?></td>
                                    <td><?php echo (int)$area['free_below_age']; ?></td>
                                    <td><span class="pill <?php echo $area['status'] === 'active' ? 'pill-green' : 'pill-red'; ?>"><?php echo ucfirst($area['status']); ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn-view" data-bs-toggle="modal" data-bs-target="#viewAreaModal" onclick='loadAreaData(<?php echo json_encode($area); ?>, "view")' title="View"><i class="fas fa-eye"></i></button>
                                            <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editAreaModal" onclick='loadAreaData(<?php echo json_encode($area); ?>, "edit")' title="Edit"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_area">
                                                <input type="hidden" name="area_id" value="<?php echo $area['id']; ?>">
                                                <button type="submit" class="btn-del" onclick="return confirm('Archive this location?')" title="Archive"><i class="fas fa-archive"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No locations found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Area Modal -->
<div class="modal fade" id="addAreaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_area">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Sinulom">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adult Price (₱)</label>
                        <input type="number" step="0.01" name="price_regular" class="form-control" value="160.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PWD/Senior Price (₱)</label>
                        <input type="number" step="0.01" name="price_discounted" class="form-control" value="110.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Children Price (₱)</label>
                        <input type="number" step="0.01" name="price_children" class="form-control" value="110.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Free Below Age</label>
                        <input type="number" name="free_below_age" class="form-control" value="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-add">Add Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Area Modal -->
<div class="modal fade" id="viewAreaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>View Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Location Name</label><p id="view_name" class="form-control-plaintext"></p></div>
                <div class="mb-3"><label class="form-label">Adult Price (₱)</label><p id="view_price_regular" class="form-control-plaintext"></p></div>
                <div class="mb-3"><label class="form-label">PWD/Senior Price (₱)</label><p id="view_price_discounted" class="form-control-plaintext"></p></div>
                <div class="mb-3"><label class="form-label">Children Price (₱)</label><p id="view_price_children" class="form-control-plaintext"></p></div>
                <div class="mb-3"><label class="form-label">Free Below Age</label><p id="view_free_below_age" class="form-control-plaintext"></p></div>
                <div class="mb-3"><label class="form-label">Status</label><p id="view_status" class="form-control-plaintext"></p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Area Modal -->
<div class="modal fade" id="editAreaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_area">
                <input type="hidden" name="area_id" id="edit_area_id">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Location Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Adult Price (₱)</label><input type="number" step="0.01" name="price_regular" id="edit_price_regular" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">PWD/Senior Price (₱)</label><input type="number" step="0.01" name="price_discounted" id="edit_price_discounted" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Children Price (₱)</label><input type="number" step="0.01" name="price_children" id="edit_price_children" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Free Below Age</label><input type="number" name="free_below_age" id="edit_free_below_age" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-add">Save Changes</button>
                </div>
            </form>
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
function loadAreaData(area, mode) {
    if (mode === 'view') {
        document.getElementById('view_name').textContent = area.name;
        document.getElementById('view_price_regular').textContent = '₱' + parseFloat(area.price_regular).toFixed(2);
        document.getElementById('view_price_discounted').textContent = '₱' + parseFloat(area.price_discounted).toFixed(2);
        document.getElementById('view_price_children').textContent = '₱' + parseFloat(area.price_children).toFixed(2);
        document.getElementById('view_free_below_age').textContent = area.free_below_age + ' years';
        document.getElementById('view_status').innerHTML = '<span class="pill ' + (area.status === 'active' ? 'pill-green' : 'pill-red') + '">' + area.status.charAt(0).toUpperCase() + area.status.slice(1) + '</span>';
    } else if (mode === 'edit') {
        document.getElementById('edit_area_id').value = area.id;
        document.getElementById('edit_name').value = area.name;
        document.getElementById('edit_price_regular').value = area.price_regular;
        document.getElementById('edit_price_discounted').value = area.price_discounted;
        document.getElementById('edit_price_children').value = area.price_children;
        document.getElementById('edit_free_below_age').value = area.free_below_age;
    }
}
initOwnerSidebar('ownerSidebarCollapsed');
</script>
</body></html>
