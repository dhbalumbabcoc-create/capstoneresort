<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);
// Handle add facility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_facility') {
    $name = escape_input($_POST['name'], $conn);
    $type = escape_input($_POST['type'], $conn);
    $description = escape_input($_POST['description'], $conn);
    $capacity = intval($_POST['capacity']);
    $max_occupancy = intval($_POST['max_occupancy']);
    $price = floatval($_POST['price']);
    // Handle amenities as array from multi-select
    $amenities = '';
    if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
        // Sanitize each amenity name
        $amenities_arr = array_map(function($a) use ($conn) { return escape_input($a, $conn); }, $_POST['amenities']);
        $amenities = implode(", ", $amenities_arr);
    }

    // Handle photo upload
    $image_path = '';
    if (!empty($_FILES['facility_photo']['name'])) {
        $file     = $_FILES['facility_photo'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','webp','gif'];
        $max_size = 25 * 1024 * 1024; // 25MB
        if (!in_array($ext, $allowed)) {
            set_error_message('Invalid image format. Use JPG, PNG, WEBP, or GIF.');
            goto add_facility_end;
        }
        if ($file['size'] > $max_size) {
            set_error_message('Image too large. Maximum size is 25MB.');
            goto add_facility_end;
        }
        $upload_dir = dirname(__DIR__) . '/images/facilities/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        $filename   = 'facility_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            set_error_message('Failed to upload image. Please try again.');
            goto add_facility_end;
        }
        $image_path = 'facilities/' . $filename;
    }

    if (empty($name) || empty($type) || empty($price)) {
        set_error_message('Please fill in all required fields');
    } else {
        // Add image_path column if it doesn't exist yet
        $conn->query("ALTER TABLE facilities ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL");
        $stmt = $conn->prepare("INSERT INTO facilities (name, type, description, capacity, max_occupancy, price, amenities, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiidss", $name, $type, $description, $capacity, $max_occupancy, $price, $amenities, $image_path);

        if ($stmt->execute()) {
            set_success_message('Facility added successfully');
            header("Location: manage_facilities.php");
            exit();
        } else {
            set_error_message('Error adding facility: ' . $conn->error);
        }
        $stmt->close();
    }
    add_facility_end:
}

// Handle update facility (edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_facility') {
    $facility_id = intval($_POST['facility_id']);
    $name = escape_input($_POST['name'], $conn);
    $type = escape_input($_POST['type'], $conn);
    $description = escape_input($_POST['description'], $conn);
    $capacity = intval($_POST['capacity']);
    $max_occupancy = intval($_POST['max_occupancy']);
    $price = floatval($_POST['price']);

    // Handle amenities as array from checkbox chips
    $amenities = '';
    if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
        $amenities_arr = array_map(function($a) use ($conn) { return escape_input($a, $conn); }, $_POST['amenities']);
        $amenities = implode(", ", $amenities_arr);
    } else if (isset($_POST['amenities'])) {
        $amenities = escape_input($_POST['amenities'], $conn);
    }

    // Handle photo upload for Edit
    $image_path = '';
    if (!empty($_FILES['facility_photo']['name'])) {
        $file     = $_FILES['facility_photo'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','webp','gif'];
        $max_size = 25 * 1024 * 1024; // 25MB
        if (!in_array($ext, $allowed)) {
            set_error_message('Invalid image format. Use JPG, PNG, WEBP, or GIF.');
            goto update_facility_end;
        }
        if ($file['size'] > $max_size) {
            set_error_message('Image too large. Maximum size is 25MB.');
            goto update_facility_end;
        }
        $upload_dir = dirname(__DIR__) . '/images/facilities/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        $filename   = 'facility_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            set_error_message('Failed to upload image. Please try again.');
            goto update_facility_end;
        }
        $image_path = 'facilities/' . $filename;
    }

    if (empty($name) || empty($type) || empty($price)) {
        set_error_message('Please fill in all required fields');
    } else {
        if ($image_path !== '') {
            $stmt = $conn->prepare("UPDATE facilities SET name = ?, type = ?, description = ?, capacity = ?, max_occupancy = ?, price = ?, amenities = ?, image_path = ? WHERE id = ?");
            $stmt->bind_param("sssiidssi", $name, $type, $description, $capacity, $max_occupancy, $price, $amenities, $image_path, $facility_id);
        } else {
            $stmt = $conn->prepare("UPDATE facilities SET name = ?, type = ?, description = ?, capacity = ?, max_occupancy = ?, price = ?, amenities = ? WHERE id = ?");
            $stmt->bind_param("sssiidsi", $name, $type, $description, $capacity, $max_occupancy, $price, $amenities, $facility_id);
        }

        if ($stmt->execute()) {
            set_success_message('Facility updated successfully');
            header("Location: manage_facilities.php");
            exit();
        } else {
            set_error_message('Error updating facility: ' . $conn->error);
        }
        $stmt->close();
    }
    update_facility_end:
}

