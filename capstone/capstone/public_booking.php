<?php
session_start();
require_once 'config/db_config.php';

$is_logged_in = !empty($_SESSION['guest_logged_in']);
$session_email = $_SESSION['guest_email'] ?? '';
$session_name  = $_SESSION['guest_name'] ?? '';
$session_phone = $_SESSION['guest_phone'] ?? ''; // if available

// Fallback: if logged in but name/phone missing from session, fetch from DB and cache them
if ($is_logged_in && $session_email && (empty($session_name) || empty($session_phone))) {
    $ga_fb = $conn->prepare("SELECT full_name, phone FROM guest_accounts WHERE email = ? LIMIT 1");
    if ($ga_fb) {
        $ga_fb->bind_param('s', $session_email);
        $ga_fb->execute();
        $ga_fb_row = $ga_fb->get_result()->fetch_assoc();
        $ga_fb->close();
        if ($ga_fb_row) {
            if (empty($session_name))  { $session_name  = $ga_fb_row['full_name'] ?? ''; $_SESSION['guest_name']  = $session_name; }
            if (empty($session_phone)) { $session_phone = $ga_fb_row['phone']     ?? ''; $_SESSION['guest_phone'] = $session_phone; }
        }
    }
}

// Split name for pre-fill
$name_parts = explode(' ', $session_name, 2);
$session_first = $name_parts[0] ?? '';
$session_last  = $name_parts[1] ?? '';

$edit_cart_id = $_GET['edit_cart_id'] ?? null;
// cart_id / cart_ids = selected item(s) from view_cart.php (Proceed to Booking)
$selected_cart_raw = $_GET['cart_ids'] ?? $_GET['cart_id'] ?? $_POST['selected_cart_id'] ?? null;
$selected_cart_ids = !empty($selected_cart_raw) ? array_filter(array_map('trim', explode(',', $selected_cart_raw))) : [];
$selected_cart_id = !empty($selected_cart_ids) ? $selected_cart_ids[0] : null;

$is_cart_booking = false;
if (isset($_GET['from']) && $_GET['from'] === 'cart') {
    $is_cart_booking = true;
} elseif (isset($_POST['from']) && $_POST['from'] === 'cart') {
    $is_cart_booking = true;
} elseif (!empty($edit_cart_id)) {
    $is_cart_booking = true;
} elseif (!empty($selected_cart_ids)) {
    $is_cart_booking = true;
}
$facility_id = isset($_GET['facility']) ? intval($_GET['facility']) : 0;
$check_in    = isset($_GET['check_in'])  ? $_GET['check_in']          : '';
$check_out   = isset($_GET['check_out']) ? $_GET['check_out']         : '';
$guests      = isset($_GET['guests'])    ? intval($_GET['guests'])     : 2;

$edit_item = null;
// Load from edit_cart_id (edit mode)
if ($edit_cart_id && isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if ($item['id'] === $edit_cart_id) {
            $edit_item = $item;
            $facility_id = $item['facility_id'];
            $check_in = $item['check_in_date'] ?? $item['check_in'] ?? '';
            $check_out = $item['check_out_date'] ?? $item['check_out'] ?? '';
            $guests = intval($item['num_guests'] ?? ($item['num_adults'] ?? 1) + ($item['num_children'] ?? 0) + ($item['num_pwd'] ?? $item['num_discounted'] ?? 0));
            break;
        }
    }
}
// Load from cart_id (selected item — Proceed to Booking)
if (!$edit_item && $selected_cart_id && isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if ($item['id'] === $selected_cart_id) {
            $edit_item = $item;
            $facility_id = $item['facility_id'];
            $check_in = $item['check_in_date'] ?? $item['check_in'] ?? '';
            $check_out = $item['check_out_date'] ?? $item['check_out'] ?? '';
            $guests = intval($item['num_guests'] ?? ($item['num_adults'] ?? 1) + ($item['num_children'] ?? 0) + ($item['num_pwd'] ?? $item['num_discounted'] ?? 0));
            break;
        }
    }
}

$facilities_result = $conn->query("SELECT * FROM facilities WHERE status = 'available' ORDER BY type, name");
$areas_result      = $conn->query("SELECT * FROM areas WHERE status = 'active' ORDER BY name");

// Fetch dynamic VAT rate from site_settings (fallback to 12%)
$vat_rate = 12.00;
$vat_res  = $conn->query("SELECT vat_rate FROM site_settings WHERE id=1 LIMIT 1");
if ($vat_res && $vat_row = $vat_res->fetch_assoc()) {
    $vat_rate = floatval($vat_row['vat_rate'] ?? 12.00);
}
$vat_multiplier = $vat_rate / 100;

$selected_facility = null;
if ($facility_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM facilities WHERE id = ?");
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $selected_facility = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$success_message = '';
$error_message   = '';

function getLandingAreaRates($area_name, $db_regular, $db_discounted, $db_children = null) {
    $name = strtolower(trim((string)$area_name));
    if (in_array($name, ['both', 'combo', 'combo package', 'sinulom + bolao'], true)) {
        return ['regular' => 160.0, 'discounted' => 130.0, 'children' => 85.0];
    }
    return ['regular' => 110.0, 'discounted' => 90.0, 'children' => 60.0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_booking') {
    $facility_id_str  = trim($_POST['facility_id'] ?? '');
    $facility_ids     = !empty($facility_id_str) ? array_map('intval', explode(',', $facility_id_str)) : [];
    $area_id          = !empty($_POST['area_id']) ? intval($_POST['area_id']) : null;
    if ($is_logged_in) {
        $guest_first_name = $session_first;
        $guest_last_name  = $session_last;
        $guest_name       = $session_name;
        $guest_email      = $session_email;
        $guest_phone      = $session_phone;
        $guest_password   = 'dummy_password_because_logged_in';
    } else {
        $guest_first_name = trim($_POST['guest_first_name'] ?? '');
        $guest_last_name  = trim($_POST['guest_last_name']  ?? '');
        $guest_name       = trim($guest_first_name . ' ' . $guest_last_name);
        $guest_email      = trim($_POST['guest_email'] ?? '');
        $guest_phone      = trim($_POST['guest_phone'] ?? '');
        $guest_password   = trim($_POST['guest_password']   ?? '');
    }
    $check_in_date    = trim($_POST['check_in_date']);
    $check_out_date   = !empty($_POST['check_out_date']) ? trim($_POST['check_out_date']) : $check_in_date;
    $num_adults       = intval($_POST['num_adults']   ?? 0);
    $num_children     = intval($_POST['num_children'] ?? 0);
    $num_below5       = intval($_POST['num_below5']   ?? 0);
    $num_pwd          = intval($_POST['num_pwd']      ?? 0);
    $num_guests       = $num_adults + $num_children + $num_below5 + $num_pwd;
    $mode             = trim($_POST['mode']      ?? 'overnight');
    $time_slot        = trim($_POST['time_slot'] ?? '');

    if (!$is_logged_in && (empty($guest_first_name) || empty($guest_last_name) || empty($guest_email) || empty($guest_phone) || empty($guest_password))) {
        $error_message = 'Please fill in all required guest details';
    } elseif (empty($check_in_date) || empty($facility_ids) || empty($area_id) || empty($time_slot)) {
        $error_message = 'Please fill in all required booking fields';
    } elseif (!$is_logged_in && (!preg_match('/^[A-Za-z]+(?:[\s\-\'][A-Za-z]+)?$/', $guest_first_name) || !preg_match('/^[A-Za-z]+(?:[\s\-\'][A-Za-z]+)?$/', $guest_last_name))) {
        $error_message = 'Please enter valid first name and last name.';
    } elseif (!$is_logged_in && !filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address';
    } elseif (!$is_logged_in && !preg_match('/^\d{11}$/', $guest_phone)) {
        $error_message = 'Please enter a valid 11-digit contact number.';
    } elseif ($check_in_date < date('Y-m-d')) {
        $error_message = 'Check-in date cannot be in the past.';
    } elseif ($mode === 'overnight' && (empty($check_out_date) || $check_out_date <= $check_in_date)) {
        $error_message = 'Please enter a valid check-out date for overnight booking';
    } elseif (!in_array($time_slot, ['8am-12pm', '12pm-5pm', 'full_day', 'overnight'], true)) {
        $error_message = 'Please select a valid time slot.';
    } else {
        $avail_ok = true; $avail_error = '';
        foreach ($facility_ids as $fid) {
            if ($mode === 'overnight') {
                // Overnight [check_in, check_out) overlaps with:
                // 1. Overnight [b.check_in, b.check_out) if max(in, b.in) < min(out, b.out)
                // 2. Daytour b.check_in if b.in >= in AND b.in < out
                $avail_stmt = $conn->prepare("
                    SELECT id FROM bookings 
                    WHERE facility_id=? AND status IN ('pending','confirmed','unpaid') 
                    AND (
                        (mode='overnight' AND check_in_date<? AND check_out_date>?)
                        OR 
                        (mode!='overnight' AND check_in_date>=? AND check_in_date<?)
                    ) LIMIT 1
                ");
                $avail_stmt->bind_param("issss", $fid, $check_out_date, $check_in_date, $check_in_date, $check_out_date);
                $avail_stmt->execute(); $avail_stmt->store_result();
                if ($avail_stmt->num_rows > 0) { 
                    $avail_ok = false; 
                    // Fetch facility name
                    $fn_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
                    $fn_stmt->bind_param("i", $fid); $fn_stmt->execute();
                    $fn_row = $fn_stmt->get_result()->fetch_assoc();
                    $fac_name_err = $fn_row ? $fn_row['name'] : 'one of the selected facilities';
                    $avail_error = "Sorry, {$fac_name_err} is already booked for the selected dates."; 
                    $fn_stmt->close();
                }
                $avail_stmt->close();
            } else {
                // Daytour overlaps with:
                // 1. Overnight [b.in, b.out) if in >= b.in AND in < b.out
                // Note: check_out_date > check_in_date means guests have NOT yet checked out — so strictly greater-than
                // allows booking on the checkout date (when guests leave that morning)
                $avail_stmt = $conn->prepare("SELECT id FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed','unpaid') AND mode='overnight' AND check_in_date<=? AND check_out_date>? LIMIT 1");
                $avail_stmt->bind_param("iss", $fid, $check_in_date, $check_in_date);
                $avail_stmt->execute(); $avail_stmt->store_result();
                if ($avail_stmt->num_rows > 0) { 
                    $avail_ok = false; 
                    $fn_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
                    $fn_stmt->bind_param("i", $fid); $fn_stmt->execute();
                    $fn_row = $fn_stmt->get_result()->fetch_assoc();
                    $fac_name_err = $fn_row ? $fn_row['name'] : 'one of the selected facilities';
                    $avail_error = "Sorry, {$fac_name_err} has an overnight booking on the selected date."; 
                    $fn_stmt->close();
                }
                $avail_stmt->close();
                if ($avail_ok) {
                    $avail_stmt2 = $conn->prepare("SELECT notes FROM bookings WHERE facility_id=? AND status IN ('pending','confirmed','unpaid') AND mode!='overnight' AND check_in_date=?");
                    $avail_stmt2->bind_param("is", $fid, $check_in_date);
                    $avail_stmt2->execute(); 
                    $res = $avail_stmt2->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $b_slot = '';
                        if (preg_match('/Time Slot:\s*([a-z0-9_\-]+)/i', $row['notes'], $m)) {
                            $b_slot = strtolower($m[1]);
                        }
                        if ($b_slot === $time_slot) {
                            $avail_ok = false;
                            $fn_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
                            $fn_stmt->bind_param("i", $fid); $fn_stmt->execute();
                            $fn_row = $fn_stmt->get_result()->fetch_assoc();
                            $fac_name_err = $fn_row ? $fn_row['name'] : 'one of the selected facilities';
                            $avail_error = "Sorry, {$fac_name_err} is already booked for that time slot.";
                            $fn_stmt->close();
                            break;
                        }
                        $full_slots = ['full_day', '8am-5pm'];
                        $part_slots = ['8am-12pm', '12pm-5pm'];
                        if (in_array($time_slot, $full_slots) && in_array($b_slot, $part_slots)) {
                            $avail_ok = false; 
                            $fn_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
                            $fn_stmt->bind_param("i", $fid); $fn_stmt->execute();
                            $fn_row = $fn_stmt->get_result()->fetch_assoc();
                            $fac_name_err = $fn_row ? $fn_row['name'] : 'one of the selected facilities';
                            $avail_error = "Sorry, {$fac_name_err} is already booked for a portion of the day."; 
                            $fn_stmt->close();
                            break;
                        }
                        if (in_array($time_slot, $part_slots) && in_array($b_slot, $full_slots)) {
                            $avail_ok = false; 
                            $fn_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
                            $fn_stmt->bind_param("i", $fid); $fn_stmt->execute();
                            $fn_row = $fn_stmt->get_result()->fetch_assoc();
                            $fac_name_err = $fn_row ? $fn_row['name'] : 'one of the selected facilities';
                            $avail_error = "Sorry, {$fac_name_err} is already booked for the full day."; 
                            $fn_stmt->close();
                            break;
                        }
                        if (in_array($time_slot, $full_slots) && in_array($b_slot, $full_slots)) {
                            $avail_ok = false; 
                            $fn_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
                            $fn_stmt->bind_param("i", $fid); $fn_stmt->execute();
                            $fn_row = $fn_stmt->get_result()->fetch_assoc();
                            $fac_name_err = $fn_row ? $fn_row['name'] : 'one of the selected facilities';
                            $avail_error = "Sorry, {$fac_name_err} is already booked for the full day."; 
                            $fn_stmt->close();
                            break;
                        }
                    }
                    $avail_stmt2->close();
                }
            }
            if (!$avail_ok) break;
        }

        if (!$avail_ok) {
            $error_message = $avail_error;
        } else {
            $checkIn  = new DateTime($check_in_date);
            $checkOut = new DateTime($check_out_date);
            $nights   = $mode === 'daytour' ? 1 : (int)$checkIn->diff($checkOut)->days;
            if ($nights <= 0) $nights = 1;

            $area_price_per_person = 0.0;
            $area_name = 'N/A';
            if ($area_id) {
                $astmt = $conn->prepare("SELECT name, price_regular, price_discounted, price_children, free_below_age FROM areas WHERE id=? AND status='active'");
                $astmt->bind_param("i", $area_id); $astmt->execute();
                $arow = $astmt->get_result()->fetch_assoc();
                if ($arow) {
                    $area_name = $arow['name'];
                    $rates = getLandingAreaRates($arow['name'] ?? '', floatval($arow['price_regular'] ?? 0), floatval($arow['price_discounted'] ?? 0), floatval($arow['price_children'] ?? 0));
                    $rate_pwd = round($rates['regular'] * 0.80, 2); // 20% discount for PWD/Seniors
                    $area_price_per_person = ($num_adults * $rates['regular']) + ($num_children * $rates['children']) + ($num_pwd * $rate_pwd) + ($num_below5 * 0);
                }
                $astmt->close();
            }

            $transportation = trim($_POST['transportation'] ?? 'none');
            $transport_cost = 0;
            $transport_guests = max(0, $num_adults + $num_pwd);
            if ($transportation === 'tignapoloan') {
                $transport_cost = $transport_guests * 50;
            } elseif ($transportation === 'cdo') {
                $transport_cost = $transport_guests * 250;
            } elseif ($transportation === 'private') {
                $transport_cost = 3500;
            }

            if (!$is_logged_in && !empty($guest_password)) {
                $guest_password_hash = password_hash($guest_password, PASSWORD_DEFAULT);
                $ga_stmt = $conn->prepare("INSERT INTO guest_accounts (email, password_hash, full_name, phone) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), phone = VALUES(phone)");
                if ($ga_stmt) { $ga_stmt->bind_param('ssss', $guest_email, $guest_password_hash, $guest_name, $guest_phone); $ga_stmt->execute(); $ga_stmt->close(); }
            } elseif ($is_logged_in) {
                $ga_stmt = $conn->prepare("UPDATE guest_accounts SET full_name = ?, phone = ? WHERE email = ?");
                if ($ga_stmt) { $ga_stmt->bind_param('sss', $guest_name, $guest_phone, $guest_email); $ga_stmt->execute(); $ga_stmt->close(); }
            }
            
            // Ensure session variables are set
            if (empty($_SESSION['guest_logged_in'])) {
                $_SESSION['guest_logged_in'] = true;
                $_SESSION['guest_email'] = $guest_email;
                $_SESSION['guest_name'] = $guest_name;
            }

            $inserted_booking_ids = [];
            
            if ($is_cart_booking && empty($selected_cart_id)) {
                // Gather details for all selected facilities
                $total_facility_price = 0;
                $facility_names = [];
                foreach ($facility_ids as $fid) {
                    $fstmt = $conn->prepare("SELECT name, price FROM facilities WHERE id=? AND status='available'");
                    $fstmt->bind_param("i", $fid); $fstmt->execute();
                    $frow = $fstmt->get_result()->fetch_assoc();
                    if ($frow) { 
                        $total_facility_price += floatval($frow['price']); 
                        $facility_names[] = $frow['name'];
                    }
                    $fstmt->close();
                }
                
                $facility_cost = $mode === 'overnight' ? ($total_facility_price * $nights) : $total_facility_price;
                $subtotal   = $facility_cost + $area_price_per_person + $transport_cost;
                $vat        = round($subtotal * $vat_multiplier, 2);
                $total_price = $subtotal + $vat;
                
                $uploaded_pwd_photos = [];
                if (!empty($_FILES['pwd_ids']) && is_array($_FILES['pwd_ids']['name'])) {
                    $upload_dir = 'uploads/pwd_ids/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0777, true);
                    }
                    foreach ($_FILES['pwd_ids']['name'] as $k => $filename) {
                        if (isset($_FILES['pwd_ids']['error'][$k]) && $_FILES['pwd_ids']['error'][$k] === UPLOAD_ERR_OK) {
                            $tmp_name = $_FILES['pwd_ids']['tmp_name'][$k];
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                                $new_filename = 'pwd_id_' . time() . '_' . mt_rand(1000, 9999) . '_' . $k . '.' . $ext;
                                $target_path = $upload_dir . $new_filename;
                                if (move_uploaded_file($tmp_name, $target_path)) {
                                    $uploaded_pwd_photos[] = $target_path;
                                }
                            }
                        }
                    }
                }

                $notes = 'Time Slot: ' . $time_slot . ' | Transport: ' . ($transport_cost > 0 ? $transportation : 'none') . ' | Below5: ' . $num_below5 . ' | PWD: ' . $num_pwd . ' | VAT: ' . $vat;
                if (!empty($uploaded_pwd_photos)) {
                    $notes .= ' | PWD_IDs: ' . implode(',', $uploaded_pwd_photos);
                }
                
                $cart_item = [
                    'id'             => !empty($_POST['edit_cart_id']) ? $_POST['edit_cart_id'] : uniqid('cart_'),
                    'facility_id'    => implode(',', $facility_ids),
                    'facility_name'  => implode(' + ', $facility_names),
                    'facility_price' => $total_facility_price,
                    'area_id'        => $area_id,
                    'area_name'      => $area_name,
                    'check_in'       => $check_in_date,
                    'check_in_date'  => $check_in_date,
                    'check_out'      => $check_out_date,
                    'check_out_date' => $check_out_date,
                    'num_guests'     => $num_guests,
                    'num_adults'     => $num_adults,
                    'num_children'   => $num_children,
                    'num_below5'     => $num_below5,
                    'num_pwd'        => $num_pwd,
                    'num_discounted' => $num_pwd,
                    'mode'           => $mode,
                    'time_slot'      => $time_slot,
                    'total_price'    => $total_price,
                    'notes'          => $notes,
                    'pwd_id_photos'  => $uploaded_pwd_photos,
                    'vat'            => $vat,
                    'transportation' => ($transport_cost > 0 ? $transportation : 'none'),
                    'transport_opt'  => ($transport_cost > 0 ? $transportation : 'none'),
                    'transport_cost' => $transport_cost,
                    'transport_fee'  => $transport_cost,
                    'guest_name'     => $guest_name,
                    'guest_email'    => $guest_email,
                    'guest_phone'    => $guest_phone
                ];

                if (!empty($_POST['edit_cart_id']) && isset($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $key => $item) {
                        if ($item['id'] === $_POST['edit_cart_id']) {
                            $_SESSION['cart'][$key] = $cart_item;
                            break;
                        }
                    }
                } else {
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }
                    $_SESSION['cart'][] = $cart_item;
                }
            } else {
                $first_item = true;
                $is_first_for_addons = true;
                foreach ($facility_ids as $fid) {
                    $facility_price = 0;
                    $facility_name = '';
                    $fstmt = $conn->prepare("SELECT name, price FROM facilities WHERE id=? AND status='available'");
                    $fstmt->bind_param("i", $fid); $fstmt->execute();
                    $frow = $fstmt->get_result()->fetch_assoc();
                    if ($frow) { 
                        $facility_price = floatval($frow['price']); 
                        $facility_name = $frow['name'];
                    }
                    $fstmt->close();

                    $facility_cost = $mode === 'overnight' ? ($facility_price * $nights) : $facility_price;

                    if ($first_item) {
                        $curr_area_fee = $area_price_per_person;
                        $curr_trans_fee = $transport_cost;
                        $curr_num_guests = $num_guests;
                        $curr_num_adults = $num_adults;
                        $curr_num_children = $num_children;
                        $curr_num_below5 = $num_below5;
                        $curr_num_pwd = $num_pwd;
                        $first_item = false;
                    } else {
                        $curr_area_fee = 0.0;
                        $curr_trans_fee = 0.0;
                        $curr_num_guests = 0;
                        $curr_num_adults = 0;
                        $curr_num_children = 0;
                        $curr_num_below5 = 0;
                        $curr_num_pwd = 0;
                    }

                    $subtotal   = $facility_cost + $curr_area_fee + $curr_trans_fee;
                    $vat        = round($subtotal * $vat_multiplier, 2);
                    $total_price = $subtotal + $vat;
                    $notes = 'Time Slot: ' . $time_slot . ' | Transport: ' . ($curr_trans_fee > 0 ? $transportation : 'none') . ' | Below5: ' . $curr_num_below5 . ' | PWD: ' . $curr_num_pwd . ' | VAT: ' . $vat;

                    $insert_stmt = $conn->prepare("INSERT INTO bookings (
                        facility_id, area_id, guest_name, guest_email, guest_phone,
                        check_in_date, check_out_date, num_guests, num_adults, num_children, num_below5, num_discounted,
                        mode, booking_type, status, total_price, notes
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'online','unpaid',?,?)");
                    $insert_stmt->bind_param("iisssssiiiiisds",
                        $fid, $area_id, $guest_name, $guest_email, $guest_phone,
                        $check_in_date, $check_out_date, $curr_num_guests, $curr_num_adults,
                        $curr_num_children, $curr_num_below5, $curr_num_pwd, $mode,
                        $total_price, $notes
                    );
                    $insert_stmt->execute();
                    $bid = $insert_stmt->insert_id;
                    $inserted_booking_ids[] = $bid;
                    $insert_stmt->close();

                    // Save addons only for the first booking item if coming from cart
                    if ($is_first_for_addons && !empty($edit_item['addon_ids']) && !empty($edit_item['addon_qtys'])) {
                        foreach ($edit_item['addon_ids'] as $idx => $aid) {
                            if ($aid <= 0) continue;
                            $qty = max(1, intval($edit_item['addon_qtys'][$idx]));
                            $ins = $conn->prepare("INSERT INTO booking_addons (booking_id, amenity_id, quantity) VALUES (?,?,?)");
                            $ins->bind_param("iii", $bid, $aid, $qty);
                            $ins->execute(); $ins->close();
                        }
                        $is_first_for_addons = false;
                    }
                }
            }

            if ($is_cart_booking && empty($selected_cart_id)) {
                header("Location: guest_dashboard.php?tab=cart&added=1");
                exit();
            } else {
                // If editing a cart item, remove it from the cart
                if (!empty($_POST['edit_cart_id']) && isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($item) {
                        return $item['id'] !== $_POST['edit_cart_id'];
                    }));
                }
                // If checking out cart item(s), remove them from the cart
                if (!empty($selected_cart_ids) && isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($item) use ($selected_cart_ids) {
                        return !in_array($item['id'], $selected_cart_ids, true);
                    }));
                }
                header("Location: public_payment.php?booking_ids=" . implode(',', $inserted_booking_ids));
                exit();
            }
        }
    }
}

/* Pre-load all bookings for calendar and facility availability logic */
$all_bookings = [];
$b_res = $conn->query("SELECT id, check_in_date, check_out_date, mode, facility_id, notes FROM bookings WHERE status IN ('confirmed','pending','unpaid')");
if ($b_res) {
    while ($b = $b_res->fetch_assoc()) {
        $slot = 'full_day';
        if (preg_match('/Time Slot:\s*([^\s|]+)/', $b['notes'], $m)) {
            $slot = $m[1];
        }
        $b['time_slot'] = $slot;
        $all_bookings[] = $b;
    }
}

