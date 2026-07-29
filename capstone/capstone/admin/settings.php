<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('admin');
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL");
$user = get_user_info($_SESSION['user_id'], $conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = trim($_POST['email']        ?? '');
    $first_name = trim($_POST['first_name']   ?? '');
    $last_name  = trim($_POST['last_name']    ?? '');
    $phone      = trim($_POST['phone']        ?? '');
    $address    = trim($_POST['address']      ?? '');
    $new_pass   = trim($_POST['new_password'] ?? '');
    $fields=[]; $params=[]; $types='';

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/profile_photos/';
        if (!file_exists($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $filename = 'admin_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
            $fields[] = 'profile_photo=?';
            $params[] = $filename;
            $types   .= 's';
        }
    }

    if ($email)      { $fields[]='email=?';      $params[]=$email;      $types.='s'; }
    if ($first_name) { $fields[]='first_name=?'; $params[]=$first_name; $types.='s'; }
    if ($last_name)  { $fields[]='last_name=?';  $params[]=$last_name;  $types.='s'; }
    if ($phone)      { $fields[]='phone=?';      $params[]=$phone;      $types.='s'; }
    if ($address)    { $fields[]='address=?';    $params[]=$address;    $types.='s'; }
    if ($new_pass)   { $fields[]='password=?';   $params[]=password_hash($new_pass,PASSWORD_DEFAULT); $types.='s'; }

    if (!empty($fields)) {
        $params[] = $_SESSION['user_id']; $types .= 'i';
        $stmt = $conn->prepare('UPDATE users SET '.implode(', ',$fields).' WHERE id=?');
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) { set_success_message('Settings updated successfully.'); $user = get_user_info($_SESSION['user_id'], $conn); }
        else set_error_message('Error updating settings.');
        $stmt->close();
    } else { set_error_message('No changes made.'); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/admin_page_styles.php'; ?>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/admin_sidebar.php'; ?>
    <div class="content">
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-cog me-2" style="color:#1B7D3A;"></i>Account Settings</div>
                <div class="dash-topbar-sub"><?= date('l, F j, Y') ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-shield-alt me-1"></i>Admin</span>
            </div>
        </div>
        <div class="dash-body">
            <?php display_success_message(); display_error_message(); ?>
            <div class="row g-4">
                <!-- Profile summary card -->
                <div class="col-lg-4">
                    <div class="settings-card">
                        <h5><i class="fas fa-id-card me-2" style="color:#1B7D3A;"></i>Profile Summary</h5>
                        <div class="text-center py-3">
                            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1B7D3A,#27A457);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;margin:0 auto 12px;overflow:hidden;border:3px solid #fff;box-shadow:0 4px 12px rgba(27,125,58,.25);" id="avatarPreview">
                                <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../uploads/profile_photos/' . $user['profile_photo'])): ?>
                                    <img src="../uploads/profile_photos/<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <?= strtoupper(substr($user['first_name']??'A',0,1).substr($user['last_name']??'',0,1)) ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success mb-2" onclick="document.getElementById('photoInput').click()" style="font-size:.78rem;border-radius:8px;font-weight:600;">
                                <i class="fas fa-camera me-1"></i> Upload Photo
                            </button>
                            <div style="font-weight:700;font-size:1.05rem;color:#1a1a1a;"><?= htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??'')) ?></div>
                            <div style="font-size:.82rem;color:#888;margin-top:2px;"><?= htmlspecialchars($user['email']??'') ?></div>
                            <span class="pill pill-green mt-2 d-inline-block">Admin</span>
                        </div>
                        <hr style="border-color:#f0f0f0;">
                        <div style="font-size:.85rem;">
                            <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid #f5f5f5;"><span style="color:#888;">Phone</span><span style="font-weight:600;"><?= htmlspecialchars($user['phone']??'—') ?></span></div>
                            <div class="d-flex justify-content-between py-2"><span style="color:#888;">Address</span><span style="font-weight:600;text-align:right;max-width:60%;"><?= htmlspecialchars($user['address']??'—') ?></span></div>
                        </div>
                    </div>
                </div>
                <!-- Edit form -->
                <div class="col-lg-8">
                    <div class="settings-card">
                        <h5><i class="fas fa-user-edit me-2" style="color:#1B7D3A;"></i>Edit Profile</h5>
                        <form method="POST" enctype="multipart/form-data" autocomplete="off">
                            <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name']??'') ?>" placeholder="First Name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name']??'') ?>" placeholder="Last Name">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']??'') ?>">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" placeholder="09XXXXXXXXX">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($user['address']??'') ?>" placeholder="Address">
                                </div>
                            </div>
                            <hr style="border-color:#f0f0f0;">
                            <h6 style="font-weight:700;color:#1a1a1a;margin-bottom:16px;"><i class="fas fa-lock me-2" style="color:#1B7D3A;"></i>Change Password</h6>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" placeholder="Leave blank to keep current password">
                                <div class="form-text">Minimum 8 characters recommended.</div>
                            </div>
                            <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var photoInput = document.getElementById('photoInput');
    if (!photoInput) return;
    photoInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:cover;">';
        };
        reader.readAsDataURL(file);
    });
});
</script>
</body>
</html>
