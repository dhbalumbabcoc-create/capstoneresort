<?php
date_default_timezone_set('Asia/Manila');
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config/db_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// ── Ensure guest_password_resets table exists ──────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `guest_password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `guest_id` INT NOT NULL,
    `otp` VARCHAR(6) NOT NULL DEFAULT '',
    `token` VARCHAR(64) DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_guest_id` (`guest_id`),
    KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add otp column just in case table exists without it
$conn->query("ALTER TABLE `guest_password_resets` ADD COLUMN IF NOT EXISTS `otp` VARCHAR(6) NOT NULL DEFAULT '' AFTER `guest_id`");

$action = $_POST['action'];

// ── Action 1: Send Forgot Password OTP ───────────────────────────────────────
if ($action === 'forgot_send_otp') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit();
    }

    // Look up email in guest_accounts
    $stmt = $conn->prepare("SELECT id, full_name FROM guest_accounts WHERE email = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database query failed.']);
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $bstmt = $conn->prepare("SELECT guest_name, guest_phone FROM bookings WHERE guest_email = ? ORDER BY id DESC LIMIT 1");
        if ($bstmt) {
            $bstmt->bind_param("s", $email);
            $bstmt->execute();
            $brow = $bstmt->get_result()->fetch_assoc();
            $bstmt->close();
            if ($brow) {
                $g_name = !empty($brow['guest_name']) ? $brow['guest_name'] : 'Guest';
                $g_phone = !empty($brow['guest_phone']) ? $brow['guest_phone'] : '';
                $temp_hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO guest_accounts (email, password_hash, full_name, phone) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)");
                if ($ins) {
                    $ins->bind_param("ssss", $email, $temp_hash, $g_name, $g_phone);
                    $ins->execute();
                    $ins->close();
                    $stmt2 = $conn->prepare("SELECT id, full_name FROM guest_accounts WHERE email = ? LIMIT 1");
                    $stmt2->bind_param("s", $email);
                    $stmt2->execute();
                    $row = $stmt2->get_result()->fetch_assoc();
                    $stmt2->close();
                }
            }
        }
    }

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No account or booking found with that email address.']);
        exit();
    }

    $guest_id  = (int)$row['id'];
    $full_name = $row['full_name'];
    $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires   = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Invalidate old OTPs for this guest
    $del = $conn->prepare("UPDATE guest_password_resets SET used=1 WHERE guest_id=? AND used=0");
    if ($del) {
        $del->bind_param("i", $guest_id);
        $del->execute();
        $del->close();
    }

    // Insert new OTP
    $ins = $conn->prepare("INSERT INTO guest_password_resets (guest_id, otp, expires_at) VALUES (?,?,?)");
    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'Failed to save reset OTP.']);
        exit();
    }
    $ins->bind_param("iss", $guest_id, $otp, $expires);
    $ins->execute();
    $ins->close();

    // Send OTP email using PHPMailer
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bucod.lyngemae123@gmail.com';
        $mail->Password   = 'mrih mgfp uaaq uagi'; // Gmail app password used for guests
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $logo_path = __DIR__ . '/images/logo.jpg';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'resort_logo', 'logo.jpg');
        }
        $mail->setFrom('bucod.lyngemae123@gmail.com', 'Sinulom Falls and Bolao Cold Spring');
        $mail->addAddress($email, $full_name);
        $mail->isHTML(true);
        $mail->Subject = 'Your Guest Password Reset OTP — Sinulom & Bolao Resort';
        
        $mail->Body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);">
  <div style="background:#1a3d2b;padding:28px 32px;text-align:center;">
    <div style="margin-bottom:12px;">
      <img src="cid:resort_logo" alt="Sinulom Falls &amp; Bolao Cold Spring Resort" style="width:75px;height:75px;object-fit:cover;border-radius:50%;border:3px solid #ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:#ffffff;display:inline-block;">
    </div>
    <h1 style="color:#fff;font-size:20px;margin:0;">Password Reset OTP</h1>
    <p style="color:rgba(255,255,255,.7);font-size:13px;margin:6px 0 0;">Sinulom Falls &amp; Bolao Cold Spring Resort</p>
  </div>
  <div style="padding:32px;">
    <p style="font-size:15px;color:#1a1a1a;">Hi <strong>' . htmlspecialchars($full_name) . '</strong>,</p>
    <p style="font-size:14px;color:#6b7280;line-height:1.6;margin:12px 0 24px;">Use the OTP below to reset your guest account password. It expires in <strong>10 minutes</strong>.</p>
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
        error_log("Guest OTP email failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
    }
    exit();
}

// ── Action 2: Verify Forgot Password OTP ─────────────────────────────────────
if ($action === 'forgot_verify_otp') {
    $email = trim($_POST['email'] ?? '');
    $otp   = trim($_POST['otp']   ?? '');

    if (empty($email) || strlen($otp) !== 6) {
        echo json_encode(['success' => false, 'message' => 'Please enter the 6-digit OTP.']);
        exit();
    }

    // Look up guest ID
    $stmt = $conn->prepare("SELECT id FROM guest_accounts WHERE email = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit();
    }

    $guest_id = $row['id'];
    $now = date('Y-m-d H:i:s');

    // Verify OTP
    $vstmt = $conn->prepare("SELECT id FROM guest_password_resets WHERE guest_id=? AND otp=? AND used=0 AND expires_at > ? ORDER BY id DESC LIMIT 1");
    if (!$vstmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }
    $vstmt->bind_param("iss", $guest_id, $otp, $now);
    $vstmt->execute();
    $vrow = $vstmt->get_result()->fetch_assoc();
    $vstmt->close();

    if (!$vrow) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try again.']);
        exit();
    }

    // Generate a short-lived token for password reset step
    $token   = bin2hex(random_bytes(32));
    $tok_exp = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $upd = $conn->prepare("UPDATE guest_password_resets SET token=?, expires_at=? WHERE id=?");
    if ($upd) {
        $upd->bind_param("ssi", $token, $tok_exp, $vrow['id']);
        $upd->execute();
        $upd->close();
    }

    echo json_encode(['success' => true, 'token' => $token]);
    exit();
}

// ── Action 3: Reset Guest Password ───────────────────────────────────────────
if ($action === 'forgot_reset_password') {
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
    $tstmt = $conn->prepare("SELECT pr.id, pr.guest_id FROM guest_password_resets pr
                             JOIN guest_accounts ga ON pr.guest_id = ga.id
                             WHERE pr.token=? AND pr.used=0 AND pr.expires_at > ? LIMIT 1");
    if (!$tstmt) {
        echo json_encode(['success' => false, 'message' => 'Database query error.']);
        exit();
    }
    $tstmt->bind_param("ss", $token, $now);
    $tstmt->execute();
    $trow = $tstmt->get_result()->fetch_assoc();
    $tstmt->close();

    if (!$trow) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit();
    }

    // Hash the password using PASSWORD_DEFAULT (verify_guest_password.php uses password_verify)
    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    
    $upd = $conn->prepare("UPDATE guest_accounts SET password_hash=? WHERE id=?");
    if (!$upd) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare update query.']);
        exit();
    }
    $upd->bind_param("si", $hashed, $trow['guest_id']);
    $upd->execute();
    $upd->close();

    // Mark token as used
    $mark = $conn->prepare("UPDATE guest_password_resets SET used=1 WHERE id=?");
    if ($mark) {
        $mark->bind_param("i", $trow['id']);
        $mark->execute();
        $mark->close();
    }

    echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now sign in.']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
exit();
