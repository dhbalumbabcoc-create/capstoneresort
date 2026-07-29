<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['guest_email']) && !empty($_SESSION['guest_logged_in'])) {
    header("Location: " . BASE_URL . "guest_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Guest Login — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;1,600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gd:#1a3d2b;--gm:#2d5a3d;--gl:#4a7c59;--cream:#f5f0e8;--txt:#1a1a1a;--muted:#6b7280;--border:#e2ddd5;--red:#c62828}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;background:var(--cream);}

/* ── Left panel ── */
.gl-left{
  flex:0 0 50%;width:50%;position:relative;overflow:hidden;
  background:var(--gd);
}
.gl-left-img{
  position:absolute;inset:0;
  background:url('images/login.jpg') center/cover no-repeat;
  opacity:.55;
}
.gl-left-overlay{
  position:absolute;inset:0;
  background:linear-gradient(to bottom,rgba(26,61,43,.3) 0%,rgba(26,61,43,.75) 100%);
}
.gl-left-content{
  position:relative;z-index:2;height:100%;
  display:flex;flex-direction:column;justify-content:space-between;
  padding:28px 36px 32px;
}
.gl-brand{display:flex;align-items:center;gap:12px;}
.gl-brand img{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.3);}
.gl-brand-txt strong{display:block;color:#fff;font-size:.9rem;font-weight:700;line-height:1.2;}
.gl-brand-txt span{font-size:.72rem;color:rgba(255,255,255,.65);}
.gl-tagline{margin-bottom:40px;}
.gl-tagline h1{font-family:'Playfair Display',serif;font-size:2.4rem;font-weight:800;color:#fff;line-height:1.2;margin-bottom:12px;}
.gl-tagline h1 em{font-style:italic;color:#86efac;}
.gl-tagline p{font-size:.9rem;color:rgba(255,255,255,.75);line-height:1.7;max-width:340px;}
.gl-footer{font-size:.72rem;color:rgba(255,255,255,.4);}

/* ── Right panel ── */
.gl-right{
  flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:clamp(24px, 4vh, 48px) clamp(24px, 4vw, 56px);background:var(--cream);overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.15) transparent;
}
.gl-right::-webkit-scrollbar{width:4px;}
.gl-right::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:4px;}
#step1,#step2{
  width:100%;max-width:400px;
}
.gl-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:.84rem;font-weight:600;text-decoration:none;margin-bottom:20px;transition:color .2s;}
.gl-back:hover{color:var(--gd);}
.gl-heading{margin-bottom:20px;}
.gl-heading h2{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:800;color:var(--txt);margin-bottom:4px;}
.gl-heading p{font-size:.84rem;color:var(--muted);}

