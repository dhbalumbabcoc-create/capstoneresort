<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "dashboard.php");
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
        $stmt = $conn->prepare("SELECT id, password, role, first_name FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $login_id, $login_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    if (verify_password($password, $user['password'])) {
                        // Block guest accounts from staff login
                        if (($user['role'] ?? '') === 'guest') {
                            $error = 'Invalid username/email or password';
                        } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id']        = $user['id'];
                        $_SESSION['user_role']       = $user['role'];
                        $_SESSION['user_name']       = $user['first_name'];
                        $_SESSION['user_first_name'] = $user['first_name'];
                        $_SESSION['last_activity']   = time();
                        $_SESSION['username']        = $login_id;
                        log_audit_event($conn, 'login_success', 'Role: ' . $user['role']);
                        if (password_get_info($user['password'])['algo'] === null) {
                            $new_hash = hash_password($password);
                            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($upd) { $upd->bind_param("si", $new_hash, $user['id']); $upd->execute(); $upd->close(); }
                        }
                        unset($_SESSION['captcha']);
                        // First-time login: redirect to account setup
                        if (!empty($user['must_change_password'])) {
                            unset($_SESSION['setup_step'], $_SESSION['otp_sent'], $_SESSION['otp_verified']);
                            header("Location: " . BASE_URL . "staff_setup.php");
                        } else {
                            header("Location: " . BASE_URL . "dashboard.php");
                        }
                        exit();
                        }                    } else {
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
<title>Sign In — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    min-height: 100vh;
    background: url('<?php echo $base_img; ?>bolao.jpg') center/cover no-repeat fixed;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 40px 6vw;
    position: relative;
}

/* dark overlay over entire background */
body::before {
    content: '';
    position: fixed; inset: 0;
    background: rgba(0,0,0,.38);
    z-index: 0;
}

/* ── Welcome text — left side ── */
.welcome-text {
    position: fixed;
    left: 6vw;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    color: #fff;
    max-width: 340px;
}
.welcome-text h1 {
    font-size: clamp(2.2rem, 3.5vw, 3rem);
    font-weight: 800;
    line-height: 1.15;
    margin-bottom: 12px;
    text-shadow: 0 2px 16px rgba(0,0,0,.55);
    letter-spacing: -.3px;
}
.welcome-text p {
    font-size: .98rem;
    font-weight: 400;
    color: rgba(255,255,255,.82);
    line-height: 1.65;
    text-shadow: 0 1px 8px rgba(0,0,0,.4);
}

/* ── Login card ── */
.login-card {
    position: relative; z-index: 1;
    background: #fff;
    border-radius: 20px;
    padding: 40px 36px 32px;
    width: 420px;
    flex-shrink: 0;
    box-shadow: 0 8px 48px rgba(0,0,0,.28), 0 2px 8px rgba(0,0,0,.12);
}

