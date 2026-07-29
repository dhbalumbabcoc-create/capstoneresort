<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

// Determine correct dashboard link based on role
$role = strtolower($_SESSION['user_role'] ?? '');
if ($role === 'owner') {
    $dashboard_url = BASE_URL . 'owner/dashboard.php';
} elseif ($role === 'frontdesk') {
    $dashboard_url = BASE_URL . 'frontdesk/dashboard.php';
} elseif ($role === 'supervisor') {
    $dashboard_url = BASE_URL . 'supervisor/dashboard.php';
} elseif ($role === 'admin') {
    $dashboard_url = BASE_URL . 'admin/dashboard.php';
} else {
    $dashboard_url = BASE_URL . 'login.php';
}

// Log the unauthorized access attempt (staff only)
$attempted_page = $_SERVER['HTTP_REFERER'] ?? ($_SERVER['REQUEST_URI'] ?? 'unknown');
log_audit_event($conn, 'unauthorized_access', 'Attempted to access restricted page. Referer: ' . $attempted_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized - Resort Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 40px;
            text-align: center;
            max-width: 500px;
        }
        .error-icon {
            font-size: 64px;
            color: #ff6b6b;
            margin-bottom: 20px;
        }
        .error-title {
            color: #333;
            margin-bottom: 10px;
        }
        .error-message {
            color: #666;
            margin-bottom: 30px;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #27A457 0%, #1B7D3A 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-lock"></i>
        </div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">You do not have permission to access this page. Please contact your administrator if you believe this is an error.</p>
        <a href="<?php echo $dashboard_url; ?>" class="btn btn-gradient">
            <i class="fas fa-home"></i> Go to Dashboard
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

