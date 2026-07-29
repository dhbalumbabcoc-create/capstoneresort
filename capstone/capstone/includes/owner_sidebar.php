<?php /* Owner Sidebar */ ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/owner-sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<?php require_once __DIR__ . '/_sidebar_shared.css.php'; ?>

<div class="sidebar-col" id="sidebarCol">
<div class="sidebar">

  <!-- Brand -->
  <div class="sb-brand">
    <img src="<?php echo BASE_URL; ?>images/logo.jpg" alt="Logo" class="sb-brand-logo">
    <div class="sb-brand-text">
      <strong>Sinulom &amp; Bolao</strong>
      <span>Resort</span>
    </div>
  </div>

  <!-- Profile block -->
  <div class="sb-profile-block">
    <?php
    $initials = 'OW';
    $fullName = 'Owner';
    $email    = '';
    $role     = 'Owner';
    if (isset($user)) {
        if (!empty($user['first_name']) && !empty($user['last_name']))
            $initials = strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1));
        $fullName = htmlspecialchars(trim($user['first_name'].' '.$user['last_name']));
        $email    = htmlspecialchars($user['email'] ?? '');
        $role     = ucfirst(htmlspecialchars($user['role'] ?? 'Owner'));
    }
    ?>
    <div class="sb-profile-avatar">
      <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../uploads/profile_photos/' . $user['profile_photo'])): ?>
        <img src="../uploads/profile_photos/<?= htmlspecialchars($user['profile_photo']) ?>" alt="Avatar">
      <?php else: ?>
        <?= $initials ?>
      <?php endif; ?>
    </div>
    <div class="sb-profile-name"><?= $fullName ?></div>
    <?php if ($email): ?><div class="sb-profile-email"><?= $email ?></div><?php endif; ?>
    <span class="sb-profile-badge"><span class="dot"></span><?= $role ?></span>
  </div>

  <!-- Nav -->
  <div class="sb-nav">

    <a class="sb-link" href="dashboard.php">
      <i class="fas fa-chart-line sb-nav-icon"></i>
      <span class="sb-label">Dashboard</span>
    </a>

    <div class="sb-section-label">Management</div>

    <button class="sb-link sb-parent" data-bs-toggle="collapse" data-bs-target="#sbMgmt" aria-expanded="true">
      <i class="fas fa-building sb-nav-icon"></i>
      <span class="sb-label">Resort Management</span>
      <i class="fas fa-chevron-down sb-arrow"></i>
    </button>
    <div class="collapse sb-sub show" id="sbMgmt">
      <a class="sb-link" href="manage_staff.php"><i class="fas fa-user-tie sb-nav-icon"></i><span class="sb-label">Staff</span></a>
      <a class="sb-link" href="manage_areas.php"><i class="fas fa-map-marker-alt sb-nav-icon"></i><span class="sb-label">Locations</span></a>
      <a class="sb-link" href="manage_facilities.php"><i class="fas fa-building sb-nav-icon"></i><span class="sb-label">Facilities</span></a>
      <a class="sb-link" href="manage_amenities.php"><i class="fas fa-star sb-nav-icon"></i><span class="sb-label">Amenities</span></a>
      <a class="sb-link" href="facilities_status.php"><i class="fas fa-toggle-on sb-nav-icon"></i><span class="sb-label">Facility Status</span></a>
    </div>

    <div class="sb-section-label">Analytics</div>

    <button class="sb-link sb-parent" data-bs-toggle="collapse" data-bs-target="#sbAnalytics" aria-expanded="true">
      <i class="fas fa-chart-bar sb-nav-icon"></i>
      <span class="sb-label">Resort Analytics</span>
      <i class="fas fa-chevron-down sb-arrow"></i>
    </button>
    <div class="collapse sb-sub show" id="sbAnalytics">
      <a class="sb-link" href="booking.php"><i class="fas fa-calendar-check sb-nav-icon"></i><span class="sb-label">Booking Analytics</span></a>
      <a class="sb-link" href="location.php"><i class="fas fa-coins sb-nav-icon"></i><span class="sb-label">Revenue Analytics</span></a>
      <a class="sb-link" href="facilities.php"><i class="fas fa-chart-pie sb-nav-icon"></i><span class="sb-label">Facility Utilization</span></a>
      <a class="sb-link" href="amenities.php"><i class="fas fa-tools sb-nav-icon"></i><span class="sb-label">Maintenance Summary</span></a>
    </div>

    <div class="sb-section-label">Archives</div>

    <button class="sb-link sb-parent" data-bs-toggle="collapse" data-bs-target="#sbArchives" aria-expanded="true">
      <i class="fas fa-archive sb-nav-icon"></i>
      <span class="sb-label">Resort Archives</span>
      <i class="fas fa-chevron-down sb-arrow"></i>
    </button>
    <div class="collapse sb-sub show" id="sbArchives">
      <a class="sb-link" href="staff_history.php"><i class="fas fa-user-clock sb-nav-icon"></i><span class="sb-label">Staff History</span></a>
      <a class="sb-link" href="premises_history.php"><i class="fas fa-home sb-nav-icon"></i><span class="sb-label">Premises History</span></a>
      <a class="sb-link" href="booking_history.php"><i class="fas fa-history sb-nav-icon"></i><span class="sb-label">Booking History</span></a>
      <a class="sb-link" href="maintenance_history.php"><i class="fas fa-wrench sb-nav-icon"></i><span class="sb-label">Maintenance History</span></a>
    </div>

    <div class="sb-section-label">Account</div>
    <a class="sb-link" href="settings.php">
      <i class="fas fa-cog sb-nav-icon"></i>
      <span class="sb-label">Settings</span>
    </a>
    <a class="sb-link" href="audit_logs.php">
      <i class="fas fa-shield-alt sb-nav-icon"></i>
      <span class="sb-label">Audit Logs</span>
    </a>

  </div>

  <!-- Sign Out -->
  <div class="sb-signout">
    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>

</div>
</div>

<script>
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const cur = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sb-link[href]').forEach(function(a){
      if (a.getAttribute('href') === cur) a.classList.add('active');
    });
    // Keep arrow rotated for open groups
    document.querySelectorAll('.sb-sub.show').forEach(function(sub){
      const btn = document.querySelector('[data-bs-target="#' + sub.id + '"]');
      if (btn) btn.setAttribute('aria-expanded', 'true');
    });
  });
})();
</script>
