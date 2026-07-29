<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('frontdesk');

$user = get_user_info($_SESSION['user_id'], $conn);

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities Status - Resort Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/frontdesk_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/frontdesk_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-door-open me-2" style="color:#1B7D3A;"></i>Facilities Status</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-concierge-bell me-1"></i>Front Desk</span>
            </div>
        </div>

        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>

            <?php
            $facilities_by_type = [];
            $kpi_total = 0; $kpi_available = 0; $kpi_maintenance = 0; $kpi_unavailable = 0;
            if ($facilities_result && $facilities_result->num_rows > 0) {
                while ($facility = $facilities_result->fetch_assoc()) {
                    $type = ucfirst($facility['type']);
                    $facilities_by_type[$type][] = $facility;
                    $kpi_total++;
                    if ($facility['status']==='available')   $kpi_available++;
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
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-building"></i></div>
                        <div><div class="kpi-num" data-count="<?= $kpi_total ?>"><?= $kpi_total ?></div><div class="kpi-lbl">Total Facilities</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                        <div><div class="kpi-num" data-count="<?= $kpi_available ?>"><?= $kpi_available ?></div><div class="kpi-lbl">Available</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon yellow"><i class="fas fa-tools"></i></div>
                        <div><div class="kpi-num" data-count="<?= $kpi_maintenance ?>"><?= $kpi_maintenance ?></div><div class="kpi-lbl">Maintenance</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div class="kpi-icon red"><i class="fas fa-times-circle"></i></div>
                        <div><div class="kpi-num" data-count="<?= $kpi_unavailable ?>"><?= $kpi_unavailable ?></div><div class="kpi-lbl">Unavailable</div></div>
                    </div>
                </div>
            </div>

            <!-- Facility Cards by Type -->
            <?php if (!empty($facilities_by_type)): ?>
                <?php foreach ($facilities_by_type as $type => $facilities): ?>
                <div class="mb-4">
                    <div class="section-hdr mb-3">
                        <h5><i class="fas <?= $type_icons[$type] ?? 'fa-door-open' ?> me-2" style="color:#1B7D3A;"></i><?= htmlspecialchars(str_replace('_',' ',$type)) ?> <span class="pill pill-green ms-1"><?= count($facilities) ?></span></h5>
                    </div>
                    <?php $idx = 0; ?>
                    <div class="row g-3">
                        <?php foreach ($facilities as $facility):
                            $imgs = $facility_images_by_type[$type] ?? ['hero-section.jpg'];
                            $img  = !empty($facility['image_path']) ? $facility['image_path'] : $imgs[$idx % count($imgs)]; $idx++;
                            $mq   = $conn->query("SELECT COUNT(*) as p FROM maintenance WHERE facility_id=".(int)$facility['id']." AND status!='completed'");
                            $mc   = $mq->fetch_assoc();
                            $st   = $facility['status'];
                            $pillClass = $st==='available' ? 'pill-green' : ($st==='maintenance' ? 'pill-yellow' : 'pill-red');
                        ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="fac-card">
                                <img src="../images/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($facility['name']) ?>" class="fac-card-img">
                                <div class="fac-card-body">
                                    <div class="fac-card-name">
                                        <i class="fas <?= $type_icons[$type] ?? 'fa-door-open' ?> me-2" style="color:#1B7D3A;"></i>
                                        <?= htmlspecialchars($facility['name']) ?>
                                    </div>
                                    <div class="mb-2"><span class="pill <?= $pillClass ?>"><?= ucfirst($st) ?></span></div>

                                    <div class="fac-info-row"><span><i class="fas fa-map-marker-alt me-1" style="color:#1B7D3A;"></i><strong>Area:</strong></span><span><?= htmlspecialchars($facility['area_name'] ?? 'N/A') ?></span></div>
                                    <div class="fac-info-row"><span><i class="fas fa-users me-1" style="color:#1B7D3A;"></i><strong>Capacity:</strong></span><span><?= $facility['capacity'] ?> pax</span></div>
                                    <div class="fac-info-row"><span><i class="fas fa-tag me-1" style="color:#1B7D3A;"></i><strong>Price:</strong></span><span>₱<?= number_format($facility['price'],2) ?></span></div>
                                    <div class="fac-info-row"><span><i class="fas fa-tools me-1" style="color:#1B7D3A;"></i><strong>Pending Maint.:</strong></span><span class="pill <?= $mc['p']>0?'pill-yellow':'pill-green' ?>"><?= $mc['p'] ?></span></div>
                                    <?php if ($facility['amenities']): ?>
                                    <div class="fac-info-row mt-1"><span><i class="fas fa-star me-1" style="color:#1B7D3A;"></i><strong>Amenities:</strong></span><span style="font-size:.78rem;"><?= htmlspecialchars($facility['amenities']) ?></span></div>
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
                <i class="fas fa-inbox fa-3x mb-3" style="opacity:.3;"></i>
                <p>No facilities found.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.kpi-num[data-count]').forEach((el,i) => {
        const t = parseInt(el.getAttribute('data-count'),10);
        setTimeout(() => { const s=performance.now(); const u=(n)=>{ const p=Math.min((n-s)/800,1); el.textContent=Math.round((1-Math.pow(1-p,3))*t); if(p<1)requestAnimationFrame(u); }; requestAnimationFrame(u); }, i*80);
    });
});
initFrontdeskSidebar();
</script>
</body>
</html>