// Append items from the current user's session cart to prevent double-booking their own cart items
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        // Skip the item currently being edited so it doesn't block itself
        if (isset($edit_cart_id) && $item['id'] === $edit_cart_id) {
            continue;
        }
        $all_bookings[] = [
            'id'             => $item['id'],
            'is_cart_item'   => true,
            'check_in_date'  => $item['check_in_date'],
            'check_out_date' => $item['check_out_date'],
            'mode'           => $item['mode'],
            'facility_id'    => $item['facility_id'],
            'time_slot'      => $item['time_slot'] ?? 'full_day'
        ];
    }
}

/* Facilities & areas arrays for JS */
$facilities_js = [];
if ($facilities_result) { while ($f = $facilities_result->fetch_assoc()) { $facilities_js[] = $f; } }
$areas_js = [];
if ($areas_result) { while ($a = $areas_result->fetch_assoc()) { $areas_js[] = $a; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Your Stay - Sinulom &amp; Bolao Cold Spring Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;1,600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --gd:#1a3d2b;--gm:#2d5a3d;--gl:#4a7c59;
  --cream:#f0ece0;--card:#fff;--card2:#f8f5ef;
  --txt:#1a1a1a;--muted:#6b7280;--red:#c62828;
  --border:#ddd8cc;--accent:#006ce4;
  --gold:#c9a84c;--hero-h:340px;
  --bg-base:#eaf3ec;
  --bg-grad1:rgba(26,61,43,0.09);
  --bg-grad2:rgba(74,124,89,0.07);
  --bg-grad3:rgba(201,168,76,0.04);
}
html{scroll-behavior:smooth}
@keyframes gradientShift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}
body{
  font-family:'Inter',sans-serif;
  color:var(--txt);
  min-height:100vh;
  position:relative;
  overflow-x:hidden;
  background: linear-gradient(-45deg, #eaf5ee 0%, #d8f1e7 25%, #e1f5f7 50%, #faf3db 75%, #eaf5ee 100%);
  background-size: 400% 400%;
  animation: gradientShift 18s ease-in-out infinite;
  background-attachment: fixed;
}
.nb{position:sticky;top:0;z-index:1000;background:var(--gd);box-shadow:0 2px 20px rgba(0,0,0,.25)}
.nb-inner{max-width:100%;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:68px}
.nb-brand{display:flex;align-items:center;gap:12px;text-decoration:none}
.nb-brand img{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.3)}
.nb-brand-txt strong{display:block;font-size:.92rem;font-weight:800;color:#fff;line-height:1.2}
.nb-brand-txt span{font-size:.7rem;color:rgba(255,255,255,.65)}
.nb-back{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.3);color:#fff;padding:8px 18px;border-radius:50px;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s}
.nb-back:hover{background:rgba(255,255,255,.22);color:#fff}
/* ── Navbar Cart Icon ── */
.nb-actions{display:flex;align-items:center;gap:10px;}
.nb-cart-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.13);border:1.5px solid rgba(255,255,255,.28);color:#fff;text-decoration:none;transition:all .22s;cursor:pointer;}
.nb-cart-btn:hover{background:rgba(238,77,45,.82);border-color:rgba(238,77,45,.6);color:#fff;transform:translateY(-2px) scale(1.07);box-shadow:0 6px 20px rgba(238,77,45,.38);}
.nb-cart-btn i{font-size:1.1rem;}
.nb-cart-badge{position:absolute;top:-5px;right:-5px;background:linear-gradient(135deg,#ee4d2d,#ff7337);color:#fff;font-size:.62rem;font-weight:800;min-width:18px;height:18px;border-radius:50px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid var(--gd);line-height:1;box-shadow:0 2px 8px rgba(238,77,45,.5);transition:transform .2s;}
.nb-cart-badge.pop{animation:badgePop .32s cubic-bezier(.36,.07,.19,.97);}
@keyframes badgePop{0%{transform:scale(1)}40%{transform:scale(1.55)}70%{transform:scale(.88)}100%{transform:scale(1)}}
.nb-cart-badge[data-count='0']{display:none;}
/* ── CINEMATIC HERO ── */
.pg-hero{
  position:relative;
  height:var(--hero-h);
  overflow:hidden;
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
}
.pg-hero-bg{
  position:absolute;
  inset:0;
  background-image:url('images/booking-header.jpg');
  background-size:cover;
  background-position:center 40%;
  transform:scale(1.08);
  transition:transform 8s ease-out;
  filter:brightness(0.55) saturate(1.1);
  will-change:transform;
}
.pg-hero-bg.loaded{transform:scale(1);}
.pg-hero-overlay{
  position:absolute;
  inset:0;
  background:
    linear-gradient(160deg, rgba(10,28,18,0.65) 0%, rgba(26,61,43,0.5) 50%, rgba(0,0,0,0.3) 100%);
}
.pg-hero-particles{
  position:absolute;
  inset:0;
  pointer-events:none;
  overflow:hidden;
}
.particle{
  position:absolute;
  border-radius:50%;
  background:rgba(255,255,255,0.18);
  animation:floatUp linear infinite;
}
@keyframes floatUp{
  0%{transform:translateY(100%) scale(0);opacity:0;}
  10%{opacity:1;}
  90%{opacity:0.5;}
  100%{transform:translateY(-120px) scale(1.2);opacity:0;}
}
.pg-hero-content{
  position:relative;
  z-index:2;
  padding:0 24px;
}
.pg-hero .ey{
  font-size:.72rem;
  font-weight:700;
  letter-spacing:5px;
  text-transform:uppercase;
  color:var(--gold);
  margin-bottom:10px;
  text-shadow:0 2px 8px rgba(0,0,0,.4);
  animation:fadeSlideUp .8s ease-out both;
}
.pg-hero h1{
  font-family:'Playfair Display',serif;
  font-size:clamp(2rem,5vw,3rem);
  font-weight:800;
  color:#fff;
  line-height:1.15;
  text-shadow:0 4px 24px rgba(0,0,0,.45);
  animation:fadeSlideUp .9s .1s ease-out both;
}
.pg-hero-sub{
  font-size:.88rem;
  color:rgba(255,255,255,.78);
  margin-top:10px;
  font-weight:500;
  letter-spacing:.5px;
  animation:fadeSlideUp 1s .2s ease-out both;
}
.pg-hero-breadcrumb{
  position:absolute;
  bottom:18px;
  left:50%;
  transform:translateX(-50%);
  display:flex;
  align-items:center;
  gap:8px;
  font-size:.72rem;
  color:rgba(255,255,255,.6);
  z-index:3;
  white-space:nowrap;
}
.pg-hero-breadcrumb a{
  color:rgba(255,255,255,.6);
  text-decoration:none;
  transition:color .2s;
}
.pg-hero-breadcrumb a:hover{color:var(--gold);}
.pg-hero-breadcrumb .sep{opacity:.4;}
.pg-hero-wave{
  position:absolute;
  bottom:-2px;
  left:0;
  width:100%;
  line-height:0;
  z-index:3;
}
.pg-hero-wave svg{display:block;width:100%;height:54px;}
@keyframes fadeSlideUp{
  from{opacity:0;transform:translateY(22px);}
  to{opacity:1;transform:translateY(0);}
}

/* ── WRAP ── */
.bk-wrap{max-width:1120px;margin:0 auto;padding:24px 24px 70px;position:relative;z-index:1;}
.err-alert{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:28px;color:var(--red);font-size:.9rem;display:flex;align-items:center;gap:10px}
/* ── STEPS BAR ── */
.steps-bar{
  display:flex;
  align-items:flex-start;
  margin-bottom:22px;
  background:rgba(255,255,255,0.7);
  backdrop-filter:blur(12px);
  -webkit-backdrop-filter:blur(12px);
  border-radius:16px;
  padding:18px 24px;
  box-shadow:0 4px 24px rgba(26,61,43,.1),0 1px 0 rgba(255,255,255,.8) inset;
  border:1px solid rgba(255,255,255,.6);
}
.step-item{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;z-index:1;cursor:pointer}
.step-item:hover .step-circle{transform:scale(1.1);}
.step-connector{flex:1;height:3px;background:var(--border);margin-top:18px;z-index:0;align-self:flex-start;border-radius:4px;transition:background .4s;}
.step-connector.done{background:linear-gradient(90deg,var(--gd),var(--gl));}
.step-circle{
  width:42px;height:42px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;border:2.5px solid var(--border);
  background:#fff;color:var(--muted);
  transition:all .35s cubic-bezier(.34,1.56,.64,1);
  margin-bottom:7px;
  box-shadow:0 2px 8px rgba(0,0,0,.07);
}
.step-item.active .step-circle{
  background:linear-gradient(135deg,var(--gm),var(--gd));
  border-color:var(--gd);color:#fff;
  box-shadow:0 6px 20px rgba(26,61,43,.38),0 0 0 4px rgba(26,61,43,.12);
  transform:scale(1.06);
}
.step-item.done .step-circle{background:var(--gm);border-color:var(--gm);color:#fff;box-shadow:0 4px 12px rgba(45,90,61,.3);}
.step-label{font-size:.69rem;font-weight:600;color:var(--muted);text-align:center;white-space:nowrap}
.step-item.active .step-label{color:var(--gd);font-weight:800}
.step-item.done .step-label{color:var(--gm)}

/* ── BOOKING CARDS ── */
.bk-card{
  background:rgba(255,255,255,0.92);
  backdrop-filter:blur(8px);
  -webkit-backdrop-filter:blur(8px);
  border-radius:18px;
  box-shadow:0 8px 36px rgba(26,61,43,.1),0 1px 0 rgba(255,255,255,.9) inset;
  border:1px solid rgba(255,255,255,.7);
  overflow:hidden;
  margin-bottom:18px;
  transition:box-shadow .3s,transform .3s;
}
.bk-card:hover{box-shadow:0 12px 44px rgba(26,61,43,.14);}
.bk-card-hdr{
  background:linear-gradient(135deg,var(--gd) 0%,var(--gm) 100%);
  padding:14px 22px;
  display:flex;align-items:center;gap:12px;
  position:relative;
  overflow:hidden;
}
.bk-card-hdr::after{
  content:'';
  position:absolute;
  right:-20px;top:-20px;
  width:100px;height:100px;
  border-radius:50%;
  background:rgba(255,255,255,0.06);
}
.bk-card-hdr i{color:rgba(255,255,255,.85);font-size:1.05rem}
.bk-card-hdr h2{font-family:'Playfair Display',serif;font-size:1.08rem;font-weight:700;color:#fff;margin:0}
.bk-card-body{padding:18px 22px}

/* Search widget wrapper */
.search-widget-wrapper {
  position: relative;
  margin-bottom: 24px;
}
.search-widget-container {
  background: var(--gd);
  border-radius: 12px;
  padding: 4px;
  display: flex;
  gap: 4px;
  align-items: stretch;
  box-shadow: 0 8px 24px rgba(26, 61, 43, 0.2);
}
.search-input-box {
  background: #fff;
  border-radius: 8px;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s ease;
  user-select: none;
  min-height: 56px;
}
.search-input-box:hover {
  background: #f7f9fa;
}
.search-input-box.active {
  border-color: var(--accent);
}
.search-input-box i {
  font-size: 1.3rem;
  color: var(--muted);
}
.search-input-val {
  display: flex;
  flex-direction: column;
  justify-content: center;
  line-height: 1.3;
}
.search-input-val .title {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--txt);
}
.search-input-val .subtitle {
  font-size: 0.72rem;
  color: var(--muted);
  font-weight: 500;
}
.search-btn-box {
  display: flex;
  align-items: center;
}
.search-submit-btn {
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0 36px;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 56px;
  box-shadow: 0 4px 12px rgba(0, 108, 228, 0.2);
}
.search-submit-btn:hover {
  background: #0056b3;
  transform: translateY(-1px);
}

/* Popup Overlay */
.popup-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: transparent;
  z-index: 998;
  display: none;
  pointer-events: none;
}
.popup-overlay.show {
  display: block;
  pointer-events: none;
}

/* Floating Dropdown Cards */
.popup-dropdown-card {
  position: absolute;
  top: calc(100% + 8px);
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
  border: 1px solid var(--border);
  z-index: 1001;
  padding: 24px;
  display: none;
  animation: slideDownFade 0.2s ease-out;
  color: var(--txt);
}
@keyframes slideDownFade {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
.popup-dropdown-card.show {
  display: block;
}
.date-popup-card {
  left: 0;
  width: 760px;
}
.guests-popup-card {
  right: 0;
  width: 400px;
}

/* Popup Tabs */
.popup-tabs {
  display: flex;
  border-bottom: 1.5px solid var(--border);
  margin-bottom: 20px;
  gap: 24px;
}
.popup-tab {
  padding: 8px 0 12px;
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  position: relative;
  transition: all 0.2s ease;
  user-select: none;
}
.popup-tab:hover {
  color: var(--gd);
}
.popup-tab.active {
  color: var(--accent);
  font-weight: 700;
}
.popup-tab.active::after {
  content: '';
  position: absolute;
  bottom: -1.5px;
  left: 0;
  width: 100%;
  height: 3px;
  background: var(--accent);
  border-radius: 3px 3px 0 0;
}
.popup-tab .badge {
  background: #d63031;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 4px;
  margin-left: 5px;
  text-transform: uppercase;
}

/* Dual calendar container */
.calendar-dual-months {
  display: flex;
  gap: 24px;
}
.calendar-single-month {
  flex: 1;
}
.calendar-month-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.calendar-month-name {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--txt);
  text-align: center;
  flex: 1;
}
.calendar-nav-btn {
  background: none;
  border: none;
  font-size: 1rem;
  color: var(--muted);
  cursor: pointer;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}
.calendar-nav-btn:hover {
  background: #f0f0f0;
  color: var(--txt);
}
.calendar-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}
.calendar-dow-lbl {
  text-align: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  padding-bottom: 8px;
}
.calendar-day-cell {
  aspect-ratio: 1.1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  border-radius: 4px;
  border: 2px solid transparent;
  user-select: none;
  transition: all 0.1s ease;
  position: relative;
}
.calendar-day-cell:hover:not(.empty):not(.past):not(.disabled):not(.booked):not(.maint) {
  background: #e8f0fe;
  color: var(--accent);
}
.calendar-day-cell.today {
  border-color: var(--accent);
  color: var(--accent);
}
.calendar-day-cell.past {
  color: #d1d5db;
  background: #fafafa;
  cursor: not-allowed;
  text-decoration: line-through;
}
.calendar-day-cell.empty {
  cursor: default;
  pointer-events: none;
}
.calendar-day-cell.booked {
  background: #fee2e2;
  color: #b91c1c;
  cursor: not-allowed;
  text-decoration: line-through;
}
.calendar-day-cell.maint {
  background: #fef3c7;
  color: #d97706;
  cursor: not-allowed;
  text-decoration: line-through;
}

/* Range Highlighting */
.calendar-day-cell.range-start {
  background: var(--accent) !important;
  color: #fff !important;
  border-radius: 50% 0 0 50% !important;
  font-weight: 700;
}
.calendar-day-cell.range-end {
  background: var(--accent) !important;
  color: #fff !important;
  border-radius: 0 50% 50% 0 !important;
  font-weight: 700;
}
.calendar-day-cell.range-start.range-end {
  border-radius: 50% !important;
}
.calendar-day-cell.range-between {
  background: #e8f0fe !important;
  color: var(--accent) !important;
  border-radius: 0 !important;
}

/* Day Use Warning & Slot selector inside dropdown */
.dayuse-info-banner {
  background: #f3f4f6;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 0.8rem;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  font-weight: 500;
}
.dayuse-info-banner i {
  color: var(--accent);
  font-size: 1.1rem;
}
.dayuse-slot-section {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}
.dayuse-slot-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--txt);
  margin-bottom: 8px;
}

/* Flexible View styles */
.flexible-subtitle {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--txt);
  margin-bottom: 12px;
}
.flexible-pills-row {
  display: flex;
  gap: 10px;
  margin-bottom: 24px;
}
.flexible-pill {
  padding: 8px 18px;
  border: 1.5px solid var(--border);
  border-radius: 50px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s ease;
  background: #fff;
}
.flexible-pill:hover {
  border-color: var(--accent);
  color: var(--accent);
}
.flexible-pill.active {
  background: #e8f0fe;
  border-color: var(--accent);
  color: var(--accent);
}
.flexible-months-scroller {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding: 6px 2px;
  margin-bottom: 24px;
  scroll-behavior: smooth;
  scrollbar-width: thin;
}
.flexible-month-box {
  flex-shrink: 0;
  width: 106px;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  padding: 14px 10px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.flexible-month-box:hover {
  border-color: var(--accent);
}
.flexible-month-box.active {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.15);
  background: #fff;
}
.flexible-month-box i {
  font-size: 1.2rem;
  color: var(--muted);
}
.flexible-month-box.active i {
  color: var(--accent);
}
.flexible-month-box .m-lbl {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--txt);
}
.flexible-month-box .y-lbl {
  font-size: 0.65rem;
  color: var(--muted);
}
.flexible-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--border);
  padding-top: 14px;
}
.flexible-clear-btn {
  background: none;
  border: none;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--accent);
  cursor: pointer;
}
.flexible-select-btn {
  background: var(--accent);
  color: #fff;
  border: none;
  padding: 8px 24px;
  border-radius: 50px;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 108, 228, 0.15);
}

/* Stepper Steppers */
.guest-stepper-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
}
.guest-stepper-row:last-child {
  border-bottom: none;
}
.guest-stepper-label {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}
.guest-stepper-label .title {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--txt);
}
.guest-stepper-label .subtitle {
  font-size: 0.72rem;
  color: var(--muted);
  font-weight: 500;
}
.guest-stepper-ctrls {
  display: flex;
  align-items: center;
  gap: 16px;
}
.guest-stepper-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--accent);
  background: #fff;
  color: var(--accent);
  font-size: 1.1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}
.guest-stepper-btn:hover:not(.disabled) {
  background: #f0f6ff;
}
.guest-stepper-btn.disabled {
  border-color: var(--border);
  color: var(--border);
  cursor: not-allowed;
}
.guest-stepper-val {
  font-size: 0.95rem;
  font-weight: 700;
  min-width: 18px;
  text-align: center;
}
.child-ages-container {
  padding-top: 14px;
  border-top: 1px solid var(--border);
  margin-top: 10px;
}
.child-ages-desc {
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--muted);
  margin-bottom: 12px;
}
.child-age-select-group {
  margin-bottom: 8px;
}
.child-age-select-group label {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--txt);
  margin-bottom: 4px;
  display: block;
}

/* Facility & Options Card Step 1 */
.facility-options-card {
  background: var(--card);
  border-radius: 16px;
  box-shadow: 0 4px 32px rgba(0,0,0,.08);
  margin-bottom: 16px;
  display: none;
  animation: slideDownFade 0.3s ease-out;
}
.facility-options-card.show {
  display: block;
}

