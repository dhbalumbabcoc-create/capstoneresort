<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

// Check if user is staff
if (!is_logged_in() || $_SESSION['user_role'] !== 'staff') {
    header("Location: " . BASE_URL . "unauthorized.php");
    exit();
}

// Handle add booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_booking') {
    $facility_id = intval($_POST['facility_id']);
    $guest_name = escape_input($_POST['guest_name'], $conn);
    $guest_email = escape_input($_POST['guest_email'], $conn);
    $guest_phone = escape_input($_POST['guest_phone'], $conn);
    $check_in = escape_input($_POST['check_in_date'], $conn);
    $check_out = escape_input($_POST['check_out_date'], $conn);
    $num_guests = intval($_POST['num_guests']);
    $mode = escape_input($_POST['mode'] ?? 'overnight', $conn);
    $total_price = floatval($_POST['total_price']);
    
    $stmt = $conn->prepare("INSERT INTO bookings (facility_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, num_guests, mode, booking_type, status, total_price, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'walk_in', 'confirmed', ?, ?)");
    $created_by = $_SESSION['user_id'];
    $stmt->bind_param("isssssisdi", $facility_id, $guest_name, $guest_email, $guest_phone, $check_in, $check_out, $num_guests, $mode, $total_price, $created_by);

    if ($stmt->execute()) {
        $booking_id = $stmt->insert_id;
        header("Location: " . BASE_URL . "receipt.php?booking_id=" . $booking_id);
        exit();
    } else {
        set_error_message('Error creating booking: ' . $conn->error);
    }
    $stmt->close();
}

// Get available facilities
$facilities_result = $conn->query("SELECT * FROM facilities WHERE status = 'available' ORDER BY type, name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Booking - Resort Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
        }
        .navbar {
            background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 70px);
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #333;
            margin-bottom: 10px;
            border-radius: 5px;
            padding: 10px 15px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #1B7D3A;
            color: white;
        }
        .content {
            padding: 30px;
        }
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
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
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🏨 Resort Management System</span>
            <div class="collapse navbar-collapse ms-auto">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../profile.php"><i class="fas fa-user"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <div class="sidebar">
                    <h5 class="mb-4">Menu</h5>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                        <a class="nav-link active" href="booking.php"><i class="fas fa-plus-circle"></i> Guest Booking</a>
                    </nav>
                </div>
            </div>

            <div class="col-md-9">
                <div class="content">
                    <?php display_success_message(); ?>
                    <?php display_error_message(); ?>

                    <h2 class="mb-4">Guest Booking</h2>

                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_booking">

                                <h5 class="mb-4">Guest Information</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Guest Name *</label>
                                            <input type="text" class="form-control" name="guest_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Guest Email</label>
                                            <input type="email" class="form-control" name="guest_email">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Guest Phone</label>
                                            <input type="text" class="form-control" name="guest_phone">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Number of Guests *</label>
                                            <input type="number" class="form-control" name="num_guests" required>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5 class="mb-4">Booking Details</h5>

                                <div class="mb-3">
                                    <label class="form-label">Facility *</label>
                                    <select class="form-control" name="facility_id" id="facility_id" required onchange="updatePrice()">
                                        <option value="">Select Facility</option>
                                        <?php 
                                        while ($facility = $facilities_result->fetch_assoc()): ?>
                                            <option value="<?php echo $facility['id']; ?>" data-price="<?php echo $facility['price']; ?>"><?php echo $facility['name']; ?> - ₱<?php echo number_format($facility['price'], 2); ?>/night</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Mode *</label>
                                    <select class="form-control" name="mode" id="mode" onchange="updatePrice()">
                                        <option value="overnight">Overnight</option>
                                        <option value="daytour">Daytour</option>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Check-in Date *</label>
                                            <input type="date" class="form-control" name="check_in_date" id="check_in_date" required onchange="updateTotal()">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Check-out Date *</label>
                                            <input type="date" class="form-control" name="check_out_date" id="check_out_date" required onchange="updateTotal()">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Total Price *</label>
                                    <input type="number" class="form-control" name="total_price" id="total_price" step="0.01" required readonly>
                                </div>

                                <button type="submit" class="btn btn-gradient btn-lg w-100">
                                    <i class="fas fa-check"></i> Complete Booking
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updatePrice() {
            updateTotal();
        }

        function updateTotal() {
            const facilitySelect = document.getElementById('facility_id');
            const checkInDate = document.getElementById('check_in_date');
            const checkOutDate = document.getElementById('check_out_date');
            const totalPriceInput = document.getElementById('total_price');
            const mode = document.getElementById('mode').value;

            const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
            const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            
            if (checkInDate.value && checkOutDate.value) {
                const checkIn = new Date(checkInDate.value);
                const checkOut = new Date(checkOutDate.value);
                let nights;
                
                if (mode === 'daytour') {
                    nights = 1;
                } else {
                    nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                }
                
                if (nights > 0) {
                    totalPriceInput.value = (nights * price).toFixed(2);
                }
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