// Handle archive facility (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_facility') {
    $facility_id = intval($_POST['facility_id']);
    $stmt = $conn->prepare("UPDATE facilities SET status = 'archived' WHERE id = ?");
    $stmt->bind_param("i", $facility_id);

    if ($stmt->execute()) {
        set_success_message('Facility archived successfully');
        $redirect_status = isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '';
        header("Location: manage_facilities.php" . $redirect_status);
        exit();
    } else {
        set_error_message('Error archiving facility: ' . $conn->error);
    }
    $stmt->close();
}

// Handle restore facility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_facility') {
    $facility_id = intval($_POST['facility_id']);
    $stmt = $conn->prepare("UPDATE facilities SET status = 'available' WHERE id = ?");
    $stmt->bind_param("i", $facility_id);

    if ($stmt->execute()) {
        set_success_message('Facility restored successfully');
        $redirect_status = isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '?status=archived';
        header("Location: manage_facilities.php" . $redirect_status);
        exit();
    } else {
        set_error_message('Error restoring facility: ' . $conn->error);
    }
    $stmt->close();
}

// Handle update facility status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $facility_id = intval($_POST['facility_id']);
    $status = escape_input($_POST['status'], $conn);
    
    $stmt = $conn->prepare("UPDATE facilities SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $facility_id);

    if ($stmt->execute()) {
        set_success_message('Facility status updated successfully');
    } else {
        set_error_message('Error updating facility: ' . $conn->error);
    }
    $stmt->close();
}

// Get facilities with search & status filters
$search = '';
if (isset($_GET['search'])) {
    $search = escape_input($_GET['search'], $conn);
}

$status_filter = isset($_GET['status']) ? escape_input($_GET['status'], $conn) : 'active';
if (!in_array($status_filter, ['all', 'available', 'archived', 'active'])) {
    $status_filter = 'active';
}

$escaped_search = $conn->real_escape_string($search);

if ($status_filter === 'archived') {
    $query = "SELECT * FROM facilities WHERE status = 'archived'" . (!empty($search) ? " AND (name LIKE '%$escaped_search%' OR type LIKE '%$escaped_search%')" : "") . " ORDER BY type, name";
} elseif ($status_filter === 'available') {
    $query = "SELECT * FROM facilities WHERE status = 'available'" . (!empty($search) ? " AND (name LIKE '%$escaped_search%' OR type LIKE '%$escaped_search%')" : "") . " ORDER BY type, name";
} elseif ($status_filter === 'all') {
    $query = "SELECT * FROM facilities" . (!empty($search) ? " WHERE (name LIKE '%$escaped_search%' OR type LIKE '%$escaped_search%')" : "") . " ORDER BY type, name";
} else {
    $query = "SELECT * FROM facilities WHERE status != 'archived'" . (!empty($search) ? " AND (name LIKE '%$escaped_search%' OR type LIKE '%$escaped_search%')" : "") . " ORDER BY type, name";
}