/* Step Panel styling */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--txt);margin-bottom:6px}
.form-group label .req{color:var(--red)}
.form-control,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'Inter',sans-serif;font-size:.9rem;color:var(--txt);background:#fff;outline:none;transition:border-color .2s,box-shadow .2s}
.form-control:focus,.form-select:focus{border-color:var(--gd);box-shadow:0 0 0 3px rgba(26,61,43,.1)}
textarea.form-control{resize:vertical;min-height:90px}
.capitalize-input{text-transform:capitalize}
.fac-info-box{background:#f0faf4;border:1.5px solid #a7f3d0;border-radius:12px;padding:16px;margin-top:12px;display:none}
.fac-info-box.show{display:block}
.fac-info-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #d1fae5;font-size:.85rem}
.fac-info-row:last-child{border-bottom:none}
.fac-info-row span{color:var(--muted)}
.fac-info-row strong{color:var(--gd);font-weight:700}
.summary-card{background:var(--card2);border-radius:14px;overflow:hidden;margin-bottom:12px}
.summary-card-hdr{background:var(--gm);padding:10px 16px;font-family:'Playfair Display',serif;font-size:.9rem;font-weight:700;color:#fff}
.summary-row{display:flex;justify-content:space-between;align-items:center;padding:7px 16px;border-bottom:1px solid var(--border);font-size:.82rem}
.summary-row:last-child{border-bottom:none}
.summary-row .lbl{color:var(--muted);font-weight:500}
.summary-row .val{color:var(--txt);font-weight:600;text-align:right;max-width:60%}
.summary-total-row{background:var(--gd);padding:11px 16px;display:flex;justify-content:space-between;align-items:center}
.summary-total-row .lbl{color:rgba(255,255,255,.8);font-weight:600;font-size:.85rem}
.summary-total-row .val{color:#fff;font-size:1.05rem;font-weight:800}
.terms-check{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;font-size:.82rem;color:var(--muted)}
.terms-check input[type=checkbox]{width:16px;height:16px;accent-color:var(--gd);flex-shrink:0;margin-top:2px;cursor:pointer}
.terms-check a{color:var(--gd);font-weight:600;text-decoration:underline;cursor:pointer}
.terms-check a:hover{color:var(--accent)}
.terms-checks-wrap{background:#f9fafb;border:1.5px solid var(--border);border-radius:12px;padding:12px 16px;margin-bottom:14px;display:flex;flex-direction:column;gap:6px}
/* Two-column landscape review layout */
.summary-cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:0}
.summary-cols .summary-card{margin-bottom:0}
@media(max-width:700px){.summary-cols{grid-template-columns:1fr;}}
/* Two-column landscape step 2 layout */
.step2-cols{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:18px;align-items:stretch}
.step2-cols .bk-card{display:flex;flex-direction:column;height:100%;margin-bottom:0}
.step2-cols .bk-card-body{flex:1}
.step2-cols.single-col{grid-template-columns:1fr}
@media(max-width:900px){.step2-cols{grid-template-columns:1fr;}}
/* Terms Modal */
.terms-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center}
.terms-modal-overlay.show{display:flex}
.terms-modal{background:#fff;border-radius:16px;max-width:720px;width:96%;max-height:86vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.25);overflow:hidden}
.terms-modal-hdr{background:var(--gd);color:#fff;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.terms-modal-hdr h3{margin:0;font-size:1rem;font-weight:700;display:flex;align-items:center;gap:10px}
.terms-modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;transition:background .2s}
.terms-modal-close:hover{background:rgba(255,255,255,0.3)}
.terms-modal-tabs{display:flex;background:#f3f4f6;border-bottom:2px solid #e5e7eb;flex-shrink:0}
.terms-modal-tab{flex:1;padding:11px 10px;font-size:.82rem;font-weight:700;color:#6b7280;border:none;background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s}
.terms-modal-tab.active{color:var(--gd);border-bottom-color:var(--gd);background:#fff}
.terms-modal-tab:hover:not(.active){color:var(--txt);background:#e9ecef}
.terms-modal-body{overflow-y:auto;padding:22px 24px;flex:1;font-size:.87rem;color:#374151;line-height:1.75}
.terms-modal-body h4{color:var(--gd);font-size:.9rem;font-weight:700;margin:18px 0 6px;padding-bottom:4px;border-bottom:2px solid #e5e7eb}
.terms-modal-body h4:first-child{margin-top:0}
.terms-modal-body ul{margin:0 0 10px 18px;padding:0}
.terms-modal-body ul li{margin-bottom:5px}
.terms-modal-body p{margin:0 0 10px}
.terms-tab-pane{display:none}.terms-tab-pane.active{display:block}
.terms-modal-footer{padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;flex-shrink:0}
.terms-modal-footer button{background:var(--gd);color:#fff;border:none;padding:10px 28px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;transition:opacity .2s}
.terms-modal-footer button:hover{opacity:.85}
.btn-row{display:flex;gap:12px;justify-content:space-between;margin-top:24px}
.btn-back{padding:12px 28px;border-radius:50px;border:1.5px solid var(--border);background:#fff;color:var(--muted);font-size:.9rem;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px}
.btn-back:hover{border-color:var(--gd);color:var(--gd)}
.btn-next{padding:12px 32px;border-radius:50px;border:none;background:var(--gd);color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(26,61,43,.3)}
.btn-next:hover{background:var(--gm);transform:translateY(-1px)}
/* ── Shopee-style Add to Cart Button ── */
.btn-add-cart{padding:12px 28px;border-radius:50px;border:none;background:linear-gradient(135deg,#ee4d2d 0%,#ff7337 100%);color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(238,77,45,.35);position:relative;overflow:hidden;}
.btn-add-cart::before{content:'';position:absolute;inset:0;background:rgba(255,255,255,0);transition:background .2s;}
.btn-add-cart:hover:not(:disabled)::before{background:rgba(255,255,255,.12);}
.btn-add-cart:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 8px 24px rgba(238,77,45,.45);}
.btn-add-cart:active:not(:disabled){transform:translateY(0);box-shadow:0 4px 12px rgba(238,77,45,.3);}
.btn-add-cart:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none;}
.btn-add-cart .cart-icon-pulse{display:inline-block;transition:transform .3s;}
.btn-add-cart:hover:not(:disabled) .cart-icon-pulse{transform:scale(1.25) rotate(-8deg);}
/* ── Cart Toast Notification ── */
#cartToast{position:fixed;bottom:28px;right:28px;z-index:9999;background:#1a3d2b;color:#fff;padding:14px 22px 14px 18px;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.22);display:flex;align-items:center;gap:12px;font-size:.92rem;font-weight:600;min-width:260px;opacity:0;transform:translateY(24px) scale(.96);transition:opacity .35s,transform .35s;pointer-events:none;}
#cartToast.show{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
#cartToast .toast-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem;}
#cartToast .toast-icon.success{background:#d1fae5;color:#065f46;}
#cartToast .toast-icon.error{background:#fee2e2;color:#991b1b;}
#cartToast .toast-msg{flex:1;}
#cartToast .toast-close{background:none;border:none;color:rgba(255,255,255,.6);font-size:1rem;cursor:pointer;padding:0 0 0 8px;line-height:1;}
#cartToast .toast-close:hover{color:#fff;}
.btn-confirm{width:100%;padding:15px;border-radius:50px;border:none;background:var(--gd);color:#fff;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 6px 24px rgba(26,61,43,.35);margin-bottom:12px}
.btn-confirm:hover:not(:disabled){background:var(--gm);transform:translateY(-2px)}
.btn-confirm:disabled{opacity:.5;cursor:not-allowed;transform:none}
.step-panel{display:none}
.step-panel.active{
  display:block;
  animation:panelFadeIn .4s ease-out both;
}
@keyframes panelFadeIn{
  from{opacity:0;transform:translateY(14px);}
  to{opacity:1;transform:translateY(0);}
}

/* Extra calendar legends */
.cal-legends-container {
  display: flex;
  gap: 16px;
  font-size: 0.72rem;
  color: var(--muted);
  justify-content: center;
  margin-top: 14px;
}
.cal-legend-pill {
  display: flex;
  align-items: center;
  gap: 6px;
}
.cal-legend-dot-round {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

@media(max-width:992px) {
  .date-popup-card {
    width: 680px;
  }
}

@media(max-width:768px){
  .nb-inner{padding:0 16px}
  .bk-wrap{padding:24px 12px 60px}
  .bk-card-body{padding:16px}
  .form-row{grid-template-columns:1fr}
  .step-label{font-size:.62rem}
  .step-circle{width:40px;height:40px;font-size:.95rem}
  :root{--hero-h:240px;}
  .steps-bar{padding:14px 12px;border-radius:12px;}
  .search-widget-container {
    flex-direction: column;
    padding: 6px;
  }
  .search-input-box {
    min-height: 50px;
  }
  .search-submit-btn {
    width: 100%;
    min-height: 50px;
  }
  .date-popup-card {
    width: 96%;
    position: fixed;
    top: 50%;
    left: 2%;
    right: 2%;
    transform: translateY(-50%) !important;
    max-height: 85vh;
    overflow-y: auto;
    border-radius: 12px;
  }
  .calendar-dual-months {
    flex-direction: column;
    gap: 16px;
  }
  .guests-popup-card {
    width: 96%;
    position: fixed;
    top: 50%;
    left: 2%;
    right: 2%;
    transform: translateY(-50%) !important;
    border-radius: 12px;
  }
}
@media(max-width:480px){
  .step-circle{width:36px;height:36px;font-size:0.85rem}
}

/* Live selection status bar */
.cal-selected-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, #f0faf4 0%, #e8f5ee 100%);
  border: 1.5px solid #a7f3d0;
  border-radius: 10px;
  padding: 12px 18px;
  font-size: 0.85rem;
  color: var(--gd);
  font-weight: 500;
}
.cal-selected-bar i {
  color: var(--gd);
  font-size: 1rem;
  flex-shrink: 0;
}

/* Time slot cards (Day Use) */
.ts-card {
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  padding: 14px 8px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.ts-card:hover {
  border-color: var(--accent);
  background: #f0f6ff;
}
.ts-card.selected {
  border-color: var(--accent);
  background: #e8f0fe;
  box-shadow: 0 0 0 3px rgba(0,108,228,0.12);
}
.ts-card.unavail {
  opacity: 0.45;
  cursor: not-allowed;
  background: #f9f9f9;
}
.ts-icon {
  font-size: 1.3rem;
  color: var(--accent);
}
.ts-card.selected .ts-icon {
  color: var(--accent);
}
.ts-name {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--txt);
}
.ts-time {
  font-size: 0.62rem;
  color: var(--muted);
  font-weight: 500;
}
/* Agoda/Booking.com-style Facility Cards */
.fac-catalog-header {
  margin-top: 28px;
  margin-bottom: 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: linear-gradient(135deg, rgba(26,61,43,0.06) 0%, rgba(74,124,89,0.04) 100%);
  border: 1.5px solid rgba(26,61,43,0.12);
  border-radius: 14px;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}
.fac-catalog-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--gd);
  display: flex;
  align-items: center;
  gap: 10px;
}
.fac-catalog-title::before {
  content: '';
  display: inline-block;
  width: 5px;
  height: 24px;
  background: linear-gradient(180deg, var(--gd), var(--gl));
  border-radius: 3px;
}
.fac-catalog-filters {
  display: flex;
  gap: 8px;
}
.fac-filter-btn {
  background: rgba(255,255,255,0.85);
  border: 1.5px solid rgba(26,61,43,0.18);
  border-radius: 50px;
  padding: 7px 18px;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.22s ease;
  box-shadow: 0 2px 8px rgba(26,61,43,0.06);
}
.fac-filter-btn:hover {
  border-color: var(--gd);
  color: var(--gd);
  background: rgba(255,255,255,1);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(26,61,43,0.12);
}
.fac-filter-btn.active {
  background: linear-gradient(135deg, var(--gd), var(--gm));
  border-color: var(--gd);
  color: #fff;
  box-shadow: 0 4px 14px rgba(26,61,43,0.28);
  transform: translateY(-1px);
}
.fac-catalog-grid {
  display: block;
  margin-bottom: 24px;
}

/* Type group section */
.fac-type-group {
  margin-bottom: 40px;
}
.fac-type-group-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--gd);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
}
.fac-type-group-title::after {
  content: '';
  flex: 1;
  height: 2px;
  background: linear-gradient(90deg, rgba(26,61,43,0.35) 0%, rgba(74,124,89,0.15) 50%, transparent 100%);
  border-radius: 2px;
}
.fac-type-group-title-icon {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, var(--gd), var(--gl));
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(26,61,43,0.25);
}
.fac-type-group-title-icon i {
  color: #fff;
  font-size: 0.85rem;
}
.fac-grid-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}
@media (max-width: 900px) {
  .fac-grid-row { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 580px) {
  .fac-grid-row { grid-template-columns: 1fr; }
}

/* Landscape Card */
.landscape-card {
  background: rgba(255,255,255,0.95);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 22px rgba(26,61,43,0.09), 0 1px 0 rgba(255,255,255,0.9) inset;
  border: 2px solid rgba(255,255,255,0.7);
  transition: all 0.28s cubic-bezier(0.34,1.56,0.64,1);
  cursor: pointer;
  position: relative;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}
.landscape-card:hover {
  transform: translateY(-6px) scale(1.012);
  box-shadow: 0 16px 44px rgba(26,61,43,0.18), 0 4px 12px rgba(26,61,43,0.08);
  border-color: var(--gd);
}
.landscape-card.selected {
  border-color: var(--gd);
  box-shadow: 0 0 0 4px rgba(26,61,43,0.18), 0 8px 24px rgba(0,0,0,0.12);
}
.landscape-card-img {
  width: 100%;
  height: 190px;
  object-fit: cover;
  display: block;
  background: #e0e0e0;
}
.landscape-card-body {
  padding: 14px 16px 16px;
}
.landscape-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 6px;
  gap: 8px;
}
.landscape-card-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--txt);
  line-height: 1.2;
}
.landscape-avail-badge {
  background: #d1fae5;
  color: #065f46;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 20px;
  white-space: nowrap;
  flex-shrink: 0;
}
.landscape-avail-badge.booked {
  background: #fee2e2;
  color: #b91c1c;
}
.landscape-card-price {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--gd);
  margin-bottom: 6px;
}
.landscape-card-price span {
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--muted);
}
.landscape-card-cap {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: var(--muted);
  font-weight: 500;
}
.landscape-card-cap i {
  color: var(--gl);
}
.landscape-selected-overlay {
  position: absolute;
  top: 10px;
  right: 10px;
  background: var(--gd);
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 20px;
  display: none;
  align-items: center;
  gap: 5px;
}
.landscape-card.selected .landscape-selected-overlay {
  display: flex;
}

/* Legacy agoda-card styles kept for reference but not used */
.agoda-card-unused {
  display: flex;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border);
  transition: all 0.3s ease;
}
.agoda-card-unused:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.agoda-card-unused.selected {
  border: 2px solid var(--accent);
  box-shadow: 0 0 0 5px rgba(0, 108, 228, 0.18);
}

/* Left section (Image Gallery & Details) */
.agoda-left {
  width: 320px;
  min-width: 320px;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #f0f0f0;
}
.agoda-gallery {
  position: relative;
  height: 220px;
  background: #000;
  overflow: hidden;
}
.agoda-gallery img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0.95;
  transition: opacity 0.3s ease;
}
.agoda-gallery-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(255, 255, 255, 0.85);
  border: none;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.75rem;
  color: #1a1a1a;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  z-index: 5;
  transition: all 0.2s ease;
}
.agoda-gallery-nav:hover {
  background: #fff;
  color: var(--accent);
  transform: translateY(-50%) scale(1.08);
}
.agoda-gallery-nav.prev {
  left: 10px;
}
.agoda-gallery-nav.next {
  right: 10px;
}
.agoda-gallery-counter {
  position: absolute;
  bottom: 10px;
  left: 10px;
  background: rgba(0, 0, 0, 0.65);
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 4px;
}
.agoda-gallery-tag {
  position: absolute;
  top: 10px;
  left: 10px;
  background: #8e44ad;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 4px;
  text-transform: uppercase;
  z-index: 2;
}

.agoda-left-details {
  padding: 16px;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.agoda-fac-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--txt);
  margin-bottom: 4px;
}
.agoda-fac-specs {
  font-size: 0.74rem;
  color: var(--muted);
  font-weight: 500;
  margin-bottom: 10px;
}
.agoda-left-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 14px;
}
.agoda-purple-badge {
  background: #f3e5f5;
  color: #8e24aa;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 6px;
  border-radius: 4px;
  display: inline-block;
}
.agoda-rating-section {
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid #f0f0f0;
  padding-bottom: 10px;
  margin-bottom: 10px;
}
.agoda-rating-score {
  background: var(--gd);
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  width: 28px;
  height: 28px;
  border-radius: 6px 6px 6px 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.agoda-rating-desc {
  line-height: 1.2;
}
.agoda-rating-desc .lbl {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--gd);
}
.agoda-rating-desc .sub {
  font-size: 0.65rem;
  color: var(--muted);
}
.agoda-amenities-list {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.agoda-amenity-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.74rem;
  color: #555;
  font-weight: 500;
}
.agoda-amenity-item i {
  color: var(--gl);
  width: 14px;
  text-align: center;
}

/* Middle section (Deals & Pricing details) */
.agoda-middle {
  flex: 1;
  display: flex;
  flex-direction: column;
  position: relative;
  border-right: 1px solid #f0f0f0;
}
.agoda-top-banner {
  background: linear-gradient(90deg, #c62828 0%, #e53935 100%);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 5px 16px;
  letter-spacing: 0.3px;
}
.agoda-middle-content {
  padding: 16px;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.agoda-deals-list {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 16px;
}
.agoda-deal-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.76rem;
  color: #444;
  font-weight: 500;
}
.agoda-deal-item i {
  width: 14px;
  text-align: center;
}
.agoda-deal-item.green {
  color: #2e7d32;
  font-weight: 600;
}
.agoda-deal-item.green i {
  color: #2e7d32;
}

.agoda-pricing-box {
  margin-top: auto;
  text-align: right;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.agoda-cheapest-label {
  color: #c62828;
  font-size: 0.74rem;
  font-weight: 700;
  margin-bottom: 2px;
}
.agoda-discount-applied {
  background: #e8f5e9;
  color: #2e7d32;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2.5px 6px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 6px;
}
.agoda-price-old {
  font-size: 0.76rem;
  color: #999;
  text-decoration: line-through;
  line-height: 1;
}
.agoda-price-new {
  font-size: 1.6rem;
  font-weight: 800;
  color: #c62828;
  line-height: 1.1;
  display: flex;
  align-items: flex-start;
  gap: 2px;
}
.agoda-price-new span {
  font-size: 0.95rem;
  margin-top: 3px;
}
.agoda-price-sub {
  font-size: 0.65rem;
  color: var(--muted);
  font-weight: 500;
}

/* Right section (CTA & Room Picker) */
.agoda-right {
  width: 200px;
  min-width: 200px;
  padding: 20px 14px;
  background: #faf9f6;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.agoda-room-dropdown {
  width: 100%;
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  padding: 7px 10px;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  margin-bottom: 10px;
  transition: all 0.2s ease;
}
.agoda-room-dropdown:hover {
  border-color: var(--accent);
}
.agoda-book-btn {
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px;
  width: 100%;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 108, 228, 0.2);
  transition: all 0.25s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1.2;
}
.agoda-book-btn:hover {
  background: #0056b3;
  transform: translateY(-1px);
}
.agoda-book-btn.selected {
  background: #2e7d32;
  box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
}
.agoda-book-btn span {
  font-size: 0.68rem;
  font-weight: 500;
  opacity: 0.9;
}
.agoda-right-badge {
  font-size: 0.72rem;
  font-weight: 700;
  color: #2e7d32;
  margin-top: 12px;
}
.agoda-right-status {
  font-size: 0.65rem;
  font-weight: 700;
  color: #e65100;
  margin-top: 4px;
}

@media(max-width: 992px) {
  .agoda-card {
    flex-direction: column;
  }
  .agoda-left, .agoda-right {
    width: 100%;
    min-width: 100%;
    border-right: none;
  }
  .agoda-left {
    border-bottom: 1px solid #f0f0f0;
  }
  .agoda-gallery {
    height: 240px;
  }
  .agoda-middle {
    border-right: none;
    border-bottom: 1px solid #f0f0f0;
  }
}

/* ── BOOKING.COM STYLE CARD (bc-style-card) ── */
.bc-style-card {
  display: flex;
  flex-direction: column;
  background: rgba(255,255,255,0.96);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 22px rgba(26,61,43,0.09), 0 1px 0 rgba(255,255,255,0.9) inset;
  border: 2px solid rgba(255,255,255,0.7);
  transition: all 0.28s cubic-bezier(0.34,1.56,0.64,1);
  cursor: pointer;
  position: relative;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}
.bc-style-card:hover {
  transform: translateY(-6px) scale(1.012);
  box-shadow: 0 16px 44px rgba(26,61,43,0.18), 0 4px 12px rgba(26,61,43,0.08);
  border-color: var(--gd);
}
.bc-style-card.selected {
  border-color: var(--gd);
  box-shadow: 0 0 0 4px rgba(26,61,43,0.18), 0 10px 30px rgba(26,61,43,0.12);
}

/* Image wrapper */
.bc-img-wrap {
  position: relative;
  width: 100%;
  height: 195px;
  overflow: hidden;
  background: #000;
  flex-shrink: 0;
}
.bc-card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.35s ease;
}
.bc-style-card:hover .bc-card-img {
  transform: scale(1.04);
}

/* "OUR BEST SELLER!" ribbon */
.bc-ribbon {
  position: absolute;
  top: 12px;
  left: 0;
  background: linear-gradient(90deg, #8e24aa, #ab47bc);
  color: #fff;
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  padding: 5px 12px 5px 10px;
  border-radius: 0 20px 20px 0;
  display: flex;
  align-items: center;
  gap: 5px;
  z-index: 3;
  box-shadow: 0 2px 8px rgba(142,36,170,0.4);
}
.bc-ribbon i {
  font-size: 0.6rem;
}

/* Carousel nav buttons */
.bc-nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255,255,255,0.9);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.78rem;
  color: #1a1a1a;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  z-index: 5;
  transition: all 0.2s ease;
  opacity: 0;
}
.bc-style-card:hover .bc-nav-btn {
  opacity: 1;
}
.bc-nav-btn:hover {
  background: #fff;
  color: var(--gd);
  transform: translateY(-50%) scale(1.08);
}
.bc-nav-prev { left: 9px; }
.bc-nav-next { right: 9px; }

/* Image counter */
.bc-img-counter {
  position: absolute;
  bottom: 10px;
  left: 10px;
  background: rgba(0,0,0,0.6);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 4px;
  z-index: 3;
}

/* Card body */
.bc-card-body {
  padding: 14px 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

/* Property name */
.bc-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--txt);
  line-height: 1.2;
}

/* Room · Capacity subtitle */
.bc-subinfo {
  font-size: 0.72rem;
  color: var(--muted);
  font-weight: 500;
}

/* Badge pills row */
.bc-badges-row {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}
.bc-badge-pill {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 4px;
  display: inline-block;
}

/* Rating row */
.bc-rating-row {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 0 4px;
  border-top: 1px solid var(--border);
}
.bc-rating-score {
  background: var(--gd);
  border-radius: 6px 6px 6px 0;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.bc-score-num {
  color: #fff;
  font-size: 0.88rem;
  font-weight: 800;
}
.bc-rating-label {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--gd);
}
.bc-rating-sub {
  font-size: 0.65rem;
  color: var(--muted);
  font-weight: 500;
}

/* Amenities list */
.bc-amenities {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.bc-amenity-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.74rem;
  color: #444;
  font-weight: 500;
}
.bc-amenity-item i {
  color: var(--gl);
  width: 14px;
  text-align: center;
  font-size: 0.78rem;
}