/* ── Form fields ── */
.gl-field{margin-bottom:12px;}
.gl-field label{display:block;font-size:.78rem;font-weight:600;color:var(--txt);margin-bottom:5px;}
.gl-input-wrap{position:relative;}
.gl-input-wrap input{
  width:100%;padding:10px 40px 10px 13px;
  background:#fff;border:1.5px solid var(--border);border-radius:9px;
  font-size:.88rem;color:var(--txt);outline:none;font-family:'Inter',sans-serif;
  transition:border-color .2s,box-shadow .2s;
}
.gl-input-wrap input:focus{border-color:var(--gd);box-shadow:0 0 0 3px rgba(26,61,43,.1);}
.gl-input-wrap input::placeholder{color:#bbb;}
.gl-input-wrap .gl-icon{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#ccc;font-size:.85rem;pointer-events:none;}
.gl-input-wrap .gl-icon-btn{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#aaa;font-size:.85rem;padding:4px;line-height:1;}
.gl-input-wrap .gl-icon-btn:hover{color:var(--gd);}

/* ── Alert ── */
.gl-alert{border-radius:9px;padding:9px 12px;font-size:.82rem;display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.gl-alert.err{background:#fdecea;color:var(--red);border:1.5px solid #f5c6cb;}
.gl-alert.ok{background:#e8f5e9;color:var(--gd);border:1.5px solid #c8e6c9;}

/* ── OTP boxes ── */
.gl-otp-row{display:flex;gap:10px;justify-content:flex-start;margin-bottom:16px;}
.gl-otp-box{
  width:48px;height:54px;border:1.5px solid var(--border);border-radius:10px;
  font-size:1.35rem;font-weight:800;text-align:center;outline:none;
  font-family:'Inter',sans-serif;color:var(--txt);background:#fff;
  transition:border-color .2s,box-shadow .2s;
}
.gl-otp-box:focus{border-color:var(--gd);box-shadow:0 0 0 3px rgba(26,61,43,.1);}
.gl-otp-box.filled{border-color:var(--gd);background:#f0faf4;color:var(--gd);}

/* ── Email hint ── */
.gl-email-hint{font-size:.82rem;color:var(--muted);margin-bottom:16px;line-height:1.5;}
.gl-email-hint strong{color:var(--txt);}

/* ── Resend ── */
.gl-resend{font-size:.82rem;color:var(--muted);margin-top:10px;}
.gl-resend a,.gl-resend button{color:var(--gd);font-weight:600;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:.82rem;padding:0;text-decoration:none;}
.gl-resend a:hover,.gl-resend button:hover{text-decoration:underline;}

/* ── Button ── */
.gl-btn{
  width:100%;padding:11px;background:var(--gd);color:#fff;border:none;
  border-radius:50px;font-size:.9rem;font-weight:700;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  font-family:'Inter',sans-serif;transition:all .25s;
  box-shadow:0 4px 14px rgba(26,61,43,.25);
}
.gl-btn:hover{background:var(--gm);transform:translateY(-1px);}
.gl-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}

/* ── Step panels ── */
#step2,#step_forgot_email,#step_forgot_otp,#step_forgot_reset{display:none;}
.strength-bar{height:4px;border-radius:4px;background:#eee;margin-top:6px;overflow:hidden;}
.strength-fill{height:100%;border-radius:4px;transition:width .3s,background .3s;width:0;}

@media(max-width:900px){
  .gl-left{display:none;}
  .gl-right{padding:40px 28px;}
}
@media(max-width:480px){
  .gl-row{grid-template-columns:1fr;}
  .gl-otp-box{width:42px;height:52px;font-size:1.2rem;}
}
</style>
</head>
<body>

<!-- Left panel -->
<div class="gl-left">
  <div class="gl-left-img"></div>
  <div class="gl-left-overlay"></div>
  <div class="gl-left-content">
    <div class="gl-brand">
      <img src="images/logo.jpg" alt="Logo">
      <div class="gl-brand-txt">
        <strong>Sinulom &amp; Bolao</strong>
        <span>Cold Spring Resort</span>
      </div>
    </div>
    <div class="gl-tagline">
      <h1>Your Natural<br><em>Sanctuary Awaits</em></h1>
      <p>Sign in to manage your bookings, access exclusive deals, and plan your perfect getaway at our cold spring resort.</p>
    </div>
    <div class="gl-footer">&copy; <?php echo date('Y'); ?> Sinulom and Bolao Cold Spring Resort.</div>
  </div>
</div>

<!-- Right panel -->
<div class="gl-right">

  <!-- STEP 1: Email + Password -->
  <div id="step1">
    <a href="landing.php" class="gl-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
    <div class="gl-heading">
      <h2>Guest Login</h2>
      <p>Use the <strong>email</strong> and <strong>password</strong> you created when you made your booking.</p>
    </div>
    <div id="alertArea1"></div>
    <div class="gl-field">
      <label>Email Address</label>
      <div class="gl-input-wrap">
        <input type="email" id="guestEmail" placeholder="you@example.com" autocomplete="email">
        <i class="fas fa-envelope gl-icon"></i>
      </div>
    </div>
    <div class="gl-field">
      <label>Password</label>
      <div class="gl-input-wrap">
        <input type="password" id="guestPassword" placeholder="Enter your password" autocomplete="current-password">
        <button type="button" class="gl-icon-btn" id="togglePwBtn" onclick="togglePw()" tabindex="-1"><i class="fas fa-eye" id="eyeIcon"></i></button>
      </div>
      <div style="text-align: right; margin-top: 6px;">
        <a href="#" onclick="showForgotEmail(); return false;" style="color: var(--gd); font-size: 0.82rem; font-weight: 600; text-decoration: none;">Forgot password?</a>
      </div>
    </div>
    <button class="gl-btn" id="sendOtpBtn" onclick="sendOtp()">
      <i class="fas fa-arrow-right"></i> Continue
    </button>
  </div>

  <!-- STEP 2: OTP Verify -->
  <div id="step2">
    <a href="#" class="gl-back" onclick="goBack();return false;"><i class="fas fa-arrow-left"></i> Back to Login</a>
    <div class="gl-heading">
      <h2>Verify Identity</h2>
      <p class="gl-email-hint">We sent a 6-digit code to<br><strong id="emailDisplay"></strong></p>
    </div>
    <div id="alertArea2"></div>
    <div class="gl-otp-row">
      <input class="gl-otp-box" id="o1" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
      <input class="gl-otp-box" id="o2" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
      <input class="gl-otp-box" id="o3" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
      <input class="gl-otp-box" id="o4" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
      <input class="gl-otp-box" id="o5" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
      <input class="gl-otp-box" id="o6" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
    </div>
    <div class="gl-resend">
      Didn't receive the code? <button id="resendBtn" onclick="resendOtp()" disabled>Resend in <span id="resendTimer">30</span>s</button>
    </div>
    <button class="gl-btn mt-3" id="verifyBtn" onclick="verifyOtp()" disabled style="margin-top:20px;">
      <i class="fas fa-shield-halved"></i> Verify &amp; Login
    </button>
  </div>

  <!-- FORGOT PASSWORD STEP 1: Enter Email -->
  <div id="step_forgot_email">
    <a href="#" class="gl-back" onclick="hideForgotEmail(); return false;"><i class="fas fa-arrow-left"></i> Back to Login</a>
    <div class="gl-heading">
      <h2>Forgot Password</h2>
      <p>Enter your email address to receive a 6-digit verification code</p>
    </div>
    <div id="alertAreaForgotEmail"></div>
    <div class="gl-field">
      <label>Email Address</label>
      <div class="gl-input-wrap">
        <input type="email" id="forgotEmail" placeholder="you@example.com">
        <i class="fas fa-envelope gl-icon"></i>
      </div>
    </div>
    <button class="gl-btn" id="forgotSendOtpBtn" onclick="sendForgotOtp()">
      <i class="fas fa-paper-plane"></i> Send Verification Code
    </button>
  </div>

  <!-- FORGOT PASSWORD STEP 2: Verify OTP -->
  <div id="step_forgot_otp">
    <a href="#" class="gl-back" onclick="backToForgotEmail(); return false;"><i class="fas fa-arrow-left"></i> Back</a>
    <div class="gl-heading">
      <h2>Verify Code</h2>
      <p class="gl-email-hint">We sent a 6-digit verification code to<br><strong id="forgotEmailDisplay"></strong></p>
    </div>
    <div id="alertAreaForgotOtp"></div>
    <div class="gl-field">
      <label>6-Digit Code</label>
      <div class="gl-input-wrap">
        <input type="text" id="forgotOtp" placeholder="_ _ _ _ _ _" maxlength="6" inputmode="numeric"
          oninput="this.value=this.value.replace(/\D/g,'').slice(0,6); checkForgotOtpLength();"
          style="width:100%;padding:14px;background:#fff;border:1.5px solid var(--border);border-radius:10px;font-size:1.6rem;font-weight:800;color:var(--gd);text-align:center;letter-spacing:10px;outline:none;font-family:inherit;">
      </div>
    </div>
    <div class="gl-resend">
      Didn't receive the code? <a href="#" onclick="resendForgotOtp(); return false;" id="resendForgotBtn">Resend Code</a>
    </div>
    <button class="gl-btn" id="forgotVerifyOtpBtn" onclick="verifyForgotOtp()" disabled style="margin-top:20px;">
      <i class="fas fa-shield-halved"></i> Verify Code
    </button>
  </div>

  <!-- FORGOT PASSWORD STEP 3: Reset Password -->
  <div id="step_forgot_reset">
    <div class="gl-heading">
      <h2>New Password</h2>
      <p>Set a new secure password for your account</p>
    </div>
    <div id="alertAreaForgotReset"></div>
    <input type="hidden" id="forgotToken" value="">
    <div class="gl-field">
      <label>New Password</label>
      <div class="gl-input-wrap">
        <input type="password" id="forgotNewPassword" placeholder="Min. 8 characters" oninput="checkForgotStrength(this.value)">
        <button type="button" class="gl-icon-btn" id="toggleForgotNewBtn" onclick="toggleForgotPass('forgotNewPassword','eyeIconForgotNew')" tabindex="-1"><i class="fas fa-eye" id="eyeIconForgotNew"></i></button>
      </div>
      <div class="strength-bar"><div class="strength-fill" id="forgotStrengthFill"></div></div>
      <div class="gl-email-hint" id="forgotStrengthText" style="margin-top:6px; margin-bottom:0;">Use at least 8 characters with letters and numbers.</div>
    </div>
    <div class="gl-field">
      <label>Confirm New Password</label>
      <div class="gl-input-wrap">
        <input type="password" id="forgotConfirmPassword" placeholder="Repeat password">
        <button type="button" class="gl-icon-btn" id="toggleForgotConfBtn" onclick="toggleForgotPass('forgotConfirmPassword','eyeIconForgotConf')" tabindex="-1"><i class="fas fa-eye" id="eyeIconForgotConf"></i></button>
      </div>
    </div>
    <button class="gl-btn" id="forgotResetBtn" onclick="submitForgotNewPass()">
      <i class="fas fa-lock"></i> Set New Password
    </button>
  </div>

</div><!-- /gl-right -->

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
let currentEmail = '';
let resendInterval = null;

function showError(msg, step) {
    const id = step===2 ? 'alertArea2' : 'alertArea1';
    document.getElementById(id).innerHTML = '<div class="gl-alert err"><i class="fas fa-exclamation-circle"></i>' + msg + '</div>';
}
function showSuccess(msg, step) {
    const id = step===2 ? 'alertArea2' : 'alertArea1';
    document.getElementById(id).innerHTML = '<div class="gl-alert ok"><i class="fas fa-check-circle"></i>' + msg + '</div>';
}
function clearAlert(step) { document.getElementById(step===2?'alertArea2':'alertArea1').innerHTML=''; }

function goToStep2() {
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    document.getElementById('o1').focus();
    startResendTimer();
}
function goBack() {
    clearAlert(2);
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    ['o1','o2','o3','o4','o5','o6'].forEach(id => { const b=document.getElementById(id); b.value=''; b.classList.remove('filled'); });
    document.getElementById('verifyBtn').disabled = true;
    if(resendInterval) clearInterval(resendInterval);
}

function startResendTimer() {
    let t = 30;
    const btn = document.getElementById('resendBtn');
    const span = document.getElementById('resendTimer');
    btn.disabled = true;
    span.textContent = t;
    resendInterval = setInterval(() => {
        t--;
        span.textContent = t;
        if(t <= 0) {
            clearInterval(resendInterval);
            btn.disabled = false;
            btn.textContent = 'Resend';
        }
    }, 1000);
}

function togglePw() {
  const inp = document.getElementById('guestPassword');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type = 'text'; ico.classList.replace('fa-eye','fa-eye-slash'); }
  else { inp.type = 'password'; ico.classList.replace('fa-eye-slash','fa-eye'); }
}

function sendOtp() {
    clearAlert(1);
    const email = document.getElementById('guestEmail').value.trim();
    const password = document.getElementById('guestPassword').value;
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('Please enter a valid email address.', 1); return; }
    if (!password) { showError('Please enter your password.', 1); return; }

    const btn = document.getElementById('sendOtpBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    // Step 1: verify email + password, then send OTP
    fetch(BASE_URL + 'verify_guest_password.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(email) + '&password=' + encodeURIComponent(password)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continue';
            showError(data.error || 'Invalid email or password.', 1);
            return;
        }
        // Password ok — now send OTP
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';
        const guestName = (data.guest_name && data.guest_name.trim()) ? data.guest_name.trim() : 'Guest';
        return fetch(BASE_URL + 'send_guest_otp.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'email=' + encodeURIComponent(email) + '&guest_name=' + encodeURIComponent(guestName) + '&contact_number=09000000000'
        });
    })
    .then(r => r ? r.json() : null)
    .then(data => {
        if (!data) return;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continue';
        if (data.success) {
            currentEmail = document.getElementById('guestEmail').value.trim();
            document.getElementById('emailDisplay').textContent = currentEmail;
            goToStep2();
        } else {
            showError(data.error || data.message || 'Failed to send code. Please try again.', 1);
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continue';
        showError('Network error. Please try again.', 1);
    });
}

function resendOtp() {
    clearAlert(2);
    const email = currentEmail;
    fetch(BASE_URL + 'send_guest_otp.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(email) + '&guest_name=Guest&contact_number=09000000000'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showSuccess('A new code has been sent.', 2); startResendTimer(); }
        else showError(data.error || 'Failed to resend.', 2);
    })
    .catch(() => showError('Network error.', 2));
}

function verifyOtp() {
    clearAlert(2);
    const otp = ['o1','o2','o3','o4','o5','o6'].map(id => document.getElementById(id).value).join('');
    if (otp.length < 6) { showError('Please enter the complete 6-digit code.', 2); return; }
    const btn = document.getElementById('verifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    fetch(BASE_URL + 'guest_verify_otp.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(currentEmail) + '&otp=' + encodeURIComponent(otp)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccess('Verified! Redirecting...', 2);
            setTimeout(() => { window.location.href = BASE_URL + 'guest_dashboard.php'; }, 1000);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shield-halved"></i> Verify &amp; Login';
            showError(data.message || 'Invalid or expired code.', 2);
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shield-halved"></i> Verify &amp; Login';
        showError('Network error.', 2);
    });
}

/* OTP box navigation */
document.querySelectorAll('.gl-otp-box').forEach((box, i, boxes) => {
    box.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g,'').slice(-1);
        this.classList.toggle('filled', this.value !== '');
        if (this.value && i < boxes.length - 1) boxes[i+1].focus();
        const all = [...boxes].every(b => b.value !== '');
        document.getElementById('verifyBtn').disabled = !all;
    });
    box.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && i > 0) { boxes[i-1].focus(); boxes[i-1].value=''; boxes[i-1].classList.remove('filled'); }
    });
    box.addEventListener('paste', function(e) {
        e.preventDefault();
        const txt = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        txt.split('').forEach((c,j) => { if(boxes[j]){ boxes[j].value=c; boxes[j].classList.add('filled'); } });
        const all = [...boxes].every(b => b.value !== '');
        document.getElementById('verifyBtn').disabled = !all;
        if(txt.length < 6 && boxes[txt.length]) boxes[txt.length].focus();
    });
});

