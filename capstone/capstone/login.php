<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (is_logged_in()) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

// ── Forgot Password AJAX handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Ensure OTP table exists (with otp column)
    $conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `otp` VARCHAR(6) NOT NULL DEFAULT '',
        `token` VARCHAR(64) DEFAULT NULL,
        `expires_at` DATETIME NOT NULL,
        `used` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add otp column if table existed without it
    $conn->query("ALTER TABLE `password_resets` ADD COLUMN IF NOT EXISTS `otp` VARCHAR(6) NOT NULL DEFAULT '' AFTER `user_id`");

    // ── Step 1: Send OTP ──────────────────────────────────────────────────────
    if ($_POST['action'] === 'forgot_send_otp') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id, first_name, last_name, role, status FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || $row['role'] === 'guest') {
            echo json_encode(['success' => false, 'message' => 'This email is not registered as a staff account.']);
            exit();
        }
        if ($row['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact the administrator.']);
            exit();
        }

        $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires   = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $user_id   = (int)$row['id'];
        $full_name = $row['first_name'] . ' ' . $row['last_name'];

        // Invalidate old OTPs for this user
        $del = $conn->prepare("UPDATE password_resets SET used=1 WHERE user_id=? AND used=0");
        $del->bind_param("i", $user_id);
        $del->execute();
        $del->close();

        // Insert new OTP
        $ins = $conn->prepare("INSERT INTO password_resets (user_id, otp, expires_at) VALUES (?,?,?)");
        $ins->bind_param("iss", $user_id, $otp, $expires);
        $ins->execute();
        $ins->close();

        // Send OTP email
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'bucod.lyngemae123@gmail.com';
            $mail->Password   = 'oqjt slmc lmsv kmis';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            $mail->setFrom('bucod.lyngemae123@gmail.com', 'Sinulom & Bolao Resort');
            $mail->addAddress($email, $full_name);
            $mail->isHTML(true);
            $mail->Subject = 'Your Password Reset OTP — Sinulom & Bolao Resort';
            $mail->Body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);">
  <div style="background:#1a3d2b;padding:28px 32px;text-align:center;">
    <h1 style="color:#fff;font-size:20px;margin:0;">Password Reset OTP</h1>
    <p style="color:rgba(255,255,255,.7);font-size:13px;margin:6px 0 0;">Sinulom Falls &amp; Bolao Cold Spring Resort</p>
  </div>
  <div style="padding:32px;">
    <p style="font-size:15px;color:#1a1a1a;">Hi <strong>' . htmlspecialchars($full_name) . '</strong>,</p>
    <p style="font-size:14px;color:#6b7280;line-height:1.6;margin:12px 0 24px;">Use the OTP below to reset your password. It expires in <strong>10 minutes</strong>.</p>
    <div style="text-align:center;margin:24px 0;">
      <div style="display:inline-block;background:#f0faf4;border:2px dashed #1B7D3A;border-radius:14px;padding:18px 40px;">
        <span style="font-size:2.4rem;font-weight:900;letter-spacing:10px;color:#1a3d2b;">' . $otp . '</span>
      </div>
    </div>
    <p style="font-size:12px;color:#aaa;text-align:center;">Do not share this OTP with anyone. If you did not request this, ignore this email.</p>
  </div>
  <div style="background:#1a3d2b;padding:16px 32px;text-align:center;">
    <p style="color:rgba(255,255,255,.6);font-size:12px;margin:0;">This is an automated message. Please do not reply.</p>
  </div>
