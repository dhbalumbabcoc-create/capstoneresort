<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);

// Handle facility status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_facility_status') {
    $facility_id = (int)$_POST['facility_id'];
    $status = $_POST['status'];
    
    // Validate status
    if (in_array($status, ['available', 'unavailable', 'maintenance'])) {
        $stmt = $conn->prepare("UPDATE facilities SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $facility_id);
        
        if ($stmt->execute()) {
            set_success_message("Facility status updated successfully!");
        } else {
            set_error_message("Failed to update facility status.");
        }
        $stmt->close();
    }
    
    header("Location: facilities_status.php");
    exit;
}

// Get facilities status with area info — exclude archived
$facilities_result = $conn->query("SELECT f.*, a.name as area_name 
                                   FROM facilities f 
                                   LEFT JOIN areas a ON f.area_id = a.id 
                                   WHERE f.status != 'archived'
                                   ORDER BY f.type, f.name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities Status - Resort Management</title>
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
                <div class="dash-topbar-title"><i class="fas fa-door-open me-2" style="color:#1B7D3A;"></i>Facilities Status</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_messages(); ?>

            <?php
            // Group facilities by type and compute KPI counts
            $facilities_by_type = [];
            $kpi_total = 0; $kpi_available = 0; $kpi_maintenance = 0; $kpi_unavailable = 0;
            if ($facilities_result && $facilities_result->num_rows > 0) {
                while ($facility = $facilities_result->fetch_assoc()) {
                    $type = ucfirst($facility['type']);
                    if (!isset($facilities_by_type[$type])) {
                        $facilities_by_type[$type] = [];
                    }
                    $facilities_by_type[$type][] = $facility;
                    $kpi_total++;
                    if ($facility['status'] === 'available') $kpi_available++;
                    elseif ($facility['status'] === 'maintenance') $kpi_maintenance++;
                    else $kpi_unavailable++;
                }
            }

            // Define icon mapping for different facility types
            $type_icons = [
                'Room' => 'fa-bed', 'Rooms' => 'fa-bed',
                'Cottage' => 'fa-home', 'Cottages' => 'fa-home',
                'Hall' => 'fa-building', 'Halls' => 'fa-building',
                'Function hall' => 'fa-building', 'Function_hall' => 'fa-building',
                'Pool' => 'fa-swimming-pool', 'Pavilion' => 'fa-umbrella-beach'
            ];

            // Facility images by type
            $facility_images_by_type = [
                'Room' => ['villa-candida.jpg', 'villa-carolina.jpg', 'villa-gracia.jpg'],
                'Rooms' => ['villa-candida.jpg', 'villa-carolina.jpg', 'villa-gracia.jpg'],
                'Cottage' => ['cottage1.jpg', 'cottage2.jpg', 'cottage3.jpg'],
                'Cottages' => ['cottage1.jpg', 'cottage2.jpg', 'cottage3.jpg'],
                'Hall' => ['fhall1.jpg', 'fhall2.jpg', 'fhall3.jpg'],
                'Halls' => ['fhall1.jpg', 'fhall2.jpg', 'fhall3.jpg'],
                'Function hall' => ['fhall1.jpg', 'fhall2.jpg', 'fhall3.jpg'],
                'Function_hall' => ['fhall1.jpg', 'fhall2.jpg', 'fhall3.jpg'],
                'Pool' => ['umbrella.jpg'], 'Pavilion' => ['umbrella.jpg']
            ];
            ?>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-building"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $kpi_total; ?>"><?php echo $kpi_total; ?></div>
                            <div class="kpi-lbl">Total Facilities</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $kpi_available; ?>"><?php echo $kpi_available; ?></div>
                            <div class="kpi-lbl">Available</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-icon yellow"><i class="fas fa-tools"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $kpi_maintenance; ?>"><?php echo $kpi_maintenance; ?></div>
                            <div class="kpi-lbl">Under Maintenance</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-icon red"><i class="fas fa-times-circle"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?php echo $kpi_unavailable; ?>"><?php echo $kpi_unavailable; ?></div>
                            <div class="kpi-lbl">Unavailable</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Facility Cards by Type -->
            <?php if (!empty($facilities_by_type)): ?>
                <?php foreach ($facilities_by_type as $type => $facilities): ?>
                    <div class="mb-4">
                        <div class="section-hdr mb-3">
                            <h5><i class="fas <?php echo $type_icons[$type] ?? 'fa-door-open'; ?> me-2" style="color:#1B7D3A;"></i><?php echo htmlspecialchars(str_replace('_', ' ', $type)); ?> <span class="pill pill-green ms-1"><?php echo count($facilities); ?></span></h5>
                        </div>
                        <?php $facility_image_index = 0; ?>
                        <div class="row g-3">
                            <?php foreach ($facilities as $facility): ?>
                                <?php
                                $images_for_type = $facility_images_by_type[$type] ?? ['hero-section.jpg'];
                                $image_file = !empty($facility['image_path']) ? $facility['image_path'] : $images_for_type[$facility_image_index % count($images_for_type)];
                                $facility_image_index++;

                                // Get maintenance status for this facility
                                $maint_q = $conn->query("SELECT COUNT(*) as pending FROM maintenance WHERE facility_id = " . (int)$facility['id'] . " AND status != 'completed'");
                                $maintenance_count = $maint_q->fetch_assoc();

                                $display_status = $maintenance_count['pending'] > 0
                                    ? 'unavailable'
                                    : ($facility['status'] === 'maintenance' ? 'available' : $facility['status']);

                                $statusPill = $display_status === 'available' ? 'pill-green' : ($display_status === 'maintenance' ? 'pill-yellow' : 'pill-red');
                                ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="fac-card">
                                        <img src="../images/<?php echo htmlspecialchars($image_file); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="fac-card-img">
                                        <div class="fac-card-body">
                                            <div class="fac-card-name">
                                                <i class="fas <?php echo $type_icons[$type] ?? 'fa-door-open'; ?> me-2" style="color:#1B7D3A;"></i>
                                                <?php echo htmlspecialchars($facility['name']); ?>
                                            </div>
                                            <div class="mb-2">
                                                <span class="pill <?php echo $statusPill; ?>"><?php echo ucfirst($display_status); ?></span>
                                            </div>
                                            <div class="fac-info-row">
                                                <span><i class="fas fa-map-marker-alt me-1" style="color:#1B7D3A;"></i><strong>Area:</strong></span>
                                                <span><?php echo htmlspecialchars($facility['area_name'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="fac-info-row">
                                                <span><i class="fas fa-users me-1" style="color:#1B7D3A;"></i><strong>Capacity:</strong></span>
                                                <span><?php echo $facility['capacity']; ?> pax</span>
                                            </div>
                                            <div class="fac-info-row">
                                                <span><i class="fas fa-tag me-1" style="color:#1B7D3A;"></i><strong>Price:</strong></span>
                                                <span>₱<?php echo number_format($facility['price'], 2); ?></span>
                                            </div>
                                            <div class="fac-info-row">
                                                <span><i class="fas fa-tools me-1" style="color:#1B7D3A;"></i><strong>Pending Maint.:</strong></span>
                                                <span class="pill <?php echo $maintenance_count['pending'] > 0 ? 'pill-yellow' : 'pill-green'; ?>"><?php echo $maintenance_count['pending']; ?></span>
                                            </div>
                                            <?php if ($facility['amenities']): ?>
                                                <div class="fac-info-row mt-1">
                                                    <span><i class="fas fa-star me-1" style="color:#1B7D3A;"></i><strong>Amenities:</strong></span>
                                                    <span><?php echo htmlspecialchars($facility['amenities']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3" style="opacity:0.3;"></i>
                    <p>No facilities found</p>
                </div>
            <?php endif; ?>
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
