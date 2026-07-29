<?php
date_default_timezone_set('Asia/Manila');
require_once 'config/db_config.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
header('Content-Type: application/json');

// Get guest info
$email = trim($_POST['email'] ?? '');
$guest_name = trim($_POST['guest_name'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email.']);
    exit;
}
if (empty($guest_name) || empty($contact_number)) {
    echo json_encode(['success' => false, 'error' => 'Name and contact number required.']);
    exit;
}
if (!preg_match('/^\d{11}$/', $contact_number)) {
    echo json_encode(['success' => false, 'error' => 'Contact number must be 11 digits only.']);
    exit;
}
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$stmt = $conn->prepare("INSERT INTO guest_otps (email, guest_name, contact_number, otp, expires_at) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('sssss', $email, $guest_name, $contact_number, $otp, $expires_at);
$stmt->execute();
$stmt->close();
try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'bucod.lyngemae123@gmail.com'; // Your Gmail address
    $mail->Password = 'mrih mgfp uaaq uagi'; // Replace with your Gmail app password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    $logo_path = __DIR__ . '/images/logo.jpg';
    if (file_exists($logo_path)) {
        $mail->addEmbeddedImage($logo_path, 'resort_logo', 'logo.jpg');
    }
    $mail->isHTML(true);
    $mail->setFrom('bucod.lyngemae123@gmail.com', 'Sinulom Falls and Bolao Cold Spring');
    $mail->addAddress($email, $guest_name);
    $mail->Subject = 'Your Booking Verification OTP — Sinulom & Bolao Resort';
    $mail->Body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);">
  <div style="background:#1a3d2b;padding:28px 32px;text-align:center;">
    <div style="margin-bottom:12px;">
      <img src="cid:resort_logo" alt="Sinulom Falls &amp; Bolao Cold Spring Resort" style="width:75px;height:75px;object-fit:cover;border-radius:50%;border:3px solid #ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:#ffffff;display:inline-block;">
    </div>
    <h1 style="color:#fff;font-size:20px;margin:0;">Booking OTP Verification</h1>
    <p style="color:rgba(255,255,255,.7);font-size:13px;margin:6px 0 0;">Sinulom Falls &amp; Bolao Cold Spring Resort</p>
  </div>
  <div style="padding:32px;">
    <p style="font-size:15px;color:#1a1a1a;">Hi <strong>' . htmlspecialchars($guest_name) . '</strong>,</p>
    <p style="font-size:14px;color:#6b7280;line-height:1.6;margin:12px 0 24px;">Use the OTP below to complete your booking reservation. It expires in <strong>10 minutes</strong>.</p>
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
    $mail->AltBody = "Your OTP is: $otp\nThis code will expire in 10 minutes.";
    $mail->send();
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    error_log("Failed to send OTP email: " . $e->getMessage());
    // For localhost dev, if mail fails to send, return success so login flow can proceed
    echo json_encode(['success' => true, 'notice' => 'OTP saved to database']);
}
