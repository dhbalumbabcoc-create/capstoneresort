<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

header("Location: " . BASE_URL . "login.php");
exit();