/* ── Forgot Password Client Logic ── */
let forgotEmail = '';

function showForgotError(msg, step) {
    let id = '';
    if (step === 'email') id = 'alertAreaForgotEmail';
    else if (step === 'otp') id = 'alertAreaForgotOtp';
    else if (step === 'reset') id = 'alertAreaForgotReset';
    document.getElementById(id).innerHTML = '<div class="gl-alert err"><i class="fas fa-exclamation-circle"></i>' + msg + '</div>';
}

function showForgotSuccess(msg, step) {
    let id = '';
    if (step === 'email') id = 'alertAreaForgotEmail';
    else if (step === 'otp') id = 'alertAreaForgotOtp';
    else if (step === 'reset') id = 'alertAreaForgotReset';
    document.getElementById(id).innerHTML = '<div class="gl-alert ok"><i class="fas fa-check-circle"></i>' + msg + '</div>';
}

function clearForgotAlert(step) {
    let id = '';
    if (step === 'email') id = 'alertAreaForgotEmail';
    else if (step === 'otp') id = 'alertAreaForgotOtp';
    else if (step === 'reset') id = 'alertAreaForgotReset';
    const el = document.getElementById(id);
    if (el) el.innerHTML = '';
}

function showForgotEmail() {
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step_forgot_email').style.display = 'block';
    document.getElementById('forgotEmail').value = '';
    clearForgotAlert('email');
    document.getElementById('forgotEmail').focus();
}

