<?php
// Suppress all errors/warnings and ensure clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'config/db_config.php';

$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$response = ['success' => false];

if (!isset($conn) || !$conn) {
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email.';
    echo json_encode($response);
    exit;
}

if (empty($otp)) {
    $response['message'] = 'OTP required.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM guest_otps WHERE email = ? AND otp = ? AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
if (!$stmt) {
    $response['message'] = 'Database error.';
    echo json_encode($response);
    exit;
}
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    $response['message'] = 'Database error.';
    echo json_encode($response);
    $stmt->close();
    exit;
}
if ($result->num_rows > 0) {
    // Store guest data with a prefix — never touch user_id or user_role
    // so staff sessions are completely unaffected
    $_SESSION['guest_email']         = $email;
    $_SESSION['guest_logged_in']     = true;
    $_SESSION['guest_last_activity'] = time();
    // Do NOT set user_role or user_id — those are staff-only keys

    // Fetch name and phone from guest_accounts so they appear in booking summary
    $ga_stmt = $conn->prepare("SELECT full_name, phone FROM guest_accounts WHERE email = ? LIMIT 1");
    if ($ga_stmt) {
        $ga_stmt->bind_param('s', $email);
        $ga_stmt->execute();
        $ga_row = $ga_stmt->get_result()->fetch_assoc();
        $ga_stmt->close();
        if ($ga_row) {
            $_SESSION['guest_name']  = $ga_row['full_name'] ?? '';
            $_SESSION['guest_phone'] = $ga_row['phone'] ?? '';
        }
    }

    $conn->query("DELETE FROM guest_otps WHERE email = '" . $conn->real_escape_string($email) . "'");
    $response['success'] = true;
} else {
    $response['message'] = 'Invalid or expired OTP.';
}
$stmt->close();
session_write_close();
echo json_encode($response);