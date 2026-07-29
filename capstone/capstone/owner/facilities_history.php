<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);
// Handle restore facility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_facility') {
    $facility_id = intval($_POST['facility_id']);
    $stmt = $conn->prepare("UPDATE facilities SET status = 'available' WHERE id = ?");
    $stmt->bind_param("i", $facility_id);

    if ($stmt->execute()) {
        set_success_message('Facility restored successfully');
    } else {
        set_error_message('Error restoring facility: ' . $conn->error);
    }
    $stmt->close();
}

// Handle permanently delete facility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'permanent_delete') {
    $facility_id = intval($_POST['facility_id']);
    $stmt = $conn->prepare("DELETE FROM facilities WHERE id = ?");
    $stmt->bind_param("i", $facility_id);

    if ($stmt->execute()) {
        set_success_message('Facility permanently deleted');
    } else {
        set_error_message('Error deleting facility: ' . $conn->error);
    }
    $stmt->close();
}

// Get all archived facilities with search filter
$search = '';
if (isset($_GET['search'])) {
    $search = escape_input($_GET['search'], $conn);
}

if (!empty($search)) {
    $query = "SELECT * FROM facilities WHERE status = 'archived' AND (name LIKE '%" . $conn->real_escape_string($search) . "%' OR type LIKE '%" . $conn->real_escape_string($search) . "%') ORDER BY type, name";
} else {
    $query = "SELECT * FROM facilities WHERE status = 'archived' ORDER BY type, name";
}

$facilities_result = $conn->query($query);