function hideForgotEmail() {
    document.getElementById('step_forgot_email').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
}

function backToForgotEmail() {
    document.getElementById('step_forgot_otp').style.display = 'none';
    document.getElementById('step_forgot_email').style.display = 'block';
    clearForgotAlert('email');
}

function checkForgotOtpLength() {
    const otp = document.getElementById('forgotOtp').value.trim();
    document.getElementById('forgotVerifyOtpBtn').disabled = (otp.length !== 6);
}

function sendForgotOtp() {
    clearForgotAlert('email');
    const email = document.getElementById('forgotEmail').value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showForgotError('Please enter a valid email address.', 'email');
        return;
    }

    const btn = document.getElementById('forgotSendOtpBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    fetch(BASE_URL + 'guest_forgot_handler.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=forgot_send_otp&email=' + encodeURIComponent(email)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
        if (data.success) {
            forgotEmail = email;
            document.getElementById('forgotEmailDisplay').textContent = email;
            document.getElementById('step_forgot_email').style.display = 'none';
            document.getElementById('step_forgot_otp').style.display = 'block';
            document.getElementById('forgotOtp').value = '';
            document.getElementById('forgotVerifyOtpBtn').disabled = true;
            clearForgotAlert('otp');
            document.getElementById('forgotOtp').focus();
        } else {
            showForgotError(data.message || 'Failed to send verification code.', 'email');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
        showForgotError('Network error. Please try again.', 'email');
    });
}