/* Price row */
.bc-price-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid var(--border);
}
.bc-price-main {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--gd);
}
.bc-price-unit {
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 500;
}
/* Image View Modal */
.img-view-modal {
  display: none;
  position: fixed;
  z-index: 10000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.85);
  align-items: center;
  justify-content: center;
}
.img-view-modal-content {
  max-width: 90%;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 8px;
  animation: zoomIn 0.3s;
}
.img-view-modal-close {
  position: absolute;
  top: 20px;
  right: 35px;
  color: #fff;
  font-size: 40px;
  font-weight: bold;
  cursor: pointer;
  z-index: 10001;
}
@keyframes zoomIn {
  from {transform:scale(0.8); opacity: 0;}
  to {transform:scale(1); opacity: 1;}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="nb">
  <div class="nb-inner">
    <a href="landing.php" class="nb-brand">
      <img src="images/logo.jpg" alt="Resort Logo">
      <div class="nb-brand-txt">
        <strong>Sinulom &amp; Bolao</strong>
        <span>Cold Spring Resort</span>
      </div>
    </a>
    <div class="nb-actions">
      <?php $cart_count = !empty($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>
      <a href="view_cart.php" class="nb-cart-btn" id="navCartBtn" title="View Cart (<?php echo $cart_count; ?> item<?php echo $cart_count !== 1 ? 's' : ''; ?>)">
        <i class="fas fa-shopping-cart"></i>
        <span class="nb-cart-badge" id="navCartBadge" data-count="<?php echo $cart_count; ?>"><?php echo $cart_count > 0 ? $cart_count : ''; ?></span>
      </a>
      <a href="landing.php" class="nb-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>
  </div>
</nav>

<!-- PAGE HERO -->
<section class="pg-hero">
  <!-- Parallax BG -->
  <div class="pg-hero-bg" id="heroBg"></div>
  <!-- Gradient Overlay -->
  <div class="pg-hero-overlay"></div>
  <!-- Floating Particles -->
  <div class="pg-hero-particles" id="heroParticles"></div>
  <!-- Content -->
  <div class="pg-hero-content">
    <div class="ey">— Reservation —</div>
    <h1>Book Your Stay</h1>
    <p class="pg-hero-sub"><i class="fas fa-map-marker-alt" style="color:var(--gold);margin-right:6px;"></i>Sinulom &amp; Bolao Cold Spring Resort</p>
  </div>
  <!-- Breadcrumb -->
  <div class="pg-hero-breadcrumb">
    <a href="landing.php"><i class="fas fa-home"></i></a>
    <span class="sep">›</span>
    <span>Booking</span>
  </div>
  <!-- Wave Divider -->
  <div class="pg-hero-wave">
    <svg viewBox="0 0 1440 54" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M0,54 L0,30 Q180,0 360,20 Q540,40 720,18 Q900,-4 1080,20 Q1260,44 1440,22 L1440,54 Z" fill="#dff0e4"/>
    </svg>
  </div>
</section>

<!-- MAIN CONTENT -->
<div class="bk-wrap">

  <?php if ($error_message): ?>
  <div class="err-alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <!-- PROGRESS BAR -->
  <div class="steps-bar">
    <div class="step-item active" id="si1" onclick="goStep(1)">
      <div class="step-circle"><i class="fas fa-calendar-alt"></i></div>
      <div class="step-label">Schedule &amp; Facility</div>
    </div>
    <div class="step-connector" id="sc1"></div>
    <div class="step-item" id="si2" onclick="goStep(2)">
      <div class="step-circle"><i class="fas fa-map-marker-alt"></i></div>
      <div class="step-label">Location &amp; Details</div>
    </div>
    <div class="step-connector" id="sc2"></div>

    <div class="step-item" id="si3" onclick="goStep(3)">
      <div class="step-circle"><i class="fas <?= ($is_cart_booking && empty($selected_cart_id)) ? 'fa-shopping-cart' : 'fa-check'; ?>"></i></div>
      <div class="step-label"><?= ($is_cart_booking && empty($selected_cart_id)) ? 'Review &amp; Add' : 'Review &amp; Submit'; ?></div>
    </div>
  </div>

  <form id="bkForm" action="public_booking.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="submit_booking">
    <input type="hidden" name="edit_cart_id" value="<?= htmlspecialchars($edit_cart_id ?? '') ?>">
    <input type="hidden" name="selected_cart_id" value="<?= htmlspecialchars($selected_cart_id ?? '') ?>">
    <input type="hidden" name="from" value="<?= $is_cart_booking ? 'cart' : '' ?>">
    <input type="hidden" name="is_cart_flow" value="<?= $is_cart_booking ? '1' : '0' ?>">
    <input type="hidden" name="facility_id" id="hFacilityId" value="<?php echo $facility_id; ?>">
    <input type="hidden" name="check_in_date"   id="hCheckIn"    value="<?php echo htmlspecialchars($check_in); ?>">
    <input type="hidden" name="check_out_date"  id="hCheckOut"   value="<?php echo htmlspecialchars($check_out); ?>">
    <input type="hidden" name="time_slot"        id="hTimeSlot"   value="">
    <input type="hidden" name="guest_first_name" id="hFirstName"  value="">
    <input type="hidden" name="guest_last_name"  id="hLastName"   value="">
    <input type="hidden" name="guest_email"      id="hEmail"      value="">
    <input type="hidden" name="guest_phone"      id="hPhone"      value="">
    <input type="hidden" name="guest_password"   id="hPassword"   value="">
    <input type="hidden" name="mode"             id="hMode"       value="daytour">
    <input type="hidden" name="area_id"          id="hAreaId"     value="">
    <input type="hidden" name="num_adults"       id="hAdults"     value="1">
    <input type="hidden" name="num_children"     id="hChildren"   value="0">
    <input type="hidden" name="num_below5"       id="hBelow5"     value="0">
    <input type="hidden" name="num_pwd"          id="hPwd"        value="0">
    <input type="hidden" name="transportation"   id="hTransport"  value="none">

    <!-- BACKDROP OVERLAY FOR CLOSING POPUPS -->
    <div class="popup-overlay" id="popupOverlay"></div>

    <!-- STEP 1: SCHEDULE & FACILITY -->
    <div class="step-panel active" id="panel1">
      
      <!-- BOOKING.COM STYLE SEARCH BAR -->
      <div class="search-widget-wrapper">
        <div class="search-widget-container">
          
          <!-- Check-in Date Button -->
          <div class="search-input-box" id="boxCheckIn" onclick="togglePopup('datePopup')">
            <i class="far fa-calendar-alt"></i>
            <div class="search-input-val">
              <span class="subtitle">Check-in Date</span>
              <span class="title" id="valCheckIn">Select date</span>
            </div>
          </div>

          <!-- Check-out Date Button -->
          <div class="search-input-box" id="boxCheckOut" onclick="togglePopup('datePopup')">
            <i class="far fa-calendar-alt"></i>
            <div class="search-input-val">
              <span class="subtitle">Check-out Date</span>
              <span class="title" id="valCheckOut">Select date</span>
            </div>
          </div>

          <!-- Guests Selector Button -->
          <div class="search-input-box" id="boxGuests" onclick="togglePopup('guestsPopup')">
            <i class="fas fa-users"></i>
            <div class="search-input-val">
              <span class="subtitle">Guests &amp; Rooms</span>
              <span class="title" id="valGuests">2 adults, 1 room</span>
            </div>
          </div>

          <!-- Search Submit Button -->
          <div class="search-btn-box">
            <button type="button" class="search-submit-btn" onclick="triggerSearch()">
              <i class="fas fa-search" style="color:#fff;margin-right:8px;"></i> SEARCH
            </button>
          </div>

        </div>

        <!-- FLOATING DATE PICKER DROPDOWN -->
        <div class="popup-dropdown-card date-popup-card" id="datePopup" onclick="event.stopPropagation()">
          
          <!-- Tabs -->
          <div class="popup-tabs">
            <div class="popup-tab active" id="tabOvernight" onclick="switchCalendarTab('overnight')">Overnight Stays</div>
            <div class="popup-tab" id="tabDayUse" onclick="switchCalendarTab('daytour')">Day Use Stays <span class="badge">New!</span></div>
            <div class="popup-tab" id="tabFlexible" onclick="switchCalendarTab('flexible')">I'm flexible</div>
          </div>

          <!-- TAB 1 & 2 CONTENT: OVERNIGHT / DAYUSE DUAL CALENDARS -->
          <div id="calendarMainSection">
            
            <!-- Dayuse Time Slot Warning Banner (Shown only in Day Use Tab) -->
            <div class="dayuse-info-banner" id="dayuseInfoBanner" style="display:none;">
              <i class="fas fa-info-circle"></i>
              <span>You're searching for Day Use Stays. Same day checkout applies. Please choose a slot below the calendars.</span>
            </div>

            <!-- Side-by-Side Dual Calendars -->
            <div class="calendar-dual-months">
              
              <!-- Month 1 -->
              <div class="calendar-single-month">
                <div class="calendar-month-header">
                  <button type="button" class="calendar-nav-btn" onclick="navigateCalendar(-1)"><i class="fas fa-chevron-left"></i></button>
                  <span class="calendar-month-name" id="monthNameLeft">June 2026</span>
                  <span></span>
                </div>
                <div class="calendar-days-grid">
                  <div class="calendar-dow-lbl">Su</div><div class="calendar-dow-lbl">Mo</div><div class="calendar-dow-lbl">Tu</div><div class="calendar-dow-lbl">We</div><div class="calendar-dow-lbl">Th</div><div class="calendar-dow-lbl">Fr</div><div class="calendar-dow-lbl">Sa</div>
                </div>
                <div class="calendar-days-grid" id="daysLeft"></div>
              </div>

              <!-- Month 2 -->
              <div class="calendar-single-month">
                <div class="calendar-month-header">
                  <span></span>
                  <span class="calendar-month-name" id="monthNameRight">July 2026</span>
                  <button type="button" class="calendar-nav-btn" onclick="navigateCalendar(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="calendar-days-grid">
                  <div class="calendar-dow-lbl">Su</div><div class="calendar-dow-lbl">Mo</div><div class="calendar-dow-lbl">Tu</div><div class="calendar-dow-lbl">We</div><div class="calendar-dow-lbl">Th</div><div class="calendar-dow-lbl">Fr</div><div class="calendar-dow-lbl">Sa</div>
                </div>
                <div class="calendar-days-grid" id="daysRight"></div>
              </div>

            </div>

            <!-- Legends -->
            <div class="cal-legends-container">
              <div class="cal-legend-pill"><div class="cal-legend-dot-round" style="background:#e8f0fe;border:1px solid var(--accent)"></div> Selected</div>
              <div class="cal-legend-pill"><div class="cal-legend-dot-round" style="background:#fff;border:1.5px solid var(--border)"></div> Available</div>
              <div class="cal-legend-pill"><div class="cal-legend-dot-round" style="background:#fee2e2;"></div> Booked</div>
              <div class="cal-legend-pill"><div class="cal-legend-dot-round" style="background:#fef3c7;"></div> Maintenance</div>
            </div>

            <!-- Day Use Slot Selector (Shown inside dropdown ONLY in Day Use Tab) -->
            <div class="dayuse-slot-section" id="dayuseSlotSection" style="display:none;">
              <div class="dayuse-slot-title"><i class="fas fa-clock"></i> Select Time Slot</div>
              <div class="cal-selected-bar" style="margin-bottom:12px;background:#f3f4f6;border-color:var(--border);">
                <i class="fas fa-clock" style="color:var(--muted)"></i>
                <span id="dayuseSelectedSlotText" style="color:var(--txt)">No slot selected — choose below:</span>
              </div>
              <div class="form-row" style="grid-template-columns: repeat(3, 1fr); gap: 10px;">
                <div class="ts-card" data-slot="8am-12pm" onclick="selectSlotWidget(this)" style="padding: 10px 4px;">
                  <i class="fas fa-sun ts-icon"></i>
                  <div class="ts-name" style="font-size:0.75rem;">Morning</div>
                  <div class="ts-time" style="font-size:0.55rem;">8:00 AM – 12:00 PM</div>
                </div>
                <div class="ts-card" data-slot="12pm-5pm" onclick="selectSlotWidget(this)" style="padding: 10px 4px;">
                  <i class="fas fa-cloud-sun ts-icon"></i>
                  <div class="ts-name" style="font-size:0.75rem;">Afternoon</div>
                  <div class="ts-time" style="font-size:0.55rem;">12:00 PM – 5:00 PM</div>
                </div>
                <div class="ts-card" data-slot="full_day" onclick="selectSlotWidget(this)" style="padding: 10px 4px;">
                  <i class="fas fa-calendar-day ts-icon"></i>
                  <div class="ts-name" style="font-size:0.75rem;">Full Day</div>
                  <div class="ts-time" style="font-size:0.55rem;">8:00 AM – 5:00 PM</div>
                </div>
              </div>
            </div>

          </div>

          <!-- TAB 3 CONTENT: FLEXIBLE STAYS -->
          <div id="flexibleMainSection" style="display:none;">
            <div class="flexible-subtitle">How long do you want to stay?</div>
            <div class="flexible-pills-row">
              <div class="flexible-pill" id="flexPill3" onclick="selectFlexDuration(3, '3 nights')">3 nights</div>
              <div class="flexible-pill active" id="flexPill7" onclick="selectFlexDuration(7, '1 week')">1 week</div>
              <div class="flexible-pill" id="flexPill30" onclick="selectFlexDuration(30, '1 month')">1 month</div>
            </div>

            <div class="flexible-subtitle">When do you want to travel?</div>
            <div style="position:relative;">
              <div class="flexible-months-scroller" id="flexMonthsScroller">
                <!-- Dynamically filled with next 6 months -->
              </div>
            </div>

            <div class="flexible-actions">
              <button type="button" class="flexible-clear-btn" onclick="clearFlexibleSelection()">Clear</button>
              <button type="button" class="flexible-select-btn" onclick="applyFlexibleSelection()">Select</button>
            </div>
          </div>

        </div>

        <!-- FLOATING GUESTS SELECTOR DROPDOWN -->
        <div class="popup-dropdown-card guests-popup-card" id="guestsPopup" onclick="event.stopPropagation()">
          
          <!-- Room row -->
          <div class="guest-stepper-row">
            <div class="guest-stepper-label">
              <span class="title">Room</span>
              <span class="subtitle">Number of rooms to book</span>
            </div>
            <div class="guest-stepper-ctrls">
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('room', -1)">-</div>
              <span class="guest-stepper-val" id="valRoomStepper">0</span>
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('room', 1)">+</div>
            </div>
          </div>

          <!-- Cottage row -->
          <div class="guest-stepper-row">
            <div class="guest-stepper-label">
              <span class="title">Cottage</span>
              <span class="subtitle">Number of cottages to book</span>
            </div>
            <div class="guest-stepper-ctrls">
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('cottage', -1)">-</div>
              <span class="guest-stepper-val" id="valCottageStepper">0</span>
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('cottage', 1)">+</div>
            </div>
          </div>

          <!-- Function Hall row -->
          <div class="guest-stepper-row">
            <div class="guest-stepper-label">
              <span class="title">Function Hall</span>
              <span class="subtitle">Number of function halls to book</span>
            </div>
            <div class="guest-stepper-ctrls">
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('functionHall', -1)">-</div>
              <span class="guest-stepper-val" id="valFunctionHallStepper">0</span>
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('functionHall', 1)">+</div>
            </div>
          </div>

          <!-- Adults row -->
          <div class="guest-stepper-row">
            <div class="guest-stepper-label">
              <span class="title">Adults</span>
              <span class="subtitle">Ages 18 or above</span>
            </div>
            <div class="guest-stepper-ctrls">
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('adults', -1)">-</div>
              <span class="guest-stepper-val" id="valAdultsStepper">2</span>
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('adults', 1)">+</div>
            </div>
          </div>

          <!-- PWD / Seniors row -->
          <div class="guest-stepper-row">
            <div class="guest-stepper-label">
              <span class="title">PWD / Seniors</span>
              <span class="subtitle">20% discount (Proof required)</span>
            </div>
            <div class="guest-stepper-ctrls">
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('pwd', -1)">-</div>
              <span class="guest-stepper-val" id="valPwdStepper">0</span>
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('pwd', 1)">+</div>
            </div>
          </div>

          <!-- PWD / Senior ID Photo Uploads Container -->
          <div class="pwd-id-container" id="pwdIdContainer" style="display:none; margin-top: 10px; margin-bottom: 15px; padding: 12px; background: #f0fdf4; border: 1px dashed #16a34a; border-radius: 10px;">
            <div style="font-size: 0.85rem; font-weight: 700; color: #15803d; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
              <i class="fas fa-id-card"></i> Upload Valid ID / Proof (1 picture per PWD/Senior)
            </div>
            <div style="font-size: 0.76rem; color: #166534; margin-bottom: 10px; line-height: 1.3;">
              Please upload a picture of a Valid ID (PWD ID, Senior Citizen ID, Passport, UMID, Govt ID) for each discounted guest.
            </div>
            <div id="pwdIdList" style="display: flex; flex-direction: column; gap: 10px;"></div>
          </div>

          <!-- Children 5+ row -->
          <div class="guest-stepper-row">
            <div class="guest-stepper-label">
              <span class="title">Children (Ages 1-17)</span>
              <span class="subtitle">Free age 5 &amp; below; Regular child rate for ages 6–17</span>
            </div>
            <div class="guest-stepper-ctrls">
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('children5', -1)">-</div>
              <span class="guest-stepper-val" id="valChildren5Stepper">0</span>
              <div class="guest-stepper-btn" onclick="adjustGuestStepper('children5', 1)">+</div>
            </div>
          </div>


          <!-- Children Age Dropdowns (Booking.com style) -->
          <div class="child-ages-container" id="childAgesContainer" style="display:none;">
            <div class="child-ages-desc">For accurate facility pricing, please select your children's correct ages:</div>
            <div id="childAgesList"></div>
          </div>

        </div>

      </div>

      <!-- Live Selection Status Message -->
      <div class="cal-selected-bar" style="margin-bottom: 20px;">
        <i class="fas fa-calendar-check" style="font-size: 1.2rem;"></i>
        <span id="searchSelectionStatusText">Please select check-in date, check-out date and click SEARCH to view available facilities.</span>
      </div>

      <!-- STEP 1 SUB-CARD: FACILITY SELECTION -->
      <div class="facility-options-card" id="facilityOptionsCard">
        <div class="bk-card-hdr"><i class="fas fa-concierge-bell"></i><h2>Select Cottage/Room</h2></div>
        <div class="bk-card-body">
          
          <div class="form-group">
            <select class="form-select" id="fFacility" onchange="onFacilitySelectionChange(this.value)" style="display: none;">
              <option value="">Select Facility</option>
              <!-- Populated dynamically via JS -->
            </select>
            
            <!-- Beautiful visual facility selection catalog -->
            <div class="fac-catalog-header">
              <span class="fac-catalog-title"><i class="fas fa-map-marked-alt" style="margin-right: 6px;"></i>Available Accommodations</span>
              <div class="fac-catalog-filters">
                <button type="button" class="fac-filter-btn active" id="btnFilterAll" onclick="filterCatalog('all')">All</button>
                <button type="button" class="fac-filter-btn" id="btnFilterRoom" onclick="filterCatalog('room')">Rooms</button>
                <button type="button" class="fac-filter-btn" id="btnFilterCottage" onclick="filterCatalog('cottage')">Cottages</button>
                <button type="button" class="fac-filter-btn" id="btnFilterHall" onclick="filterCatalog('function_hall')">Halls</button>
              </div>
            </div>
            
            <div id="selectionStatusBanner" style="display:none; margin: 10px 0; padding: 12px 18px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; align-items: center; gap: 10px; border: 1.5px solid var(--border);"></div>
            <div class="fac-catalog-grid" id="facCatalogGrid">
              <!-- Dynamically populated card grid via JS -->
            </div>
            
            <div class="fac-info-box" id="facInfoBox">
              <div class="fac-info-row"><span>Selected Facility</span><strong id="facInfoName">—</strong></div>
              <div class="fac-info-row"><span>Daily Price</span><strong id="facInfoPrice">—</strong></div>
              <div class="fac-info-row"><span>Max Guest Capacity</span><strong id="facInfoCap">—</strong></div>
              <div class="fac-info-row"><span>Your Total Guests</span><strong id="facInfoGuests">—</strong></div>
            </div>
          </div>

        </div>
      </div>

      <div class="btn-row" id="step1BtnRow" style="flex-wrap:wrap;gap:10px;display:none;">
        <span></span>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <button type="button" class="btn-add-cart" id="addToCartStep1Btn" onclick="handleAddToCartStep1()" disabled title="Add selected facility to cart">
            <span class="cart-icon-pulse"><i class="fas fa-shopping-cart"></i></span>
            Add to Cart
          </button>
          <button type="button" class="btn-next" id="nextToStep2Btn" onclick="goStep(2)" disabled style="opacity:0.6;cursor:not-allowed;transform:none;box-shadow:none;">Next: Guest Profile <i class="fas fa-arrow-right"></i></button>
        </div>
      </div>
      <!-- Cart Toast Notification -->
      <div id="cartToast">
        <div class="toast-icon success" id="cartToastIcon"><i class="fas fa-check"></i></div>
        <span class="toast-msg" id="cartToastMsg">Added to cart!</span>
        <button class="toast-close" onclick="hideCartToast()"><i class="fas fa-times"></i></button>
      </div>

    </div>

    <!-- STEP 2: LOCATION & GUEST DETAILS -->
    <div class="step-panel" id="panel2">
      
      <div class="step2-cols<?php echo $is_logged_in ? ' single-col' : ''; ?>">
        <!-- STEP 2 SUB-CARD: LOCATION & TRANSPORTATION SELECTION -->
        <div class="bk-card">
          <div class="bk-card-hdr"><i class="fas fa-map-marker-alt"></i><h2>Select Location &amp; Transportation</h2></div>
          <div class="bk-card-body">
            <div class="form-group" style="margin-bottom: 15px;">
              <label>Select Spring Location Area <span class="req">*</span></label>
              <select class="form-select" id="fArea" onchange="onAreaChange(this.value)">
                <option value="">Select Location</option>
                <?php foreach ($areas_js as $a): ?>
                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
              <label>Select Time Slot <span class="req">*</span></label>
              <select class="form-select" id="fTimeSlot" onchange="onTimeSlotChange(this.value)">
                <option value="">Select Time Slot</option>
                <option value="8am-12pm">Morning (8:00 AM – 12:00 PM)</option>
                <option value="12pm-5pm">Afternoon (12:00 PM – 5:00 PM)</option>
                <option value="full_day">Full Day (8:00 AM – 5:00 PM)</option>
                <option value="overnight">Overnight (8:00 AM – 8:00 AM)</option>
              </select>
            </div>

            <div class="form-group">
              <label style="font-weight:700">Transportation Options (Optional)</label>
              <select class="form-select" id="fTransport" name="transportation" onchange="onTransportChange(this.value)">
                <option value="none">No Transportation needed</option>
                <option value="tignapoloan">Tignapoloan Crossing → Sinulom &amp; Bolao Cold Spring Resort — ₱50/person</option>
                <option value="cdo">Cagayan De Oro → Sinulom &amp; Bolao Cold Spring Resort — ₱250/person</option>
                <option value="private">Private Vehicle Rental (6 Hours) — ₱3,500</option>
              </select>
            </div>
          </div>
        </div>

        <?php if (!$is_logged_in): ?>
        <div class="bk-card">
          <div class="bk-card-hdr"><i class="fas fa-user"></i><h2>Guest Profile & Login Details</h2></div>
          <div class="bk-card-body">
            <div class="form-row">
              <div class="form-group">
                <label>First Name <span class="req">*</span></label>
                <input type="text" class="form-control capitalize-input" id="fFirstName" placeholder="Juan" autocomplete="given-name" autocapitalize="words">
              </div>
              <div class="form-group">
                <label>Last Name <span class="req">*</span></label>
                <input type="text" class="form-control capitalize-input" id="fLastName" placeholder="dela Cruz" autocomplete="family-name" autocapitalize="words">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Contact Number <span class="req">*</span></label>
                <input type="tel" class="form-control" id="fPhone" placeholder="09123456789" maxlength="11" autocomplete="tel" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
              </div>
              <div class="form-group">
                <label>Email Address <span class="req">*</span></label>
                <input type="email" class="form-control" id="fEmail" placeholder="juan@email.com" autocomplete="email">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Password <span class="req">*</span></label>
                <div style="position:relative;">
                  <input type="password" class="form-control" id="fPassword" placeholder="Create a password (min. 6 chars)" autocomplete="new-password" style="padding-right:44px;">
                  <button type="button" onclick="togglePwVis('fPassword','eyePass')" tabindex="-1" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.9rem;"><i class="fas fa-eye" id="eyePass"></i></button>
                </div>
                <small style="color:#6b7280;font-size:.78rem;"><i class="fas fa-info-circle"></i> You will use this password to log in at <strong>Guest Login</strong> after booking.</small>
              </div>
              <div class="form-group">
                <label>Confirm Password <span class="req">*</span></label>
                <div style="position:relative;">
                  <input type="password" class="form-control" id="fConfirmPassword" placeholder="Re-enter password" autocomplete="new-password" style="padding-right:44px;">
                  <button type="button" onclick="togglePwVis('fConfirmPassword','eyeConfirm')" tabindex="-1" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.9rem;"><i class="fas fa-eye" id="eyeConfirm"></i></button>
                </div>
              </div>
            </div>
            <div id="pwMatchMsg" style="display:none;font-size:.8rem;margin-top:-8px;margin-bottom:8px;"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="btn-row">
        <button type="button" class="btn-back" onclick="goStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
        <button type="button" class="btn-next" onclick="goStep(3)">Next: Review Booking <i class="fas fa-arrow-right"></i></button>
      </div>
    </div>

    <!-- STEP 3: CONFIRM (Summary - Same as original Step 4) -->
    <div class="step-panel" id="panel3">
      <div class="bk-card">
        <div class="bk-card-hdr"><i class="fas fa-check-circle"></i><h2>Booking Review &amp; Confirmation</h2></div>
        <div class="bk-card-body">
          <div class="summary-cols">
            <!-- LEFT: Reservation Details -->
            <div class="summary-card">
              <div class="summary-card-hdr">Reservation Details</div>
              <div class="summary-row"><span class="lbl">Schedule Date</span><span class="val" id="sumDate">—</span></div>
              <div class="summary-row"><span class="lbl">Time Slot / Mode</span><span class="val" id="sumSlot">—</span></div>
              <div class="summary-row"><span class="lbl">Guest Name</span><span class="val" id="sumName">—</span></div>
              <div class="summary-row"><span class="lbl">Contact</span><span class="val" id="sumPhone">—</span></div>
              <div class="summary-row"><span class="lbl">Email</span><span class="val" id="sumEmail">—</span></div>
              <div class="summary-row"><span class="lbl">Booking Mode</span><span class="val" id="sumMode">—</span></div>
              <div class="summary-row"><span class="lbl">Location Area</span><span class="val" id="sumArea">—</span></div>
              <div class="summary-row"><span class="lbl">Cottage / Facility</span><span class="val" id="sumFacility">—</span></div>
              <div class="summary-row"><span class="lbl">Check-in</span><span class="val" id="sumCheckIn">—</span></div>
              <div class="summary-row"><span class="lbl">Check-out</span><span class="val" id="sumCheckOut">—</span></div>
              <div class="summary-row"><span class="lbl">Adults</span><span class="val" id="sumAdults">—</span></div>
              <div class="summary-row"><span class="lbl">Children (Ages 6–17)</span><span class="val" id="sumChildren">—</span></div>
              <div class="summary-row"><span class="lbl">Children Age 5 &amp; Below <span style="background:#d1fae5;color:#065f46;font-size:10px;padding:1px 6px;border-radius:50px;font-weight:700;">FREE</span></span><span class="val" id="sumBelow5">—</span></div>
              <div class="summary-row"><span class="lbl">PWD / Seniors</span><span class="val" id="sumPwd">—</span></div>
            </div>
            <!-- RIGHT: Price Breakdown -->
            <div class="summary-card">
              <div class="summary-card-hdr" style="background:var(--gl);">Price Breakdown</div>
              <div class="summary-row"><span class="lbl">Facility Price</span><span class="val" id="sumFacilityPrice">—</span></div>
              <div class="summary-row"><span class="lbl">Adults (<span id="sumAdultRate">—</span>/pax)</span><span class="val" id="sumAdultTotal">—</span></div>
              <div class="summary-row"><span class="lbl">Children Ages 6–17 (<span id="sumChildRate">—</span>/pax)</span><span class="val" id="sumChildTotal">—</span></div>
              <div class="summary-row"><span class="lbl">Children Age 5 &amp; Below <span style="background:#d1fae5;color:#065f46;font-size:10px;padding:1px 6px;border-radius:50px;font-weight:700;">FREE</span></span><span class="val" id="sumBelow5Total" style="color:#059669;font-weight:700;">FREE</span></div>
              <div class="summary-row"><span class="lbl">PWD/Seniors (<span id="sumPwdRate">—</span>/pax)</span><span class="val" id="sumPwdTotal">—</span></div>
              <div class="summary-row"><span class="lbl">Location Access Fees</span><span class="val" id="sumLocationTotal">—</span></div>
              <div class="summary-row"><span class="lbl" id="sumTransportLbl">Transportation</span><span class="val" id="sumTransportTotal">—</span></div>
              <div class="summary-row" style="border-top:2px solid var(--border);margin-top:4px;"><span class="lbl" style="font-weight:700;">Subtotal</span><span class="val" id="sumSubtotal">—</span></div>
              <div class="summary-row"><span class="lbl">VAT (<?= number_format($vat_rate, 2) ?>%)</span><span class="val" id="sumVat">—</span></div>
              <div class="summary-total-row"><span class="lbl">Total Amount (VAT Inclusive)</span><span class="val" id="sumTotal">—</span></div>
            </div>
          </div><!-- /.summary-cols -->
          <!-- Terms & Conditions Modal -->
          <div class="terms-modal-overlay" id="termsModal">
            <div class="terms-modal">
              <div class="terms-modal-hdr">
                <h3><i class="fas fa-file-contract"></i> Resort Policy &amp; Terms and Conditions</h3>
                <button class="terms-modal-close" onclick="closeTermsModal()"><i class="fas fa-times"></i></button>
              </div>
              <!-- Tabs -->
              <div class="terms-modal-tabs">
                <button class="terms-modal-tab active" id="tabBtnPrivacy" onclick="switchTermsTab('privacy')">
                  <i class="fas fa-shield-alt"></i> Privacy Policy
                </button>
                <button class="terms-modal-tab" id="tabBtnTerms" onclick="switchTermsTab('terms')">
                  <i class="fas fa-file-alt"></i> Terms &amp; Conditions
                </button>
              </div>
              <div class="terms-modal-body">

                <!-- ── PRIVACY POLICY TAB ── -->
                <div class="terms-tab-pane active" id="tabPrivacy">
                  <p style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;color:#166534;font-weight:600;margin-bottom:18px;">
                    <i class="fas fa-leaf" style="margin-right:8px;"></i>Sinulom and Bolao Cold Spring respects the privacy of its guests and is committed to protecting personal information in accordance with the <strong>Data Privacy Act of 2012</strong> of the Philippines.
                  </p>

                  <h4><i class="fas fa-clipboard-list" style="margin-right:6px;color:var(--gd)"></i>1. Information We Collect</h4>
                  <p>We may collect the following information during reservations, payments, and guest transactions:</p>
                  <ul>
                    <li>Full name</li>
                    <li>Contact number</li>
                    <li>Email address</li>
                    <li>Booking and reservation details</li>
                    <li>Payment information and transaction records</li>
                    <li>Valid government-issued identification when required</li>
                    <li>Senior Citizen ID or PWD ID for discount verification, when applicable</li>
                  </ul>

                  <h4><i class="fas fa-bullseye" style="margin-right:6px;color:var(--gd)"></i>2. Purpose of Collection</h4>
                  <p>Personal information is collected for the following purposes:</p>
                  <ul>
                    <li>Processing reservations, payments, and guest transactions</li>
                    <li>Providing customer service and support</li>
                    <li>Verifying guest identity and eligibility for Senior Citizen or PWD discounts</li>
                    <li>Managing room, cottage, hall, and facility reservations</li>
                    <li>Improving Resort services and guest experience</li>
                    <li>Maintaining safety and security within the Resort premises</li>
                    <li>Complying with legal and regulatory requirements</li>
                  </ul>

                  <h4><i class="fas fa-lock" style="margin-right:6px;color:var(--gd)"></i>3. Data Protection</h4>
                  <ul>
                    <li>Guest information is kept secure and accessible only to authorized personnel.</li>
                    <li>The Resort implements reasonable measures to protect personal information from unauthorized access, disclosure, alteration, or misuse.</li>
                    <li>The Resort does not sell, rent, or share personal information with unauthorized third parties.</li>
                  </ul>

                  <h4><i class="fas fa-video" style="margin-right:6px;color:var(--gd)"></i>4. CCTV Monitoring</h4>
                  <ul>
                    <li>CCTV cameras may operate in selected public areas of the Resort for safety, security, and incident monitoring purposes.</li>
                  </ul>

                  <h4><i class="fas fa-user-shield" style="margin-right:6px;color:var(--gd)"></i>5. Guest Rights</h4>
                  <p>Guests may request access to, correction of, or deletion of their personal information, subject to applicable laws, legal obligations, and Resort policies.</p>

                  <h4><i class="fas fa-sync-alt" style="margin-right:6px;color:var(--gd)"></i>6. Policy Updates</h4>
                  <p>Sinulom and Bolao Cold Spring reserves the right to update this Privacy Policy at any time without prior notice. Any changes shall take effect upon posting on the Resort's website, booking system, or official communication channels.</p>

                  <h4><i class="fas fa-envelope" style="margin-right:6px;color:var(--gd)"></i>7. Contact Information</h4>
                  <div style="background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;padding:14px 18px;line-height:2;">
                    <strong style="color:var(--gd);display:block;margin-bottom:6px;">SINULOM FALLS AGRO/ECOTOURISM PARK &amp; ADVENTURE RESORT, INC.</strong>
                    <span style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#374151;"><i class="fas fa-map-marker-alt" style="color:var(--gd);width:14px;"></i> Tambo, Impakibil, Tignapoloan, Cagayan de Oro City</span>
                    <span style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#374151;"><i class="fas fa-phone" style="color:var(--gd);width:14px;"></i> 0917-722-4999 / 0907-227-5353</span>
                    <span style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#374151;"><i class="fas fa-envelope" style="color:var(--gd);width:14px;"></i> sinulomfalls@gmail.com</span>
                  </div>
                  <p style="margin-top:16px;padding:12px 14px;background:#fefce8;border-radius:8px;border:1px solid #fde68a;color:#92400e;font-size:.82rem;">
                    <i class="fas fa-info-circle" style="margin-right:6px;"></i>By checking the agreement box, you confirm that you have read, understood, and agree to all the above Privacy Policy terms.
                  </p>
                </div>

                <!-- ── TERMS & CONDITIONS TAB ── -->
                <div class="terms-tab-pane" id="tabTerms">
                  <p style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 16px;color:#1e40af;font-weight:600;margin-bottom:18px;">
                    <i class="fas fa-handshake" style="margin-right:8px;"></i>Welcome to Sinulom and Bolao Cold Spring. By making a reservation, entering the premises, or using our booking system and facilities, guests agree to the following Terms and Conditions.
                  </p>

                  <h4><i class="fas fa-credit-card" style="margin-right:6px;color:var(--gd)"></i>1. Reservations and Payments</h4>
                  <ul>
                    <li>Reservations for rooms, cottages, halls, and other facilities are subject to availability.</li>
                    <li>Bookings are confirmed only after payment of the required reservation fee or upon approved reservation confirmation.</li>
                    <li>The Resort accepts payments through <strong>Cash</strong> and <strong>GCash</strong>.</li>
                    <li>Full payment must be settled before the use of the reserved facility unless otherwise approved by management.</li>
                    <li>Guests are responsible for providing accurate payment and reservation information.</li>
                  </ul>

                  <h4><i class="fas fa-clock" style="margin-right:6px;color:var(--gd)"></i>2. Operating Hours</h4>
                  <ul>
                    <li>Resort operating hours are from <strong>8:00 AM to 5:00 PM</strong>.</li>
                    <li>Guests are expected to follow check-in, check-out, and facility schedules provided by management.</li>
                  </ul>

                  <h4><i class="fas fa-users" style="margin-right:6px;color:var(--gd)"></i>3. Guest Responsibilities</h4>
                  <ul>
                    <li>Guests must behave respectfully toward staff and other visitors.</li>
                    <li>Damages to Resort property caused by guests shall be charged accordingly.</li>
                    <li>Illegal activities, excessive noise, dangerous behavior, and vandalism are strictly prohibited.</li>
                  </ul>

                  <h4><i class="fas fa-swimming-pool" style="margin-right:6px;color:var(--gd)"></i>4. Pool and Facility Use</h4>
                  <ul>
                    <li>Guests use pools and Resort facilities at their own risk.</li>
                    <li>Children must always be supervised by their parents or guardians.</li>
                    <li>The Resort is not liable for accidents, injuries, lost belongings, or damages resulting from guest negligence or failure to follow Resort rules.</li>
                  </ul>

                  <h4><i class="fas fa-undo" style="margin-right:6px;color:var(--gd)"></i>5. Cancellation, Rescheduling, and Refunds</h4>
                  <ul>
                    <li>Cancellation of reservations on the day of the booking is <strong>not allowed</strong>.</li>
                    <li>Reservation payments and deposits are <strong>non-refundable</strong>.</li>
                    <li>Rescheduling is permitted within <strong>one (1) month</strong> from the original booking date, subject to availability.</li>
                    <li>Requests for rescheduling must be made at least <strong>two (2) days</strong> before the scheduled booking date. Failure to notify the Resort within the required period may result in forfeiture of the deposit.</li>
                    <li>No-shows and unused reservations are strictly <strong>non-refundable</strong>.</li>
                    <li>Any exception to this policy shall be subject to management approval.</li>
                  </ul>

                  <h4><i class="fas fa-id-card" style="margin-right:6px;color:var(--gd)"></i>6. Senior Citizen and PWD Discounts</h4>
                  <ul>
                    <li>Guests claiming Senior Citizen or PWD discounts must upload or present a valid and unexpired Senior Citizen ID or PWD ID during reservation or upon check-in.</li>
                    <li>The name on the submitted ID must match the reservation details.</li>
                    <li>Failure to provide valid proof of eligibility may result in the discount being denied and the regular rate being applied.</li>
                    <li>The Resort reserves the right to verify the authenticity of submitted identification documents.</li>
                    <li>Discounts shall be granted in accordance with applicable Philippine laws and Resort policies.</li>
                  </ul>

                  <h4><i class="fas fa-ban" style="margin-right:6px;color:var(--gd)"></i>7. Right to Refuse Service</h4>
                  <p>The Resort reserves the right to refuse entry or remove guests who violate Resort rules, disturb other guests, engage in unlawful activities, or create safety and security concerns.</p>

                  <h4><i class="fas fa-edit" style="margin-right:6px;color:var(--gd)"></i>8. Changes to Terms</h4>
                  <p>Sinulom and Bolao Cold Spring reserves the right to modify or update these Terms and Conditions at any time without prior notice.</p>

                  <h4><i class="fas fa-envelope" style="margin-right:6px;color:var(--gd)"></i>9. Contact Information</h4>
                  <div style="background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;padding:14px 18px;line-height:2;">
                    <strong style="color:var(--gd);display:block;margin-bottom:6px;">SINULOM FALLS AGRO/ECOTOURISM PARK &amp; ADVENTURE RESORT, INC.</strong>
                    <span style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#374151;"><i class="fas fa-map-marker-alt" style="color:var(--gd);width:14px;"></i> Tambo, Impakibil, Tignapoloan, Cagayan de Oro City</span>
                    <span style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#374151;"><i class="fas fa-phone" style="color:var(--gd);width:14px;"></i> 0917-722-4999 / 0907-227-5353</span>
                    <span style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#374151;"><i class="fas fa-envelope" style="color:var(--gd);width:14px;"></i> sinulomfalls@gmail.com</span>
                  </div>
                  <p style="margin-top:16px;padding:12px 14px;background:#fefce8;border-radius:8px;border:1px solid #fde68a;color:#92400e;font-size:.82rem;">
                    <i class="fas fa-info-circle" style="margin-right:6px;"></i>By checking the agreement box, you confirm that you have read, understood, and agree to these Terms and Conditions.
                  </p>
                </div>

              </div><!-- /.terms-modal-body -->
              <div class="terms-modal-footer">
                <button onclick="closeTermsModal(true)"><i class="fas fa-check" style="margin-right:6px;"></i>I Understand</button>
              </div>
            </div>
          </div>


          <div class="terms-checks-wrap">
            <div class="terms-check">
              <input type="checkbox" id="detailsCheck" onchange="updateConfirmBtn()">
              <label for="detailsCheck">I confirm that all the provided guest and booking details are correct.</label>
            </div>
            <div class="terms-check">
              <input type="checkbox" id="termsCheck" onchange="updateConfirmBtn()">
              <label for="termsCheck">I have read and agree to the resort's <a onclick="event.stopPropagation(); openTermsModal('privacy')">Privacy Policy</a> and <a onclick="event.stopPropagation(); openTermsModal('terms')">Terms &amp; Conditions</a>.</label>
            </div>
          </div>
          <?php if ($is_cart_booking && empty($selected_cart_id)): ?>
            <?php if (!empty($edit_cart_id)): ?>
              <button type="submit" class="btn-confirm" id="confirmBtn" disabled>
                <i class="fas fa-save"></i> Save Changes
              </button>
            <?php else: ?>
              <button type="submit" class="btn-confirm" id="confirmBtn" disabled>
                <i class="fas fa-shopping-cart"></i> Add to Cart
              </button>
            <?php endif; ?>
          <?php else: ?>
            <button type="submit" class="btn-confirm" id="confirmBtn" disabled>
              <i class="fas fa-check-circle"></i> Submit Booking
            </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="btn-row">
        <button type="button" class="btn-back" onclick="goStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
        <span></span>
      </div>
    </div>

  </form>
</div>

<script>
/* ── DATA FROM PHP ── */
const BOOKINGS = <?php echo json_encode($all_bookings); ?>;
const FACILITIES   = <?php echo json_encode($facilities_js); ?>;
const AREAS        = <?php echo json_encode($areas_js); ?>;
const FAC_COUNT = FACILITIES.length;

function fmtDate(d) {
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

function formatDisplayDate(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
}

function slotConflicts(slotA, slotB) {
  if (slotA === slotB) return true;
  if (slotA === 'overnight' || slotB === 'overnight') return true;
  if (slotA === 'full_day' && (slotB === '8am-12pm' || slotB === '12pm-5pm' || slotB === '8am-5pm')) return true;
  if (slotB === 'full_day' && (slotA === '8am-12pm' || slotA === '12pm-5pm' || slotA === '8am-5pm')) return true;
  if (slotA === '8am-5pm' && (slotB === '8am-12pm' || slotB === '12pm-5pm' || slotB === 'full_day')) return true;
  if (slotB === '8am-5pm' && (slotA === '8am-12pm' || slotA === '12pm-5pm' || slotA === 'full_day')) return true;
  return false;
}

function getBookedFacilities(dateStr, slotStr) {
  const targetDateIn = new Date(dateStr + 'T00:00:00');
  let targetDateOut = new Date(dateStr + 'T00:00:00');
  if (slotStr === 'overnight') {
      targetDateOut.setDate(targetDateOut.getDate() + 1);
  }

  const bookedFacIds = new Set();
  BOOKINGS.forEach(b => {
    // Skip this booking if it's the one we are editing
    const editCartIdInput = document.querySelector('input[name="edit_cart_id"]');
    if (editCartIdInput && b.id && editCartIdInput.value === b.id.toString() && b.is_cart_item) return;

    const bStart = new Date(b.check_in_date + 'T00:00:00');
    const bEnd   = b.check_out_date ? new Date(b.check_out_date + 'T00:00:00') : bStart;
    
    let isOverlap = false;
    if (b.mode === 'overnight') {
        if (slotStr === 'overnight') {
            // Overlap: [targetIn, targetOut) overlaps [bStart, bEnd) — checkout day is FREE
            isOverlap = (Math.max(targetDateIn.getTime(), bStart.getTime()) < Math.min(targetDateOut.getTime(), bEnd.getTime()));
        } else {
            // Daytour on a date: blocked only if strictly inside the overnight range (not on checkout day)
            isOverlap = (targetDateIn >= bStart && targetDateIn < bEnd);
        }
    } else {
        if (slotStr === 'overnight') {
            // Overnight trying to book: daytour blocks only its own day
            isOverlap = (bStart >= targetDateIn && bStart < targetDateOut);
        } else {
            isOverlap = (targetDateIn.getTime() === bStart.getTime());
        }
    }

    if (isOverlap) {
      const fids = b.facility_id ? b.facility_id.toString().split(',') : [];
      fids.forEach(fid => {
        if (b.mode === 'overnight' || slotStr === 'overnight') {
            bookedFacIds.add(fid);
        } else if (slotConflicts(b.time_slot, slotStr)) {
            bookedFacIds.add(fid);
        }
      });
    }
  });
  return bookedFacIds;
}

/* ── STATE MANAGEMENT ── */
let currentStep   = 1;
let bookingMode   = 'overnight'; // 'overnight', 'daytour', 'flexible'
let checkInDate   = null;
let checkOutDate  = null;
let selectedSlot  = 'overnight'; // Overnight default
let currentYear, currentMonth;  // For dual calendar left side index

// Steppers Guest counts
let guestsState = {
  room: 0,
  cottage: 0,
  functionHall: 0,
  adults: 2,
  pwd: 0,
  children5: 0,
  below5: 0
};

const SLOT_LABELS = {
  '8am-12pm'  : 'Morning (8:00 AM – 12:00 PM)',
  '12pm-5pm'  : 'Afternoon (12:00 PM – 5:00 PM)',
  'full_day'  : 'Full Day (8:00 AM – 5:00 PM)',
  'overnight' : 'Overnight (8:00 AM – 8:00 AM)'
};

/* ── POPUP TRIGGERS ── */
function togglePopup(popupId) {
  const dateCard  = document.getElementById('datePopup');
  const guestCard = document.getElementById('guestsPopup');
  const overlay   = document.getElementById('popupOverlay');
  const boxIn     = document.getElementById('boxCheckIn');
  const boxOut    = document.getElementById('boxCheckOut');
  const boxG      = document.getElementById('boxGuests');
  const boxTime   = document.getElementById('boxTimeSlot');

  boxIn.classList.remove('active');
  boxOut.classList.remove('active');
  boxG.classList.remove('active');
  if (boxTime) boxTime.classList.remove('active');

  if (popupId === 'datePopup') {
    if (dateCard.classList.contains('show')) {
      closeAllPopups();
    } else {
      guestCard.classList.remove('show');
      dateCard.classList.add('show');
      overlay.classList.add('show');
      boxIn.classList.add('active');
      boxOut.classList.add('active');
      if (boxTime) boxTime.classList.add('active');
      renderDualCalendars();
    }
  } else if (popupId === 'guestsPopup') {
    if (guestCard.classList.contains('show')) {
      closeAllPopups();
    } else {
      dateCard.classList.remove('show');
      guestCard.classList.add('show');
      overlay.classList.add('show');
      boxG.classList.add('active');
    }
  }
}

function closeAllPopups() {
  document.getElementById('datePopup').classList.remove('show');
  document.getElementById('guestsPopup').classList.remove('show');
  document.getElementById('popupOverlay').classList.remove('show');
  document.getElementById('boxCheckIn').classList.remove('active');
  document.getElementById('boxCheckOut').classList.remove('active');
  document.getElementById('boxGuests').classList.remove('active');
  const boxTime = document.getElementById('boxTimeSlot');
  if (boxTime) boxTime.classList.remove('active');
}

/* ── OUTSIDE-CLICK: close popups when clicking outside them ── */
document.addEventListener('mousedown', function(e) {
  const dateCard    = document.getElementById('datePopup');
  const guestCard   = document.getElementById('guestsPopup');
  const boxIn       = document.getElementById('boxCheckIn');
  const boxOut      = document.getElementById('boxCheckOut');
  const boxG        = document.getElementById('boxGuests');
  const boxTime     = document.getElementById('boxTimeSlot');
  const searchBtn   = document.querySelector('.search-submit-btn');

  const dateOpen  = dateCard  && dateCard.classList.contains('show');
  const guestOpen = guestCard && guestCard.classList.contains('show');
  if (!dateOpen && !guestOpen) return;

  const insideDateTrigger  = (boxIn  && boxIn.contains(e.target))  ||
                             (boxOut && boxOut.contains(e.target))  ||
                             (boxTime && boxTime.contains(e.target));
  const insideDatePopup    = dateCard  && dateCard.contains(e.target);
  const insideGuestTrigger = boxG && boxG.contains(e.target);
  const insideGuestPopup   = guestCard && guestCard.contains(e.target);
  const insideSearchBtn    = searchBtn && searchBtn.contains(e.target);

  if (dateOpen  && !insideDatePopup  && !insideDateTrigger  && !insideSearchBtn) { closeAllPopups(); return; }
  if (guestOpen && !insideGuestPopup && !insideGuestTrigger && !insideSearchBtn) { closeAllPopups(); return; }
});

/* ── TAB SWITCHING ── */
function switchCalendarTab(mode) {
  bookingMode = mode;
  document.querySelectorAll('.popup-tab').forEach(t => t.classList.remove('active'));
  
  const tabOvernight = document.getElementById('tabOvernight');
  const tabDayUse = document.getElementById('tabDayUse');
  const tabFlexible = document.getElementById('tabFlexible');
  const calMain = document.getElementById('calendarMainSection');
  const flexMain = document.getElementById('flexibleMainSection');
  const dayuseBanner = document.getElementById('dayuseInfoBanner');
  const dayuseSlots = document.getElementById('dayuseSlotSection');

  if (mode === 'overnight') {
    tabOvernight.classList.add('active');
    calMain.style.display = 'block';
    flexMain.style.display = 'none';
    dayuseBanner.style.display = 'none';
    dayuseSlots.style.display = 'none';
    selectedSlot = 'overnight';
    document.getElementById('hMode').value = 'overnight';
    // Reset selections if they switch
    checkInDate = null;
    checkOutDate = null;
    updateSearchBarDisplays();
    renderDualCalendars();
  } else if (mode === 'daytour') {
    tabDayUse.classList.add('active');
    calMain.style.display = 'block';
    flexMain.style.display = 'none';
    dayuseBanner.style.display = 'flex';
    dayuseSlots.style.display = 'block';
    selectedSlot = 'full_day'; // Default to full day for Daytour
    document.getElementById('hMode').value = 'daytour';
    checkInDate = null;
    checkOutDate = null;
    updateSearchBarDisplays();
    renderDualCalendars();
    // Preselect full day card
    const cards = document.querySelectorAll('#dayuseSlotSection .ts-card');
    cards.forEach(c => {
      c.classList.remove('selected');
      if (c.dataset.slot === 'full_day') c.classList.add('selected');
    });
    document.getElementById('dayuseSelectedSlotText').textContent = 'Selected slot: ' + SLOT_LABELS[selectedSlot];
  } else if (mode === 'flexible') {
    tabFlexible.classList.add('active');
    calMain.style.display = 'none';
    flexMain.style.display = 'block';
    renderFlexibleMonths();
  }
}

/* ── CALENDAR NAVIGATION & RENDER ── */
function navigateCalendar(dir) {
  currentMonth += dir;
  if (currentMonth < 0) {
    currentMonth = 11;
    currentYear--;
  } else if (currentMonth > 11) {
    currentMonth = 0;
    currentYear++;
  }
  renderDualCalendars();
}

function renderDualCalendars() {
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  const leftMonth = currentMonth;
  const leftYear = currentYear;
  let rightMonth = currentMonth + 1;
  let rightYear = currentYear;
  if (rightMonth > 11) {
    rightMonth = 0;
    rightYear++;
  }

  document.getElementById('monthNameLeft').textContent = MONTHS[leftMonth] + ' ' + leftYear;
  document.getElementById('monthNameRight').textContent = MONTHS[rightMonth] + ' ' + rightYear;

  renderMonthGrid('daysLeft', leftYear, leftMonth);
  renderMonthGrid('daysRight', rightYear, rightMonth);
}

function renderMonthGrid(gridId, year, month) {
  const grid = document.getElementById(gridId);
  grid.innerHTML = '';

  const today = new Date(); today.setHours(0,0,0,0);
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  // Booked sets
  const bookedSet = buildBookedSet();

  // Leading empty cells
  for (let i = 0; i < firstDay; i++) {
    const cell = document.createElement('div');
    cell.className = 'calendar-day-cell empty';
    grid.appendChild(cell);
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month, day);
    const key = fmtDate(date);
    const cell = document.createElement('div');
    cell.className = 'calendar-day-cell';
    cell.textContent = day;

    if (date < today) {
      cell.classList.add('past');
    } else if (bookedSet.has(key)) {
      cell.classList.add('booked');
    } else {
      cell.addEventListener('click', () => handleDateClick(key, date));
    }

    if (key === fmtDate(today)) cell.classList.add('today');

    // Range Highlight classes
    if (checkInDate && key === checkInDate) {
      cell.classList.add('range-start');
    }
    if (checkOutDate && key === checkOutDate) {
      cell.classList.add('range-end');
    }
    if (checkInDate && checkOutDate && key > checkInDate && key < checkOutDate) {
      cell.classList.add('range-between');
    }

    grid.appendChild(cell);
  }
}

function handleDateClick(dateStr, dateObj) {
  if (bookingMode === 'daytour') {
    // Single date pick
    checkInDate = dateStr;
    checkOutDate = dateStr;
    updateSearchBarDisplays();
    renderDualCalendars();
    // Do not automatically close yet if slot is needed, or close if slot already selected
    if (selectedSlot) {
      setTimeout(closeAllPopups, 300);
    }
  } else {
    // Overnight Stays Range picking
    if (!checkInDate || (checkInDate && checkOutDate)) {
      checkInDate = dateStr;
      checkOutDate = null;
    } else if (checkInDate && !checkOutDate) {
      if (dateStr < checkInDate) {
        checkInDate = dateStr;
      } else if (dateStr === checkInDate) {
        // Double click same day is allowed as 1-night stay check-out next day
        const nextDay = new Date(dateObj);
        nextDay.setDate(nextDay.getDate() + 1);
        checkOutDate = fmtDate(nextDay);
        setTimeout(closeAllPopups, 300);
      } else {
        checkOutDate = dateStr;
        setTimeout(closeAllPopups, 300);
      }
    }
    updateSearchBarDisplays();
    renderDualCalendars();
  }
}

function selectSlotWidget(card) {
  if (card.classList.contains('unavail')) return;
  document.querySelectorAll('#dayuseSlotSection .ts-card').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  selectedSlot = card.dataset.slot;
  document.getElementById('hTimeSlot').value = selectedSlot;
  document.getElementById('dayuseSelectedSlotText').textContent = 'Selected slot: ' + SLOT_LABELS[selectedSlot];

  updateSearchBarDisplays();

  if (checkInDate) {
    setTimeout(closeAllPopups, 300);
  }
}

function updateTimeSlotOptions() {
  const fTimeSlot = document.getElementById('fTimeSlot');
  if (!fTimeSlot) return;

  if (bookingMode === 'overnight') {
    selectedSlot = 'overnight';
    document.getElementById('hTimeSlot').value = 'overnight';
    
    fTimeSlot.innerHTML = '<option value="overnight">Overnight (8:00 AM – 8:00 AM)</option>';
    fTimeSlot.value = 'overnight';
    fTimeSlot.disabled = true;
    fTimeSlot.style.backgroundColor = '#f3f4f6';
    fTimeSlot.style.cursor = 'not-allowed';
    return;
  }

  // Daytour mode
  fTimeSlot.disabled = false;
  fTimeSlot.style.backgroundColor = '';
  fTimeSlot.style.cursor = '';

  const daytourSlots = [
    { value: '8am-12pm', label: 'Morning (8:00 AM – 12:00 PM)' },
    { value: '12pm-5pm', label: 'Afternoon (12:00 PM – 5:00 PM)' },
    { value: 'full_day', label: 'Full Day (8:00 AM – 5:00 PM)' }
  ];

  let availableOptions = [];

  if (!checkInDate) {
    availableOptions = daytourSlots;
  } else {
    daytourSlots.forEach(slotObj => {
      const slot = slotObj.value;
      const bookedFacs = getBookedFacilities(checkInDate, slot);
      let isBooked = false;

      if (selectedFacilityIds && selectedFacilityIds.length > 0) {
        // Booked if ANY of the selected facilities is booked for this slot
        isBooked = selectedFacilityIds.some(fid => bookedFacs.has(fid.toString()));
      } else {
        // If no facility is selected yet, check if ALL facilities are booked for this slot
        const totalFacCount = (FACILITIES && FACILITIES.length) ? FACILITIES.length : 1;
        isBooked = bookedFacs.size >= totalFacCount;
      }

      if (!isBooked) {
        availableOptions.push(slotObj);
      }
    });
  }

  // Update dayuseSlotSection cards in calendar popup if present
  const slotCards = document.querySelectorAll('#dayuseSlotSection .ts-card');
  slotCards.forEach(card => {
    const cardSlot = card.dataset.slot;
    if (checkInDate) {
      const bookedFacs = getBookedFacilities(checkInDate, cardSlot);
      let isBooked = false;
      if (selectedFacilityIds && selectedFacilityIds.length > 0) {
        isBooked = selectedFacilityIds.some(fid => bookedFacs.has(fid.toString()));
      } else {
        const totalFacCount = (FACILITIES && FACILITIES.length) ? FACILITIES.length : 1;
        isBooked = bookedFacs.size >= totalFacCount;
      }
      card.classList.toggle('unavail', isBooked);
    } else {
      card.classList.remove('unavail');
    }
  });

  if (availableOptions.length === 0) {
    fTimeSlot.innerHTML = '<option value="">No Time Slots Available (All Booked)</option>';
    fTimeSlot.value = '';
    fTimeSlot.disabled = true;
    fTimeSlot.style.backgroundColor = '#f3f4f6';
    fTimeSlot.style.cursor = 'not-allowed';
    selectedSlot = '';
    document.getElementById('hTimeSlot').value = '';
  } else {
    let optionsHtml = '<option value="">Select Time Slot</option>';
    let currentStillAvailable = false;

    availableOptions.forEach(opt => {
      if (opt.value === selectedSlot) currentStillAvailable = true;
      optionsHtml += `<option value="${opt.value}">${opt.label}</option>`;
    });

    fTimeSlot.innerHTML = optionsHtml;

    if (selectedSlot && currentStillAvailable) {
      fTimeSlot.value = selectedSlot;
    } else if (selectedSlot === 'overnight' || !currentStillAvailable) {
      if (availableOptions.length === 1) {
        selectedSlot = availableOptions[0].value;
        fTimeSlot.value = selectedSlot;
      } else {
        selectedSlot = '';
        fTimeSlot.value = '';
      }
    }
    document.getElementById('hTimeSlot').value = selectedSlot;
  }
}

function updateSearchBarDisplays() {
  const valIn = document.getElementById('valCheckIn');
  const valOut = document.getElementById('valCheckOut');
  const valTimeSlot = document.getElementById('valTimeSlot');

  if (checkInDate) {
    valIn.textContent = formatDisplayDate(checkInDate);
    document.getElementById('hCheckIn').value = checkInDate;
  } else {
    valIn.textContent = 'Select date';
    document.getElementById('hCheckIn').value = '';
  }

  if (checkOutDate) {
    valOut.textContent = bookingMode === 'daytour' ? 'Same day checkout' : formatDisplayDate(checkOutDate);
    document.getElementById('hCheckOut').value = checkOutDate;
  } else {
    valOut.textContent = bookingMode === 'daytour' ? 'Same day checkout' : 'Select date';
    document.getElementById('hCheckOut').value = '';
  }

  // Update time slot dropdown options based on mode & booked slots
  updateTimeSlotOptions();
  
  if (valTimeSlot) {
    let slotText = 'Select time';
    if (selectedSlot) {
      if (selectedSlot === 'overnight') {
        slotText = 'Overnight (8am-8am)';
      } else if (selectedSlot === '8am-12pm') {
        slotText = 'Morning (8am-12pm)';
      } else if (selectedSlot === '12pm-5pm') {
        slotText = 'Afternoon (12pm-5pm)';
      } else if (selectedSlot === 'full_day') {
        slotText = 'Full Day (8am-5pm)';
      } else {
        slotText = SLOT_LABELS[selectedSlot] || selectedSlot;
      }
    }
    valTimeSlot.textContent = slotText;
  }
  
  document.getElementById('hTimeSlot').value = selectedSlot;
  document.getElementById('hMode').value = bookingMode === 'daytour' ? 'daytour' : 'overnight';

  const fTimeSlot = document.getElementById('fTimeSlot');
  if (fTimeSlot && selectedSlot) {
    fTimeSlot.value = selectedSlot;
  }
}

function buildBookedSet() {
  const s = new Set();
  if (FAC_COUNT === 0) return s;
  const activeDates = new Set();
  BOOKINGS.forEach(b => {
    const start = new Date(b.check_in_date  + 'T00:00:00');
    // For overnight bookings, exclude the checkout date (guests check OUT that day — it's free again)
    const end   = (b.mode === 'overnight' && b.check_out_date)
                    ? new Date(b.check_out_date + 'T00:00:00')
                    : (b.check_out_date ? new Date(b.check_out_date + 'T00:00:00') : start);
    // Use strict < end so checkout day is NOT marked as booked
    for (let d = new Date(start); d < end; d.setDate(d.getDate()+1)) activeDates.add(fmtDate(d));
    // For daytour, also add that single day
    if (b.mode !== 'overnight') activeDates.add(fmtDate(start));
  });
  activeDates.forEach(dateStr => {
    const morn = getBookedFacilities(dateStr, '8am-12pm').size;
    const aft  = getBookedFacilities(dateStr, '12pm-5pm').size;
    const over = getBookedFacilities(dateStr, 'overnight').size;
    
    if (morn >= FAC_COUNT && aft >= FAC_COUNT && over >= FAC_COUNT) {
        s.add(dateStr);
    }
  });
  return s;
}

/* ── "I'M FLEXIBLE" LOGIC ── */
let flexDuration = 7;
let flexSelectedMonthIdx = 1; // June 2026 default in horizontally scrolling boxes

function renderFlexibleMonths() {
  const scroller = document.getElementById('flexMonthsScroller');
  scroller.innerHTML = '';

  const MONTHS_SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const start = new Date();
  
  for (let i = 0; i < 6; i++) {
    const d = new Date(start.getFullYear(), start.getMonth() + i, 1);
    const mName = MONTHS_SHORT[d.getMonth()];
    const yName = d.getFullYear();
    
    const box = document.createElement('div');
    box.className = 'flexible-month-box' + (i === flexSelectedMonthIdx ? ' active' : '');
    box.onclick = () => {
      document.querySelectorAll('.flexible-month-box').forEach(b => b.classList.remove('active'));
      box.classList.add('active');
      flexSelectedMonthIdx = i;
    };
    
    box.innerHTML = `
      <i class="far fa-calendar-alt"></i>
      <span class="m-lbl">${mName}</span>
      <span class="y-lbl">${yName}</span>
    `;
    scroller.appendChild(box);
  }
}

function selectFlexDuration(days, lbl) {
  flexDuration = days;
  document.querySelectorAll('.flexible-pill').forEach(p => p.classList.remove('active'));
  if (days === 3) document.getElementById('flexPill3').classList.add('active');
  if (days === 7) document.getElementById('flexPill7').classList.add('active');
  if (days === 30) document.getElementById('flexPill30').classList.add('active');
}

function clearFlexibleSelection() {
  flexDuration = 7;
  flexSelectedMonthIdx = 1;
  document.querySelectorAll('.flexible-pill').forEach(p => p.classList.remove('active'));
  document.getElementById('flexPill7').classList.add('active');
  renderFlexibleMonths();
}

function applyFlexibleSelection() {
  const start = new Date();
  const d = new Date(start.getFullYear(), start.getMonth() + flexSelectedMonthIdx, 1);
  
  // Set Check-in as 1st of that month, Check-out based on flexDuration nights
  checkInDate = fmtDate(d);
  const checkoutObj = new Date(d);
  checkoutObj.setDate(d.getDate() + flexDuration);
  checkOutDate = fmtDate(checkoutObj);
  
  bookingMode = 'overnight';
  selectedSlot = 'overnight';
  
  updateSearchBarDisplays();
  closeAllPopups();
  triggerSearch();
}

/* ── GUEST STEPPERS ── */
function adjustGuestStepper(type, delta) {
  const prevVal = guestsState[type] ?? 0;
  let newVal = prevVal + delta;

  if (type === 'cottage' || type === 'functionHall' || type === 'room') {
    newVal = Math.max(0, Math.min(10, newVal));
  } else if (type === 'adults') {
    newVal = Math.max(1, Math.min(50, newVal));
  } else if (type === 'pwd') {
    newVal = Math.max(0, Math.min(50, newVal));
  } else if (type === 'children5') {
    newVal = Math.max(0, Math.min(50, newVal));
  } else if (type === 'below5') {
    newVal = Math.max(0, Math.min(50, newVal));
  }

  guestsState[type] = newVal;
  updateGuestsStepperUI();
}

function updateGuestsStepperUI() {
  document.getElementById('valRoomStepper').textContent = guestsState.room;
  document.getElementById('valCottageStepper').textContent = guestsState.cottage;
  document.getElementById('valFunctionHallStepper').textContent = guestsState.functionHall;
  document.getElementById('valAdultsStepper').textContent = guestsState.adults;
  document.getElementById('valPwdStepper').textContent = guestsState.pwd;
  document.getElementById('valChildren5Stepper').textContent = guestsState.children5;
  // below5 stepper removed from UI; keep value at 0
  guestsState.below5 = 0;

  // Format search bar caption
  const totalAdults = guestsState.adults + guestsState.pwd;
  const totalKids = guestsState.children5;
  let caption = `${totalAdults} adult${totalAdults > 1 ? 's' : ''}`;
  if (totalKids > 0) {
    caption += `, ${totalKids} child${totalKids > 1 ? 'ren' : ''}`;
  }
  let accomParts = [];
  if (guestsState.room > 0) accomParts.push(`${guestsState.room} room${guestsState.room > 1 ? 's' : ''}`);
  if (guestsState.cottage > 0) accomParts.push(`${guestsState.cottage} cottage${guestsState.cottage > 1 ? 's' : ''}`);
  if (guestsState.functionHall > 0) accomParts.push(`${guestsState.functionHall} hall${guestsState.functionHall > 1 ? 's' : ''}`);
  if (accomParts.length > 0) {
    caption += ` · ${accomParts.join(', ')}`;
  }
  document.getElementById('valGuests').textContent = caption;

  // Sync to hidden form fields
  document.getElementById('hAdults').value = guestsState.adults;
  document.getElementById('hChildren').value = guestsState.children5;
  document.getElementById('hBelow5').value = guestsState.below5;
  document.getElementById('hPwd').value = guestsState.pwd;

  // Dynamically render child ages
  const container = document.getElementById('childAgesContainer');
  const list = document.getElementById('childAgesList');
  list.innerHTML = '';
  
  if (totalKids > 0) {
    container.style.display = 'block';
    for (let i = 1; i <= totalKids; i++) {
      const ageGroup = document.createElement('div');
      ageGroup.className = 'child-age-select-group';
      let optionsHtml = '';
      for (let age = 1; age <= 17; age++) {
        const isSelected = (age === 8) ? ' selected' : '';
        optionsHtml += `<option value="${age}"${isSelected}>${age} year${age > 1 ? 's' : ''} old</option>`;
      }
      ageGroup.innerHTML = `
        <label>Age of Child ${i}</label>
        <select class="form-select child-age-select" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px;" onchange="syncHiddenFields(); updateEstimatedPriceLive(); buildSummary();">
          ${optionsHtml}
        </select>
      `;
      list.appendChild(ageGroup);
    }
  } else {
    container.style.display = 'none';
  }

  // Update estimated pricing instantly if facility selected
  updateEstimatedPriceLive();

  // Dynamically render PWD / Senior ID file upload inputs
  const pwdContainer = document.getElementById('pwdIdContainer');
  const pwdList = document.getElementById('pwdIdList');
  if (pwdContainer && pwdList) {
    const desiredCount = guestsState.pwd;
    const currentCount = pwdList.children.length;
    if (desiredCount > 0) {
      pwdContainer.style.display = 'block';
      if (currentCount !== desiredCount) {
        let html = '';
        for (let i = 1; i <= desiredCount; i++) {
          const prevId = 'pwd_id_prev_' + i;
          html += `
            <div class="pwd-id-item" style="background: #ffffff; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
              <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #1e293b; margin-bottom: 5px;">
                <i class="fas fa-id-card" style="color: #16a34a; margin-right: 4px;"></i> PWD / Senior #${i} Valid ID Photo <span style="color:#ef4444">*</span>
              </label>
              <input type="file" name="pwd_ids[]" id="pwd_id_input_${i}" class="pwd-id-file-input form-control" accept="image/*" style="font-size:0.78rem; padding:5px 8px; border-radius:6px; border:1px solid #cbd5e1;" onchange="previewPwdIdPhoto(this, '${prevId}')">
              <div id="${prevId}" class="pwd-id-preview" style="margin-top: 6px; display: none;">
                <img src="" style="max-height: 100px; max-width: 100%; border-radius: 6px; border: 1px solid #cbd5e1; object-fit: contain;">
              </div>
            </div>
          `;
        }
        pwdList.innerHTML = html;
      }
    } else {
      pwdContainer.style.display = 'none';
      pwdList.innerHTML = '';
    }
  }
}

function previewPwdIdPhoto(input, prevId) {
  const container = document.getElementById(prevId);
  if (!container) return;
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = container.querySelector('img');
      if (img) {
        img.src = e.target.result;
        container.style.display = 'block';
      }
    };
    reader.readAsDataURL(input.files[0]);
  } else {
    container.style.display = 'none';
  }
}

