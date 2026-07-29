<?php
date_default_timezone_set('Asia/Manila');
require_once 'config/db_config.php';
header('Content-Type: application/json');
$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input.']);
    exit;
}


$stmt = $conn->prepare("SELECT id, otp FROM guest_otps WHERE email = ? AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $otp_id = $row['id'];
    $db_otp = $row['otp'];
    if ($db_otp === $otp) {
        $conn->query("DELETE FROM guest_otps WHERE id = $otp_id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP.']);
}
$stmt->close();
