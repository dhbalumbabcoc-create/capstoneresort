<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

// Must be logged in as staff with must_change_password = 1
if (!is_logged_in()) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = get_user_info($user_id, $conn);

if (!$user || !$user['must_change_password']) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

$step    = $_SESSION['setup_step'] ?? 'otp';   // otp → password
$error   = '';
$success = '';
$base_img = BASE_URL . 'images/';

// ── STEP 1: Send / Verify OTP ──────────────────────────────────────────────
if ($step === 'otp') {

    // Auto-send OTP on first visit
    if (!isset($_SESSION['otp_sent'])) {
        $otp        = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $conn->prepare("UPDATE users SET setup_otp=?, setup_otp_expires=? WHERE id=?")
             ->bind_param('ssi', $otp, $expires_at, $user_id) || null;
        $stmt = $conn->prepare("UPDATE users SET setup_otp=?, setup_otp_expires=? WHERE id=?");
        $stmt->bind_param('ssi', $otp, $expires_at, $user_id);
        $stmt->execute();
        $stmt->close();

        // Send email
        require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'bucod.lyngemae123@gmail.com';
            $mail->Password   = 'mrih mgfp uaaq uagi';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('bucod.lyngemae123@gmail.com', 'Sinulom & Bolao Resort');
            $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            $mail->Subject = 'Account Verification OTP — Sinulom & Bolao Resort';
            $mail->isHTML(true);
            $mail->Body = '
<div style="font-family:Inter,sans-serif;max-width:480px;margin:0 auto;background:#f4f6f9;padding:32px 20px;">
  <div style="background:#fff;border-radius:16px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,.08);">
    <div style="text-align:center;margin-bottom:24px;">
      <div style="background:linear-gradient(135deg,#1B7D3A,#27A457);width:56px;height:56px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;">
        <span style="color:#fff;font-size:1.6rem;">🔐</span>
      </div>
    </div>
    <h2 style="text-align:center;color:#111;font-size:1.4rem;margin-bottom:8px;">Verify Your Account</h2>
    <p style="text-align:center;color:#888;font-size:.9rem;margin-bottom:28px;">Hi <strong>' . htmlspecialchars($user['first_name']) . '</strong>, use the OTP below to verify your new staff account.</p>
    <div style="background:#f0faf4;border:2px dashed #27A457;border-radius:12px;padding:20px;text-align:center;margin-bottom:24px;">
      <div style="font-size:2.4rem;font-weight:900;letter-spacing:10px;color:#1B7D3A;">' . $otp . '</div>
      <div style="color:#888;font-size:.8rem;margin-top:8px;">Expires in 15 minutes</div>
    </div>
    <p style="color:#aaa;font-size:.78rem;text-align:center;">If you did not expect this email, please contact your administrator.</p>
  </div>
</div>';
            $mail->send();
            $_SESSION['otp_sent'] = true;
        } catch (Exception $e) {
            $error = 'Could not send OTP email. Please contact your administrator.';
        }
    }

    // Handle OTP verification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
        $entered_otp = trim($_POST['otp']);
        $stmt = $conn->prepare("SELECT setup_otp, setup_otp_expires FROM users WHERE id=?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['setup_otp'])) {
            $error = 'OTP not found. Please refresh to resend.';
        } elseif (strtotime($row['setup_otp_expires']) < time()) {
            $error = 'OTP has expired. Please refresh to get a new one.';
            unset($_SESSION['otp_sent']);
        } elseif ($row['setup_otp'] !== $entered_otp) {
            $error = 'Incorrect OTP. Please try again.';
        } else {
            // OTP correct — move to password step
            $_SESSION['setup_step'] = 'password';
            $_SESSION['otp_verified'] = true;
            header("Location: " . BASE_URL . "staff_setup.php");
            exit();
        }
    }

    // Resend OTP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
        unset($_SESSION['otp_sent']);
        header("Location: " . BASE_URL . "staff_setup.php");
        exit();
    }
}