</div></body></html>';
            $mail->AltBody = "Hi $full_name,\n\nYour OTP is: $otp\n\nExpires in 10 minutes. Do not share this.";
            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent to ' . $email]);
        } catch (\Exception $e) {
            error_log("OTP email failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
        }
        exit();
    }

    // ── Step 2: Verify OTP ────────────────────────────────────────────────────
    if ($_POST['action'] === 'forgot_verify_otp') {
        $email = trim($_POST['email'] ?? '');
        $otp   = trim($_POST['otp']   ?? '');

        if (empty($email) || strlen($otp) !== 6) {
            echo json_encode(['success' => false, 'message' => 'Please enter the 6-digit OTP.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND role!='guest' AND status='active' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $urow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$urow) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit();
        }

        $now = date('Y-m-d H:i:s');
        $vstmt = $conn->prepare("SELECT id FROM password_resets WHERE user_id=? AND otp=? AND used=0 AND expires_at > ? ORDER BY id DESC LIMIT 1");
        $vstmt->bind_param("iss", $urow['id'], $otp, $now);
        $vstmt->execute();
        $vrow = $vstmt->get_result()->fetch_assoc();
        $vstmt->close();

        if (!$vrow) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try again.']);
            exit();
        }

        // Generate a short-lived token for the password reset step
        $token   = bin2hex(random_bytes(32));
        $tok_exp = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $upd = $conn->prepare("UPDATE password_resets SET token=?, expires_at=? WHERE id=?");
        $upd->bind_param("ssi", $token, $tok_exp, $vrow['id']);
        $upd->execute();
        $upd->close();

        echo json_encode(['success' => true, 'token' => $token]);
        exit();
    }

    // ── Step 3: Reset Password ────────────────────────────────────────────────
    if ($_POST['action'] === 'forgot_reset_password') {
        $token    = trim($_POST['token']    ?? '');
        $new_pass = $_POST['new_password']  ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid session. Please start over.']);
            exit();
        }
        if (strlen($new_pass) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            exit();
        }
        if ($new_pass !== $conf_pass) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit();
        }
        if (!preg_match('/[A-Za-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one letter and one number.']);
            exit();
        }

        $now = date('Y-m-d H:i:s');
        $tstmt = $conn->prepare("SELECT pr.id, pr.user_id FROM password_resets pr
                                  JOIN users u ON pr.user_id=u.id
                                  WHERE pr.token=? AND pr.used=0 AND pr.expires_at>?
                                  AND u.status='active' AND u.role!='guest' LIMIT 1");
        $tstmt->bind_param("ss", $token, $now);
        $tstmt->execute();
        $trow = $tstmt->get_result()->fetch_assoc();
        $tstmt->close();

        if (!$trow) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
            exit();
        }

        $hashed = hash_password($new_pass);
        $upd = $conn->prepare("UPDATE users SET password=?, must_change_password=0 WHERE id=?");
        $upd->bind_param("si", $hashed, $trow['user_id']);
        $upd->execute();
        $upd->close();

        $mark = $conn->prepare("UPDATE password_resets SET used=1 WHERE id=?");
        $mark->bind_param("i", $trow['id']);
        $mark->execute();
        $mark->close();

        echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now sign in.']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

if (!isset($_SESSION['captcha'])) {
    $_SESSION['num1'] = rand(10, 99);
    $_SESSION['num2'] = rand(1, 9);
    $_SESSION['captcha'] = $_SESSION['num1'] + $_SESSION['num2'];
}