/* Brand */
.brand {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 32px;
}
.brand img {
    width: 44px; height: 44px; border-radius: 12px;
    object-fit: cover;
    border: 2px solid #e8f5e9;
    box-shadow: 0 2px 8px rgba(27,125,58,.15);
}
.brand-text strong {
    display: block; font-size: .9rem; font-weight: 800; color: #111; line-height: 1.2;
}
.brand-text span { font-size: .75rem; color: #aaa; font-weight: 400; }

/* Card heading */
.card-heading { margin-bottom: 28px; }
.card-heading h2 { font-size: 1.6rem; font-weight: 800; color: #111; margin-bottom: 5px; line-height: 1.2; }
.card-heading p { font-size: .88rem; color: #aaa; }

/* Error */
.alert-error {
    background: #fdecea; color: #c62828;
    border: 1.5px solid #f5c6cb; border-radius: 12px;
    padding: 11px 14px; font-size: .84rem;
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 20px;
    animation: shake .35s ease;
}
@keyframes shake {
    0%,100%{transform:translateX(0)}
    20%{transform:translateX(-5px)}
    40%{transform:translateX(5px)}
    60%{transform:translateX(-3px)}
    80%{transform:translateX(3px)}
}

/* Fields */
.field { margin-bottom: 18px; }
.field label {
    display: flex; align-items: center; gap: 4px;
    font-size: .82rem; font-weight: 700; color: #222; margin-bottom: 8px;
}
.field label .req { color: #e53935; font-size: .75rem; }
.input-wrap { position: relative; }
.input-wrap input {
    width: 100%;
    padding: 13px 44px 13px 16px;
    background: #eef2f7;
    border: 1.5px solid transparent;
    border-radius: 12px;
    font-size: .92rem; color: #111;
    outline: none; font-family: inherit;
    transition: border-color .2s, background .2s, box-shadow .2s;
}
.input-wrap input:focus {
    background: #fff;
    border-color: #27A457;
    box-shadow: 0 0 0 3px rgba(39,164,87,.13);
}
.input-wrap input::placeholder { color: #bbc; }
.input-wrap .fi {
    position: absolute; right: 14px; top: 50%;
    transform: translateY(-50%);
    color: #ccc; font-size: .9rem; cursor: pointer;
    transition: color .2s;
}
.input-wrap .fi:hover { color: #27A457; }

/* Security row */
.sec-row {
    display: flex; align-items: center; gap: 8px;
    margin: 22px 0 12px;
    font-size: .72rem; font-weight: 700; color: #bbb;
    text-transform: uppercase; letter-spacing: 1px;
}
.sec-row i { color: #27A457; font-size: .82rem; }
.sec-row::after { content: ''; flex: 1; height: 1px; background: #efefef; }

/* CAPTCHA */
.captcha-row {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 26px;
}
.cap-num {
    background: #eef2f7;
    border: 1.5px solid #d0e8d8;
    border-radius: 12px;
    padding: 11px 18px;
    font-size: 1.15rem; font-weight: 800; color: #1B7D3A;
    min-width: 56px; text-align: center;
}
.cap-op { font-size: 1.1rem; color: #ccc; font-weight: 700; }
.cap-ans {
    width: 80px; padding: 11px 8px;
    background: #eef2f7;
    border: 1.5px solid transparent;
    border-radius: 12px;
    font-size: 1.1rem; text-align: center;
    outline: none; font-family: inherit; font-weight: 700;
    transition: border-color .2s, background .2s, color .2s;
}
.cap-ans:focus { background: #fff; border-color: #27A457; box-shadow: 0 0 0 3px rgba(39,164,87,.13); }
.cap-ans.ok  { border-color: #27A457; background: #e8f5e9; color: #1B7D3A; }
.cap-ans.bad { border-color: #e53935; background: #fdecea; color: #c62828; }
.cap-refresh {
    color: #ccc; cursor: pointer; font-size: 1rem;
    transition: color .2s, transform .4s; padding: 4px;
}
.cap-refresh:hover { color: #27A457; transform: rotate(180deg); }

/* Sign In button */
.btn-signin {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
    color: #fff; border: none; border-radius: 14px;
    font-size: 1rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 9px;
    font-family: inherit; letter-spacing: .3px;
    transition: all .25s;
    box-shadow: 0 4px 18px rgba(27,125,58,.35);
}
.btn-signin:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(27,125,58,.45); }
.btn-signin:active { transform: translateY(0); }

/* Forgot */
.forgot-link {
    display: block; text-align: center; margin-top: 18px;
    color: #27A457; font-size: .88rem; font-weight: 600;
    text-decoration: none; transition: opacity .2s;
}
.forgot-link:hover { opacity: .7; }

/* Footer */
.card-footer {
    margin-top: 32px; padding-top: 18px;
    border-top: 1px solid #f0f0f0;
    font-size: .73rem; color: #ccc; text-align: center;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 860px) {
    body { justify-content: center; padding: 24px 16px; }
    .welcome-text { display: none; }
    .login-card { width: 100%; max-width: 420px; }
}
@media (max-width: 480px) {
    .login-card { padding: 32px 20px 24px; }
}
</style>
</head>
<body>

<!-- Left: Welcome Back text over background -->
<div class="welcome-text">
    <h1>Welcome Back</h1>
    <p>Sign in to continue managing Sinulom and Bolao Cold Spring.</p>
</div>

<!-- Right: Login card -->
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

        <div class="field">
            <label>Username or Email <span class="req">*</span></label>
            <div class="input-wrap">
                <input type="text" name="email" placeholder="owner@resort.com" required autocomplete="username">
                <i class="fa-regular fa-user fi"></i>
            </div>
        </div>

        <div class="field">
            <label>Password <span class="req">*</span></label>
            <div class="input-wrap">
                <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                <i id="toggleIcon" class="fa-regular fa-eye-slash fi" onclick="togglePassword()" title="Show / hide"></i>
            </div>
        </div>

        <div class="sec-row">
            <i class="fas fa-shield-halved"></i> Security Verification
        </div>

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

        <a href="#" class="forgot-link">Forgot your password?</a>

    </form>

    <div class="card-footer">
        &copy; <?php echo date('Y'); ?> Sinulom Falls &amp; Bolao Cold Spring Resort.<br>All rights reserved.
    </div>

</div><!-- /.login-card -->

<script>
const expectedCaptcha = <?php echo (int)$_SESSION['captcha']; ?>;
function togglePassword() {
    const p = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    const show = p.type === 'text';
    p.type = show ? 'password' : 'text';
    icon.className = (show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye') + ' fi';
}
function refreshCaptcha() { location.reload(); }
const capInput = document.getElementById('captchaInput');
if (capInput) {
    capInput.addEventListener('input', function () {
        this.classList.remove('ok', 'bad');
        if (!this.value.trim()) return;
        this.classList.add(parseInt(this.value, 10) === expectedCaptcha ? 'ok' : 'bad');
    });
}
</script>
</body>
</html>