// ── STEP 2: Set New Password ───────────────────────────────────────────────
if ($step === 'password') {
    if (!($_SESSION['otp_verified'] ?? false)) {
        $_SESSION['setup_step'] = 'otp';
        header("Location: " . BASE_URL . "staff_setup.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $new_pass     = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (strlen($new_pass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Passwords do not match.';
        } else {
            $hashed = hash_password($new_pass);
            $stmt = $conn->prepare("UPDATE users SET password=?, must_change_password=0, email_verified=1, setup_otp=NULL, setup_otp_expires=NULL WHERE id=?");
            $stmt->bind_param('si', $hashed, $user_id);
            $stmt->execute();
            $stmt->close();

            // Clear setup session vars
            unset($_SESSION['setup_step'], $_SESSION['otp_sent'], $_SESSION['otp_verified']);
            $success = 'Account setup complete! Redirecting...';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Setup — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;background:url('<?php echo $base_img; ?>bolao.jpg') center/cover no-repeat fixed;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;}
body::before{content:'';position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:0;}
.setup-card{position:relative;z-index:1;background:#fff;border-radius:20px;padding:40px 36px;width:100%;max-width:440px;box-shadow:0 8px 48px rgba(0,0,0,.3);}
.setup-logo{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
.setup-logo img{width:44px;height:44px;border-radius:12px;object-fit:cover;border:2px solid #e8f5e9;}
.setup-logo strong{display:block;font-size:.9rem;font-weight:800;color:#111;}
.setup-logo span{font-size:.75rem;color:#aaa;}
.step-indicator{display:flex;align-items:center;gap:8px;margin-bottom:28px;}
.step-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;}
.step-dot.active{background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;}
.step-dot.done{background:#e8f5e9;color:#1B7D3A;}
.step-dot.pending{background:#f0f0f0;color:#bbb;}
.step-line{flex:1;height:2px;background:#f0f0f0;}
.step-line.done{background:#27A457;}
.setup-heading{margin-bottom:24px;}
.setup-heading h2{font-size:1.5rem;font-weight:800;color:#111;margin-bottom:6px;}
.setup-heading p{font-size:.88rem;color:#888;line-height:1.6;}
.alert-error{background:#fdecea;color:#c62828;border:1.5px solid #f5c6cb;border-radius:12px;padding:11px 14px;font-size:.84rem;display:flex;align-items:center;gap:8px;margin-bottom:20px;animation:shake .35s ease;}
.alert-success{background:#e8f5e9;color:#1B7D3A;border:1.5px solid #c8e6c9;border-radius:12px;padding:11px 14px;font-size:.84rem;display:flex;align-items:center;gap:8px;margin-bottom:20px;}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-5px)}40%{transform:translateX(5px)}60%{transform:translateX(-3px)}80%{transform:translateX(3px)}}
.field{margin-bottom:18px;}
.field label{display:block;font-size:.82rem;font-weight:700;color:#222;margin-bottom:8px;}
.input-wrap{position:relative;}
.input-wrap input{width:100%;padding:13px 44px 13px 16px;background:#eef2f7;border:1.5px solid transparent;border-radius:12px;font-size:.92rem;color:#111;outline:none;font-family:inherit;transition:border-color .2s,background .2s,box-shadow .2s;}
.input-wrap input:focus{background:#fff;border-color:#27A457;box-shadow:0 0 0 3px rgba(39,164,87,.13);}
.input-wrap .fi{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#ccc;font-size:.9rem;cursor:pointer;transition:color .2s;}
.input-wrap .fi:hover{color:#27A457;}
/* OTP boxes */
.otp-row{display:flex;gap:10px;justify-content:center;margin-bottom:24px;}
.otp-box{width:52px;height:60px;border:2px solid #e0e0e0;border-radius:12px;font-size:1.6rem;font-weight:800;text-align:center;outline:none;font-family:inherit;color:#111;background:#eef2f7;transition:border-color .2s,box-shadow .2s;}
.otp-box:focus{border-color:#27A457;background:#fff;box-shadow:0 0 0 3px rgba(39,164,87,.13);}
.otp-box.filled{border-color:#27A457;background:#e8f5e9;color:#1B7D3A;}
.btn-primary{width:100%;padding:14px;background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;border:none;border-radius:14px;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;font-family:inherit;transition:all .25s;box-shadow:0 4px 18px rgba(27,125,58,.35);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(27,125,58,.45);}
.btn-link{background:none;border:none;color:#27A457;font-size:.88rem;font-weight:600;cursor:pointer;font-family:inherit;padding:0;transition:opacity .2s;}
.btn-link:hover{opacity:.7;}
.resend-row{text-align:center;margin-top:16px;}
.email-hint{background:#f0faf4;border-radius:10px;padding:12px 16px;font-size:.85rem;color:#555;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.email-hint i{color:#27A457;}
.pw-strength{height:4px;border-radius:2px;margin-top:6px;transition:all .3s;background:#eee;}
.pw-strength.weak{background:#e53935;width:33%;}
.pw-strength.medium{background:#ffa000;width:66%;}
.pw-strength.strong{background:#27A457;width:100%;}
.pw-hint{font-size:.75rem;color:#aaa;margin-top:4px;}
</style>
</head>
<body>
<div class="setup-card">

    <div class="setup-logo">
        <img src="<?php echo $base_img; ?>logo.jpg" alt="Logo">
        <div>
            <strong>Sinulom &amp; Bolao</strong>
            <span>Resort Management</span>
        </div>
    </div>

    <!-- Step indicator -->
    <div class="step-indicator">
        <div class="step-dot <?= $step==='otp' ? 'active' : 'done' ?>">
            <?= $step==='otp' ? '1' : '<i class="fas fa-check"></i>' ?>
        </div>
        <div class="step-line <?= $step==='password' ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step==='password' ? 'active' : 'pending' ?>">2</div>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="alert-success"><i class="fas fa-check-circle"></i><?= $success ?></div>
    <script>setTimeout(()=>location.href='<?= BASE_URL ?>dashboard.php',1500);</script>
    <?php endif; ?>

    <?php if ($step === 'otp' && empty($success)): ?>
    <!-- ── STEP 1: OTP ── -->
    <div class="setup-heading">
        <h2><i class="fas fa-envelope-open-text me-2" style="color:#27A457;font-size:1.2rem;"></i>Verify Your Email</h2>
        <p>We sent a 6-digit OTP to your email address. Enter it below to continue.</p>
    </div>

    <div class="email-hint">
        <i class="fas fa-envelope"></i>
        <span>Sent to: <strong><?= htmlspecialchars(substr($user['email'], 0, 3) . '***@' . explode('@', $user['email'])[1]) ?></strong></span>
    </div>

    <form method="POST" id="otpForm">
        <div class="otp-row">
            <?php for ($i = 1; $i <= 6; $i++): ?>
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" id="otp<?= $i ?>" autocomplete="off">
            <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="otpHidden">
        <button type="submit" class="btn-primary" id="verifyBtn" disabled>
            <i class="fas fa-shield-halved"></i> Verify OTP
        </button>
    </form>

    <div class="resend-row">
        <form method="POST" style="display:inline;">
            <input type="hidden" name="resend" value="1">
            <button type="submit" class="btn-link"><i class="fas fa-rotate-right me-1"></i>Resend OTP</button>
        </form>
    </div>

    <?php elseif ($step === 'password' && empty($success)): ?>
    <!-- ── STEP 2: New Password ── -->
    <div class="setup-heading">
        <h2><i class="fas fa-lock me-2" style="color:#27A457;font-size:1.2rem;"></i>Set Your Password</h2>
        <p>Create a strong password for your account. You'll use this to log in from now on.</p>
    </div>

    <form method="POST">
        <div class="field">
            <label>New Password</label>
            <div class="input-wrap">
                <input type="password" id="newPass" name="new_password" placeholder="At least 8 characters" required oninput="checkStrength(this.value)">
                <i class="fa-regular fa-eye-slash fi" id="toggleNew" onclick="togglePw('newPass','toggleNew')"></i>
            </div>
            <div class="pw-strength" id="pwStrength"></div>
            <div class="pw-hint" id="pwHint">Enter a password</div>
        </div>
        <div class="field">
            <label>Confirm Password</label>
            <div class="input-wrap">
                <input type="password" id="confirmPass" name="confirm_password" placeholder="Re-enter password" required>
                <i class="fa-regular fa-eye-slash fi" id="toggleConfirm" onclick="togglePw('confirmPass','toggleConfirm')"></i>
            </div>
        </div>
        <button type="submit" class="btn-primary">
            <i class="fas fa-check-circle"></i> Save Password &amp; Finish
        </button>
    </form>
    <?php endif; ?>

</div>

<script>
// ── OTP boxes ──
const boxes = document.querySelectorAll('.otp-box');
const hidden = document.getElementById('otpHidden');
const verifyBtn = document.getElementById('verifyBtn');

boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        box.value = box.value.replace(/\D/g,'').slice(-1);
        box.classList.toggle('filled', box.value !== '');
        if (box.value && i < boxes.length - 1) boxes[i+1].focus();
        updateHidden();
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) boxes[i-1].focus();
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const digits = (e.clipboardData.getData('text')).replace(/\D/g,'').slice(0,6);
        digits.split('').forEach((d,j) => { if(boxes[j]){ boxes[j].value=d; boxes[j].classList.add('filled'); } });
        updateHidden();
        if (digits.length === 6 && verifyBtn) verifyBtn.disabled = false;
    });
});

function updateHidden() {
    const val = Array.from(boxes).map(b=>b.value).join('');
    if (hidden) hidden.value = val;
    if (verifyBtn) verifyBtn.disabled = val.length < 6;
}

// ── Password strength ──
function checkStrength(val) {
    const bar = document.getElementById('pwStrength');
    const hint = document.getElementById('pwHint');
    if (!bar) return;
    bar.className = 'pw-strength';
    if (!val) { hint.textContent = 'Enter a password'; return; }
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/\d/.test(val) && /[^A-Za-z0-9]/.test(val)) score++;
    const levels = ['weak','medium','strong'];
    const labels = ['Weak — add uppercase, numbers & symbols','Medium — add numbers or symbols','Strong password'];
    bar.classList.add(levels[score-1] || 'weak');
    hint.textContent = labels[score-1] || 'Too short';
}

function togglePw(id, iconId) {
    const inp = document.getElementById(id);
    const icon = document.getElementById(iconId);
    const show = inp.type === 'text';
    inp.type = show ? 'password' : 'text';
    icon.className = (show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye') + ' fi';
}
</script>
</body>
</html>