$facilities_result = $conn->query($query);
$areas_result = $conn->query("SELECT * FROM areas WHERE status = 'active' ORDER BY id DESC");
$archived_areas_result = $conn->query("SELECT * FROM areas WHERE status = 'archived' ORDER BY id DESC");
// Fetch all amenities for dropdown
$amenities_result = $conn->query("SELECT * FROM amenities ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Facilities - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/owner_page_styles.php'; ?>
    <style>
        /* Scoped styles for premium Add/Edit Modal design */
        .modal-dialog-centered.modal-lg {
            max-width: 900px;
        }
        .form-section-title {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1B7D3A;
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 6px;
            margin-bottom: 16px;
        }
        .input-group-text {
            background-color: #f8fafc;
            border-color: #e0e0e0;
            color: #64748b;
            border-radius: 10px 0 0 10px !important;
            font-size: 0.9rem;
        }
        .input-group .form-control, .input-group .form-select {
            border-radius: 0 10px 10px 0 !important;
        }
        .input-group:focus-within .input-group-text {
            border-color: #1B7D3A;
            color: #1B7D3A;
        }
        /* Upload Zone */
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 120px;
        }
        .upload-zone:hover {
            border-color: #1B7D3A;
            background: #f0fdf4;
        }
        .upload-zone i {
            font-size: 1.8rem;
            color: #94a3b8;
            transition: color 0.2s ease;
        }
        .upload-zone:hover i {
            color: #1B7D3A;
        }
        .upload-zone p {
            margin: 0;
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }
        .upload-zone small {
            font-size: 0.72rem;
            color: #94a3b8;
        }
        .upload-preview-container {
            position: relative;
            width: 100%;
            margin-top: 10px;
            border-radius: 10px;
            overflow: hidden;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .upload-preview-img {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
        }
        .upload-preview-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ef4444;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
        }
        .upload-preview-remove:hover {
            background: #ef4444;
            color: #fff;
            transform: scale(1.05);
        }
        /* Amenities grid */
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 8px;
            max-height: 160px;
            overflow-y: auto;
            padding: 8px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            background: #f8fafc;
        }
        .amenity-chip-label {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 6px;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            user-select: none;
        }
        .amenity-chip-label:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        .amenity-chip-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .amenity-chip-input:checked + .amenity-chip-label {
            background: #e8f5e9;
            border-color: #1B7D3A;
            color: #1B7D3A;
            box-shadow: 0 2px 8px rgba(27,125,58,0.12);
        }
        .amenity-chip-input:checked + .amenity-chip-label::before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin-right: 5px;
            font-size: 0.7rem;
        }
        .kpi-card {
            transition: all 0.25s ease;
            border: 2px solid transparent;
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        }
        .active-kpi {
            border-color: #1B7D3A !important;
            background: #f0fdf4 !important;
            box-shadow: 0 4px 14px rgba(27,125,58,0.15) !important;
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
                <div class="dash-topbar-title"><i class="fas fa-building me-2" style="color:#1B7D3A;"></i>Manage Facilities</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_messages(); ?>

            <!-- KPI Cards -->
            <!-- KPI Cards -->
            <?php
            $total_fac_result = $conn->query("SELECT COUNT(*) AS cnt FROM facilities WHERE status != 'archived'");
            $total_fac = $total_fac_result ? intval($total_fac_result->fetch_assoc()['cnt']) : 0;

            $avail_result = $conn->query("SELECT COUNT(*) AS cnt FROM facilities WHERE status = 'available'");
            $avail_fac = $avail_result ? intval($avail_result->fetch_assoc()['cnt']) : 0;

            $arch_result = $conn->query("SELECT COUNT(*) AS cnt FROM facilities WHERE status = 'archived'");
            $arch_fac = $arch_result ? intval($arch_result->fetch_assoc()['cnt']) : 0;

            $all_fac_rows = [];
            if ($facilities_result) {
                while ($fr = $facilities_result->fetch_assoc()) {
                    $all_fac_rows[] = $fr;
                }
            }
            ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <a href="?status=active<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="text-decoration-none">
                        <div class="kpi-card <?php echo ($status_filter === 'active' || $status_filter === 'all') ? 'active-kpi' : ''; ?>">
                            <div class="kpi-icon blue"><i class="fas fa-building"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?php echo $total_fac; ?>"><?php echo $total_fac; ?></div>
                                <div class="kpi-lbl">Total Facilities</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="?status=available<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="text-decoration-none">
                        <div class="kpi-card <?php echo $status_filter === 'available' ? 'active-kpi' : ''; ?>">
                            <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?php echo $avail_fac; ?>"><?php echo $avail_fac; ?></div>
                                <div class="kpi-lbl">Available</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="?status=archived<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="text-decoration-none">
                        <div class="kpi-card <?php echo $status_filter === 'archived' ? 'active-kpi' : ''; ?>">
                            <div class="kpi-icon orange"><i class="fas fa-archive"></i></div>
                            <div>
                                <div class="kpi-num" data-count="<?php echo $arch_fac; ?>"><?php echo $arch_fac; ?></div>
                                <div class="kpi-lbl">Archived</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="section-hdr mb-0">
                        <h5>
                            <?php 
                            if ($status_filter === 'archived') {
                                echo '<i class="fas fa-archive me-2 text-warning"></i>Archived Facilities';
                            } elseif ($status_filter === 'available') {
                                echo '<i class="fas fa-check-circle me-2 text-success"></i>Available Facilities';
                            } else {
                                echo '<i class="fas fa-building me-2 text-primary"></i>All Facilities';
                            }
                            ?>
                        </h5>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <form method="GET" class="search-bar">
                            <?php if (!empty($status_filter)): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <?php endif; ?>
                            <input type="text" class="form-control" name="search" placeholder="Search by name or type..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn-add"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>" class="btn-del"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </form>
                        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addFacilityModal">
                            <i class="fas fa-plus"></i> Add Facility
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
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
                            <?php foreach ($all_fac_rows as $facility): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($facility['name']); ?></td>
                                    <td><span class="pill pill-blue"><?php echo ucfirst(str_replace('_', ' ', $facility['type'])); ?></span></td>
                                    <td><?php echo $facility['capacity'] !== null && $facility['capacity'] !== '' ? htmlspecialchars($facility['capacity']) : '-'; ?></td>
                                    <td>₱<?php echo number_format($facility['price'], 2); ?></td>
                                    <td>
                                        <span class="pill <?php echo $facility['status'] === 'available' ? 'pill-green' : ($facility['status'] === 'archived' ? 'pill-yellow' : 'pill-yellow'); ?>">
                                            <?php echo ucfirst($facility['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn-view" data-bs-toggle="modal" data-bs-target="#viewFacilityModal<?php echo $facility['id']; ?>" title="View"><i class="fas fa-eye"></i></button>
                                            <?php if ($facility['status'] === 'archived'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="event.stopPropagation();">
                                                    <input type="hidden" name="action" value="restore_facility">
                                                    <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" style="border-radius:8px; padding: 4px 10px; font-weight:600; font-size:0.8rem;" onclick="return confirm('Restore this facility to Available?')" title="Restore">
                                                        <i class="fas fa-rotate-left me-1"></i> Restore
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editFacilityModal<?php echo $facility['id']; ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                                <form method="POST" style="display:inline;" onsubmit="event.stopPropagation();">
                                                    <input type="hidden" name="action" value="delete_facility">
                                                    <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                                                    <button type="submit" class="btn-del" onclick="return confirm('Archive this facility?')" title="Archive"><i class="fas fa-archive"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($all_fac_rows)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No facilities found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Facility Modal -->
<div class="modal fade" id="addFacilityModal" tabindex="-1" aria-labelledby="addFacilityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFacilityModalLabel"><i class="fas fa-plus me-2"></i>Add Facility</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_facility">
                    
                    <div class="row">
                        <!-- Left Column: Basic Details -->
                        <div class="col-md-6 border-end pe-md-4">
                            <div class="form-section-title">
                                <i class="fas fa-info-circle me-1"></i> Basic Details
                            </div>
                            
                            <!-- Name Input -->
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-font"></i></span>
                                    <input type="text" class="form-control" name="name" placeholder="e.g. Deluxe Room A" required>
                                </div>
                            </div>
                            
                            <!-- Type Input -->
                            <div class="mb-3">
                                <label class="form-label">Type *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-list"></i></span>
                                    <select class="form-select" name="type" required>
                                        <option value="room">Room</option>
                                        <option value="cottage">Cottage</option>
                                        <option value="function_hall">Function Hall</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Price Input -->
                            <div class="mb-3">
                                <label class="form-label">Price (&#8369;) *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                    <input type="number" class="form-control" name="price" step="0.01" placeholder="0.00" required>
                                </div>
                            </div>
                            
                            <!-- Capacity & Max Occupancy -->
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Capacity</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-users"></i></span>
                                        <input type="number" class="form-control" name="capacity" min="1" placeholder="e.g. 2">
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Max Occupancy</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user-plus"></i></span>
                                        <input type="number" class="form-control" name="max_occupancy" min="1" placeholder="e.g. 4">
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Briefly describe the facility..." style="border-radius:10px;"></textarea>
                            </div>
                        </div>
                        
                        <!-- Right Column: Amenities & Media -->
                        <div class="col-md-6 ps-md-4">
                            <div class="form-section-title">
                                <i class="fas fa-concierge-bell me-1"></i> Features & Media
                            </div>
                            
                            <!-- Amenities Chip Selection -->
                            <div class="mb-3">
                                <label class="form-label d-block">Amenities</label>
                                <div class="amenities-grid">
                                    <?php if ($amenities_result && $amenities_result->num_rows > 0): ?>
                                        <?php $amenities_result->data_seek(0); while ($amenity = $amenities_result->fetch_assoc()): ?>
                                            <div>
                                                <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($amenity['name']); ?>" id="amenity_add_<?php echo htmlspecialchars($amenity['id']); ?>" class="amenity-chip-input">
                                                <label for="amenity_add_<?php echo htmlspecialchars($amenity['id']); ?>" class="amenity-chip-label">
                                                    <?php echo htmlspecialchars($amenity['name']); ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="text-muted p-2 text-center fs-7">No amenities available</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Photo Upload Area -->
                            <div class="mb-3">
                                <label class="form-label">Facility Photo</label>
                                <label for="facilityPhotoInput" class="upload-zone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload photo</p>
                                    <small>Accepted: JPG, PNG, WEBP, GIF. Max 25MB.</small>
                                    <input type="file" class="d-none" name="facility_photo" accept="image/jpeg,image/png,image/webp,image/gif" id="facilityPhotoInput">
                                </label>
                                
                                <!-- Dynamic Preview -->
                                <div id="photoPreview" class="upload-preview-container" style="display:none;">
                                    <button type="button" class="upload-preview-remove" id="btnRemovePhoto"><i class="fas fa-times"></i></button>
                                    <img id="photoPreviewImg" src="" alt="Preview" class="upload-preview-img">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" style="border-radius:10px; font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Facility</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Per-facility View & Edit Modals -->
<?php foreach ($all_fac_rows as $facility): ?>
<!-- View Modal -->
<div class="modal fade" id="viewFacilityModal<?php echo $facility['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Facility Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($facility['image_path'])): ?>
                <div class="mb-3"><img src="../images/<?= htmlspecialchars($facility['image_path']) ?>" alt="<?= htmlspecialchars($facility['name']) ?>" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;"></div>
                <?php endif; ?>
                <div class="mb-3"><strong>Name:</strong><p class="ms-2 mb-0"><?php echo htmlspecialchars($facility['name']); ?></p></div>
                <div class="mb-3"><strong>Type:</strong><p class="ms-2 mb-0"><span class="pill pill-blue"><?php echo ucfirst(str_replace('_', ' ', $facility['type'])); ?></span></p></div>
                <div class="mb-3"><strong>Description:</strong><p class="ms-2 mb-0"><?php echo htmlspecialchars($facility['description'] ?: 'No description'); ?></p></div>
                <div class="mb-3"><strong>Capacity:</strong> <?php echo $facility['capacity'] ?: 'N/A'; ?> &nbsp;|&nbsp; <strong>Max Occupancy:</strong> <?php echo $facility['max_occupancy'] ?: 'N/A'; ?></div>
                <div class="mb-3"><strong>Price:</strong><p class="ms-2 text-success fs-5 mb-0">&#8369;<?php echo number_format($facility['price'], 2); ?></p></div>
                <div class="mb-3"><strong>Amenities:</strong><p class="ms-2 mb-0"><?php echo htmlspecialchars($facility['amenities']); ?></p></div>
                <div class="mb-3"><strong>Status:</strong><span class="pill <?php echo $facility['status'] === 'available' ? 'pill-green' : 'pill-yellow'; ?> ms-2"><?php echo ucfirst($facility['status']); ?></span></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<!-- Edit Modal -->
<div class="modal fade" id="editFacilityModal<?php echo $facility['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Facility</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_facility">
                <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                <div class="modal-body">
                    <div class="row">
                        <!-- Left Column: Basic Details -->
                        <div class="col-md-6 border-end pe-md-4">
                            <div class="form-section-title">
                                <i class="fas fa-info-circle me-1"></i> Basic Details
                            </div>
                            
                            <!-- Name Input -->
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-font"></i></span>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($facility['name']); ?>" required>
                                </div>
                            </div>
                            
                            <!-- Type Input -->
                            <div class="mb-3">
                                <label class="form-label">Type *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-list"></i></span>
                                    <select class="form-select" name="type" required>
                                        <option value="room" <?php if($facility['type']=='room') echo 'selected'; ?>>Room</option>
                                        <option value="cottage" <?php if($facility['type']=='cottage') echo 'selected'; ?>>Cottage</option>
                                        <option value="function_hall" <?php if($facility['type']=='function_hall') echo 'selected'; ?>>Function Hall</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Price Input -->
                            <div class="mb-3">
                                <label class="form-label">Price (&#8369;) *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                    <input type="number" class="form-control" name="price" step="0.01" value="<?php echo htmlspecialchars($facility['price']); ?>" required>
                                </div>
                            </div>
                            
                            <!-- Capacity & Max Occupancy -->
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Capacity</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-users"></i></span>
                                        <input type="number" class="form-control" name="capacity" min="1" value="<?php echo htmlspecialchars($facility['capacity']); ?>">
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Max Occupancy</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user-plus"></i></span>
                                        <input type="number" class="form-control" name="max_occupancy" min="1" value="<?php echo htmlspecialchars($facility['max_occupancy']); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" style="border-radius:10px;"><?php echo htmlspecialchars($facility['description']); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Right Column: Amenities & Media -->
                        <div class="col-md-6 ps-md-4">
                            <div class="form-section-title">
                                <i class="fas fa-concierge-bell me-1"></i> Features & Media
                            </div>
                            
                            <!-- Amenities Chip Selection -->
                            <div class="mb-3">
                                <label class="form-label d-block">Amenities</label>
                                <div class="amenities-grid">
                                    <?php 
                                    $selected_amenities = [];
                                    if (!empty($facility['amenities'])) {
                                        $selected_amenities = array_map('trim', explode(',', $facility['amenities']));
                                    }
                                    ?>
                                    <?php if ($amenities_result && $amenities_result->num_rows > 0): ?>
                                        <?php $amenities_result->data_seek(0); while ($amenity = $amenities_result->fetch_assoc()): ?>
                                            <?php $checked = in_array($amenity['name'], $selected_amenities) ? 'checked' : ''; ?>
                                            <div>
                                                <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($amenity['name']); ?>" id="amenity_edit_<?php echo $facility['id']; ?>_<?php echo htmlspecialchars($amenity['id']); ?>" class="amenity-chip-input" <?php echo $checked; ?>>
                                                <label for="amenity_edit_<?php echo $facility['id']; ?>_<?php echo htmlspecialchars($amenity['id']); ?>" class="amenity-chip-label">
                                                    <?php echo htmlspecialchars($amenity['name']); ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="text-muted p-2 text-center fs-7">No amenities available</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Photo Upload Area -->
                            <div class="mb-3">
                                <label class="form-label">Facility Photo</label>
                                <label for="facilityPhotoInput_edit_<?php echo $facility['id']; ?>" class="upload-zone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload new photo</p>
                                    <small>Accepted: JPG, PNG, WEBP, GIF. Max 25MB.</small>
                                    <input type="file" class="d-none" name="facility_photo" accept="image/jpeg,image/png,image/webp,image/gif" id="facilityPhotoInput_edit_<?php echo $facility['id']; ?>">
                                </label>
                                
                                <!-- Dynamic Preview -->
                                <?php 
                                $has_image = !empty($facility['image_path']);
                                $img_src = $has_image ? '../images/' . htmlspecialchars($facility['image_path']) : '';
                                ?>
                                <div id="photoPreview_edit_<?php echo $facility['id']; ?>" class="upload-preview-container" style="<?php echo $has_image ? 'display:block;' : 'display:none;'; ?>">
                                    <button type="button" class="upload-preview-remove" id="btnRemovePhoto_edit_<?php echo $facility['id']; ?>"><i class="fas fa-times"></i></button>
                                    <img id="photoPreviewImg_edit_<?php echo $facility['id']; ?>" src="<?php echo $img_src; ?>" alt="Preview" class="upload-preview-img">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" style="border-radius:10px; font-weight:600;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-add">Update Facility</button>
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
<script>
// Generic photo preview and remove functionality
function setupPhotoPreview(inputId, previewId, imgId, removeId) {
    const fileInput = document.getElementById(inputId);
    const previewContainer = document.getElementById(previewId);
    const previewImg = document.getElementById(imgId);
    const removeBtn = document.getElementById(removeId);

    if (!fileInput || !previewContainer || !previewImg) return;

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => { 
                previewImg.src = e.target.result; 
                previewContainer.style.display = 'block'; 
            };
            reader.readAsDataURL(file);
        } else {
            if (!previewImg.getAttribute('data-original-src')) {
                previewContainer.style.display = 'none';
            }
        }
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.value = '';
            
            const originalSrc = previewImg.getAttribute('data-original-src');
            if (originalSrc) {
                previewImg.src = originalSrc;
            } else {
                previewImg.src = '';
                previewContainer.style.display = 'none';
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Add modal preview
    setupPhotoPreview('facilityPhotoInput', 'photoPreview', 'photoPreviewImg', 'btnRemovePhoto');
    
    // Edit modals preview
    <?php foreach ($all_fac_rows as $facility): ?>
    setupPhotoPreview(
        'facilityPhotoInput_edit_<?php echo $facility['id']; ?>', 
        'photoPreview_edit_<?php echo $facility['id']; ?>', 
        'photoPreviewImg_edit_<?php echo $facility['id']; ?>', 
        'btnRemovePhoto_edit_<?php echo $facility['id']; ?>'
    );
    const img_<?php echo $facility['id']; ?> = document.getElementById('photoPreviewImg_edit_<?php echo $facility['id']; ?>');
    if (img_<?php echo $facility['id']; ?> && img_<?php echo $facility['id']; ?>.getAttribute('src')) {
        img_<?php echo $facility['id']; ?>.setAttribute('data-original-src', img_<?php echo $facility['id']; ?>.getAttribute('src'));
    }
    <?php endforeach; ?>
});
</script>
</body></html>
