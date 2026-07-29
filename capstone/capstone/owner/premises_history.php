<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_role('owner');

// Ensure $user is defined for sidebar
if (isset($_SESSION['user_id'])) {
    $user = get_user_info($_SESSION['user_id'], $conn);
} else {
    $user = ['first_name' => 'Owner', 'last_name' => '', 'role' => 'owner'];
}


// Fetch Location History — only active areas
$location_result = $conn->query("SELECT * FROM areas WHERE status = 'active' ORDER BY created_at DESC");

// Fetch Facility History — JOIN areas to get the area name
$facility_result = $conn->query("SELECT f.*, a.name AS area_name FROM facilities f LEFT JOIN areas a ON f.area_id = a.id ORDER BY f.created_at DESC");

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


// Fetch Amenities History (no facility_id in amenities table)
$amenities_result = $conn->query("SELECT * FROM amenities ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premises History - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/owner_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/owner_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-landmark me-2" style="color:#1B7D3A;"></i>Premises History</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
                <span style="font-size:.85rem;color:#888;"><?php echo isset($user) ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : ''; ?></span>
            </div>
        </div>

        <div class="dash-body">
            <?php display_messages(); ?>

            <!-- Search bar -->
            <div class="d-flex justify-content-end mb-4">
                <form method="GET" class="search-bar">
                    <input type="text" class="form-control" name="search"
                           placeholder="Search by name or type..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="?" class="btn-clear"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Location History -->
            <div class="section-hdr">
                <h5><i class="fas fa-map-marker-alt me-2" style="color:#1B7D3A;"></i>Location History</h5>
                <p>All area / location records</p>
            </div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Adult Rate</th>
                                <th>PWD/Senior Rate</th>
                                <th>Children Rate</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $location_result->data_seek(0);
                            $has = false;
                            while ($row = $location_result->fetch_assoc()):
                                $has = true;
                                $lst = $row['status'] ?? 'active';
                                $lPill = $lst === 'active' ? 'pill-green' : 'pill-red';
                            ?>
                            <tr>
                                <td><strong style="color:#1B7D3A;">#<?php echo $row['id']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td>&#8369;<?php echo number_format($row['price_regular'] ?? 0, 2); ?></td>
                                <td>&#8369;<?php echo number_format($row['price_discounted'] ?? 0, 2); ?></td>
                                <td>&#8369;<?php echo number_format($row['price_children'] ?? 0, 2); ?></td>
                                <td><span class="pill <?php echo $lPill; ?>"><?php echo ucfirst($lst); ?></span></td>
                                <td style="font-size:.82rem;color:#888;"><?php echo isset($row['created_at']) ? date('M d, Y H:i', strtotime($row['created_at'])) : '—'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$has): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No location records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Facility History -->
            <div class="section-hdr">
                <h5><i class="fas fa-building me-2" style="color:#1B7D3A;"></i>Facility History</h5>
                <p>All facility records including archived</p>
            </div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $facility_result->data_seek(0);
                            $has = false;
                            while ($row = $facility_result->fetch_assoc()):
                                $has = true;
                                $st = $row['status'] ?? 'unknown';
                                $pillClass = $st === 'available' ? 'pill-green'
                                           : ($st === 'maintenance' ? 'pill-yellow'
                                           : ($st === 'archived'   ? 'pill-grey'
                                           : 'pill-red'));
                            ?>
                            <tr>
                                <td><strong style="color:#1B7D3A;">#<?php echo $row['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><span class="pill <?php echo $pillClass; ?>"><?php echo ucfirst($st); ?></span></td>
                                <td style="font-size:.82rem;color:#888;"><?php echo isset($row['created_at']) ? date('M d, Y H:i', strtotime($row['created_at'])) : '—'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$has): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No facility records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Amenities History -->
            <div class="section-hdr">
                <h5><i class="fas fa-concierge-bell me-2" style="color:#1B7D3A;"></i>Amenities History</h5>
                <p>All amenity records</p>
            </div>
            <div class="table-card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $amenities_result->data_seek(0);
                            $has = false;
                            while ($row = $amenities_result->fetch_assoc()):
                                $has = true;
                                $ast = $row['status'] ?? 'active';
                                $aPill = $ast === 'active' ? 'pill-green' : 'pill-red';
                            ?>
                            <tr>
                                <td><strong style="color:#1B7D3A;">#<?php echo $row['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo isset($row['price']) ? '₱'.number_format($row['price'],2) : '—'; ?></td>
                                <td><span class="pill <?php echo $aPill; ?>"><?php echo ucfirst($ast); ?></span></td>
                                <td style="font-size:.82rem;color:#888;"><?php echo isset($row['created_at']) ? date('M d, Y H:i', strtotime($row['created_at'])) : '—'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$has): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No amenity records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.dash-body -->
    </div><!-- /.content -->
</div><!-- /.main-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
initOwnerSidebar('ownerSidebarCollapsed');
</script>
</body>
</html>