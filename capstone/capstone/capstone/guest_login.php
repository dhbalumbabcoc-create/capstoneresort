<?php
require_once 'config/db_config.php';

// If already logged in as guest, go to dashboard
if (isset($_SESSION['guest_email']) && ($_SESSION['user_role'] ?? '') === 'guest') {
    header("Location: " . BASE_URL . "guest_dashboard.php");
    exit();
}

$base_img = BASE_URL . 'images/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest Login — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter','Segoe UI',system-ui,sans-serif;min-height:100vh;background-image:url('<?php echo $base_img; ?>bolao.jpg');background-size:cover;background-position:center;background-attachment:fixed;display:flex;align-items:center;justify-content:center;padding:40px 16px;position:relative;}
body::before{content:'';position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:0;}

/* Card */
.guest-card{position:relative;z-index:1;background:#fff;border-radius:20px;padding:40px 36px 32px;width:420px;flex-shrink:0;box-shadow:0 8px 48px rgba(0,0,0,.28),0 2px 8px rgba(0,0,0,.12);}

/* Brand */
.brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
.brand img{width:44px;height:44px;border-radius:12px;object-fit:cover;border:2px solid #e8f5e9;box-shadow:0 2px 8px rgba(27,125,58,.15);}
.brand-text strong{display:block;font-size:.9rem;font-weight:800;color:#111;line-height:1.2;}
.brand-text span{font-size:.75rem;color:#aaa;font-weight:400;}

/* Heading */
.card-heading{margin-bottom:24px;}
.card-heading h2{font-size:1.5rem;font-weight:800;color:#111;margin-bottom:5px;}
.card-heading p{font-size:.88rem;color:#aaa;}

/* Steps */
.step-indicator{display:flex;align-items:center;gap:8px;margin-bottom:24px;}
.step-dot{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0;transition:all .3s;}
.step-dot.active{background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;}
.step-dot.done{background:#e8f5e9;color:#1B7D3A;}
.step-dot.pending{background:#f0f0f0;color:#bbb;}
.step-line{flex:1;height:2px;background:#f0f0f0;transition:background .3s;}
.step-line.done{background:#27A457;}

/* Alert */
.alert-error{background:#fdecea;color:#c62828;border:1.5px solid #f5c6cb;border-radius:12px;padding:11px 14px;font-size:.84rem;display:flex;align-items:center;gap:8px;margin-bottom:18px;animation:shake .35s ease;}
.alert-success{background:#e8f5e9;color:#1B7D3A;border:1.5px solid #c8e6c9;border-radius:12px;padding:11px 14px;font-size:.84rem;display:flex;align-items:center;gap:8px;margin-bottom:18px;}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-5px)}40%{transform:translateX(5px)}60%{transform:translateX(-3px)}80%{transform:translateX(3px)}}

/* Fields */
.field{margin-bottom:16px;}
.field label{display:block;font-size:.82rem;font-weight:700;color:#222;margin-bottom:7px;}
.field label .req{color:#e53935;font-size:.75rem;}
.input-wrap{position:relative;}
.input-wrap input{width:100%;padding:13px 44px 13px 16px;background:#eef2f7;border:1.5px solid transparent;border-radius:12px;font-size:.92rem;color:#111;outline:none;font-family:inherit;transition:border-color .2s,background .2s,box-shadow .2s;}
.input-wrap input:focus{background:#fff;border-color:#27A457;box-shadow:0 0 0 3px rgba(39,164,87,.13);}
.input-wrap input::placeholder{color:#bbc;}
.input-wrap .fi{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#ccc;font-size:.9rem;}

/* OTP boxes */
.otp-row{display:flex;gap:8px;justify-content:center;margin-bottom:20px;}
.otp-box{width:50px;height:58px;border:2px solid #e0e0e0;border-radius:12px;font-size:1.5rem;font-weight:800;text-align:center;outline:none;font-family:inherit;color:#111;background:#eef2f7;transition:border-color .2s,box-shadow .2s;}
.otp-box:focus{border-color:#27A457;background:#fff;box-shadow:0 0 0 3px rgba(39,164,87,.13);}
.otp-box.filled{border-color:#27A457;background:#e8f5e9;color:#1B7D3A;}

/* Email hint */
.email-hint{background:#f0faf4;border-radius:10px;padding:11px 14px;font-size:.84rem;color:#555;margin-bottom:18px;display:flex;align-items:center;gap:10px;}
.email-hint i{color:#27A457;}

/* Buttons */
.btn-primary{width:100%;padding:14px;background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;border:none;border-radius:14px;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;font-family:inherit;transition:all .25s;box-shadow:0 4px 18px rgba(27,125,58,.35);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(27,125,58,.45);}
.btn-primary:active{transform:translateY(0);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-link-green{background:none;border:none;color:#27A457;font-size:.88rem;font-weight:600;cursor:pointer;font-family:inherit;padding:0;transition:opacity .2s;display:block;text-align:center;margin-top:14px;width:100%;}
.btn-link-green:hover{opacity:.7;}

/* Back link */
.back-link{display:flex;align-items:center;gap:6px;color:#888;font-size:.84rem;font-weight:600;text-decoration:none;margin-bottom:20px;transition:color .2s;}
.back-link:hover{color:#1B7D3A;}

/* Footer */
.card-footer-txt{margin-top:28px;padding-top:16px;border-top:1px solid #f0f0f0;font-size:.73rem;color:#ccc;text-align:center;line-height:1.6;}

/* Staff login link */
.staff-link{text-align:center;margin-top:16px;font-size:.82rem;color:#aaa;}
.staff-link a{color:#27A457;font-weight:600;text-decoration:none;}
.staff-link a:hover{text-decoration:underline;}

@media(max-width:480px){.guest-card{padding:32px 20px 24px;}}
</style>
</head>
<body>

<!-- Right: Guest login card -->
<div class="guest-card">

    <div class="brand">
        <img src="<?php echo $base_img; ?>logo.jpg" alt="Resort Logo">
        <div class="brand-text">
            <strong>Sinulom &amp; Bolao</strong>
            <span>Guest Portal</span>
        </div>
    </div>

    <!-- Step indicator -->
    <div class="step-indicator" id="stepIndicator">
        <div class="step-dot active" id="dot1">1</div>
        <div class="step-line" id="line1"></div>
        <div class="step-dot pending" id="dot2">2</div>
    </div>

    <!-- Alert area -->
    <div id="alertArea"></div>

    <!-- STEP 1: Guest info + email -->
    <div id="step1">
        <div class="card-heading">
            <h2>Guest Login</h2>
            <p>Enter your details to receive a verification code</p>
        </div>

        <div class="field">
            <label>Full Name <span class="req">*</span></label>
            <div class="input-wrap">
                <input type="text" id="guestName" placeholder="Juan dela Cruz" required>
                <i class="fa-regular fa-user fi"></i>
            </div>
        </div>
        <div class="field">
            <label>Contact Number <span class="req">*</span></label>
            <div class="input-wrap">
                <input type="tel" id="guestPhone" placeholder="09XXXXXXXXX" inputmode="numeric" maxlength="11" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)" required>
                <i class="fas fa-phone fi"></i>
            </div>
        </div>
        <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <div class="input-wrap">
                <input type="email" id="guestEmail" placeholder="you@email.com" required>
                <i class="fa-regular fa-envelope fi"></i>
            </div>
        </div>

        <button class="btn-primary" id="sendOtpBtn" onclick="sendOtp()">
            <i class="fas fa-paper-plane"></i> Send Verification Code
        </button>

        <div class="staff-link">
            Staff? <a href="<?php echo BASE_URL; ?>login.php">Sign in here</a>
        </div>
    </div>

    <!-- STEP 2: OTP verification -->
    <div id="step2" style="display:none;">
        <a href="#" class="back-link" onclick="goBack(); return false;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div class="card-heading">
            <h2>Enter OTP</h2>
            <p>Check your email for the 6-digit code</p>
        </div>

        <div class="email-hint" id="emailHint">
            <i class="fas fa-envelope"></i>
            <span>Code sent to <strong id="emailDisplay"></strong></span>
        </div>

        <div class="otp-row">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="o1" autocomplete="off">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="o2" autocomplete="off">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="o3" autocomplete="off">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="o4" autocomplete="off">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="o5" autocomplete="off">
            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="o6" autocomplete="off">
        </div>

        <button class="btn-primary" id="verifyBtn" onclick="verifyOtp()" disabled>
            <i class="fas fa-shield-halved"></i> Verify &amp; Login
        </button>

        <button class="btn-link-green" id="resendBtn" onclick="resendOtp()">
            <i class="fas fa-rotate-right me-1"></i> Resend Code
        </button>
    </div>

    <div class="card-footer-txt">
        &copy; <?php echo date('Y'); ?> Sinulom Falls &amp; Bolao Cold Spring Resort.<br>All rights reserved.
    </div>

</div><!-- /.guest-card -->

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
let currentEmail = '';

// ── Alert helpers ──
function showError(msg) {
    document.getElementById('alertArea').innerHTML =
        '<div class="alert-error"><i class="fas fa-exclamation-circle"></i>' + msg + '</div>';
}
function showSuccess(msg) {
    document.getElementById('alertArea').innerHTML =
        '<div class="alert-success"><i class="fas fa-check-circle"></i>' + msg + '</div>';
}
function clearAlert() {
    document.getElementById('alertArea').innerHTML = '';
}

// ── Step navigation ──
function goToStep2() {
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    document.getElementById('dot1').className = 'step-dot done';
    document.getElementById('dot1').innerHTML = '<i class="fas fa-check" style="font-size:.7rem;"></i>';
    document.getElementById('line1').className = 'step-line done';
    document.getElementById('dot2').className = 'step-dot active';
    document.getElementById('o1').focus();
}
function goBack() {
    clearAlert();
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    document.getElementById('dot1').className = 'step-dot active';
    document.getElementById('dot1').innerHTML = '1';
    document.getElementById('line1').className = 'step-line';
    document.getElementById('dot2').className = 'step-dot pending';
    // Clear OTP boxes
    ['o1','o2','o3','o4','o5','o6'].forEach(id => {
        const b = document.getElementById(id);
        b.value = ''; b.classList.remove('filled');
    });
    document.getElementById('verifyBtn').disabled = true;
}

// ── Send OTP ──
function sendOtp() {
    clearAlert();
    const name  = document.getElementById('guestName').value.trim();
    const phone = document.getElementById('guestPhone').value.trim();
    const email = document.getElementById('guestEmail').value.trim();

    if (!name)  { showError('Please enter your full name.'); return; }
    if (!/^\d{11}$/.test(phone)) { showError('Contact number must be exactly 11 digits.'); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('Please enter a valid email address.'); return; }

    const btn = document.getElementById('sendOtpBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    fetch(BASE_URL + 'send_guest_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(email) +
              '&guest_name=' + encodeURIComponent(name) +
              '&contact_number=' + encodeURIComponent(phone)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
        if (data.success) {
            currentEmail = email;
            document.getElementById('emailDisplay').textContent = email;
            goToStep2();
        } else {
            showError(data.error || data.message || 'Failed to send OTP. Please try again.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
        showError('Network error. Please try again.');
    });
}

// ── Verify OTP ──
function verifyOtp() {
    clearAlert();
    const otp = ['o1','o2','o3','o4','o5','o6'].map(id => document.getElementById(id).value).join('');
    if (otp.length < 6) { showError('Please enter the complete 6-digit code.'); return; }

    const btn = document.getElementById('verifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    fetch(BASE_URL + 'guest_verify_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(currentEmail) + '&otp=' + encodeURIComponent(otp)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccess('Verified! Redirecting to your dashboard...');
            setTimeout(() => { window.location.href = BASE_URL + 'guest_dashboard.php'; }, 1200);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shield-halved"></i> Verify &amp; Login';
            showError(data.message || 'Invalid or expired OTP. Please try again.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shield-halved"></i> Verify &amp; Login';
        showError('Network error. Please try again.');
    });
}

// ── Resend OTP ──
function resendOtp() {
    clearAlert();
    const name  = document.getElementById('guestName').value.trim();
    const phone = document.getElementById('guestPhone').value.trim();

    fetch(BASE_URL + 'send_guest_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(currentEmail) +
              '&guest_name=' + encodeURIComponent(name) +
              '&contact_number=' + encodeURIComponent(phone)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccess('New code sent to ' + currentEmail);
        } else {
            showError(data.error || 'Failed to resend code.');
        }
    });
}

// ── OTP box keyboard navigation ──
const otpBoxes = ['o1','o2','o3','o4','o5','o6'].map(id => document.getElementById(id));
otpBoxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        box.value = box.value.replace(/\D/g,'').slice(-1);
        box.classList.toggle('filled', box.value !== '');
        if (box.value && i < otpBoxes.length - 1) otpBoxes[i+1].focus();
        document.getElementById('verifyBtn').disabled =
            otpBoxes.some(b => !b.value);
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) otpBoxes[i-1].focus();
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const digits = e.clipboardData.getData('text').replace(/\D/g,'').slice(0,6);
        digits.split('').forEach((d,j) => {
            if (otpBoxes[j]) { otpBoxes[j].value = d; otpBoxes[j].classList.add('filled'); }
        });
        document.getElementById('verifyBtn').disabled = digits.length < 6;
    });
});

// Allow Enter key to submit
document.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
        if (document.getElementById('step1').style.display !== 'none') sendOtp();
        else verifyOtp();
    }
});
</script>
</body>
</html>