$error = '';
if (!empty($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = escape_input($_POST['email'] ?? '', $conn);
    $password = $_POST['password'] ?? '';
    $captcha  = $_POST['captcha'] ?? '';

    if (empty($login_id) || empty($password) || empty($captcha)) {
        $error = 'Please fill in all fields';
    } elseif ($captcha != $_SESSION['captcha']) {
        $error = 'Incorrect captcha answer';
    } else {
        $stmt = $conn->prepare("SELECT id, password, role, first_name, must_change_password FROM users WHERE (email = ? OR username = ?) AND (status = 'active' OR (status = 'pending' AND must_change_password = 1)) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $login_id, $login_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();

                    // Block guest role from staff login
                    if (($user['role'] ?? '') === 'guest') {
                        $error = 'Invalid username/email or password';
                    } elseif (verify_password($password, $user['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id']        = $user['id'];
                        $_SESSION['user_role']       = $user['role'];
                        $_SESSION['user_name']       = $user['first_name'];
                        $_SESSION['user_first_name'] = $user['first_name'];
                        $_SESSION['last_activity']   = time();
                        $_SESSION['username']        = $login_id;

                        log_audit_event($conn, 'login_success', 'Role: ' . $user['role']);

                        // Upgrade plain-text passwords
                        if (password_get_info($user['password'])['algo'] === null) {
                            $new_hash = hash_password($password);
                            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($upd) { $upd->bind_param("si", $new_hash, $user['id']); $upd->execute(); $upd->close(); }
                        }

                        unset($_SESSION['captcha']);

                        // First-time login: must set new password + verify email
                        if (!empty($user['must_change_password'])) {
                            unset($_SESSION['setup_step'], $_SESSION['otp_sent'], $_SESSION['otp_verified']);
                            header("Location: " . BASE_URL . "staff_setup.php");
                        } else {
                            header("Location: " . BASE_URL . "dashboard.php");
                        }
                        exit();

                    } else {
                        $error = 'Invalid username/email or password';
                        $_SESSION['username'] = $login_id;
                        log_audit_event($conn, 'login_failed', 'Wrong password for account: ' . $login_id);
                        unset($_SESSION['username']);
                    }
                } else {
                    $error = 'Invalid username/email or password';
                    $_SESSION['username'] = $login_id;
                    log_audit_event($conn, 'login_failed', 'Account not found: ' . $login_id);
                    unset($_SESSION['username']);
                }
            } else { $error = 'Login failed. Please try again.'; }
            $stmt->close();
        } else { $error = 'Database setup incomplete.'; }
    }

    $_SESSION['num1'] = rand(10, 99);
    $_SESSION['num2'] = rand(1, 9);
    $_SESSION['captcha'] = $_SESSION['num1'] + $_SESSION['num2'];
}
$base_img = BASE_URL . 'images/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In - Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter','Segoe UI',system-ui,sans-serif;min-height:100vh;background-image:url('<?php echo $base_img; ?>bolao.jpg');background-size:cover;background-position:center;background-attachment:fixed;display:flex;align-items:center;justify-content:flex-end;padding:clamp(10px,2vh,20px) clamp(16px,4vw,5vw);position:relative;}
body::before{content:'';position:fixed;inset:0;background:rgba(0,0,0,.40);z-index:0;}
.welcome-text{position:fixed;left:6vw;top:50%;transform:translateY(-50%);z-index:1;color:#fff;max-width:380px;}
.welcome-text h1{font-size:clamp(1.8rem,3vw,2.6rem);font-weight:800;line-height:1.15;margin-bottom:8px;text-shadow:0 2px 16px rgba(0,0,0,.6);}
.welcome-text p{font-size:.9rem;color:rgba(255,255,255,.82);line-height:1.5;text-shadow:0 1px 8px rgba(0,0,0,.4);}
.login-card{position:relative;z-index:1;background:#fff;border-radius:18px;padding:clamp(16px,2.2vh,22px) clamp(20px,2.5vw,28px) clamp(12px,1.8vh,16px);width:400px;flex-shrink:0;box-shadow:0 8px 48px rgba(0,0,0,.28),0 2px 8px rgba(0,0,0,.12);max-height:calc(100vh - 24px);overflow-y:auto;scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.15) transparent;}
.login-card::-webkit-scrollbar{width:4px;}
.login-card::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:4px;}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.brand img{width:38px;height:38px;border-radius:9px;object-fit:cover;border:2px solid #e8f5e9;}
.brand-text strong{display:block;font-size:.86rem;font-weight:800;color:#111;line-height:1.2;}
.brand-text span{font-size:.72rem;color:#aaa;}
.card-heading{margin-bottom:12px;}
.card-heading h2{font-size:1.4rem;font-weight:800;color:#111;margin-bottom:2px;line-height:1.2;}
.card-heading p{font-size:.8rem;color:#aaa;}
.alert-error{background:#fdecea;color:#c62828;border:1.5px solid #f5c6cb;border-radius:9px;padding:7px 10px;font-size:.8rem;display:flex;align-items:center;gap:8px;margin-bottom:10px;animation:shake .35s ease;}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-5px)}40%{transform:translateX(5px)}60%{transform:translateX(-3px)}80%{transform:translateX(3px)}}
.field{margin-bottom:10px;}
.field label{display:flex;align-items:center;gap:4px;font-size:.76rem;font-weight:700;color:#222;margin-bottom:4px;}
.field label .req{color:#e53935;font-size:.7rem;}
.input-wrap{position:relative;}
.input-wrap input{width:100%;padding:9px 38px 9px 13px;background:#eef2f7;border:1.5px solid transparent;border-radius:9px;font-size:.86rem;color:#111;outline:none;font-family:inherit;transition:border-color .2s,background .2s,box-shadow .2s;}
.input-wrap input:focus{background:#fff;border-color:#27A457;box-shadow:0 0 0 3px rgba(39,164,87,.13);}
.input-wrap input::placeholder{color:#bbc;}
.input-wrap .fi{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#ccc;font-size:.85rem;cursor:pointer;transition:color .2s;}
.input-wrap .fi:hover{color:#27A457;}
.sec-row{display:flex;align-items:center;gap:8px;margin:10px 0 6px;font-size:.65rem;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:1px;}
.sec-row i{color:#27A457;font-size:.75rem;}
.sec-row::after{content:'';flex:1;height:1px;background:#efefef;}
.captcha-row{display:flex;align-items:center;gap:8px;margin-bottom:12px;}
.cap-num{background:#eef2f7;border:1.5px solid #d0e8d8;border-radius:9px;padding:7px 12px;font-size:.92rem;font-weight:800;color:#1B7D3A;min-width:44px;text-align:center;}
.cap-op{font-size:.95rem;color:#ccc;font-weight:700;}
.cap-ans{width:66px;padding:7px 6px;background:#eef2f7;border:1.5px solid transparent;border-radius:9px;font-size:.92rem;text-align:center;outline:none;font-family:inherit;font-weight:700;transition:border-color .2s,background .2s,color .2s;}
.cap-ans:focus{background:#fff;border-color:#27A457;box-shadow:0 0 0 3px rgba(39,164,87,.13);}
.cap-ans.ok{border-color:#27A457;background:#e8f5e9;color:#1B7D3A;}
.cap-ans.bad{border-color:#e53935;background:#fdecea;color:#c62828;}
.cap-refresh{color:#ccc;cursor:pointer;font-size:.88rem;transition:color .2s,transform .4s;padding:3px;}
.cap-refresh:hover{color:#27A457;transform:rotate(180deg);}
.btn-signin{width:100%;padding:10px;background:linear-gradient(135deg,#1B7D3A 0%,#27A457 100%);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;letter-spacing:.3px;transition:all .25s;box-shadow:0 4px 14px rgba(27,125,58,.3);}
.btn-signin:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(27,125,58,.4);}
.btn-signin:active{transform:translateY(0);}
.forgot-link{display:block;text-align:center;margin-top:8px;color:#27A457;font-size:.8rem;font-weight:600;text-decoration:none;transition:opacity .2s;}
.forgot-link:hover{opacity:.7;}
.btn-guest{width:100%;padding:9px;background:transparent;color:#555;border:1.5px solid #d0d0d0;border-radius:10px;font-size:.88rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;letter-spacing:.2px;transition:all .25s;margin-top:8px;}
.btn-guest:hover{background:#f5f5f5;border-color:#27A457;color:#1B7D3A;transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,.06);}
.btn-guest:active{transform:translateY(0);}
.card-footer{margin-top:12px;padding-top:10px;border-top:1px solid #f0f0f0;font-size:.7rem;color:#ccc;text-align:center;line-height:1.4;}
@media(max-width:960px){body{justify-content:center;padding:16px;}.welcome-text{display:none;}.login-card{width:100%;max-width:400px;}}
@media(max-width:480px){.login-card{padding:18px 16px 14px;}}
</style>
</head>
<body>
<div class="welcome-text">
    <h1>Welcome Back</h1>
    <p>Sign in to continue managing Sinulom and Bolao Cold Spring.</p>
</div>
<div class="login-card">
    <div class="brand">
        <img src="<?php echo $base_img; ?>logo.jpg" alt="Resort Logo">
        <div class="brand-text">
            <strong>Sinulom &amp; Bolao</strong>
            <span>Resort Management</span>
        </div>
    </div>
    <div class="card-heading">
        <h2>Sign In</h2>
        <p>Enter your credentials to access the system</p>
    </div>
    <?php if (!empty($error)): ?>
    <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
        <!-- Honeypot fields to prevent browser autofill -->
        <input type="text" name="fake_user" style="display:none;" tabindex="-1" autocomplete="off">
        <input type="password" name="fake_pass" style="display:none;" tabindex="-1" autocomplete="new-password">
        <div class="field">
            <label>Email Address<span class="req">*</span></label>
            <div class="input-wrap">
                <input type="text" name="email" placeholder="owner@resort.com" required autocomplete="off">
                <i class="fa-regular fa-user fi"></i>
            </div>
        </div>
        <div class="field">
            <label>Password <span class="req">*</span></label>
            <div class="input-wrap">
                <input type="password" id="password" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="new-password">
                <i id="toggleIcon" class="fa-regular fa-eye-slash fi" onclick="togglePassword()" title="Show / hide"></i>
            </div>
        </div>
        <div class="sec-row"><i class="fas fa-shield-halved"></i> Security Verification</div>
        <div class="captcha-row">
            <div class="cap-num"><?php echo (int)$_SESSION['num1']; ?></div>
            <span class="cap-op">+</span>
            <div class="cap-num"><?php echo (int)$_SESSION['num2']; ?></div>
            <span class="cap-op">=</span>
            <input type="number" class="cap-ans" id="captchaInput" name="captcha" placeholder="?" required>
            <i class="fas fa-rotate-right cap-refresh" onclick="refreshCaptcha()" title="New numbers"></i>
        </div>
        <button type="submit" class="btn-signin">
            <i class="fas fa-right-to-bracket"></i> Sign In
        </button>
        <a href="#" class="forgot-link" onclick="openForgot(event)">Forgot your password?</a>
        <button type="button" class="btn-guest" onclick="window.location.href='<?php echo BASE_URL; ?>guest_login.php'">
            <i class="fas fa-user-clock"></i> Guest Login
        </button>
    </form>

    <!-- Forgot Password Modal — 2-step OTP -->
    <div id="forgotOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;align-items:center;justify-content:center;">
      <div style="background:#fff;border-radius:18px;padding:36px 32px;width:100%;max-width:400px;box-shadow:0 12px 48px rgba(0,0,0,.25);position:relative;margin:20px;">
        <button onclick="closeForgot()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:1.3rem;color:#aaa;cursor:pointer;line-height:1;">&times;</button>

        <!-- Step 1: Email -->
        <div id="fpStep1">
          <div style="text-align:center;margin-bottom:20px;">
            <div style="width:52px;height:52px;background:#e8f5e9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
              <i class="fas fa-envelope-open-text" style="color:#1B7D3A;font-size:1.3rem;"></i>
            </div>
            <h3 style="font-size:1.15rem;font-weight:800;color:#111;margin:0 0 6px;">Forgot Password?</h3>
            <p style="font-size:.83rem;color:#aaa;margin:0;">Enter your staff email. We'll send a 6-digit OTP.</p>
          </div>
          <div id="fpMsg1" style="display:none;border-radius:10px;padding:10px 14px;font-size:.84rem;margin-bottom:14px;"></div>
          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:.82rem;font-weight:700;color:#222;margin-bottom:7px;">Email Address</label>
            <div style="position:relative;">
              <input type="email" id="fpEmail" placeholder="your@email.com"
                style="width:100%;padding:12px 42px 12px 14px;background:#eef2f7;border:1.5px solid transparent;border-radius:11px;font-size:.92rem;color:#111;outline:none;font-family:inherit;"
                onfocus="this.style.borderColor='#27A457';this.style.background='#fff'"
                onblur="this.style.borderColor='transparent';this.style.background='#eef2f7'"
                onkeydown="if(event.key==='Enter')sendOtp()">
              <i class="fa-regular fa-envelope" style="position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#ccc;"></i>
            </div>
          </div>
          <button onclick="sendOtp()" id="fpBtn1"
            style="width:100%;padding:13px;background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;border:none;border-radius:12px;font-size:.93rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;">
            <i class="fas fa-paper-plane"></i> Send OTP
          </button>
          <p style="text-align:center;font-size:.76rem;color:#ccc;margin-top:12px;">Only active staff accounts can reset their password.</p>
        </div>

        <!-- Step 2: OTP Verification -->
        <div id="fpStep2" style="display:none;">
          <div style="text-align:center;margin-bottom:20px;">
            <div style="width:52px;height:52px;background:#e8f5e9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
              <i class="fas fa-shield-halved" style="color:#1B7D3A;font-size:1.3rem;"></i>
            </div>
            <h3 style="font-size:1.15rem;font-weight:800;color:#111;margin:0 0 6px;">Enter OTP</h3>
            <p id="fpOtpHint" style="font-size:.83rem;color:#aaa;margin:0;">A 6-digit OTP was sent to your email.</p>
          </div>
          <div id="fpMsg2" style="display:none;border-radius:10px;padding:10px 14px;font-size:.84rem;margin-bottom:14px;"></div>
          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:.82rem;font-weight:700;color:#222;margin-bottom:7px;">6-Digit OTP</label>
            <input type="text" id="fpOtp" placeholder="_ _ _ _ _ _" maxlength="6" inputmode="numeric"
              oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)"
              onkeydown="if(event.key==='Enter')verifyOtp()"
              style="width:100%;padding:14px;background:#eef2f7;border:1.5px solid transparent;border-radius:11px;font-size:1.6rem;font-weight:800;color:#1a3d2b;text-align:center;letter-spacing:10px;outline:none;font-family:inherit;"
              onfocus="this.style.borderColor='#27A457';this.style.background='#fff'"
              onblur="this.style.borderColor='transparent';this.style.background='#eef2f7'">
          </div>
          <button onclick="verifyOtp()" id="fpBtn2"
            style="width:100%;padding:13px;background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;border:none;border-radius:12px;font-size:.93rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;margin-bottom:10px;">
            <i class="fas fa-check-circle"></i> Verify OTP
          </button>
          <button onclick="backToEmail()"
            style="width:100%;padding:10px;background:none;border:1.5px solid #e0e0e0;border-radius:12px;font-size:.85rem;font-weight:600;color:#888;cursor:pointer;font-family:inherit;">
            <i class="fas fa-arrow-left"></i> Back
          </button>
          <div id="fpResend" style="text-align:center;margin-top:12px;font-size:.78rem;color:#aaa;">
            Didn't receive it? <a href="#" onclick="resendOtp(event)" style="color:#27A457;font-weight:600;">Resend OTP</a>
          </div>
        </div>

        <!-- Step 3: New Password -->
        <div id="fpStep3" style="display:none;">
          <div style="text-align:center;margin-bottom:20px;">
            <div style="width:52px;height:52px;background:#e8f5e9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
              <i class="fas fa-lock" style="color:#1B7D3A;font-size:1.3rem;"></i>
            </div>
            <h3 style="font-size:1.15rem;font-weight:800;color:#111;margin:0 0 6px;">Set New Password</h3>
            <p style="font-size:.83rem;color:#aaa;margin:0;">OTP verified. Enter your new password.</p>
          </div>
          <div id="fpMsg3" style="display:none;border-radius:10px;padding:10px 14px;font-size:.84rem;margin-bottom:14px;"></div>
          <input type="hidden" id="fpToken" value="">
          <div style="margin-bottom:14px;">
            <label style="display:block;font-size:.82rem;font-weight:700;color:#222;margin-bottom:7px;">New Password</label>
            <div style="position:relative;">
              <input type="password" id="fpNewPass" placeholder="Min. 8 characters"
                style="width:100%;padding:12px 42px 12px 14px;background:#eef2f7;border:1.5px solid transparent;border-radius:11px;font-size:.92rem;color:#111;outline:none;font-family:inherit;"
                onfocus="this.style.borderColor='#27A457';this.style.background='#fff'"
                onblur="this.style.borderColor='transparent';this.style.background='#eef2f7'"
                oninput="checkPassStrength(this.value)">
              <i class="fa-regular fa-eye-slash" id="fpToggleNew" onclick="fpToggle('fpNewPass','fpToggleNew')"
                style="position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#ccc;cursor:pointer;"></i>
            </div>
            <div style="height:4px;background:#eee;border-radius:4px;margin-top:6px;overflow:hidden;">
              <div id="fpStrengthBar" style="height:100%;width:0;border-radius:4px;transition:width .3s,background .3s;"></div>
            </div>
            <div id="fpStrengthTxt" style="font-size:.73rem;color:#aaa;margin-top:3px;">Use letters and numbers.</div>
          </div>
          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:.82rem;font-weight:700;color:#222;margin-bottom:7px;">Confirm Password</label>
            <div style="position:relative;">
              <input type="password" id="fpConfPass" placeholder="Repeat password"
                style="width:100%;padding:12px 42px 12px 14px;background:#eef2f7;border:1.5px solid transparent;border-radius:11px;font-size:.92rem;color:#111;outline:none;font-family:inherit;"
                onfocus="this.style.borderColor='#27A457';this.style.background='#fff'"
                onblur="this.style.borderColor='transparent';this.style.background='#eef2f7'"
                onkeydown="if(event.key==='Enter')submitNewPass()">
              <i class="fa-regular fa-eye-slash" id="fpToggleConf" onclick="fpToggle('fpConfPass','fpToggleConf')"
                style="position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#ccc;cursor:pointer;"></i>
            </div>
          </div>
          <button onclick="submitNewPass()" id="fpBtn3"
            style="width:100%;padding:13px;background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;border:none;border-radius:12px;font-size:.93rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;">
            <i class="fas fa-check-circle"></i> Reset Password
          </button>
        </div>

      </div>
    </div>
    <div class="card-footer">
        &copy; <?php echo date('Y'); ?> Sinulom Falls &amp; Bolao Cold Spring Resort.<br>All rights reserved.
    </div>
</div>
<script>
const expectedCaptcha = <?php echo (int)$_SESSION['captcha']; ?>;
function togglePassword(){
    const p=document.getElementById('password'),icon=document.getElementById('toggleIcon'),show=p.type==='text';
    p.type=show?'password':'text';
    icon.className=(show?'fa-regular fa-eye-slash':'fa-regular fa-eye')+' fi';
}
function refreshCaptcha(){location.reload();}
const capInput=document.getElementById('captchaInput');
if(capInput){capInput.addEventListener('input',function(){this.classList.remove('ok','bad');if(!this.value.trim())return;this.classList.add(parseInt(this.value,10)===expectedCaptcha?'ok':'bad');});}
    // Force-clear any autofilled values on page load
    window.addEventListener('load', function() {
        setTimeout(function() {
            document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]').forEach(function(el) {
                if (!el.closest('[style*="display:none"]')) {
                    el.value = '';
                    el.setAttribute('readonly', true);
                    setTimeout(function() { el.removeAttribute('readonly'); }, 200);
                }
            });
        }, 100);
    });

function openForgot(e){
    e.preventDefault();
    document.getElementById('forgotOverlay').style.display='flex';
    document.getElementById('fpEmail').value='';
    document.getElementById('fpMsg1').style.display='none';
    document.getElementById('fpStep1').style.display='block';
    document.getElementById('fpStep2').style.display='none';
    document.getElementById('fpBtn1').disabled=false;
    document.getElementById('fpBtn1').innerHTML='<i class="fas fa-paper-plane"></i> Send OTP';
    setTimeout(()=>document.getElementById('fpEmail').focus(),100);
}
function closeForgot(){
    document.getElementById('forgotOverlay').style.display='none';
}
document.getElementById('forgotOverlay').addEventListener('click',function(e){
    if(e.target===this) closeForgot();
});
function backToEmail(){
    document.getElementById('fpStep2').style.display='none';
    document.getElementById('fpStep1').style.display='block';
    document.getElementById('fpMsg1').style.display='none';
}
function showMsg(id,text,type){
    const el=document.getElementById(id);
    el.textContent=text; el.style.display='block';
    if(type==='success'){el.style.background='#e8f5e9';el.style.color='#1B7D3A';el.style.border='1.5px solid #c8e6c9';}
    else{el.style.background='#fdecea';el.style.color='#c62828';el.style.border='1.5px solid #f5c6cb';}
}
let fpCurrentEmail='';
function sendOtp(){
    const email=document.getElementById('fpEmail').value.trim();
    const btn=document.getElementById('fpBtn1');
    if(!email){showMsg('fpMsg1','Please enter your email address.','error');return;}
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';
    const fd=new FormData(); fd.append('action','forgot_send_otp'); fd.append('email',email);
    fetch(window.location.href,{method:'POST',body:fd})
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            fpCurrentEmail=email;
            document.getElementById('fpOtpHint').textContent='OTP sent to '+email+'. Expires in 10 minutes.';
            document.getElementById('fpOtp').value='';
            document.getElementById('fpMsg2').style.display='none';
            document.getElementById('fpStep1').style.display='none';
            document.getElementById('fpStep2').style.display='block';
            setTimeout(()=>document.getElementById('fpOtp').focus(),100);
        } else {
            showMsg('fpMsg1',data.message,'error');
        }
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-paper-plane"></i> Send OTP';
    })
    .catch(()=>{
        showMsg('fpMsg1','Something went wrong. Please try again.','error');
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-paper-plane"></i> Send OTP';
    });
}
function resendOtp(e){
    e.preventDefault();
    document.getElementById('fpStep2').style.display='none';
    document.getElementById('fpStep1').style.display='block';
    document.getElementById('fpMsg1').style.display='none';
    sendOtp();
}
function verifyOtp(){
    const otp=document.getElementById('fpOtp').value.trim();
    const btn=document.getElementById('fpBtn2');
    if(otp.length!==6){showMsg('fpMsg2','Please enter the 6-digit OTP.','error');return;}
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Verifying...';
    const fd=new FormData(); fd.append('action','forgot_verify_otp'); fd.append('email',fpCurrentEmail); fd.append('otp',otp);
    fetch(window.location.href,{method:'POST',body:fd})
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            document.getElementById('fpToken').value=data.token;
            document.getElementById('fpStep2').style.display='none';
            document.getElementById('fpStep3').style.display='block';
            document.getElementById('fpMsg3').style.display='none';
            document.getElementById('fpNewPass').value='';
            document.getElementById('fpConfPass').value='';
            setTimeout(()=>document.getElementById('fpNewPass').focus(),100);
        } else {
            showMsg('fpMsg2',data.message,'error');
            btn.disabled=false;
            btn.innerHTML='<i class="fas fa-check-circle"></i> Verify OTP';
        }
    })
    .catch(()=>{
        showMsg('fpMsg2','Something went wrong. Please try again.','error');
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-check-circle"></i> Verify OTP';
    });
}
function fpToggle(inputId,iconId){
    const el=document.getElementById(inputId),icon=document.getElementById(iconId);
    const show=el.type==='text';
    el.type=show?'password':'text';
    icon.className=(show?'fa-regular fa-eye-slash':'fa-regular fa-eye');
}
function checkPassStrength(v){
    let s=0;
    if(v.length>=8)s++;
    if(/[A-Z]/.test(v))s++;
    if(/[0-9]/.test(v))s++;
    if(/[^A-Za-z0-9]/.test(v))s++;
    const bar=document.getElementById('fpStrengthBar'),txt=document.getElementById('fpStrengthTxt');
    const colors=['#e53935','#ff7043','#fdd835','#27A457'];
    const labels=['Too short','Weak','Fair','Strong'];
    bar.style.width=(s*25)+'%';
    bar.style.background=colors[Math.max(0,s-1)]||'#eee';
    txt.textContent=v.length?labels[Math.max(0,s-1)]:'Use letters and numbers.';
    txt.style.color=colors[Math.max(0,s-1)]||'#aaa';
}
function submitNewPass(){
    const token=document.getElementById('fpToken').value;
    const np=document.getElementById('fpNewPass').value;
    const cp=document.getElementById('fpConfPass').value;
    const btn=document.getElementById('fpBtn3');
    if(np.length<8){showMsg('fpMsg3','Password must be at least 8 characters.','error');return;}
    if(np!==cp){showMsg('fpMsg3','Passwords do not match.','error');return;}
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';
    const fd=new FormData();
    fd.append('action','forgot_reset_password');
    fd.append('token',token);
    fd.append('new_password',np);
    fd.append('confirm_password',cp);
    fetch(window.location.href,{method:'POST',body:fd})
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            showMsg('fpMsg3',data.message,'success');
            btn.style.display='none';
            setTimeout(()=>closeForgot(),2500);
        } else {
            showMsg('fpMsg3',data.message,'error');
            btn.disabled=false;
            btn.innerHTML='<i class="fas fa-check-circle"></i> Reset Password';
        }
    })
    .catch(()=>{
        showMsg('fpMsg3','Something went wrong. Please try again.','error');
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-check-circle"></i> Reset Password';
    });
}
</script>
</body>
</html>