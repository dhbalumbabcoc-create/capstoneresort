<?php /* Supervisor Sidebar */ ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/owner-sidebar.css">
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
    $initials = 'SV';
    $fullName = 'Supervisor';
    $email    = '';
    $role     = 'Supervisor';
    if (isset($user)) {
        if (!empty($user['first_name']) && !empty($user['last_name']))
            $initials = strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1));
        $fullName = htmlspecialchars(trim($user['first_name'].' '.$user['last_name']));
        $email    = htmlspecialchars($user['email'] ?? '');
        $role     = ucfirst(htmlspecialchars($user['role'] ?? 'Supervisor'));
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

    <div class="sb-section-label">Facility</div>

    <button class="sb-link sb-parent" data-bs-toggle="collapse" data-bs-target="#sbFacility" aria-expanded="false">
      <i class="fas fa-tools sb-nav-icon"></i>
      <span class="sb-label">Facility Management</span>
      <i class="fas fa-chevron-down sb-arrow"></i>
    </button>
    <div class="collapse sb-sub" id="sbFacility">
      <a class="sb-link" href="facilities.php"><i class="fas fa-check-circle sb-nav-icon"></i><span class="sb-label">Facility Status</span></a>
      <a class="sb-link" href="maintenance.php"><i class="fas fa-exclamation-triangle sb-nav-icon"></i><span class="sb-label">Maintenance</span></a>
    </div>

    <div class="sb-section-label">Reports</div>

    <button class="sb-link sb-parent" data-bs-toggle="collapse" data-bs-target="#sbReports" aria-expanded="false">
      <i class="fas fa-file-alt sb-nav-icon"></i>
      <span class="sb-label">Reports</span>
      <i class="fas fa-chevron-down sb-arrow"></i>
    </button>
    <div class="collapse sb-sub" id="sbReports">
      <a class="sb-link" href="maintenance_history.php"><i class="fas fa-clipboard-list sb-nav-icon"></i><span class="sb-label">Maintenance History</span></a>
      <a class="sb-link" href="guest_feedback.php"><i class="fas fa-star sb-nav-icon"></i><span class="sb-label">Guest Feedback</span></a>
    </div>

    <div class="sb-section-label">Account</div>
    <a class="sb-link" href="my_account.php">
      <i class="fas fa-cog sb-nav-icon"></i>
      <span class="sb-label">Settings</span>
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
  document.addEventListener('DOMContentLoaded',function(){
    const cur=window.location.pathname.split('/').pop();
    document.querySelectorAll('.sb-link[href]').forEach(function(a){if(a.getAttribute('href')===cur)a.classList.add('active');});
    document.querySelectorAll('.sb-sub').forEach(function(sub){if(sub.querySelector('.sb-link.active')){const btn=document.querySelector('[data-bs-target="#'+sub.id+'"]');if(btn){btn.setAttribute('aria-expanded','true');sub.classList.add('show');}}});
  });
})();
</script>
