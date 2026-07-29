<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('supervisor');

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
    
    header("Location: facilities.php");
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
    <title>Facilities Status - Supervisor</title>
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
                <div class="dash-topbar-title"><i class="fas fa-door-open me-2" style="color:#1B7D3A;"></i>Facilities Status</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-hard-hat me-1"></i>Supervisor</span>
                <span style="font-size:.85rem;color:#888;"><?php echo isset($user)?htmlspecialchars($user['first_name'].' '.$user['last_name']):''; ?></span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>
            <?php
            $facilities_by_type = [];
            $kpi_total=0;$kpi_available=0;$kpi_maintenance=0;$kpi_unavailable=0;
            if ($facilities_result && $facilities_result->num_rows > 0) {
                while ($facility = $facilities_result->fetch_assoc()) {
                    $type = ucfirst($facility['type']);
                    $facilities_by_type[$type][] = $facility;
                    $kpi_total++;
                    if ($facility['status']==='available') $kpi_available++;
                    elseif ($facility['status']==='maintenance') $kpi_maintenance++;
                    else $kpi_unavailable++;
                }
            }
            $type_icons = ['Room'=>'fa-bed','Rooms'=>'fa-bed','Cottage'=>'fa-home','Cottages'=>'fa-home','Hall'=>'fa-building','Halls'=>'fa-building','Function hall'=>'fa-building','Function_hall'=>'fa-building'];
            $facility_images_by_type = [
                'Room'=>['villa-candida.jpg','villa-carolina.jpg','villa-gracia.jpg'],
                'Rooms'=>['villa-candida.jpg','villa-carolina.jpg','villa-gracia.jpg'],
                'Cottage'=>['cottage1.jpg','cottage2.jpg','cottage3.jpg'],
                'Cottages'=>['cottage1.jpg','cottage2.jpg','cottage3.jpg'],
                'Hall'=>['fhall1.jpg','fhall2.jpg','fhall3.jpg'],
                'Halls'=>['fhall1.jpg','fhall2.jpg','fhall3.jpg'],
                'Function hall'=>['fhall1.jpg','fhall2.jpg','fhall3.jpg'],
                'Function_hall'=>['fhall1.jpg','fhall2.jpg','fhall3.jpg'],
            ];
            ?>
            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon blue"><i class="fas fa-building"></i></div><div><div class="kpi-num" data-count="<?= $kpi_total ?>"><?= $kpi_total ?></div><div class="kpi-lbl">Total Facilities</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon green"><i class="fas fa-check-circle"></i></div><div><div class="kpi-num" data-count="<?= $kpi_available ?>"><?= $kpi_available ?></div><div class="kpi-lbl">Available</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon yellow"><i class="fas fa-tools"></i></div><div><div class="kpi-num" data-count="<?= $kpi_maintenance ?>"><?= $kpi_maintenance ?></div><div class="kpi-lbl">Maintenance</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-icon red"><i class="fas fa-times-circle"></i></div><div><div class="kpi-num" data-count="<?= $kpi_unavailable ?>"><?= $kpi_unavailable ?></div><div class="kpi-lbl">Unavailable</div></div></div></div>
            </div>
            <!-- Facility Cards -->
            <?php if (!empty($facilities_by_type)): ?>
                <?php foreach ($facilities_by_type as $type => $facilities): ?>
                <div class="mb-4">
                    <div class="section-hdr mb-3">
                        <h5><i class="fas <?= $type_icons[$type]??'fa-door-open' ?> me-2" style="color:#1B7D3A;"></i><?= htmlspecialchars(str_replace('_',' ',$type)) ?> <span class="pill pill-green ms-1"><?= count($facilities) ?></span></h5>
                    </div>
                    <?php $idx=0; ?>
                    <div class="row g-3">
                        <?php foreach ($facilities as $facility):
                            $imgs = $facility_images_by_type[$type]??['hero-section.jpg'];
                            $img  = !empty($facility['image_path']) ? $facility['image_path'] : $imgs[$idx%count($imgs)]; $idx++;
                            $mq   = $conn->query("SELECT COUNT(*) as p FROM maintenance WHERE facility_id=".(int)$facility['id']." AND status!='completed'");
                            $mc   = $mq->fetch_assoc();
                            $st   = $facility['status'];
                            $pillClass = $st==='available'?'pill-green':($st==='maintenance'?'pill-yellow':'pill-red');
                        ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="fac-card">
                                <img src="../images/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($facility['name']) ?>" class="fac-card-img">
                                <div class="fac-card-body">
                                    <div class="fac-card-name"><i class="fas <?= $type_icons[$type]??'fa-door-open' ?> me-2" style="color:#1B7D3A;"></i><?= htmlspecialchars($facility['name']) ?></div>
                                    <div class="mb-2"><span class="pill <?= $pillClass ?>"><?= ucfirst($st) ?></span></div>
                                    <form method="POST" class="d-flex align-items-center gap-2 mb-3">
                                        <input type="hidden" name="action" value="update_facility_status">
                                        <input type="hidden" name="facility_id" value="<?= (int)$facility['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" style="border:1.5px solid #e0e0e0;border-radius:8px;font-size:.82rem;">
                                            <option value="available" <?= $st==='available'?'selected':'' ?>>Available</option>
                                            <option value="unavailable" <?= $st==='unavailable'?'selected':'' ?>>Unavailable</option>
                                            <option value="maintenance" <?= $st==='maintenance'?'selected':'' ?>>Maintenance</option>
                                        </select>
                                        <button type="submit" class="btn-update"><i class="fas fa-save"></i></button>
                                    </form>
                                    <div class="fac-info-row"><span><i class="fas fa-map-marker-alt me-1" style="color:#1B7D3A;"></i><strong>Area:</strong></span><span><?= htmlspecialchars($facility['area_name']??'N/A') ?></span></div>
                                    <div class="fac-info-row"><span><i class="fas fa-users me-1" style="color:#1B7D3A;"></i><strong>Capacity:</strong></span><span><?= $facility['capacity'] ?> pax</span></div>
                                    <div class="fac-info-row"><span><i class="fas fa-tag me-1" style="color:#1B7D3A;"></i><strong>Price:</strong></span><span>&#8369;<?= number_format($facility['price'],2) ?></span></div>
                                    <div class="fac-info-row"><span><i class="fas fa-tools me-1" style="color:#1B7D3A;"></i><strong>Pending Maint.:</strong></span><span class="pill <?= $mc['p']>0?'pill-yellow':'pill-green' ?>"><?= $mc['p'] ?></span></div>
                                    <?php if ($facility['amenities']): ?><div class="fac-info-row mt-1"><span><i class="fas fa-star me-1" style="color:#1B7D3A;"></i><strong>Amenities:</strong></span><span style="font-size:.78rem;"><?= htmlspecialchars($facility['amenities']) ?></span></div><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3" style="opacity:.3;"></i><p>No facilities found.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.kpi-num[data-count]').forEach((el,i)=>{
        const t=parseInt(el.getAttribute('data-count'),10);
        setTimeout(()=>{const s=performance.now();const u=(n)=>{const p=Math.min((n-s)/800,1);el.textContent=Math.round((1-Math.pow(1-p,3))*t);if(p<1)requestAnimationFrame(u);};requestAnimationFrame(u);},i*80);
    });
});
</script>
</body></html>