<?php
date_default_timezone_set('Asia/Manila');
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config/db_config.php';

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
    exit;
}
if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required.']);
    exit;
}

$stmt = $conn->prepare("SELECT password_hash, full_name FROM guest_accounts WHERE email = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    // Check if email exists in bookings table (e.g. booked prior to account setup)
    $bstmt = $conn->prepare("SELECT guest_name, guest_phone FROM bookings WHERE guest_email = ? ORDER BY id DESC LIMIT 1");
    if ($bstmt) {
        $bstmt->bind_param('s', $email);
        $bstmt->execute();
        $brow = $bstmt->get_result()->fetch_assoc();
        $bstmt->close();
        if ($brow) {
            $guest_name = !empty($brow['guest_name']) ? $brow['guest_name'] : 'Guest';
            $guest_phone = !empty($brow['guest_phone']) ? $brow['guest_phone'] : '';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $inst = $conn->prepare("INSERT INTO guest_accounts (email, password_hash, full_name, phone) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), phone = VALUES(phone)");
            if ($inst) {
                $inst->bind_param('ssss', $email, $hash, $guest_name, $guest_phone);
                $inst->execute();
                $inst->close();
                echo json_encode(['success' => true, 'guest_name' => $guest_name]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false, 'error' => 'No account found with that email. Please check your email address or make a booking first.']);
    exit;
}

$row = $result->fetch_assoc();
if (!password_verify($password, $row['password_hash'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password. Please use the password you created when you booked.']);
    exit;
}

echo json_encode(['success' => true, 'guest_name' => $row['full_name']]);