function resendForgotOtp() {
    clearForgotAlert('otp');
    const btn = document.getElementById('resendForgotBtn');
    const originalText = btn.textContent;
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.5';
    btn.textContent = 'Sending...';

    fetch(BASE_URL + 'guest_forgot_handler.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=forgot_send_otp&email=' + encodeURIComponent(forgotEmail)
    })
    .then(r => r.json())
    .then(data => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        btn.textContent = originalText;
        if (data.success) {
            showForgotSuccess('A new verification code has been sent.', 'otp');
        } else {
            showForgotError(data.message || 'Failed to resend code.', 'otp');
        }
    })
    .catch(() => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        btn.textContent = originalText;
        showForgotError('Network error.', 'otp');
    });
}

function verifyForgotOtp() {
    clearForgotAlert('otp');
    const otp = document.getElementById('forgotOtp').value.trim();
    if (otp.length !== 6) {
        showForgotError('Please enter the 6-digit code.', 'otp');
        return;
    }

    const btn = document.getElementById('forgotVerifyOtpBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    fetch(BASE_URL + 'guest_forgot_handler.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=forgot_verify_otp&email=' + encodeURIComponent(forgotEmail) + '&otp=' + encodeURIComponent(otp)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shield-halved"></i> Verify Code';
        if (data.success) {
            document.getElementById('forgotToken').value = data.token;
            document.getElementById('step_forgot_otp').style.display = 'none';
            document.getElementById('step_forgot_reset').style.display = 'block';
            document.getElementById('forgotNewPassword').value = '';
            document.getElementById('forgotConfirmPassword').value = '';
            checkForgotStrength('');
            clearForgotAlert('reset');
            document.getElementById('forgotNewPassword').focus();
        } else {
            showForgotError(data.message || 'Invalid or expired code.', 'otp');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shield-halved"></i> Verify Code';
        showForgotError('Network error.', 'otp');
    });
}