// Facility analytics
$total_facilities = $conn->query("SELECT COUNT(*) AS cnt FROM facilities")->fetch_assoc()['cnt'] ?? 0;
$active_facilities = $conn->query("SELECT COUNT(*) AS cnt FROM facilities WHERE status = 'available'")->fetch_assoc()['cnt'] ?? 0;
$archived_facilities = $conn->query("SELECT COUNT(*) AS cnt FROM facilities WHERE status = 'archived'")->fetch_assoc()['cnt'] ?? 0;
$most_booked_result = $conn->query("SELECT f.name, COUNT(b.id) AS cnt FROM facilities f LEFT JOIN bookings b ON f.id = b.facility_id GROUP BY f.id ORDER BY cnt DESC, f.name LIMIT 1");
$most_booked = $most_booked_result && $most_booked_result->num_rows > 0 ? $most_booked_result->fetch_assoc()['name'] : '-';
// Bookings per facility
$bookings_result = $conn->query("SELECT f.name, COUNT(b.id) AS cnt FROM facilities f LEFT JOIN bookings b ON f.id = b.facility_id GROUP BY f.id ORDER BY f.name");
$bookings_labels = [];
$bookings_counts = [];
while ($row = $bookings_result->fetch_assoc()) { $bookings_labels[] = $row['name']; $bookings_counts[] = $row['cnt']; }
// Revenue per facility
$revenue_result = $conn->query("SELECT f.name, SUM(b.total_price) AS revenue FROM facilities f LEFT JOIN bookings b ON f.id = b.facility_id AND b.status = 'confirmed' GROUP BY f.id ORDER BY f.name");
$revenue_labels = [];
$revenue_counts = [];
while ($row = $revenue_result->fetch_assoc()) { $revenue_labels[] = $row['name']; $revenue_counts[] = floatval($row['revenue']); }
// Status distribution
$status_result = $conn->query("SELECT status, COUNT(*) AS cnt FROM facilities GROUP BY status");
$status_labels = [];
$status_counts = [];
while ($row = $status_result->fetch_assoc()) { $status_labels[] = ucfirst($row['status']); $status_counts[] = $row['cnt']; }
// Maintenance per facility
$maintenance_result = $conn->query("SELECT f.name, COUNT(m.id) AS cnt FROM facilities f LEFT JOIN maintenance m ON f.id = m.facility_id GROUP BY f.id ORDER BY f.name");
$maintenance_labels = [];
$maintenance_counts = [];
while ($row = $maintenance_result->fetch_assoc()) { $maintenance_labels[] = $row['name']; $maintenance_counts[] = $row['cnt']; }
// Ratings per facility
// Facility rating analytics removed due to missing feedback schema
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Reports & Analytics - Resort Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
     <div class="main-container">
            <?php require_once '../includes/owner_sidebar.php'; ?>

            <div class="content">

                <?php display_messages(); ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Location Reports</h2>
                </div>

                <!-- CENTERED ROW -->
                <div class="row g-3 justify-content-center">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card" style="height: 200px; border-left: 4px solid #75a6e6;">
                            <div class="stat-icon me-3" style="font-size: 2.2rem;">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <h5>Total Facilities</h5>
                                <div class="display-5 fw-bold"><?php echo $total_facilities; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card" style="height: 200px; border-left: 4px solid #75a6e6;">
                            <div class="stat-icon me-3" style="font-size: 2.2rem;">
                                <i class="fas fa-check-circle" style="color:#43e97b;"></i>
                            </div>
                            <div>
                                <h5>Active Facilities</h5>
                                <div class="display-5 fw-bold"><?php echo $active_facilities; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card" style="height: 200px; border-left: 4px solid #75a6e6;">
                            <div class="stat-icon me-3" style="font-size: 2.2rem;">
                                <i class="fas fa-archive" style="color:#a3a3a3;"></i>
                            </div>
                            <div>
                                <h5>Archived Facilities</h5>
                                <div class="display-5 fw-bold"><?php echo $archived_facilities; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card" style="height: 200px; border-left: 4px solid #75a6e6;">
                            <div class="stat-icon me-3" style="font-size: 2.2rem;">
                                <i class="fas fa-star" style="color:#ffd700;"></i>
                            </div>
                            <div>
                                <h5>Most Booked Facility</h5>
                                <div class="display-6 fw-bold"><?php echo htmlspecialchars($most_booked); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Bookings per Facility</h5>
                            <canvas id="bookingsFacilityChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Revenue per Facility</h5>
                            <canvas id="revenueFacilityChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Facility Status Distribution</h5>
                            <canvas id="statusFacilityChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Maintenance per Facility</h5>
                            <canvas id="maintenanceFacilityChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Archived Facilities</h5>
                                    <form method="GET" class="d-flex gap-2">
                                        <input type="text" class="form-control" name="search" placeholder="Search by name or type..." value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">
                                        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
                                        <?php if (!empty($search)): ?>
                                            <a href="?" class="btn btn-secondary"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <table class="table table-hover table-bordered align-middle">
                                    <thead class="table-success text-center">
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Capacity</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($facility = $facilities_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $facility['name']; ?></td>
                                                    <td class="text-center"><span class="type-badge type-<?php echo $facility['type']; ?>"><?php echo ucfirst(str_replace('_', ' ', $facility['type'])); ?></span></td>
                                                    <td class="text-center"><?php echo $facility['max_occupancy'] ?: '-'; ?></td>
                                                    <td class="text-center">₱<?php echo number_format($facility['price'], 2); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge archived-badge">
                                                            <i class="fas fa-archive"></i> Archived
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <!-- Actions content here -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="restore_facility">
                                                            <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Restore this facility?')" title="Restore">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="permanent_delete">
                                                            <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Permanently delete this facility?')" title="Delete Permanently">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>

                                                <!-- View Modal -->
                                                <div class="modal fade" id="viewModal<?php echo $facility['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title"><?php echo $facility['name']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $facility['type'])); ?></p>
                                                                <p><strong>Description:</strong> <?php echo $facility['description'] ?: 'No description'; ?></p>
                                                                <p><strong>Capacity:</strong> <?php echo $facility['capacity']; ?></p>
                                                                <p><strong>Max Occupancy:</strong> <?php echo $facility['max_occupancy']; ?></p>
                                                                <p><strong>Price:</strong> ₱<?php echo number_format($facility['price'], 2); ?></p>
                                                                <p><strong>Amenities:</strong> <?php echo $facility['amenities'] ?: 'None'; ?></p>
                                                                <p><strong>Status:</strong> <span class="badge archived-badge"><i class="fas fa-archive"></i> Archived</span></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <!-- End of archived facilities table rendering -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            const sidebarCol = document.getElementById('sidebarCol');
            const navbarBrand = document.getElementById('navbarBrand');
            // Check saved state
            const sidebarState = localStorage.getItem('ownerSidebarCollapsed');
            if (sidebarState === 'true') {
                sidebarCol.classList.add('collapsed');
                sidebarToggle.classList.add('collapsed');
                navbarBrand.classList.add('collapsed');
            }
            sidebarToggle.addEventListener('click', function() {
                const isCollapsed = sidebarCol.classList.toggle('collapsed');
                this.classList.toggle('collapsed');
                navbarBrand.classList.toggle('collapsed');
                // Save state
                localStorage.setItem('ownerSidebarCollapsed', isCollapsed);
            });
        }

        // Active State Persistence for sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarMenu = document.getElementById('ownerSidebarMenu');
            if (!sidebarMenu) return;
            sidebarMenu.querySelectorAll('.sidebar-group').forEach(function(group) {
                const parent = group.querySelector('.sidebar-parent');
                const collapse = group.querySelector('.collapse');
                if (!parent || !collapse) return;
                const activeChild = collapse.querySelector('.nav-link.active');
                if (activeChild) {
                    collapse.classList.add('show');
                    parent.setAttribute('aria-expanded', 'true');
                }
            });
        });
    </script>
</body>
</html>
