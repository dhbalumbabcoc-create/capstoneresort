<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = '';
$valid   = false;
$user_id = null;
$reset_row_id = null;

// Validate token
if ($token) {
    $stmt = $conn->prepare("SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.status, u.role
                            FROM password_resets pr
                            JOIN users u ON pr.user_id = u.id
                            WHERE pr.token = ? AND pr.used = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $error = 'Invalid or expired reset link. Please request a new OTP.';
    } elseif (strtotime($row['expires_at']) < time()) {
        $error = 'This reset link has expired. Please request a new OTP.';
    } elseif ($row['status'] !== 'active') {
        $error = 'Your account is inactive. Please contact the administrator.';
    } elseif ($row['role'] === 'guest') {
        $error = 'Invalid reset link.';
    } else {
        $valid   = true;
        $user_id = $row['user_id'];
        $reset_row_id = $row['id'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $new_pass    = $_POST['new_password']     ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/[A-Za-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        $error = 'Password must contain at least one letter and one number.';
    } else {
        $hashed = hash_password($new_pass);

        // Update password and clear must_change_password flag
        $upd = $conn->prepare("UPDATE users SET password=?, must_change_password=0 WHERE id=?");
        $upd->bind_param("si", $hashed, $user_id);
        $upd->execute();
        $upd->close();

        // Mark token as used
        $mark = $conn->prepare("UPDATE password_resets SET used=1 WHERE token=?");
        $mark->bind_param("s", $token);
        $mark->execute();
        $mark->close();
        $success = 'Your password has been reset successfully. You can now sign in.';
        $valid   = false; // hide the form
    }
}

$base_img = BASE_URL . 'images/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter','Segoe UI',system-ui,sans-serif;min-height:100vh;background-image:url('<?= $base_img ?>bolao.jpg');background-size:cover;background-position:center;background-attachment:fixed;display:flex;align-items:center;justify-content:center;padding:clamp(12px,2vh,24px) 16px;position:relative;}
body::before{content:'';position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:0;}
.card{position:relative;z-index:1;background:#fff;border-radius:18px;padding:clamp(20px,3vh,28px) clamp(20px,3vw,30px) clamp(16px,2vh,22px);width:100%;max-width:390px;box-shadow:0 8px 48px rgba(0,0,0,.28);max-height:calc(100vh - 24px);overflow-y:auto;scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.15) transparent;}
.card::-webkit-scrollbar{width:4px;}
.card::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:4px;}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:18px;}
.brand img{width:38px;height:38px;border-radius:10px;object-fit:cover;border:2px solid #e8f5e9;}
.brand-text strong{display:block;font-size:.86rem;font-weight:800;color:#111;line-height:1.2;}
.brand-text span{font-size:.72rem;color:#aaa;}
.icon-wrap{width:50px;height:50px;background:#e8f5e9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;}
.icon-wrap i{font-size:1.3rem;color:#1B7D3A;}
h2{font-size:1.35rem;font-weight:800;color:#111;text-align:center;margin-bottom:4px;}
.sub{font-size:.82rem;color:#aaa;text-align:center;margin-bottom:16px;}
.alert{border-radius:10px;padding:9px 12px;font-size:.82rem;display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.alert-error{background:#fdecea;color:#c62828;border:1.5px solid #f5c6cb;}
.alert-success{background:#e8f5e9;color:#1B7D3A;border:1.5px solid #c8e6c9;}
.field{margin-bottom:18px;}
.field label{display:block;font-size:.82rem;font-weight:700;color:#222;margin-bottom:7px;}
.input-wrap{position:relative;}
.input-wrap input{width:100%;padding:13px 44px 13px 16px;background:#eef2f7;border:1.5px solid transparent;border-radius:12px;font-size:.92rem;color:#111;outline:none;font-family:inherit;transition:border-color .2s,background .2s;}
.input-wrap input:focus{background:#fff;border-color:#27A457;box-shadow:0 0 0 3px rgba(39,164,87,.13);}
.input-wrap .fi{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#ccc;font-size:.9rem;cursor:pointer;}
.input-wrap .fi:hover{color:#27A457;}
.hint{font-size:.75rem;color:#aaa;margin-top:5px;}
.strength-bar{height:4px;border-radius:4px;background:#eee;margin-top:6px;overflow:hidden;}
.strength-fill{height:100%;border-radius:4px;transition:width .3s,background .3s;width:0;}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;border:none;border-radius:14px;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;font-family:inherit;transition:all .25s;box-shadow:0 4px 18px rgba(27,125,58,.35);}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(27,125,58,.45);}
.back-link{display:block;text-align:center;margin-top:16px;color:#27A457;font-size:.88rem;font-weight:600;text-decoration:none;}
.back-link:hover{opacity:.7;}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <img src="<?= $base_img ?>logo.jpg" alt="Resort Logo">
    <div class="brand-text">
      <strong>Sinulom &amp; Bolao</strong>
      <span>Resort Management</span>
    </div>
  </div>

  <div class="icon-wrap"><i class="fas fa-key"></i></div>
  <h2>Reset Password</h2>
  <p class="sub">Enter your new password below.</p>

  <?php if ($error): ?>
  <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
  <a href="<?= BASE_URL ?>login.php" class="btn" style="text-decoration:none;margin-top:4px;"><i class="fas fa-right-to-bracket"></i> Go to Sign In</a>

  <?php elseif ($valid): ?>
  <form method="POST">
    <div class="field">
      <label>New Password</label>
      <div class="input-wrap">
        <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters" required>
        <i class="fa-regular fa-eye-slash fi" id="toggleNew" onclick="togglePass('new_password','toggleNew')"></i>
      </div>
      <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
      <div class="hint" id="strengthText">Use at least 8 characters with letters and numbers.</div>
    </div>
    <div class="field">
      <label>Confirm New Password</label>
      <div class="input-wrap">
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
        <i class="fa-regular fa-eye-slash fi" id="toggleConfirm" onclick="togglePass('confirm_password','toggleConfirm')"></i>
      </div>
    </div>
    <button type="submit" class="btn"><i class="fas fa-lock"></i> Set New Password</button>
  </form>

  <?php elseif (!$token): ?>
  <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i>No reset token provided.</div>
  <?php endif; ?>

  <a href="<?= BASE_URL ?>login.php" class="back-link"><i class="fas fa-arrow-left me-1"></i> Back to Sign In</a>
</div>

<script>
function togglePass(id, iconId){
    const el=document.getElementById(id), icon=document.getElementById(iconId);
    const show=el.type==='text';
    el.type=show?'password':'text';
    icon.className=(show?'fa-regular fa-eye-slash':'fa-regular fa-eye')+' fi';
}
const pwInput=document.getElementById('new_password');
if(pwInput){
    pwInput.addEventListener('input',function(){
        const v=this.value;
        let score=0;
        if(v.length>=8) score++;
        if(/[A-Z]/.test(v)) score++;
        if(/[0-9]/.test(v)) score++;
        if(/[^A-Za-z0-9]/.test(v)) score++;
        const fill=document.getElementById('strengthFill');
        const txt=document.getElementById('strengthText');
        const colors=['#e53935','#ff7043','#fdd835','#27A457'];
        const labels=['Too short','Weak','Fair','Strong'];
        fill.style.width=(score*25)+'%';
        fill.style.background=colors[Math.max(0,score-1)]||'#eee';
        txt.textContent=v.length?labels[Math.max(0,score-1)]:'Use at least 8 characters with letters and numbers.';
        txt.style.color=colors[Math.max(0,score-1)]||'#aaa';
    });
}
</script>
</body>
</html>