function toggleForgotPass(inputId, iconId) {
    const el = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (el.type === 'password') {
        el.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        el.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkForgotStrength(v) {
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    
    const fill = document.getElementById('forgotStrengthFill');
    const txt = document.getElementById('forgotStrengthText');
    const colors = ['#e53935', '#ff7043', '#fdd835', '#27A457'];
    const labels = ['Too short', 'Weak', 'Fair', 'Strong'];
    
    fill.style.width = (score * 25) + '%';
    fill.style.background = colors[Math.max(0, score - 1)] || '#eee';
    txt.textContent = v.length ? labels[Math.max(0, score - 1)] : 'Use at least 8 characters with letters and numbers.';
    txt.style.color = v.length ? colors[Math.max(0, score - 1)] : 'var(--muted)';
}

function submitForgotNewPass() {
    clearForgotAlert('reset');
    const token = document.getElementById('forgotToken').value;
    const newPass = document.getElementById('forgotNewPassword').value;
    const confPass = document.getElementById('forgotConfirmPassword').value;

    if (newPass.length < 8) {
        showForgotError('Password must be at least 8 characters.', 'reset');
        return;
    }
    if (newPass !== confPass) {
        showForgotError('Passwords do not match.', 'reset');
        return;
    }
    if (!preg_match_letters_numbers(newPass)) {
        showForgotError('Password must contain at least one letter and one number.', 'reset');
        return;
    }

    const btn = document.getElementById('forgotResetBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';

    fetch(BASE_URL + 'guest_forgot_handler.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=forgot_reset_password&token=' + encodeURIComponent(token) + '&new_password=' + encodeURIComponent(newPass) + '&confirm_password=' + encodeURIComponent(confPass)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showForgotSuccess('Password reset successfully! Redirecting to login...', 'reset');
            setTimeout(() => {
                document.getElementById('step_forgot_reset').style.display = 'none';
                document.getElementById('step1').style.display = 'block';
                document.getElementById('guestEmail').value = forgotEmail;
                document.getElementById('guestPassword').value = '';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock"></i> Set New Password';
                clearAlert(1);
            }, 2500);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> Set New Password';
            showForgotError(data.message || 'Failed to reset password.', 'reset');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Set New Password';
        showForgotError('Network error.', 'reset');
    });
}

function preg_match_letters_numbers(str) {
    return /[A-Za-z]/.test(str) && /[0-9]/.test(str);
}
</script>
</body>
</html>