// Track selected facility IDs
let selectedFacilityIds = [];

function isFacilityBookedForRange(facilityId, checkIn, checkOut, mode, slot) {
  if (mode === 'overnight' && checkOut) {
    const start = new Date(checkIn + 'T00:00:00');
    // Exclude checkout date: a booking that ends on checkOut does NOT block checkOut day
    const end = new Date(checkOut + 'T00:00:00');
    for (let d = new Date(start); d < end; d.setDate(d.getDate() + 1)) {
      const dateStr = fmtDate(d);
      const bookedSet = getBookedFacilities(dateStr, 'overnight');
      if (bookedSet.has(facilityId.toString())) {
        return true;
      }
    }
    return false;
  } else {
    const bookedSet = getBookedFacilities(checkIn, slot);
    return bookedSet.has(facilityId.toString());
  }
}

function updateSelectionBanner(facIds, totalReq) {
  const banner = document.getElementById('selectionStatusBanner');
  if (!banner) return;
  
  const step2LabelEl = document.querySelector('#si2 .step-label');
  const nextStepLabel = step2LabelEl ? step2LabelEl.textContent.trim() : 'Location & Details';
  
  if (totalReq === 0) {
    if (facIds.length === 1) {
      banner.style.display = 'flex';
      banner.style.background = '#e8f5e9';
      banner.style.borderColor = '#c8e6c9';
      banner.style.color = '#1a7a3a';
      banner.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 6px;"></i> 1 accommodation selected. Click ${nextStepLabel} at the top to proceed.`;
    } else {
      banner.style.display = 'none';
    }
    return;
  }
  
  const reqRooms = guestsState.room || 0;
  const reqCottages = guestsState.cottage || 0;
  const reqHalls = guestsState.functionHall || 0;
  
  const selRooms = facIds.filter(id => FACILITIES.find(f => f.id.toString() === id.toString())?.type === 'room').length;
  const selCottages = facIds.filter(id => FACILITIES.find(f => f.id.toString() === id.toString())?.type === 'cottage').length;
  const selHalls = facIds.filter(id => FACILITIES.find(f => f.id.toString() === id.toString())?.type === 'function_hall').length;
  
  const isComplete = (selRooms === reqRooms && selCottages === reqCottages && selHalls === reqHalls);
  
  banner.style.display = 'flex';
  if (isComplete) {
    banner.style.background = '#e8f5e9';
    banner.style.borderColor = '#c8e6c9';
    banner.style.color = '#1a7a3a';
    
    let text = '<i class="fas fa-check-circle" style="margin-right: 6px;"></i> Perfect! Selected: ';
    let parts = [];
    if (reqRooms > 0) parts.push(`${reqRooms} room(s)`);
    if (reqCottages > 0) parts.push(`${reqCottages} cottage(s)`);
    if (reqHalls > 0) parts.push(`${reqHalls} function hall(s)`);
    text += parts.join(', ') + `. Click ${nextStepLabel} at the top to proceed.`;
    banner.innerHTML = text;
  } else {
    banner.style.background = '#fff8e1';
    banner.style.borderColor = '#ffe082';
    banner.style.color = '#b78103';
    
    let needed = [];
    if (selRooms < reqRooms) needed.push(`${reqRooms - selRooms} Room(s)`);
    if (selCottages < reqCottages) needed.push(`${reqCottages - selCottages} Cottage(s)`);
    if (selHalls < reqHalls) needed.push(`${reqHalls - selHalls} Function Hall(s)`);
    
    let text = '<i class="fas fa-info-circle" style="margin-right: 6px;"></i> Selection needed: ';
    text += needed.join(', ') + '.';
    banner.innerHTML = text;
  }
}

