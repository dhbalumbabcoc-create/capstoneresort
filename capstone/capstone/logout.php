<?php
session_start();
require_once 'config/db_config.php';
require_once 'includes/functions.php';

$role     = isset($_SESSION['user_role'])    ? strtolower($_SESSION['user_role']) : null;
$is_guest = !empty($_SESSION['guest_logged_in']) && !empty($_SESSION['guest_email']);

if ($is_guest) {
    // Clear only guest session keys — leave staff keys untouched
    unset($_SESSION['guest_email'], $_SESSION['guest_logged_in'], $_SESSION['guest_last_activity']);
    header("Location: " . BASE_URL . "guest_login.php");
    exit();
}

// Staff logout
log_audit_event($conn, 'logout', null);
session_unset();
session_destroy();
header("Location: " . BASE_URL . "login.php");
exit();

