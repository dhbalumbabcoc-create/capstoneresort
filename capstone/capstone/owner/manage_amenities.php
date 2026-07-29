<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);

// Any verify_csrf or CSRF-related code has been removed.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_amenity') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;

    if (empty($name)) {
        set_error_message('Amenity name is required');
    } elseif ($price < 0) {
        set_error_message('Price must be 0 or greater');
    } else {
        $stmt = $conn->prepare("INSERT INTO amenities (name, description, price) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $name, $description, $price);
        if ($stmt->execute()) {
            set_success_message('Amenity added successfully');
            header("Location: manage_amenities.php");
            exit();
        } else {
            set_error_message('Error adding amenity. Please try again.');
        }
        $stmt->close();
    }
}

// Handle delete amenity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_amenity') {
    $amenity_id = intval($_POST['amenity_id'] ?? 0);
    if ($amenity_id > 0) {
        $stmt = $conn->prepare("DELETE FROM amenities WHERE id = ?");
        $stmt->bind_param("i", $amenity_id);
        if ($stmt->execute()) {
            set_success_message('Amenity deleted successfully');
            header("Location: manage_amenities.php");
            exit();
        } else {
            set_error_message('Error deleting amenity. Please try again.');
        }
        $stmt->close();
    } else {
        set_error_message('Invalid amenity ID.');
    }
}

// Handle search
$search = '';
if (isset($_GET['search'])) {
    $search = escape_input($_GET['search'], $conn);
}

// Get amenities with search filter
$amenities_query = "SELECT * FROM amenities";
if (!empty($search)) {
    $search_like = "%{$search}%";
    $amenities_query .= " WHERE name LIKE '$search_like' OR description LIKE '$search_like'";
}
$amenities_query .= " ORDER BY name";
$amenities_result = $conn->query($amenities_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Amenities - Resort Management</title>
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
                <div class="dash-topbar-title"><i class="fas fa-concierge-bell me-2" style="color:#1B7D3A;"></i>Manage Amenities</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_messages(); ?>

            <!-- KPI Cards -->
            <?php
            $amenities_result->data_seek(0);
            $all_amenity_rows = [];
            $active_count = 0;
            while ($ar = $amenities_result->fetch_assoc()) {
                $all_amenity_rows[] = $ar;
                if (($ar['status'] ?? 'active') === 'active') $active_count++;
            }
            $total_amenities = count($all_amenity_rows);
            ?>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-icon teal"><i class="fas fa-concierge-bell"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $total_amenities; ?>"><?php echo $total_amenities; ?></div>
                            <div class="kpi-lbl">Total Amenities</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $active_count; ?>"><?php echo $active_count; ?></div>
                            <div class="kpi-lbl">Active Amenities</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="section-hdr mb-0"><h5>All Amenities</h5></div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <form method="GET" class="search-bar">
                            <input type="text" class="form-control" name="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn-add"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn-del"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </form>
                        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addAmenityModal">
                            <i class="fas fa-plus"></i> Add Amenity
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_amenity_rows)): ?>
                                <?php foreach ($all_amenity_rows as $amenity): ?>
                                    <tr>
                                        <td><?php echo $amenity['id']; ?></td>
                                        <td><?php echo htmlspecialchars($amenity['name']); ?></td>
                                        <td><?php echo htmlspecialchars($amenity['description']); ?></td>
                                        <td><?php echo isset($amenity['price']) ? '₱' . number_format($amenity['price'], 2) : '-'; ?></td>
                                        <td><span class="pill <?php echo ($amenity['status'] ?? 'active') === 'active' ? 'pill-green' : 'pill-red'; ?>"><?php echo ucfirst($amenity['status'] ?? 'active'); ?></span></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn-edit" data-bs-toggle="modal" data-bs-target="#editAmenityModal<?php echo $amenity['id']; ?>"><i class="fas fa-edit"></i></button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_amenity">
                                                    <input type="hidden" name="amenity_id" value="<?php echo $amenity['id']; ?>">
                                                    <button type="submit" class="btn-del" onclick="return confirm('Delete this amenity?')"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No amenities found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Amenity Modal -->
<div class="modal fade" id="addAmenityModal" tabindex="-1" aria-labelledby="addAmenityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="manage_amenities.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAmenityModalLabel"><i class="fas fa-plus me-2"></i>Add Amenity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_amenity">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="mb-3"><label class="form-label">Name *</label><input type="text" class="form-control" name="name" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Price (₱) *</label><input type="number" class="form-control" name="price" step="0.01" min="0" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Amenity</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Per-amenity Edit Modals -->
<?php foreach ($all_amenity_rows as $amenity): ?>
<div class="modal fade" id="editAmenityModal<?php echo $amenity['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Amenity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_amenity">
                    <input type="hidden" name="amenity_id" value="<?php echo $amenity['id']; ?>">
                    <div class="mb-3"><label class="form-label">Amenity Name</label><input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($amenity['name']); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($amenity['description']); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Price (₱)</label><input type="number" class="form-control" name="price" step="0.01" value="<?php echo isset($amenity['price']) ? htmlspecialchars($amenity['price']) : ''; ?>" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

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