/* ── SEARCH AVAILABILITY ── */
function triggerSearch() {
  if (!checkInDate) {
    alert('Please select check-in date first.');
    togglePopup('datePopup');
    return;
  }
  if (!checkOutDate && bookingMode === 'overnight') {
    alert('Please select check-out date.');
    togglePopup('datePopup');
    return;
  }

  closeAllPopups();

  // Reset selected facilities when triggering a new search
  selectedFacilityIds = [];
  document.getElementById('hFacilityId').value = '';
  const facSel = document.getElementById('fFacility');
  if (facSel) facSel.value = '';
  onFacilitySelectionChange([]);

  // Update live status bar
  const rangeLbl = bookingMode === 'daytour' ? formatDisplayDate(checkInDate) : `${formatDisplayDate(checkInDate)} to ${formatDisplayDate(checkOutDate)}`;
  const totalGuests = guestsState.adults + guestsState.pwd + guestsState.children5 + guestsState.below5;
  let slotLabel = '';
  if (selectedSlot === 'overnight') slotLabel = 'Overnight (8am-8am)';
  else if (selectedSlot === '8am-12pm') slotLabel = 'Morning (8am-12pm)';
  else if (selectedSlot === '12pm-5pm') slotLabel = 'Afternoon (12pm-5pm)';
  else if (selectedSlot === 'full_day') slotLabel = 'Full Day (8am-5pm)';
  else slotLabel = SLOT_LABELS[selectedSlot] || selectedSlot || '';

  document.getElementById('searchSelectionStatusText').innerHTML = `
    <strong>Searching for:</strong> ${bookingMode === 'daytour' ? 'Day Use' : 'Overnight Stay'} (${slotLabel}) on <strong>${rangeLbl}</strong> for <strong>${totalGuests} guests</strong>.
  `;

  // Show facility options card
  document.getElementById('facilityOptionsCard').classList.add('show');
  const step1BtnRow = document.getElementById('step1BtnRow');
  if (step1BtnRow) step1BtnRow.style.display = 'flex';

  // Refresh facility catalog with current area (or all if none selected)
  const areaSelect = document.getElementById('fArea');
  onAreaChange(areaSelect.value);

  // Smooth scroll to facilities catalog
  setTimeout(() => {
    document.getElementById('facilityOptionsCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 200);
}

function onAreaChange(areaId) {
  document.getElementById('hAreaId').value = areaId;
  updateFacilityDropdownWidget();
}

function onTransportChange(val) {
  document.getElementById('hTransport').value = val;
  buildSummary();
}

function onTimeSlotChange(val) {
  if (!val) return;
  selectedSlot = val;
  if (val === 'overnight') {
    bookingMode = 'overnight';
    if (checkInDate && (!checkOutDate || checkOutDate <= checkInDate)) {
      const d = new Date(checkInDate + 'T00:00:00');
      d.setDate(d.getDate() + 1);
      checkOutDate = fmtDate(d);
    }
  } else {
    bookingMode = 'daytour';
    checkOutDate = checkInDate; // Same day check-out for day use
  }
  document.getElementById('hTimeSlot').value = selectedSlot;
  document.getElementById('hMode').value = bookingMode;
  
  if (checkOutDate) {
    document.getElementById('hCheckOut').value = checkOutDate;
  }
  
  updateSearchBarDisplays();
  
  // Sync dropdown and facility widget
  updateFacilityDropdownWidget();
  
  // Sync calendar tab popup state
  document.querySelectorAll('.popup-tab').forEach(t => t.classList.remove('active'));
  if (bookingMode === 'daytour') {
    const tabDay = document.getElementById('tabDayUse');
    if (tabDay) tabDay.classList.add('active');
    const dayBanner = document.getElementById('dayuseInfoBanner');
    if (dayBanner) dayBanner.style.display = 'flex';
    const daySlots = document.getElementById('dayuseSlotSection');
    if (daySlots) daySlots.style.display = 'block';
    
    // Pre-select the card inside dayuseSlotSection
    document.querySelectorAll('#dayuseSlotSection .ts-card').forEach(c => {
      c.classList.toggle('selected', c.dataset.slot === selectedSlot);
    });
  } else {
    const tabOver = document.getElementById('tabOvernight');
    if (tabOver) tabOver.classList.add('active');
    const dayBanner = document.getElementById('dayuseInfoBanner');
    if (dayBanner) dayBanner.style.display = 'none';
    const daySlots = document.getElementById('dayuseSlotSection');
    if (daySlots) daySlots.style.display = 'none';
  }
}

function updateFacilityDropdownWidget() {
  const facSel = document.getElementById('fFacility');
  const areaSelect = document.getElementById('fArea');
  const currentVal = facSel.value;
  facSel.innerHTML = '<option value="">Select Facility</option>';
  
  if (!checkInDate || !selectedSlot) return;
  const bookedFacs = checkInDate && selectedSlot ? getBookedFacilities(checkInDate, selectedSlot) : new Set();
  
  const hasRoomSelect = (guestsState.room || 0) > 0;
  const hasCottageSelect = (guestsState.cottage || 0) > 0;
  const hasHallSelect = (guestsState.functionHall || 0) > 0;
  const anyTypeSelected = hasRoomSelect || hasCottageSelect || hasHallSelect;

  FACILITIES.forEach(f => {
    // Filter by selected area if chosen (safely handle null area_id)
    if (areaSelect.value) {
      const facAreaId = (f.area_id != null) ? String(f.area_id) : '';
      if (facAreaId && facAreaId !== String(areaSelect.value)) return;
    }

    // Filter by selected facility types if any is specified in guestsState
    if (anyTypeSelected) {
      if (f.type === 'room' && !hasRoomSelect) return;
      if (f.type === 'cottage' && !hasCottageSelect) return;
      if (f.type === 'function_hall' && !hasHallSelect) return;
    }

    if (!isFacilityBookedForRange(f.id, checkInDate, checkOutDate, bookingMode, selectedSlot)) {
      const opt = document.createElement('option');
      opt.value = f.id;
      opt.setAttribute('data-price', f.price);
      opt.setAttribute('data-type', f.type);
      opt.setAttribute('data-capacity', f.capacity || 'N/A');
      opt.setAttribute('data-name', f.name);
      opt.textContent = `${f.name} — ${f.type.charAt(0).toUpperCase() + f.type.slice(1)} — ₱${parseFloat(f.price).toLocaleString('en-PH', {minimumFractionDigits:2})}`;
      facSel.appendChild(opt);
    }
  });

  // Filter out any selected facilities that are no longer available on these new dates/area
  selectedFacilityIds = selectedFacilityIds.filter(id => {
    const f = FACILITIES.find(fac => fac.id.toString() === id.toString());
    if (!f) return false;
    
    // Check area filter
    if (areaSelect.value) {
      const facAreaId = (f.area_id != null) ? String(f.area_id) : '';
      if (facAreaId && facAreaId !== String(areaSelect.value)) return false;
    }
    
    // Check availability
    if (isFacilityBookedForRange(f.id, checkInDate, checkOutDate, bookingMode, selectedSlot)) return false;
    
    return true;
  });
  
  // Sync to hidden inputs and update button states
  document.getElementById('hFacilityId').value = selectedFacilityIds.join(',');
  facSel.value = selectedFacilityIds.length > 0 ? selectedFacilityIds[0] : '';
  
  onFacilitySelectionChange(selectedFacilityIds);

  // Render visual catalog cards
  renderFacilityCatalog();
}

function onFacilitySelectionChange(facIds) {
  // If a single ID is passed, convert to array
  if (!Array.isArray(facIds)) {
    facIds = facIds ? facIds.toString().split(',') : [];
  }
  
  // Sync global selectedFacilityIds array
  selectedFacilityIds = facIds.map(id => id.toString());
  
  document.getElementById('hFacilityId').value = facIds.join(',');
  
  const facSel = document.getElementById('fFacility');
  if (facIds.length > 0) {
    facSel.value = facIds[0];
    // Auto-select area based on chosen facility
    const firstFac = FACILITIES.find(f => f.id.toString() === facIds[0].toString());
    if (firstFac && firstFac.area_id) {
      const areaSelect = document.getElementById('fArea');
      if (areaSelect) {
        areaSelect.value = firstFac.area_id;
        document.getElementById('hAreaId').value = firstFac.area_id;
      }
    }
  } else {
    facSel.value = '';
  }

  const box = document.getElementById('facInfoBox');
  const nextBtn = document.getElementById('nextToStep2Btn');

  // Update validation requirement
  const reqRooms = guestsState.room || 0;
  const reqCottages = guestsState.cottage || 0;
  const reqHalls = guestsState.functionHall || 0;
  const totalReq = reqRooms + reqCottages + reqHalls;
  
  let isComplete = false;
  if (totalReq === 0) {
    isComplete = facIds.length === 1;
  } else {
    const selRooms = facIds.filter(id => FACILITIES.find(f => f.id.toString() === id.toString())?.type === 'room').length;
    const selCottages = facIds.filter(id => FACILITIES.find(f => f.id.toString() === id.toString())?.type === 'cottage').length;
    const selHalls = facIds.filter(id => FACILITIES.find(f => f.id.toString() === id.toString())?.type === 'function_hall').length;
    isComplete = (selRooms === reqRooms && selCottages === reqCottages && selHalls === reqHalls);
  }

  if (facIds.length === 0) {
    box.classList.remove('show');
    if (nextBtn) {
      nextBtn.disabled = true;
      nextBtn.style.opacity = '0.6';
      nextBtn.style.cursor = 'not-allowed';
    }
    return;
  }

  // Build info text
  let totalFacPrice = 0;
  let totalCap = 0;
  let facNames = [];
  
  facIds.forEach(id => {
    const f = FACILITIES.find(fac => fac.id.toString() === id.toString());
    if (f) {
      facNames.push(f.name);
      totalFacPrice += parseFloat(f.price);
      totalCap += parseInt(f.capacity) || 0;
    }
  });

  document.getElementById('facInfoName').textContent  = facNames.join(', ') || '—';
  document.getElementById('facInfoPrice').textContent = '₱' + totalFacPrice.toLocaleString('en-PH', {minimumFractionDigits:2}) + (bookingMode === 'overnight' ? ' / night' : ' / event');
  document.getElementById('facInfoCap').textContent   = totalCap || 'N/A';
  
  const totalGuests = guestsState.adults + guestsState.pwd + guestsState.children5 + guestsState.below5;
  document.getElementById('facInfoGuests').textContent = totalGuests + ' guest(s)';
  box.classList.add('show');

  // Enable Next button and Add to Cart button if selection matches requirements
  const cartBtn = document.getElementById('addToCartStep1Btn');
  if (nextBtn) {
    if (isComplete) {
      nextBtn.disabled = false;
      nextBtn.style.opacity = '1';
      nextBtn.style.cursor = 'pointer';
      nextBtn.style.boxShadow = '0 4px 16px rgba(26,61,43,.3)';
      if (cartBtn) { cartBtn.disabled = false; }
    } else {
      nextBtn.disabled = true;
      nextBtn.style.opacity = '0.6';
      nextBtn.style.cursor = 'not-allowed';
      nextBtn.style.boxShadow = 'none';
      if (cartBtn) { cartBtn.disabled = true; }
    }
  }

  // Sync selected state visually in catalog cards
  document.querySelectorAll('.landscape-card').forEach(card => {
    const cardId = card.dataset.id;
    const isSelected = facIds.includes(cardId.toString());
    card.classList.toggle('selected', isSelected);
  });

  updateSelectionBanner(facIds, totalReq);
  updateTimeSlotOptions();
}

/* ── ADD TO CART (Step 1) ── */
let _cartToastTimer = null;
function showCartToast(msg, isSuccess) {
  const toast = document.getElementById('cartToast');
  const icon  = document.getElementById('cartToastIcon');
  const msgEl = document.getElementById('cartToastMsg');
  if (!toast) return;
  icon.className = 'toast-icon ' + (isSuccess ? 'success' : 'error');
  icon.innerHTML = isSuccess ? '<i class="fas fa-check"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
  msgEl.textContent = msg;
  toast.classList.add('show');
  if (_cartToastTimer) clearTimeout(_cartToastTimer);
  _cartToastTimer = setTimeout(() => hideCartToast(), 3500);
}
function hideCartToast() {
  const toast = document.getElementById('cartToast');
  if (toast) toast.classList.remove('show');
}
function handleAddToCartStep1() {
  const btn = document.getElementById('addToCartStep1Btn');
  if (!btn || btn.disabled) return;

  // Gather selected facility IDs
  const facIds = Array.from(selectedFacilityIds || []).map(String);
  if (facIds.length === 0) {
    showCartToast('Please select a facility first.', false);
    return;
  }

  // Gather current booking info — read from the correct hidden inputs
  const checkIn    = document.getElementById('hCheckIn')   ? document.getElementById('hCheckIn').value   : '';
  const checkOut   = document.getElementById('hCheckOut')  ? document.getElementById('hCheckOut').value  : '';
  const mode       = document.getElementById('hMode')      ? document.getElementById('hMode').value      : 'daytour';
  const timeSlot   = document.getElementById('hTimeSlot')  ? document.getElementById('hTimeSlot').value  : '';
  const areaId     = document.getElementById('hAreaId')    ? document.getElementById('hAreaId').value    : '';
  const adults     = document.getElementById('hAdults')    ? document.getElementById('hAdults').value    : 1;
  const children   = document.getElementById('hChildren')  ? document.getElementById('hChildren').value  : 0;
  const below5     = document.getElementById('hBelow5')    ? document.getElementById('hBelow5').value    : 0;
  const discounted = document.getElementById('hPwd')       ? document.getElementById('hPwd').value       : 0;
  const transport  = document.getElementById('hTransport') ? document.getElementById('hTransport').value : 'none';

  // PWD / Senior ID files validation & append
  const pwdCount = parseInt(discounted) || 0;
  const pwdFileInputs = document.querySelectorAll('.pwd-id-file-input');
  if (pwdCount > 0) {
    let uploadedCount = 0;
    pwdFileInputs.forEach(input => {
      if (input.files && input.files[0]) uploadedCount++;
    });
    if (uploadedCount < pwdCount) {
      showCartToast('Please upload a Valid ID photo for each PWD / Senior guest (' + uploadedCount + '/' + pwdCount + ' uploaded).', false);
      return;
    }
  }

  // Animate button
  btn.disabled = true;
  const origHTML = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

  // Send a single request with all selected facility IDs joined by a comma
  const formData = new FormData();
  formData.append('action', 'add');
  formData.append('facility_id', facIds.join(','));
  formData.append('check_in_date', checkIn);
  formData.append('check_out_date', checkOut);
  formData.append('mode', mode);
  formData.append('time_slot', timeSlot);
  formData.append('area_id', areaId);
  formData.append('num_adults', adults);
  formData.append('num_children', children);
  formData.append('num_below5', below5);
  formData.append('num_discounted', discounted);
  formData.append('transport_option', transport);
  formData.append('transport_fee', 0);
  formData.append('total_price', 0);

  pwdFileInputs.forEach(input => {
    if (input.files && input.files[0]) {
      formData.append('pwd_ids[]', input.files[0]);
    }
  });

  fetch('cart_action.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        btn.innerHTML = '<i class="fas fa-check"></i> Added!';
        btn.style.background = 'linear-gradient(135deg,#059669 0%,#34d399 100%)';
        const addedCount = facIds.length;
        showCartToast('🛒 ' + addedCount + ' facilit' + (addedCount > 1 ? 'ies' : 'y') + ' added to your cart!', true);
        // Update navbar cart badge with the latest count
        const lastCount = d.cart_count;
        const navBadge = document.getElementById('navCartBadge');
        if (navBadge && lastCount !== undefined) {
          const c = lastCount;
          navBadge.textContent = c > 0 ? c : '';
          navBadge.setAttribute('data-count', c);
          navBadge.classList.remove('pop');
          void navBadge.offsetWidth;
          navBadge.classList.add('pop');
          setTimeout(() => navBadge.classList.remove('pop'), 400);
        }
        const badge = document.getElementById('cartCountBadge') || document.querySelector('.cart-count');
        if (badge && lastCount !== undefined) badge.textContent = lastCount;
        setTimeout(() => {
          btn.innerHTML = origHTML;
          btn.style.background = '';
          btn.disabled = false;
        }, 2200);
      } else {
        btn.innerHTML = origHTML;
        btn.disabled = false;
        showCartToast(d.message || 'Could not add to cart.', false);
      }
    })
    .catch(() => {
      btn.innerHTML = origHTML;
      btn.disabled = false;
      showCartToast('Network error. Please try again.', false);
    });
}

/* ── LIVE PRICING CALCULATOR STEP 1 ── */
function updateEstimatedPriceLive() {
  // Live price panel removed — no-op
  return;
}

/* ── STEP NAVIGATION ── */
const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

function goStep(n) {
  if (n > currentStep) {
    for (let s = currentStep; s < n; s++) {
      if (!validateStep(s)) return;
    }
  }
  syncHiddenFields();

  currentStep = n;
  document.querySelectorAll('.step-panel').forEach((p, i) => {
    p.classList.toggle('active', i+1 === n);
  });
  updateStepBar();
  if (n === 2) updateTimeSlotOptions();
  if (n === 3) buildSummary();
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function updateStepBar() {
  for (let i = 1; i <= 3; i++) {
    const si = document.getElementById('si'+i);
    if (!si) continue; 
    si.classList.remove('active','done');
    if (i === currentStep) si.classList.add('active');
    else if (i < currentStep) si.classList.add('done');
    if (i < 3) {
      const sc = document.getElementById('sc'+i);
      if (sc) sc.classList.toggle('done', i < currentStep);
    }
  }
}

function validateStep(step) {
  if (step === 1) {
    if (!checkInDate) { alert('Please select a date on the calendar.'); return false; }
    if (!selectedSlot) { alert('Please select a time slot.'); return false; }
    if (!document.getElementById('fFacility').value) { alert('Please select an available Facility (cottage/room).'); return false; }
    if (guestsState.pwd > 0) {
      const pwdFileInputs = document.querySelectorAll('.pwd-id-file-input');
      let uploadedCount = 0;
      pwdFileInputs.forEach(input => {
        if (input.files && input.files[0]) uploadedCount++;
      });
      if (uploadedCount < guestsState.pwd) {
        alert('Please upload a Valid ID photo for each PWD / Senior guest (' + uploadedCount + '/' + guestsState.pwd + ' uploaded).');
        return false;
      }
    }
    return true;
  }
  if (step === 2) {
    if (!document.getElementById('fArea').value) { alert('Please select a spring location.'); return false; }
    if (!document.getElementById('fTimeSlot').value) { alert('Please select a time slot.'); return false; }
    if (!isLoggedIn) {
      const fn = document.getElementById('fFirstName').value.trim();
      const ln = document.getElementById('fLastName').value.trim();
      const ph = document.getElementById('fPhone').value.trim();
      const em = document.getElementById('fEmail').value.trim();
      const pw = document.getElementById('fPassword').value;
      const cp = document.getElementById('fConfirmPassword').value;
      if (!fn || !ln || !ph || !em || !pw || !cp) { alert('Please fill in all guest details including password.'); return false; }
      if (!/^[A-Za-z]+(?:[\s\-'][A-Za-z]+)?$/.test(fn) || !/^[A-Za-z]+(?:[\s\-'][A-Za-z]+)?$/.test(ln)) {
        alert('Please enter valid first and last name (letters only).'); return false;
      }
      if (!/^\d{11}$/.test(ph)) { alert('Please enter a valid 11-digit contact number (e.g. 09123456789).'); return false; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { alert('Please enter a valid email address.'); return false; }
      if (pw.length < 6) { alert('Password must be at least 6 characters.'); return false; }
      if (pw !== cp) { alert('Passwords do not match.'); return false; }
    }
    return true;
  }
  return true;
}

function getChildrenAgeCounts() {
  let under5 = 0;
  let regular = 0;
  document.querySelectorAll('.child-age-select').forEach(select => {
    const age = parseInt(select.value) || 8;
    if (age <= 5) {
      under5++;
    } else {
      regular++;
    }
  });
  const totalKids = guestsState.children5;
  if (totalKids > 0 && under5 === 0 && regular === 0) {
    regular = totalKids;
  }
  return { under5, regular };
}

function syncHiddenFields() {
  if (isLoggedIn) {
    document.getElementById('hFirstName').value = <?php echo json_encode($session_first); ?>;
    document.getElementById('hLastName').value  = <?php echo json_encode($session_last); ?>;
    document.getElementById('hEmail').value     = <?php echo json_encode($session_email); ?>;
    document.getElementById('hPhone').value     = <?php echo json_encode($session_phone); ?>;
    document.getElementById('hPassword').value  = "dummy_password_because_logged_in";
  } else {
    const fFN = document.getElementById('fFirstName');
    const fLN = document.getElementById('fLastName');
    const fPh = document.getElementById('fPhone');
    const fEm = document.getElementById('fEmail');
    const fPw = document.getElementById('fPassword');
    
    if (fFN) document.getElementById('hFirstName').value = fFN.value.trim();
    if (fLN) document.getElementById('hLastName').value  = fLN.value.trim();
    if (fPh) document.getElementById('hPhone').value     = fPh.value.trim();
    if (fEm) document.getElementById('hEmail').value     = fEm.value.trim();
    if (fPw) document.getElementById('hPassword').value  = fPw.value;
  }
  document.getElementById('hMode').value       = bookingMode;
  document.getElementById('hAreaId').value     = document.getElementById('fArea').value;
  // Always use the full selectedFacilityIds array (comma-separated) — not just fFacility.value
  document.getElementById('hFacilityId').value = selectedFacilityIds.join(',');
  document.getElementById('hAdults').value     = guestsState.adults;
  
  const ageCounts = getChildrenAgeCounts();
  document.getElementById('hChildren').value   = ageCounts.regular;
  document.getElementById('hBelow5').value     = ageCounts.under5;
  
  document.getElementById('hPwd').value        = guestsState.pwd;
  document.getElementById('hTransport').value  = document.getElementById('fTransport').value;
  document.getElementById('hCheckIn').value    = checkInDate;
  document.getElementById('hCheckOut').value   = checkOutDate || checkInDate;
  document.getElementById('hTimeSlot').value   = selectedSlot;
}

function togglePwVis(inputId, iconId) {
  const inp = document.getElementById(inputId);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; ico.classList.replace('fa-eye','fa-eye-slash'); }
  else { inp.type = 'password'; ico.classList.replace('fa-eye-slash','fa-eye'); }
}

// Live password match indicator
document.addEventListener('DOMContentLoaded', () => {
  function checkPwMatch() {
    const pw = document.getElementById('fPassword').value;
    const cp = document.getElementById('fConfirmPassword').value;
    const msg = document.getElementById('pwMatchMsg');
    if (!pw && !cp) { msg.style.display='none'; return; }
    if (!cp) { msg.style.display='none'; return; }
    if (pw === cp) {
      msg.style.display='block'; msg.style.color='#059669';
      msg.innerHTML='<i class="fas fa-check-circle"></i> Passwords match';
    } else {
      msg.style.display='block'; msg.style.color='var(--red)';
      msg.innerHTML='<i class="fas fa-times-circle"></i> Passwords do not match';
    }
  }
  const fPw = document.getElementById('fPassword');
  const fCp = document.getElementById('fConfirmPassword');
  if (fPw) fPw.addEventListener('input', checkPwMatch);
  if (fCp) fCp.addEventListener('input', checkPwMatch);
});

/* ── SUMMARY BUILDER ── */
function buildSummary() {
  syncHiddenFields();
  const dateStr = checkInDate ? formatDisplayDate(checkInDate) : '—';
  const slotStr = selectedSlot ? (SLOT_LABELS[selectedSlot] || selectedSlot) : '—';

  let name, email, phone;
  if (isLoggedIn) {
      name = <?php echo json_encode($session_name); ?>;
      email = <?php echo json_encode($session_email); ?>;
      phone = <?php echo json_encode($session_phone); ?>;
      document.getElementById('hFirstName').value = <?php echo json_encode($session_first); ?>;
      document.getElementById('hLastName').value = <?php echo json_encode($session_last); ?>;
      document.getElementById('hEmail').value = email;
      document.getElementById('hPhone').value = phone;
      document.getElementById('hPassword').value = "dummy_password_because_logged_in";
  } else {
      const fn = document.getElementById('fFirstName').value.trim();
      const ln = document.getElementById('fLastName').value.trim();
      name  = (fn + ' ' + ln).trim() || '—';
      email = document.getElementById('fEmail').value.trim() || '—';
      phone = document.getElementById('fPhone').value.trim() || '—';
  }

  const modeLabel = bookingMode === 'overnight' ? 'Overnight Stay' : 'Day Use Stay';

  const areaSel  = document.getElementById('fArea');
  const areaName = areaSel.options[areaSel.selectedIndex]?.text || '—';

  // Build info for ALL selected facilities
  let totalFacPrice = 0;
  let facNames = [];
  selectedFacilityIds.forEach(fid => {
    const f = FACILITIES.find(fac => fac.id.toString() === fid.toString());
    if (f) {
      facNames.push(f.name);
      totalFacPrice += parseFloat(f.price);
    }
  });
  const facName  = facNames.length > 0 ? facNames.join(', ') : '—';
  const facPrice = totalFacPrice;

  // Nights calculation
  let nights = 1;
  if (bookingMode === 'overnight' && checkInDate && checkOutDate) {
    const d1 = new Date(checkInDate + 'T00:00:00');
    const d2 = new Date(checkOutDate + 'T00:00:00');
    nights = Math.max(1, Math.round((d2 - d1) / (1000*60*60*24)));
  }
  const facilityTotal = bookingMode === 'overnight' ? facPrice * nights : facPrice;

  // Area access rates
  const areaNameLower = (areaSel.options[areaSel.selectedIndex]?.text || '').toLowerCase().trim();
  let rateRegular = 110, rateChild = 60;
  if (['both','combo','combo package','sinulom + bolao'].includes(areaNameLower)) {
    rateRegular = 160; rateChild = 85;
  }
  const ratePwd = Math.round(rateRegular * 0.80 * 100) / 100;

  const ageCounts = getChildrenAgeCounts();
  const regularKidsCount = ageCounts.regular;
  const under5KidsCount = ageCounts.under5;
  
  const areaTotal = (guestsState.adults * rateRegular) + (regularKidsCount * rateChild) + (guestsState.pwd * ratePwd);
  
  const transportSel = document.getElementById('fTransport');
  const transportOpt = transportSel ? transportSel.value : 'none';
  let transportCost = 0;
  let transportLabel = 'None';

  const transportGuests = Math.max(0, guestsState.adults + guestsState.pwd);
  if (transportOpt === 'tignapoloan') {
    transportCost = transportGuests * 50;
    transportLabel = 'Tignapoloan Crossing (' + transportGuests + ' pax)';
  } else if (transportOpt === 'cdo') {
    transportCost = transportGuests * 250;
    transportLabel = 'Cagayan De Oro (' + transportGuests + ' pax)';
  } else if (transportOpt === 'private') {
    transportCost = 3500;
    transportLabel = 'Private Vehicle Rental';
  }

  const subtotal     = facilityTotal + areaTotal + transportCost;
  const vatRate      = <?= json_encode($vat_multiplier) ?>;
  const vat          = Math.round(subtotal * vatRate * 100) / 100;
  const total        = subtotal + vat;

  const fmt = v => '₱' + v.toLocaleString('en-PH', {minimumFractionDigits:2});

  document.getElementById('sumDate').textContent     = dateStr;
  document.getElementById('sumSlot').textContent     = slotStr;
  document.getElementById('sumName').textContent     = name;
  document.getElementById('sumPhone').textContent    = phone;
  document.getElementById('sumEmail').textContent    = email;
  document.getElementById('sumMode').textContent     = modeLabel;
  document.getElementById('sumArea').textContent     = areaName;
  document.getElementById('sumFacility').textContent = facName;
  document.getElementById('sumCheckIn').textContent  = checkInDate ? formatDisplayDate(checkInDate) : '—';
  document.getElementById('sumCheckOut').textContent = (bookingMode === 'overnight' && checkOutDate) ? formatDisplayDate(checkOutDate) : 'Same day';
  document.getElementById('sumAdults').textContent   = guestsState.adults;
  document.getElementById('sumChildren').textContent = regularKidsCount;
  document.getElementById('sumBelow5').textContent   = under5KidsCount + ' (FREE)';
  document.getElementById('sumPwd').textContent      = guestsState.pwd + ' (20% OFF)';

  // Price breakdown — show all facilities combined
  const nightsLabel = bookingMode === 'overnight' && nights > 1 ? ' × ' + nights + ' night(s)' : '';
  document.getElementById('sumFacilityPrice').textContent = fmt(facPrice) + (nightsLabel ? nightsLabel + ' = ' + fmt(facilityTotal) : '');
  document.getElementById('sumAdultRate').textContent     = fmt(rateRegular);
  document.getElementById('sumAdultTotal').textContent    = guestsState.adults + ' × ' + fmt(rateRegular) + ' = ' + fmt(guestsState.adults * rateRegular);
  document.getElementById('sumChildRate').textContent     = fmt(rateChild);
  document.getElementById('sumChildTotal').textContent    = regularKidsCount + ' × ' + fmt(rateChild) + ' = ' + fmt(regularKidsCount * rateChild);
  document.getElementById('sumPwdRate').textContent       = fmt(ratePwd);
  document.getElementById('sumPwdTotal').textContent      = guestsState.pwd + ' × ' + fmt(ratePwd) + ' = ' + fmt(guestsState.pwd * ratePwd);
  document.getElementById('sumLocationTotal').textContent = fmt(areaTotal);
  document.getElementById('sumTransportLbl').textContent  = transportLabel;
  document.getElementById('sumTransportTotal').textContent= transportCost > 0 ? fmt(transportCost) : '—';
  document.getElementById('sumSubtotal').textContent      = fmt(subtotal);
  document.getElementById('sumVat').textContent           = fmt(vat);
  document.getElementById('sumTotal').textContent         = fmt(total);
}

/* ── VISUAL CATALOG CONTROLLERS ── */
let currentCatalogFilter = 'all';
let activeImageIndices = {};

function filterCatalog(type) {
  currentCatalogFilter = type;
  document.querySelectorAll('.fac-filter-btn').forEach(btn => btn.classList.remove('active'));
  if (type === 'all') document.getElementById('btnFilterAll').classList.add('active');
  if (type === 'room') document.getElementById('btnFilterRoom').classList.add('active');
  if (type === 'cottage') document.getElementById('btnFilterCottage').classList.add('active');
  if (type === 'function_hall') document.getElementById('btnFilterHall').classList.add('active');
  renderFacilityCatalog();
}

function selectCatalogFacility(id) {
  id = id.toString();
  const idx = selectedFacilityIds.indexOf(id);
  if (idx === -1) {
    // Not yet selected — add it
    selectedFacilityIds.push(id);
  } else {
    // Already selected — deselect (toggle off)
    selectedFacilityIds.splice(idx, 1);
  }
  document.getElementById('hFacilityId').value = selectedFacilityIds.join(',');
  const facSel = document.getElementById('fFacility');
  facSel.value = selectedFacilityIds.length > 0 ? selectedFacilityIds[0] : '';
  onFacilitySelectionChange(selectedFacilityIds);
}

function slideGalleryImage(facId, dir, event) {
  if (event) event.stopPropagation();
  const f = FACILITIES.find(fac => fac.id.toString() === facId.toString());
  if (!f) return;
  
  const galleries = {
    'room': ['villa-gracia.jpg', 'villa-carolina.jpg', 'villa-candida.jpg'],
    'cottage': ['cottage1.jpg', 'cottage2.jpg', 'cottage3.jpg'],
    'function_hall': ['fhall1.jpg', 'fhall2.jpg', 'fhall3.jpg']
  };
  
  const type = f.type;
  const imgs = f.image_path ? [f.image_path] : (galleries[type] || ['hero-section.jpg']);
  
  if (activeImageIndices[facId] === undefined) {
    activeImageIndices[facId] = 0;
  }
  
  let newIdx = activeImageIndices[facId] + dir;
  if (newIdx < 0) newIdx = imgs.length - 1;
  if (newIdx >= imgs.length) newIdx = 0;
  
  activeImageIndices[facId] = newIdx;
  
  const imgEl = document.getElementById(`imgGallery_${facId}`);
  const counterEl = document.getElementById(`counterGallery_${facId}`);
  if (imgEl) imgEl.src = `images/${imgs[newIdx]}`;
  if (counterEl) counterEl.textContent = `${newIdx + 1}/${imgs.length}`;
}

function getFacilityImage(name, type) {
  const n = name.toLowerCase();
  const t = type.toLowerCase();
  if (n.includes('gracia')) return 'villa-gracia.jpg';
  if (n.includes('carolina')) return 'villa-carolina.jpg';
  if (n.includes('candida')) return 'villa-candida.jpg';
  if (n.includes('cottage 1')) return 'cottage1.jpg';
  if (n.includes('cottage 2')) return 'cottage2.jpg';
  if (n.includes('cottage 3')) return 'cottage3.jpg';
  if (n.includes('hall 1')) return 'fhall1.jpg';
  if (n.includes('hall 2')) return 'fhall2.jpg';
  if (n.includes('hall 3')) return 'fhall3.jpg';
  
  // Fallbacks by type
  if (t === 'room') return 'villa-gracia.jpg';
  if (t === 'cottage') return 'cottage1.jpg';
  if (t.includes('hall')) return 'fhall1.jpg';
  return 'hero-section.jpg';
}

function renderFacilityCatalog() {
  const grid = document.getElementById('facCatalogGrid');
  if (!grid) return;
  grid.innerHTML = '';

  // If no date selected yet, show prompt
  if (!checkInDate) {
    grid.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);"><i class="fas fa-calendar-alt" style="font-size:2rem;margin-bottom:12px;display:block;opacity:0.4;"></i>Please select your dates and click Search to view available accommodations.</div>';
    return;
  }
  
  const facSel = document.getElementById('fFacility');
  const selectedId = facSel.value;

  // Build facility list directly from FACILITIES array for reliability
  const areaSelect = document.getElementById('fArea');
  const bookedFacs = checkInDate && selectedSlot ? getBookedFacilities(checkInDate, selectedSlot) : new Set();

  const hasRoomSelect = (guestsState.room || 0) > 0;
  const hasCottageSelect = (guestsState.cottage || 0) > 0;
  const hasHallSelect = (guestsState.functionHall || 0) > 0;
  const anyTypeSelected = hasRoomSelect || hasCottageSelect || hasHallSelect;

  const availableFacs = FACILITIES.filter(f => {
    // Area filter
    if (areaSelect.value) {
      const facAreaId = (f.area_id != null) ? String(f.area_id) : '';
      if (facAreaId && facAreaId !== String(areaSelect.value)) return false;
    }
    // Availability filter
    if (bookedFacs.has(String(f.id))) return false;

    // Filter by selected facility types if any is specified in guestsState
    if (anyTypeSelected) {
      if (f.type === 'room' && !hasRoomSelect) return false;
      if (f.type === 'cottage' && !hasCottageSelect) return false;
      if (f.type === 'function_hall' && !hasHallSelect) return false;
    }

    return true;
  });

  // Dynamic filter buttons visibility
  const btnFilterRoom = document.getElementById('btnFilterRoom');
  const btnFilterCottage = document.getElementById('btnFilterCottage');
  const btnFilterHall = document.getElementById('btnFilterHall');

  if (anyTypeSelected) {
    if (btnFilterRoom) btnFilterRoom.style.display = hasRoomSelect ? 'inline-block' : 'none';
    if (btnFilterCottage) btnFilterCottage.style.display = hasCottageSelect ? 'inline-block' : 'none';
    if (btnFilterHall) btnFilterHall.style.display = hasHallSelect ? 'inline-block' : 'none';

    let filterReset = false;
    if (currentCatalogFilter === 'room' && !hasRoomSelect) { currentCatalogFilter = 'all'; filterReset = true; }
    if (currentCatalogFilter === 'cottage' && !hasCottageSelect) { currentCatalogFilter = 'all'; filterReset = true; }
    if (currentCatalogFilter === 'function_hall' && !hasHallSelect) { currentCatalogFilter = 'all'; filterReset = true; }

    if (filterReset) {
      document.querySelectorAll('.fac-filter-btn').forEach(btn => btn.classList.remove('active'));
      const btnAll = document.getElementById('btnFilterAll');
      if (btnAll) btnAll.classList.add('active');
    }
  } else {
    if (btnFilterRoom) btnFilterRoom.style.display = 'inline-block';
    if (btnFilterCottage) btnFilterCottage.style.display = 'inline-block';
    if (btnFilterHall) btnFilterHall.style.display = 'inline-block';
  }

  if (availableFacs.length === 0) {
    grid.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);"><i class="fas fa-ban" style="font-size:2rem;margin-bottom:12px;display:block;opacity:0.5;"></i>No accommodations available for the selected dates and slot.</div>';
    return;
  }
  
  const galleries = {
    'room': ['villa-gracia.jpg', 'villa-carolina.jpg', 'villa-candida.jpg'],
    'cottage': ['cottage1.jpg', 'cottage2.jpg', 'cottage3.jpg'],
    'function_hall': ['fhall1.jpg', 'fhall2.jpg', 'fhall3.jpg']
  };

  // Group by type
  const typeOrder = ['room', 'cottage', 'function_hall'];
  const typeLabels = { 'room': 'Room', 'cottage': 'Cottage', 'function_hall': 'Function Hall' };
  const typeUnitLabel = { 'room': '/ night', 'cottage': '/ night', 'function_hall': '/ event' };

  let totalShown = 0;

  typeOrder.forEach(groupType => {
    // Apply type filter from filter buttons
    if (currentCatalogFilter !== 'all' && groupType !== currentCatalogFilter) return;

    const groupFacs = availableFacs.filter(f => f.type === groupType);
    if (groupFacs.length === 0) return;

    totalShown += groupFacs.length;

    // Section heading
    const group = document.createElement('div');
    group.className = 'fac-type-group';

    const heading = document.createElement('div');
    heading.className = 'fac-type-group-title';
    // Icon mapping
    const typeIcons = { 'room': 'fas fa-bed', 'cottage': 'fas fa-umbrella-beach', 'function_hall': 'fas fa-star' };
    const iconClass = typeIcons[groupType] || 'fas fa-building';
    heading.innerHTML = `<div class="fac-type-group-title-icon"><i class="${iconClass}"></i></div>${typeLabels[groupType] || groupType}`;
    group.appendChild(heading);

    const row = document.createElement('div');
    row.className = 'fac-grid-row';

    groupFacs.forEach(f => {
      const id = String(f.id);
      const price = parseFloat(f.price);
      const capacity = f.capacity || 'N/A';
      const name = f.name;
      const unitLabel = typeUnitLabel[groupType] || '/ night';
      const isSelected = selectedFacilityIds.includes(id);

      const imgs = f.image_path ? [f.image_path] : (galleries[groupType] || ['hero-section.jpg']);
      if (activeImageIndices[id] === undefined) activeImageIndices[id] = 0;
      const currentImg = imgs[activeImageIndices[id]];

      // Determine amenities and badges per type
      const amenitiesByType = {
        'room': [
          { icon: 'fas fa-bed', label: 'Comfortable Bed' },
          { icon: 'fas fa-chair', label: 'Seating Chairs' },
          { icon: 'fas fa-snowflake', label: 'Soft Pillows' },
          { icon: 'fas fa-couch', label: 'Sofa Lounge' }
        ],
        'cottage': [
          { icon: 'fas fa-umbrella-beach', label: 'Open Air Cottage' },
          { icon: 'fas fa-users', label: 'Group Seating' },
          { icon: 'fas fa-water', label: 'Spring View' },
          { icon: 'fas fa-concierge-bell', label: 'On-call Service' }
        ],
        'function_hall': [
          { icon: 'fas fa-microphone', label: 'Sound System' },
          { icon: 'fas fa-tv', label: 'Projector & Screen' },
          { icon: 'fas fa-chair', label: 'Banquet Seating' },
          { icon: 'fas fa-utensils', label: 'Catering Area' }
        ]
      };
      const amenities = amenitiesByType[groupType] || amenitiesByType['room'];
      const amenitiesHtml = amenities.map(a =>
        `<div class="bc-amenity-item"><i class="${a.icon}"></i><span>${a.label}</span></div>`
      ).join('');

      const ratingByType = { 'room': '8.5', 'cottage': '8.2', 'function_hall': '8.8' };
      const ratingLabelByType = { 'room': 'Room comfort and quality', 'cottage': 'Spacious and relaxing', 'function_hall': 'Excellent event venue' };
      const rating = ratingByType[groupType] || '8.5';
      const ratingLabel = ratingLabelByType[groupType] || 'Comfort and quality';

      const badgesByType = {
        'room': [
          { text: 'Recommended', color: '#006ce4', bg: '#e8f0fe' },
          { text: 'Kids stay FREE!', color: '#006ce4', bg: '#e8f0fe' },
          { text: 'Preferred by Families', color: '#8e24aa', bg: '#f3e5f5' }
        ],
        'cottage': [
          { text: 'Great for Groups', color: '#006ce4', bg: '#e8f0fe' },
          { text: 'Open Air & Fresh', color: '#2e7d32', bg: '#e8f5e9' },
          { text: 'Kids Friendly', color: '#006ce4', bg: '#e8f0fe' }
        ],
        'function_hall': [
          { text: 'Perfect for Events', color: '#006ce4', bg: '#e8f0fe' },
          { text: 'High Capacity', color: '#2e7d32', bg: '#e8f5e9' },
          { text: 'Premium Setup', color: '#8e24aa', bg: '#f3e5f5' }
        ]
      };
      const badges = badgesByType[groupType] || badgesByType['room'];
      const badgesHtml = badges.map(b =>
        `<span class="bc-badge-pill" style="color:${b.color};background:${b.bg};">${b.text}</span>`
      ).join('');

      const imgCount = f.image_path ? 1 : (galleries[groupType] || []).length;
      const currentImgIdx = (activeImageIndices[id] || 0);

      const card = document.createElement('div');
      card.className = `landscape-card bc-style-card${isSelected ? ' selected' : ''}`;
      card.dataset.id = id;
      card.onclick = () => selectCatalogFacility(id);

      card.innerHTML = `
        <div class="bc-img-wrap">
          <img class="bc-card-img" src="images/${currentImg}" alt="${name}" id="imgGallery_${id}" loading="lazy" onclick="openImageModal(this.src); event.stopPropagation();" style="cursor: zoom-in;">
          <div class="bc-ribbon"><i class="fas fa-star"></i> OUR BEST SELLER!</div>
          <button class="bc-nav-btn bc-nav-prev" onclick="slideGalleryImage('${id}', -1, event)" aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>
          <button class="bc-nav-btn bc-nav-next" onclick="slideGalleryImage('${id}', 1, event)" aria-label="Next image"><i class="fas fa-chevron-right"></i></button>
          <div class="bc-img-counter" id="counterGallery_${id}">${currentImgIdx + 1}/${imgCount}</div>
          <div class="landscape-selected-overlay"><i class="fas fa-check-circle"></i> Selected</div>
        </div>
        <div class="bc-card-body">
          <div class="bc-name">${name}</div>
          <div class="bc-subinfo">${typeLabels[groupType]} &middot; Capacity ${capacity} pax</div>
          <div class="bc-badges-row">${badgesHtml}</div>
          <div class="bc-rating-row">
            <div class="bc-rating-score">
              <span class="bc-score-num">${rating}</span>
            </div>
            <div class="bc-rating-text">
              <div class="bc-rating-label">Excellent</div>
              <div class="bc-rating-sub">${ratingLabel}</div>
            </div>
          </div>
          <div class="bc-amenities">${amenitiesHtml}</div>
          <div class="bc-price-row">
            <div class="bc-price-main">₱${price.toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
            <div class="bc-price-unit">${unitLabel}</div>
          </div>
        </div>
      `;

      row.appendChild(card);
    });

    group.appendChild(row);
    grid.appendChild(group);
  });

  if (totalShown === 0) {
    grid.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);"><i class="fas fa-filter" style="font-size:2rem;margin-bottom:12px;display:block;opacity:0.5;"></i>No facilities of this type match your filter.</div>';
  }

  // Sync selected state visually after re-render
  onFacilitySelectionChange(selectedFacilityIds);
}

function _renderFacilityCatalog_legacy_unused() {
  const galleries = {
    'room': ['villa-gracia.jpg', 'villa-carolina.jpg', 'villa-candida.jpg'],
    'cottage': ['cottage1.jpg', 'cottage2.jpg', 'cottage3.jpg'],
    'function_hall': ['fhall1.jpg', 'fhall2.jpg', 'fhall3.jpg']
  };

  let shownCount = 0;
  [].forEach(f => {
    const id = String(f.id);
    const price = parseFloat(f.price);
    const regularPrice = Math.round(price * 1.38);
    const discountAmount = regularPrice - price;
    const type = f.type;
    const capacity = f.capacity || 'N/A';
    const name = f.name;
    
    // Filter by type
    if (currentCatalogFilter !== 'all' && type !== currentCatalogFilter) return;
    
    shownCount++;
    const isSelected = selectedFacilityIds.includes(id);
    
    // Parse amenities from DB string
    const rawAmenities = f.amenities ? f.amenities.split(',').map(a => a.trim().toLowerCase()) : [];
    let amenitiesHtml = '';
    rawAmenities.forEach(am => {
      if (!am) return;
      let icon = 'fa-check';
      let cleanName = am.charAt(0).toUpperCase() + am.slice(1);
      
      if (am.includes('bed')) { icon = 'fa-bed'; cleanName = 'Comfortable Bed'; }
      else if (am.includes('chair')) { icon = 'fa-chair'; cleanName = 'Seating Chairs'; }
      else if (am.includes('pillow')) { icon = 'fa-feather'; cleanName = 'Soft Pillows'; }
      else if (am.includes('sofa')) { icon = 'fa-couch'; cleanName = 'Sofa Lounge'; }
      else if (am.includes('table')) { icon = 'fa-table'; cleanName = 'Dining Table'; }
      else if (am.includes('tv') || am.includes('television')) { icon = 'fa-tv'; cleanName = 'Satellite TV'; }
      else if (am.includes('wifi') || am.includes('internet')) { icon = 'fa-wifi'; cleanName = 'Free WiFi'; }
      else if (am.includes('aircon') || am.includes('ac')) { icon = 'fa-wind'; cleanName = 'Air Conditioning'; }
      
      amenitiesHtml += `
        <div class="agoda-amenity-item">
          <i class="fas ${icon}"></i> <span>${cleanName}</span>
        </div>
      `;
    });
    
    // Fallback if empty amenities
    if (!amenitiesHtml) {
      amenitiesHtml = `
        <div class="agoda-amenity-item"><i class="fas fa-check"></i> Clean and Sanitized</div>
        <div class="agoda-amenity-item"><i class="fas fa-square-parking"></i> Parking Included</div>
        <div class="agoda-amenity-item"><i class="fas fa-snowflake"></i> Cool Mountain Air</div>
      `;
    }
    
    const imgs = f.image_path ? [f.image_path] : (galleries[type] || ['hero-section.jpg']);
    if (activeImageIndices[id] === undefined) {
      activeImageIndices[id] = 0;
    }
    const currentImg = imgs[activeImageIndices[id]];
    
    const typeLabel = type === 'room' ? 'Room' : (type === 'cottage' ? 'Cottage' : 'Function Hall');
    const unitLabel = type.includes('hall') ? '/ event' : '/ night';
    
    const card = document.createElement('div');
    card.className = `agoda-card${isSelected ? ' selected' : ''}`;
    card.dataset.id = id;
    
    card.innerHTML = `
      <!-- LEFT SECTION (IMAGE GALLERY & SPEC DETAILS) -->
      <div class="agoda-left">
        <div class="agoda-gallery">
          <span class="agoda-gallery-tag">Our best seller!</span>
          <button type="button" class="agoda-gallery-nav prev" onclick="slideGalleryImage('${id}', -1, event)"><i class="fas fa-chevron-left"></i></button>
          <img src="images/${currentImg}" alt="${name}" id="imgGallery_${id}" onclick="openImageModal(this.src); event.stopPropagation();" style="cursor: zoom-in;">
          <button type="button" class="agoda-gallery-nav next" onclick="slideGalleryImage('${id}', 1, event)"><i class="fas fa-chevron-right"></i></button>
          <div class="agoda-gallery-counter" id="counterGallery_${id}">${activeImageIndices[id] + 1}/${imgs.length}</div>
        </div>
        <div class="agoda-left-details">
          <h3 class="agoda-fac-title">${name}</h3>
          <div class="agoda-fac-specs">${typeLabel} &middot; Capacity ${capacity} pax</div>
          <div class="agoda-left-badges">
            <span class="agoda-purple-badge">Recommended</span>
            <span class="agoda-purple-badge">Kids stay FREE!</span>
            <span class="agoda-purple-badge">Preferred by Families</span>
          </div>
          <div class="agoda-rating-section">
            <div class="agoda-rating-score">8.5</div>
            <div class="agoda-rating-desc">
              <div class="lbl">Excellent</div>
              <div class="sub">Room comfort and quality</div>
            </div>
          </div>
          <div class="agoda-amenities-list">
            ${amenitiesHtml}
          </div>
        </div>
      </div>
      
      <!-- MIDDLE SECTION (DEALS & DYNAMIC PRICING) -->
      <div class="agoda-middle">
        <div class="agoda-top-banner"><i class="fas fa-fire"></i> Lowest price available!</div>
        <div class="agoda-middle-content">
          <div class="agoda-deals-list">
            <div class="agoda-deal-item green"><i class="fas fa-check"></i> Breakfast included</div>
            <div class="agoda-deal-item green"><i class="fas fa-check"></i> No payment until Jun 2, 2026</div>
            <div class="agoda-deal-item"><i class="fas fa-square-parking"></i> Free Parking</div>
            <div class="agoda-deal-item"><i class="fas fa-wifi"></i> Free WiFi</div>
          </div>
          <div class="agoda-pricing-box">
            <span class="agoda-cheapest-label">Cheapest price you've seen!</span>
            <div class="agoda-discount-applied">
              <i class="fas fa-tags"></i> ₱${discountAmount.toLocaleString('en-PH')} applied
            </div>
            <span class="agoda-price-old">₱${regularPrice.toLocaleString('en-PH')}</span>
            <div class="agoda-price-new">
              <span>₱</span>${price.toLocaleString('en-PH', {minimumFractionDigits: 0})}
            </div>
            <span class="agoda-price-sub">${unitLabel} before taxes</span>
          </div>
        </div>
      </div>
      
      <!-- RIGHT SECTION (ACTION BUTTON) -->
      <div class="agoda-right">
        <button type="button" class="agoda-book-btn${isSelected ? ' selected' : ''}" onclick="selectCatalogFacility('${id}')">
          Book
          <span>${isSelected ? 'Selected' : 'Pay later'}</span>
        </button>
        <span class="agoda-right-status">Our last few!</span>
      </div>
    `;
    grid.appendChild(card);
  });
  
  if (shownCount === 0) {
    grid.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);"><i class="fas fa-filter" style="font-size:2rem;margin-bottom:12px;display:block;opacity:0.5;"></i>No facilities of this type match your filter.</div>';
  }
}

/* ── TERMS & CONDITIONS LOGIC ── */
function updateConfirmBtn() {
  const details = document.getElementById('detailsCheck');
  const terms   = document.getElementById('termsCheck');
  const btn     = document.getElementById('confirmBtn');
  if (btn) btn.disabled = !(details && details.checked && terms && terms.checked);
}

function openTermsModal(tab) {
  const m = document.getElementById('termsModal');
  if (m) m.classList.add('show');
  document.body.style.overflow = 'hidden';
  switchTermsTab(tab || 'privacy');
}

function closeTermsModal(agreed) {
  const m = document.getElementById('termsModal');
  if (m) m.classList.remove('show');
  document.body.style.overflow = '';
  // If user clicked "I Understand", auto-check the terms checkbox and scroll to submit
  if (agreed) {
    const termsBox = document.getElementById('termsCheck');
    if (termsBox && !termsBox.checked) {
      termsBox.checked = true;
    }
    updateConfirmBtn();
    // Scroll smoothly to the confirm/submit button
    const btn = document.getElementById('confirmBtn');
    if (btn) {
      setTimeout(() => {
        btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 200);
    }
  }
}

function switchTermsTab(tab) {
  // Toggle tab buttons
  document.getElementById('tabBtnPrivacy').classList.toggle('active', tab === 'privacy');
  document.getElementById('tabBtnTerms').classList.toggle('active', tab === 'terms');
  // Toggle panes
  document.getElementById('tabPrivacy').classList.toggle('active', tab === 'privacy');
  document.getElementById('tabTerms').classList.toggle('active', tab === 'terms');
  // Reset scroll to top
  const body = document.querySelector('.terms-modal-body');
  if (body) body.scrollTop = 0;
}

// Close modal when clicking the dark overlay backdrop
document.getElementById('termsModal').addEventListener('click', function(e) {
  if (e.target === this) closeTermsModal();
});

/* ── INIT ON DOMLOAD ── */
document.addEventListener('DOMContentLoaded', () => {
  const t = new Date();
  currentYear = t.getFullYear();
  currentMonth = t.getMonth();

  // Auto-capitalize name fields
  function capitalizeWords(str) {
    return str.replace(/\b\w/g, c => c.toUpperCase());
  }
  ['fFirstName', 'fLastName'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', function() {
      const pos = this.selectionStart;
      this.value = capitalizeWords(this.value);
      this.setSelectionRange(pos, pos);
    });
  });

  // Pre-fill parameters if present
  const preCheckIn = document.getElementById('hCheckIn').value;
  if (preCheckIn) {
    checkInDate = preCheckIn;
    // Default overnight range checkout to checkIn + 1 day
    const d = new Date(preCheckIn + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    checkOutDate = fmtDate(d);
    
    currentYear = d.getFullYear();
    currentMonth = d.getMonth();
    
    updateSearchBarDisplays();
    triggerSearch();
  }

  // Pre-select facility from URL param
  const preFacId = document.getElementById('hFacilityId').value;
  if (preFacId) {
    // Wait for dropdown populating
    setTimeout(() => {
      const facSel = document.getElementById('fFacility');
      if (facSel) {
        facSel.value = preFacId;
        onFacilitySelectionChange(preFacId);
      }
    }, 300);
  }

  <?php if ($edit_item): ?>
  // Edit cart item prepopulating
  bookingMode = <?= json_encode($edit_item['mode'] ?? 'daytour') ?>;
  checkInDate = <?= json_encode($edit_item['check_in_date'] ?? $edit_item['check_in'] ?? '') ?>;
  checkOutDate = <?= json_encode($edit_item['check_out_date'] ?? $edit_item['check_out'] ?? '') ?>;
  selectedSlot = <?= json_encode($edit_item['time_slot'] ?? '') ?>;

  guestsState.adults = <?= intval($edit_item['num_adults'] ?? 1) ?>;
  guestsState.children5 = <?= intval($edit_item['num_children'] ?? 0) ?>;
  guestsState.below5 = <?= intval($edit_item['num_below5'] ?? 0) ?>;
  guestsState.pwd = <?= intval($edit_item['num_pwd'] ?? $edit_item['num_discounted'] ?? 0) ?>;

  document.getElementById('fArea').value = <?= json_encode($edit_item['area_id'] ?? '') ?>;
  document.getElementById('fTransport').value = <?= json_encode($edit_item['transport_opt'] ?? ($edit_item['transportation'] ?? 'none')) ?>;
  document.getElementById('fSpecial').value = <?= json_encode($edit_item['notes'] ?? '') ?>;

  updateGuestsStepperUI();
  updateSearchBarDisplays();
  
  // Switch calendar tab internally without wiping inputs
  document.querySelectorAll('.popup-tab').forEach(t => t.classList.remove('active'));
  if (bookingMode === 'daytour') {
    document.getElementById('tabDayUse').classList.add('active');
    document.getElementById('dayuseInfoBanner').style.display = 'flex';
    document.getElementById('dayuseSlotSection').style.display = 'block';
  } else {
    document.getElementById('tabOvernight').classList.add('active');
  }

  triggerSearch();

  // Populate facility after loading
  setTimeout(() => {
    const facSel = document.getElementById('fFacility');
    if (facSel) {
      facSel.value = <?= json_encode($edit_item['facility_id']) ?>;
      onFacilitySelectionChange(<?= json_encode($edit_item['facility_id']) ?>);
    }
  }, 400);

  <?php endif; ?>

  // Steppers init
  updateGuestsStepperUI();

  // Prevent premature form submit
  document.getElementById('bkForm').addEventListener('submit', function(e) {
      if (currentStep < 3) {
          e.preventDefault();
          alert('Please complete all steps before submitting.');
      }
  });

  // ── AUTO-JUMP TO STEP 2 WHEN PROCEEDING WITH A SELECTED CART ITEM ──
  // When the user clicks "Proceed to Booking" for a selected cart item,
  // skip Step 1 and go directly to "Location & Details" (Step 2).
  const shouldAutoJump = <?php echo (!empty($selected_cart_id) || !empty($selected_cart_ids)) ? 'true' : 'false'; ?>;
  if (shouldAutoJump) {
    // Jump straight to Step 2 without Step 1 validation
    currentStep = 2;
    document.querySelectorAll('.step-panel').forEach((p, i) => {
      p.classList.toggle('active', i + 1 === 2);
    });
    updateStepBar();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
});

function openImageModal(src) {
  const modal = document.getElementById('imageModal');
  const modalImg = document.getElementById('modalImage');
  modalImg.src = src;
  modal.style.display = 'flex';
}

function closeImageModal() {
  const modal = document.getElementById('imageModal');
  modal.style.display = 'none';
}
</script>

<!-- Image View Modal -->
<div id="imageModal" class="img-view-modal" onclick="closeImageModal()">
  <span class="img-view-modal-close" onclick="closeImageModal()">&times;</span>
  <img class="img-view-modal-content" id="modalImage" onclick="event.stopPropagation()">
</div>

<script>
/* ── Hero Animations ── */
(function() {
  /* 1. Zoom-in the BG on load */
  const heroBg = document.getElementById('heroBg');
  if (heroBg) {
    window.addEventListener('load', () => {
      setTimeout(() => heroBg.classList.add('loaded'), 80);
    });
  }

  /* 2. Parallax on scroll */
  const hero = document.querySelector('.pg-hero');
  if (hero && heroBg) {
    window.addEventListener('scroll', () => {
      const scrollY = window.scrollY;
      const heroH = hero.offsetHeight;
      if (scrollY < heroH + 100) {
        heroBg.style.transform = `scale(1) translateY(${scrollY * 0.28}px)`;
      }
    }, { passive: true });
  }

  /* 3. Floating particles */
  const container = document.getElementById('heroParticles');
  if (container) {
    const heroEl = document.querySelector('.pg-hero');
    const heroHeight = heroEl ? heroEl.offsetHeight : 320;
    const count = 18;
    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 6 + 3;
      p.style.cssText = [
        `width:${size}px`,
        `height:${size}px`,
        `left:${Math.random() * 100}%`,
        `bottom:${-size}px`,
        `opacity:${Math.random() * 0.5 + 0.1}`,
        `animation-duration:${Math.random() * 6 + 5}s`,
        `animation-delay:${Math.random() * 8}s`,
        `background:rgba(${Math.random() > 0.5 ? '255,255,255' : '201,168,76'},${Math.random() * 0.25 + 0.08})`
      ].join(';');
      container.appendChild(p);
    }
  }
})();
</script>

</body>
</html>

