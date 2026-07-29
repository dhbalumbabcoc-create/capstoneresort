<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

define('GUEST_SESSION_TIMEOUT', 8 * 3600);
$guest_email = $_SESSION['guest_email'] ?? null;
$guest_logged_in = $_SESSION['guest_logged_in'] ?? false;
if (!$guest_email || !$guest_logged_in) { header('Location: ' . BASE_URL . 'guest_login.php'); exit(); }
if (isset($_SESSION['guest_last_activity']) && (time() - (int)$_SESSION['guest_last_activity']) > GUEST_SESSION_TIMEOUT) {
    session_unset(); session_destroy(); header('Location: landing.php?msg=session_expired'); exit();
}
$_SESSION['guest_last_activity'] = time();

// Ensure guest_accounts has profile_pic column
$check_col = $conn->query("SHOW COLUMNS FROM `guest_accounts` LIKE 'profile_pic'");
if ($check_col && $check_col->num_rows === 0) {
    $conn->query("ALTER TABLE `guest_accounts` ADD `profile_pic` VARCHAR(255) DEFAULT NULL");
}

// Handle cancel booking — cancels ALL sibling bookings in the same transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'])) {
    $cid = intval($_POST['cancel_booking_id']);

    // Fetch the primary booking to get transaction key
    $pstmt = $conn->prepare("SELECT guest_email, check_in_date, check_out_date, created_at FROM bookings WHERE id=? AND guest_email=? AND status='pending'");
    $pstmt->bind_param("is", $cid, $guest_email); $pstmt->execute();
    $prow = $pstmt->get_result()->fetch_assoc(); $pstmt->close();

    if ($prow) {
        // Cancel all siblings with same transaction key
        $cs = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE guest_email=? AND check_in_date=? AND check_out_date=? AND created_at=? AND status='pending'");
        $cs->bind_param("ssss", $prow['guest_email'], $prow['check_in_date'], $prow['check_out_date'], $prow['created_at']);
        $cs->execute(); $cs->close();

        // Send cancellation email for the primary booking
        $bstmt = $conn->prepare("SELECT b.*, f.name as facility_name, a.name as area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=?");
        $bstmt->bind_param("i", $cid); $bstmt->execute();
        $bdata = $bstmt->get_result()->fetch_assoc(); $bstmt->close();
        if ($bdata && !empty($bdata['guest_email'])) {
            require_once 'includes/send_status_email.php';
            sendBookingStatusEmail($bdata, 'cancelled');
        }
    }

    header("Location: guest_dashboard.php?tab=" . ($_POST['tab'] ?? 'upcoming')); exit();
}

// Handle edit booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_booking_id'])) {
    $eid          = intval($_POST['edit_booking_id']);
    $new_adults   = max(1, intval($_POST['edit_num_adults']   ?? 1));
    $new_children = max(0, intval($_POST['edit_num_children'] ?? 0));
    $new_checkin  = trim($_POST['edit_check_in']  ?? '');
    $new_checkout = trim($_POST['edit_check_out'] ?? '');
    $addon_ids    = array_map('intval', (array)($_POST['addon_ids']  ?? []));
    $addon_qtys   = array_map('intval', (array)($_POST['addon_qtys'] ?? []));

    // Only allow editing pending bookings belonging to this guest
    $chk = $conn->prepare("SELECT b.*, f.price AS facility_price, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=? AND b.guest_email=? AND b.status='pending'");
    $chk->bind_param("is", $eid, $guest_email); $chk->execute();
    $brow = $chk->get_result()->fetch_assoc(); $chk->close();
    if ($brow) {
        $new_guests = $new_adults + $new_children;
        if ($brow['mode'] === 'daytour') { $new_checkout = $new_checkin; }

        // Recalculate base price
        $facility_price = floatval($brow['facility_price'] ?? 0);
        $area_name_lc   = strtolower($brow['area_name'] ?? '');
        $isBoth = strpos($area_name_lc,'both')!==false || (strpos($area_name_lc,'sinulom')!==false && strpos($area_name_lc,'bolao')!==false);
        $rate_adult = $isBoth ? 160 : 110;
        $rate_child = $isBoth ? 85  : 60;
        $nights = 1;
        if ($brow['mode'] === 'overnight' && $new_checkin && $new_checkout && $new_checkout > $new_checkin) {
            $nights = max(1, (int)((strtotime($new_checkout) - strtotime($new_checkin)) / 86400));
        }
        $base_price = ($facility_price * $nights) + ($new_adults * $rate_adult) + ($new_children * $rate_child);

        // Ensure booking_addons table exists with quantity column
        $conn->query("CREATE TABLE IF NOT EXISTS `booking_addons` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `booking_id` INT NOT NULL,
            `amenity_id` INT DEFAULT NULL,
            `facility_id` INT DEFAULT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("ALTER TABLE `booking_addons` ADD COLUMN IF NOT EXISTS `quantity` INT NOT NULL DEFAULT 1");

        // Delete old add-ons for this booking
        $del = $conn->prepare("DELETE FROM booking_addons WHERE booking_id=?");
        $del->bind_param("i", $eid); $del->execute(); $del->close();

        // Insert new add-ons and calculate add-on total
        $addon_total = 0.0;
        foreach ($addon_ids as $idx => $aid) {
            if ($aid <= 0) continue;
            $qty = max(1, $addon_qtys[$idx] ?? 1);
            $astmt = $conn->prepare("SELECT price FROM amenities WHERE id=? AND status='active'");
            $astmt->bind_param("i", $aid); $astmt->execute();
            $arow = $astmt->get_result()->fetch_assoc(); $astmt->close();
            if ($arow) {
                $addon_total += floatval($arow['price']) * $qty;
                $ins = $conn->prepare("INSERT INTO booking_addons (booking_id, amenity_id, facility_id, quantity) VALUES (?,?,NULL,?)");
                $ins->bind_param("iii", $eid, $aid, $qty); $ins->execute(); $ins->close();
            }
        }

        $new_total = $base_price + $addon_total;
        $up = $conn->prepare("UPDATE bookings SET num_adults=?, num_children=?, num_guests=?, check_in_date=?, check_out_date=?, total_price=? WHERE id=? AND guest_email=? AND status='pending'");
        $up->bind_param("iiissdis", $new_adults, $new_children, $new_guests, $new_checkin, $new_checkout, $new_total, $eid, $guest_email);
        $up->execute(); $up->close();

        // Send updated receipt email — re-fetch after update
        $bstmt = $conn->prepare("SELECT b.*, f.name as facility_name, a.name as area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=?");
        $bstmt->bind_param("i", $eid); $bstmt->execute();
        $bdata = $bstmt->get_result()->fetch_assoc(); $bstmt->close();

        if ($bdata && !empty($bdata['guest_email'])) {
            $notes_str = $bdata['notes'] ?? '';
            $time_slot = '';
            if (preg_match('/Time Slot:\s*(\S+)/i', $notes_str, $m)) {
                $time_slot = $m[1];
            }
            $email_data = [
                'booking_id'    => $eid,
                'guest_name'    => $bdata['guest_name']    ?? '',
                'guest_email'   => $bdata['guest_email']   ?? '',
                'guest_phone'   => $bdata['guest_phone']   ?? '',
                'facility_name' => $bdata['facility_name'] ?? 'N/A',
                'area_name'     => $bdata['area_name']     ?? 'N/A',
                'check_in_date' => $bdata['check_in_date'] ?? $new_checkin,
                'check_out_date'=> $bdata['check_out_date'] ?? $new_checkout,
                'num_adults'    => $bdata['num_adults']    ?? $new_adults,
                'num_children'  => $bdata['num_children']  ?? $new_children,
                'mode'          => $bdata['mode']          ?? 'daytour',
                'time_slot'     => $time_slot,
                'total_price'   => $new_total,
                'notes'         => $bdata['notes'] ?? '',
            ];
            try {
                require_once 'includes/send_booking_email.php';
                sendBookingConfirmationEmail($email_data);
            } catch (\Exception $e) {
                error_log("Edit booking email failed: " . $e->getMessage());
            }
        }
    }
    header("Location: guest_dashboard.php?tab=upcoming&edited=1"); exit();
}

// Handle confirmed booking add-ons update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addon_booking_id'])) {
    $aid_bk    = intval($_POST['addon_booking_id']);
    $addon_ids  = array_map('intval', (array)($_POST['addon_ids']  ?? []));
    $addon_qtys = array_map('intval', (array)($_POST['addon_qtys'] ?? []));

    // Only allow confirmed bookings belonging to this guest
    $chk = $conn->prepare("SELECT b.*, f.price AS facility_price, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=? AND b.guest_email=? AND b.status='confirmed'");
    $chk->bind_param("is", $aid_bk, $guest_email); $chk->execute();
    $brow = $chk->get_result()->fetch_assoc(); $chk->close();

    if ($brow) {
        // Calculate base price by taking current total and subtracting old add-ons
        $old_addon_total = 0.0;
        $astmt = $conn->prepare("SELECT SUM(a.price * ba.quantity) as tot FROM booking_addons ba JOIN amenities a ON ba.amenity_id = a.id WHERE ba.booking_id=?");
        $astmt->bind_param("i", $aid_bk);
        $astmt->execute();
        $arow = $astmt->get_result()->fetch_assoc();
        if ($arow && $arow['tot']) $old_addon_total = floatval($arow['tot']);
        $astmt->close();

        $base_price = floatval($brow['total_price']) - $old_addon_total;

        $conn->query("CREATE TABLE IF NOT EXISTS `booking_addons` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `booking_id` INT NOT NULL,
            `amenity_id` INT DEFAULT NULL,
            `facility_id` INT DEFAULT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("ALTER TABLE `booking_addons` ADD COLUMN IF NOT EXISTS `quantity` INT NOT NULL DEFAULT 1");

        // Delete old add-ons
        $del = $conn->prepare("DELETE FROM booking_addons WHERE booking_id=?");
        $del->bind_param("i", $aid_bk); $del->execute(); $del->close();

        // Insert new add-ons
        $addon_total = 0.0;
        foreach ($addon_ids as $idx => $aid) {
            if ($aid <= 0) continue;
            $qty = max(1, $addon_qtys[$idx] ?? 1);
            $astmt = $conn->prepare("SELECT price FROM amenities WHERE id=? AND status='active'");
            $astmt->bind_param("i", $aid); $astmt->execute();
            $arow = $astmt->get_result()->fetch_assoc(); $astmt->close();
            if ($arow) {
                $addon_total += floatval($arow['price']) * $qty;
                $ins = $conn->prepare("INSERT INTO booking_addons (booking_id, amenity_id, facility_id, quantity) VALUES (?,?,NULL,?)");
                $ins->bind_param("iii", $aid_bk, $aid, $qty); $ins->execute(); $ins->close();
            }
        }

        $new_total = $base_price + $addon_total;
        $up = $conn->prepare("UPDATE bookings SET total_price=? WHERE id=? AND guest_email=? AND status='confirmed'");
        $up->bind_param("dis", $new_total, $aid_bk, $guest_email);
        $up->execute(); $up->close();

        // Send updated receipt email
        $bstmt = $conn->prepare("SELECT b.*, f.name as facility_name, a.name as area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.id=?");
        $bstmt->bind_param("i", $aid_bk); $bstmt->execute();
        $bdata = $bstmt->get_result()->fetch_assoc(); $bstmt->close();
        if ($bdata && !empty($bdata['guest_email'])) {
            $notes_str = $bdata['notes'] ?? '';
            $time_slot = '';
            if (preg_match('/Time Slot:\s*(\S+)/i', $notes_str, $m)) $time_slot = $m[1];
            $email_data = [
                'booking_id'     => $aid_bk,
                'guest_name'     => $bdata['guest_name']    ?? '',
                'guest_email'    => $bdata['guest_email']   ?? '',
                'guest_phone'    => $bdata['guest_phone']   ?? '',
                'facility_name'  => $bdata['facility_name'] ?? 'N/A',
                'area_name'      => $bdata['area_name']     ?? 'N/A',
                'check_in_date'  => $bdata['check_in_date'] ?? '',
                'check_out_date' => $bdata['check_out_date'] ?? '',
                'num_adults'     => $bdata['num_adults']    ?? 0,
                'num_children'   => $bdata['num_children']  ?? 0,
                'mode'           => $bdata['mode']          ?? 'daytour',
                'time_slot'      => $time_slot,
                'total_price'    => $new_total,
                'notes'          => $bdata['notes'] ?? '',
            ];
            try {
                require_once 'includes/send_booking_email.php';
                sendBookingConfirmationEmail($email_data);
            } catch (\Exception $e) {
                error_log("Addon email failed: " . $e->getMessage());
            }
        }
    }
    header("Location: guest_dashboard.php?tab=upcoming&addon_saved=1"); exit();
}

// Fetch current guest account & profile info
$profile_pic = $_SESSION['guest_profile_pic'] ?? '';
$guest_name  = $_SESSION['guest_name']  ?? 'Guest';
$guest_phone = $_SESSION['guest_phone'] ?? '';

$ga_stmt = $conn->prepare("SELECT full_name, phone, profile_pic FROM guest_accounts WHERE email = ? LIMIT 1");
if ($ga_stmt) {
    $ga_stmt->bind_param('s', $guest_email);
    $ga_stmt->execute();
    $ga_row = $ga_stmt->get_result()->fetch_assoc();
    $ga_stmt->close();
    if ($ga_row) {
        if (!empty($ga_row['full_name']))   $guest_name  = $ga_row['full_name'];
        if (!empty($ga_row['phone']))       $guest_phone = $ga_row['phone'];
        if (!empty($ga_row['profile_pic'])) $profile_pic = $ga_row['profile_pic'];
    }
}

// Handle edit profile & photo upload
$profile_success = '';
$profile_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $new_first = trim($_POST['edit_first_name'] ?? '');
    $new_last  = trim($_POST['edit_last_name']  ?? '');
    $new_phone = trim($_POST['edit_phone']       ?? '');
    
    $new_profile_pic = $profile_pic;

    // Photo Upload Logic
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['profile_pic']['tmp_name'];
        $file_name = $_FILES['profile_pic']['name'];
        $file_size = $_FILES['profile_pic']['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($file_ext, $allowed)) {
            $profile_error = 'Invalid photo format. Please upload JPG, PNG, WEBP, or GIF.';
        } elseif ($file_size > 5 * 1024 * 1024) {
            $profile_error = 'Image size must be under 5MB.';
        } else {
            $upload_dir = 'uploads/profile_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $clean_email = preg_replace('/[^a-zA-Z0-9]/', '_', $guest_email);
            $target_filename = 'guest_' . $clean_email . '_' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $target_filename;

            if (move_uploaded_file($file_tmp, $target_path)) {
                if (!empty($profile_pic) && file_exists($upload_dir . $profile_pic)) {
                    @unlink($upload_dir . $profile_pic);
                }
                $new_profile_pic = $target_filename;
            } else {
                $profile_error = 'Failed to save uploaded photo. Please try again.';
            }
        }
    }

    if (empty($profile_error)) {
        if (empty($new_first)) {
            $profile_error = 'First name is required.';
        } elseif (!empty($new_phone) && !preg_match('/^\d{11}$/', $new_phone)) {
            $profile_error = 'Phone number must be exactly 11 digits.';
        } else {
            $new_name = trim($new_first . ' ' . $new_last);
            
            // Update bookings
            $up = $conn->prepare("UPDATE bookings SET guest_name=?, guest_phone=? WHERE guest_email=?");
            $up->bind_param("sss", $new_name, $new_phone, $guest_email);
            $up->execute(); $up->close();

            // Update guest_accounts with profile_pic
            $upga = $conn->prepare("UPDATE guest_accounts SET full_name=?, phone=?, profile_pic=? WHERE email=?");
            if ($upga) { 
                $upga->bind_param("ssss", $new_name, $new_phone, $new_profile_pic, $guest_email); 
                $upga->execute(); 
                $upga->close(); 
            }

            $_SESSION['guest_name']        = $new_name;
            $_SESSION['guest_phone']       = $new_phone;
            $_SESSION['guest_profile_pic'] = $new_profile_pic;
            $profile_success = 'Profile updated successfully!';

            $guest_name  = $new_name;
            $guest_phone = $new_phone;
            $profile_pic = $new_profile_pic;
        }
    }
}

// Fetch all bookings for statistics & tabs
$stmt = $conn->prepare("SELECT b.*, f.name AS facility_name, f.capacity, f.price AS facility_price, a.name AS area_name FROM bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN areas a ON b.area_id=a.id WHERE b.guest_email=? ORDER BY b.created_at DESC");
$stmt->bind_param("s", $guest_email); $stmt->execute();
$res = $stmt->get_result();
$all_bookings = [];
while ($r = $res->fetch_assoc()) $all_bookings[] = $r;
$stmt->close();

if (empty($guest_name) || $guest_name === 'Guest') {
    $guest_name = $all_bookings[0]['guest_name'] ?? 'Guest';
}
if (empty($guest_phone)) {
    $guest_phone = $all_bookings[0]['guest_phone'] ?? '';
}

$name_parts  = explode(' ', trim($guest_name), 2);
$first_name  = $name_parts[0] ?? 'Guest';
$last_name   = $name_parts[1] ?? '';
$initials    = strtoupper(substr($first_name, 0, 1)) . (isset($last_name[0]) ? strtoupper(substr($last_name, 0, 1)) : '');
if (empty($initials)) $initials = 'G';

// Helper function to render avatar image or fallback initials
function getAvatarHtml($profile_pic, $initials, $sizeCss = 'width:56px;height:56px;font-size:1.35rem;') {
    $picPath = 'uploads/profile_photos/' . $profile_pic;
    if (!empty($profile_pic) && file_exists($picPath)) {
        $ver = filemtime($picPath);
        return '<img src="' . htmlspecialchars($picPath) . '?v=' . $ver . '" alt="Profile Avatar" style="' . $sizeCss . 'border-radius:50%;object-fit:cover;box-shadow:0 4px 12px rgba(0,0,0,0.15);border:2px solid #fff;">';
    }
    return '<div class="avatar-initials-circle" style="' . $sizeCss . 'border-radius:50%;background:linear-gradient(135deg,#2d5a3d,#1a3d2b);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;border:2px solid rgba(255,255,255,0.4);box-shadow:0 4px 12px rgba(0,0,0,0.12);">' . htmlspecialchars($initials) . '</div>';
}

// Group sibling bookings
function groupBookings(array $bookings): array {
    $groups = [];
    foreach ($bookings as $b) {
        $key = $b['guest_email'] . '|' . $b['check_in_date'] . '|' . $b['check_out_date'] . '|' . $b['created_at'];
        if (!isset($groups[$key])) {
            $groups[$key] = $b;
            $groups[$key]['facility_names'] = [$b['facility_name'] ?? 'N/A'];
            $groups[$key]['all_ids']        = [$b['id']];
            $groups[$key]['_total']         = floatval($b['total_price']);
            $groups[$key]['_adults']        = intval($b['num_adults']);
            $groups[$key]['_children']      = intval($b['num_children']);
        } else {
            $groups[$key]['facility_names'][] = $b['facility_name'] ?? 'N/A';
            $groups[$key]['all_ids'][]        = $b['id'];
            $groups[$key]['_total']          += floatval($b['total_price']);
            $groups[$key]['_adults']         += intval($b['num_adults']);
            $groups[$key]['_children']       += intval($b['num_children']);
        }
    }
    foreach ($groups as &$g) {
        $g['total_price']  = $g['_total'];
        $g['num_adults']   = $g['_adults'];
        $g['num_children'] = $g['_children'];
        $g['num_guests']   = $g['_adults'] + $g['_children'];
        $g['facility_name_display'] = implode(', ', array_unique($g['facility_names']));
        $g['primary_id']  = min($g['all_ids']);
    }
    unset($g);
    return array_values($groups);
}

$upcoming_raw = array_values(array_filter($all_bookings, fn($b) => in_array($b['status'], ['pending','confirmed'])));
$history_raw  = array_values(array_filter($all_bookings, fn($b) => in_array($b['status'], ['cancelled','declined','completed','checked out'])));

$upcoming = groupBookings($upcoming_raw);
$history  = groupBookings($history_raw);

$confirmed_cnt = count(array_filter($upcoming, fn($b) => $b['status']==='confirmed'));
$total_stays = count(array_filter($history, fn($b) => in_array($b['status'], ['completed','checked out'])));
$total_spent = array_sum(array_column(array_filter($all_bookings, fn($b) => in_array($b['status'], ['confirmed','completed','checked out'])), 'total_price'));
$first_booking_date = !empty($all_bookings) ? date('F Y', strtotime(end($all_bookings)['created_at'])) : date('F Y');

$active_tab = $_GET['tab'] ?? 'profile';
$edit_success = isset($_GET['edited']) ? 'Booking updated successfully! An updated receipt has been sent to your email.' : '';
$edit_success = $edit_success ?: (isset($_GET['addon_saved']) ? 'Add-ons updated! An updated receipt has been sent to your email.' : '');

// Load active amenities for edit modal
$amenities_js = [];
$am_res = $conn->query("SELECT id, name, price FROM amenities WHERE status='active' ORDER BY name");
if ($am_res) { while ($am = $am_res->fetch_assoc()) $amenities_js[] = $am; }

function getFacilityImage($facility_name) {
    global $conn;
    static $cache = null;
    if ($cache === null && isset($conn)) {
        $cache = [];
        $res = $conn->query("SELECT name, image_path FROM facilities");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cache[strtolower(trim($row['name']))] = $row['image_path'];
            }
        }
    }
    
    $name = strtolower(trim($facility_name));
    if (isset($cache[$name]) && !empty($cache[$name])) {
        return 'images/' . $cache[$name];
    }

    if (strpos($name, 'gracia') !== false) {
        return 'images/villa-gracia.jpg';
    } elseif (strpos($name, 'carolina') !== false) {
        return 'images/villa-carolina.jpg';
    } elseif (strpos($name, 'candida') !== false) {
        return 'images/villa-candida.jpg';
    } elseif (strpos($name, 'cottage 1') !== false || $name === 'cottage1') {
        return 'images/cottage1.jpg';
    } elseif (strpos($name, 'cottage 2') !== false || $name === 'cottage2') {
        return 'images/cottage2.jpg';
    } elseif (strpos($name, 'cottage 3') !== false || $name === 'cottage3') {
        return 'images/cottage3.jpg';
    } elseif (strpos($name, 'hall 1') !== false || strpos($name, 'fhall1') !== false) {
        return 'images/fhall1.jpg';
    } elseif (strpos($name, 'hall 2') !== false || strpos($name, 'fhall2') !== false) {
        return 'images/fhall2.jpg';
    } elseif (strpos($name, 'hall 3') !== false || strpos($name, 'fhall3') !== false) {
        return 'images/fhall3.jpg';
    }
    if (strpos($name, 'cottage') !== false) return 'images/cottage1.jpg';
    if (strpos($name, 'hall') !== false) return 'images/fhall1.jpg';
    if (strpos($name, 'villa') !== false) return 'images/villa-carolina.jpg';
    return 'images/logo.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest Dashboard — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --gd-dark: #122b1e;
  --gd-primary: #1a3d2b;
  --gd-medium: #2d5a3d;
  --gd-light: #4a7c59;
  --gd-accent: #10b981;
  --gd-bg: #f4f7f4;
  --gd-card-bg: #ffffff;
  --txt-main: #0f172a;
  --txt-muted: #64748b;
  --border-light: #e2e8f0;
  --border-hover: #cbd5e1;
  --red-accent: #ef4444;
  --red-bg: #fef2f2;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background-color: var(--gd-bg);
  color: var(--txt-main);
  min-height: 100vh;
  display: flex;
  -webkit-font-smoothing: antialiased;
}

/* ── Sidebar Styling ── */
.gd-sidebar {
  position: fixed; top: 0; left: 0; height: 100vh; width: 260px;
  background: linear-gradient(180deg, #112a1d 0%, #1a3d2b 100%);
  display: flex; flex-direction: column; z-index: 100;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.gd-sb-brand {
  padding: 20px 20px 16px; display: flex; align-items: center; gap: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.gd-sb-brand img {
  width: 42px; height: 42px; border-radius: 12px; object-fit: cover;
  border: 2px solid rgba(255, 255, 255, 0.25); shadow: 0 4px 10px rgba(0,0,0,0.2);
}
.gd-sb-brand-txt strong {
  display: block; font-family: 'Playfair Display', serif; font-size: 0.95rem; font-weight: 700;
  color: #fff; letter-spacing: 0.3px; line-height: 1.2;
}
.gd-sb-brand-txt span {
  font-size: 0.72rem; color: #a7f3d0; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;
}

/* Sidebar Profile Card */
.gd-sb-profile-block {
  padding: 20px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  text-align: center; position: relative;
}
.gd-sb-avatar-wrap {
  position: relative; display: inline-block; margin-bottom: 10px; cursor: pointer;
}
.gd-sb-avatar-wrap:hover .gd-sb-avatar-edit-overlay { opacity: 1; }
.gd-sb-avatar-edit-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.5); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; color: #fff;
  font-size: 0.9rem; opacity: 0; transition: opacity 0.2s; border: 2px solid #fff;
}
.gd-sb-profile-name { color: #fff; font-weight: 700; font-size: 0.95rem; margin-bottom: 2px; }
.gd-sb-profile-email { color: rgba(255,255,255,0.65); font-size: 0.72rem; margin-bottom: 8px; word-break: break-all; }
.gd-sb-profile-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.3);
  border-radius: 20px; padding: 4px 12px; font-size: 0.7rem; font-weight: 600;
}
.gd-sb-profile-badge .dot {
  width: 7px; height: 7px; border-radius: 50%; background: #10b981;
  box-shadow: 0 0 8px #10b981; flex-shrink: 0;
}

.gd-sb-nav { flex: 1; padding: 16px 12px; overflow-y: auto; scrollbar-width: thin; }
.gd-sb-nav::-webkit-scrollbar { width: 4px; }
.gd-sb-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

.gd-sb-link {
  display: flex; align-items: center; gap: 12px; padding: 11px 16px; border-radius: 12px;
  color: rgba(255,255,255,0.75); font-size: 0.88rem; font-weight: 500; cursor: pointer;
  text-decoration: none; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); margin-bottom: 4px; position: relative;
}
.gd-sb-link i { width: 20px; text-align: center; font-size: 0.95rem; flex-shrink: 0; transition: transform 0.2s; }
.gd-sb-link:hover { background: rgba(255, 255, 255, 0.08); color: #fff; transform: translateX(2px); }
.gd-sb-link:hover i { transform: scale(1.1); }
.gd-sb-link.active {
  background: linear-gradient(90deg, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0.06) 100%);
  color: #fff; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.gd-sb-link.active::before {
  content: ''; position: absolute; left: 0; top: 20%; bottom: 20%; width: 4px;
  background: var(--gd-accent); border-radius: 0 4px 4px 0; box-shadow: 0 0 10px var(--gd-accent);
}

.gd-sb-logout { padding: 14px 12px; border-top: 1px solid rgba(255, 255, 255, 0.08); }
.gd-sb-logout a {
  display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-radius: 12px;
  color: rgba(255,255,255,0.7); font-size: 0.88rem; font-weight: 600; text-decoration: none; transition: all 0.2s;
}
.gd-sb-logout a:hover { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }

/* Mobile Navbar Toggle */
.mobile-header {
  display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px;
  background: var(--gd-dark); z-index: 99; padding: 0 16px; align-items: center;
  justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.15);
}
.mobile-header-brand { display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; }
.mobile-header-brand img { width: 32px; height: 32px; border-radius: 8px; }
.mobile-toggle-btn { background: none; border: none; color: #fff; font-size: 1.3rem; cursor: pointer; padding: 6px; }

/* ── Main Layout Content ── */
.gd-main { margin-left: 260px; flex: 1; min-height: 100vh; padding: 32px 44px; transition: margin 0.3s; }
.gd-content-wrap { max-width: 960px; margin: 0 auto; }

/* Header title */
.gd-page-header {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;
}
.gd-page-title {
  font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 800; color: var(--txt-main);
  position: relative; padding-bottom: 6px;
}
.gd-page-title::after {
  content: ''; position: absolute; bottom: 0; left: 0; width: 40px; height: 3.5px;
  background: var(--gd-primary); border-radius: 2px;
}

/* ── Luxury Profile Card ── */
.profile-hero-card {
  background: var(--gd-card-bg); border-radius: 20px; border: 1px solid var(--border-light);
  overflow: hidden; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05); margin-bottom: 20px; transition: transform 0.2s, box-shadow 0.2s;
}
.profile-hero-banner {
  height: 120px; background: linear-gradient(135deg, #112a1d 0%, #2d5a3d 100%);
  position: relative; display: flex; align-items: flex-end; padding: 0 30px 15px;
}
.profile-hero-banner::after {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle at 80% 20%, rgba(16,185,129,0.15) 0%, transparent 60%);
}
.profile-avatar-container {
  position: relative; margin-top: -50px; padding: 0 30px; display: flex; align-items: flex-end; justify-content: space-between;
}
.profile-avatar-box {
  position: relative; width: 100px; height: 100px; border-radius: 50%;
  border: 4px solid #fff; box-shadow: 0 8px 24px rgba(0,0,0,0.15); background: #fff;
  cursor: pointer; overflow: hidden;
}
.profile-avatar-box img, .profile-avatar-box .avatar-initials-circle {
  width: 100%!important; height: 100%!important; border: none!important; border-radius: 0!important; box-shadow: none!important;
}
.profile-avatar-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.2s;
  display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; font-size: 0.75rem; font-weight: 600; gap: 4px;
}
.profile-avatar-box:hover .profile-avatar-overlay { opacity: 1; }

.profile-card-body { padding: 24px 30px 30px; }
.profile-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px 30px; margin-bottom: 24px; }
.profile-field-item { background: #f8faf8; padding: 14px 18px; border-radius: 12px; border: 1px solid #edf2ed; }
.profile-field-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.6px; font-weight: 700; color: var(--txt-muted); margin-bottom: 4px; display: block; }
.profile-field-value { font-size: 0.98rem; font-weight: 700; color: var(--txt-main); }

.profile-actions-bar {
  display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--border-light);
}

.profile-edit-btn {
  display: inline-flex; align-items: center; gap: 8px; padding: 11px 26px; border-radius: 50px;
  background: linear-gradient(135deg, var(--gd-primary) 0%, var(--gd-medium) 100%);
  color: #fff; font-size: 0.88rem; font-weight: 700; border: none; cursor: pointer;
  box-shadow: 0 4px 14px rgba(26,61,43,0.25); transition: all 0.2s ease;
}
.profile-edit-btn:hover {
  transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,61,43,0.35); color: #fff;
}

/* ── Profile Stats ── */
.profile-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 20px; }
.stat-card {
  background: var(--gd-card-bg); border-radius: 16px; padding: 20px; border: 1px solid var(--border-light);
  display: flex; align-items: center; gap: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
.stat-icon-box {
  width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.stat-icon-box.green { background: #e6f4ea; color: #1e7e34; }
.stat-icon-box.blue { background: #e8f2fe; color: #1a73e8; }
.stat-icon-box.gold { background: #fef3c7; color: #d97706; }
.stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--txt-muted); display: block; margin-bottom: 2px; }
.stat-value { font-size: 1.15rem; font-weight: 800; color: var(--txt-main); }

/* ── Booking Cards ── */
.bk-card {
  background: var(--gd-card-bg); border-radius: 16px; padding: 22px 26px; margin-bottom: 16px;
  border: 1px solid var(--border-light); box-shadow: 0 4px 16px rgba(0,0,0,0.03); transition: all 0.2s ease;
}
.bk-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.07); border-color: var(--border-hover); }
.bk-card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; }
.bk-status-badge {
  display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 30px;
  font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px;
}
.bk-status-badge.confirmed { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.bk-status-badge.pending { background: #fffbebfb; color: #b45309; border: 1px solid #fde68a; }
.bk-status-badge.cancelled { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.bk-status-badge.completed { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.bk-booking-id { font-size: 0.78rem; font-weight: 600; color: var(--txt-muted); margin-left: 8px; }

.bk-price { font-size: 1.15rem; font-weight: 800; color: var(--gd-primary); }
.bk-facility-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 700; color: var(--txt-main); margin: 8px 0 14px; }

.bk-images-container { display: flex; gap: 12px; margin: 14px 0 18px; flex-wrap: wrap; }
.bk-image-card {
  position: relative; width: 140px; height: 90px; border-radius: 12px; overflow: hidden;
  border: 1px solid var(--border-light); box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.2s;
}
.bk-image-card:hover { transform: scale(1.03); }
.bk-image-card img { width: 100%; height: 100%; object-fit: cover; }
.bk-image-label {
  position: absolute; bottom: 0; inset-x: 0; background: linear-gradient(transparent, rgba(0,0,0,0.85));
  color: #fff; font-size: 0.68rem; font-weight: 700; padding: 6px 8px 4px; text-align: center;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.bk-divider { border: none; border-top: 1px solid #f1f5f9; margin: 14px 0; }
.bk-meta { display: flex; flex-wrap: wrap; gap: 16px 28px; }
.bk-meta-item { display: flex; align-items: flex-start; gap: 8px; }
.bk-meta-item i { color: var(--gd-light); font-size: 0.88rem; margin-top: 2px; }
.bk-meta-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--txt-muted); font-weight: 700; margin-bottom: 2px; }
.bk-meta-value { font-size: 0.88rem; font-weight: 700; color: var(--txt-main); }

.bk-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9; }

/* Buttons */
.bk-btn-cancel {
  display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 50px;
  background: #fff; color: var(--red-accent); border: 1.5px solid #fecaca; font-size: 0.82rem; font-weight: 700;
  cursor: pointer; text-decoration: none; transition: all 0.2s;
}
.bk-btn-cancel:hover { background: var(--red-bg); border-color: var(--red-accent); }

.bk-btn-edit {
  display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 50px;
  background: #fff; color: var(--gd-primary); border: 1.5px solid #a7f3d0; font-size: 0.82rem; font-weight: 700;
  cursor: pointer; text-decoration: none; transition: all 0.2s;
}
.bk-btn-edit:hover { background: #ecfdf5; border-color: var(--gd-primary); }

.bk-btn-rebook {
  display: inline-flex; align-items: center; gap: 6px; padding: 9px 22px; border-radius: 50px;
  background: linear-gradient(135deg, var(--gd-primary) 0%, var(--gd-medium) 100%);
  color: #fff; font-size: 0.84rem; font-weight: 700; border: none; cursor: pointer; text-decoration: none;
  box-shadow: 0 4px 12px rgba(26,61,43,0.2); transition: all 0.2s;
}
.bk-btn-rebook:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26,61,43,0.3); color: #fff; }

.bk-btn-view {
  display: inline-flex; align-items: center; gap: 6px; color: var(--gd-medium); font-size: 0.85rem; font-weight: 700;
  text-decoration: none; transition: color 0.2s;
}
.bk-btn-view:hover { color: var(--gd-primary); text-decoration: underline; }

/* Modals */
.ep-overlay, .em-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(6px);
  z-index: 500; display: none; align-items: center; justify-content: center; padding: 20px;
}
.ep-overlay.open, .em-overlay.open { display: flex; animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.ep-modal, .em-modal {
  background: #fff; border-radius: 24px; padding: 32px 36px; width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.25); position: relative;
}
.ep-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.ep-modal-header h3 { font-family: 'Playfair Display', serif; font-size: 1.35rem; font-weight: 800; color: var(--txt-main); }
.modal-close-btn { background: none; border: none; font-size: 1.2rem; color: var(--txt-muted); cursor: pointer; transition: color 0.2s; }
.modal-close-btn:hover { color: var(--red-accent); }

/* Avatar Edit inside Modal */
.ep-photo-upload-zone {
  display: flex; flex-direction: column; align-items: center; margin-bottom: 24px; text-align: center;
}
.ep-photo-preview-wrap {
  position: relative; width: 110px; height: 110px; border-radius: 50%; border: 3px solid var(--gd-accent);
  box-shadow: 0 8px 20px rgba(0,0,0,0.12); overflow: hidden; margin-bottom: 10px; cursor: pointer; background: #f8faf8;
}
.ep-photo-preview-wrap img, .ep-photo-preview-wrap .avatar-initials-circle {
  width: 100%!important; height: 100%!important; border: none!important; border-radius: 0!important; box-shadow: none!important;
}
.ep-photo-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.55); color: #fff;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  font-size: 0.72rem; font-weight: 700; opacity: 0; transition: opacity 0.2s; gap: 4px;
}
.ep-photo-preview-wrap:hover .ep-photo-overlay { opacity: 1; }
.ep-photo-hint { font-size: 0.76rem; color: var(--txt-muted); font-weight: 600; }

.ep-field, .em-field { margin-bottom: 18px; }
.ep-field label, .em-field label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--txt-main); margin-bottom: 6px; }
.ep-field input, .em-field input {
  width: 100%; padding: 12px 16px; border: 1.5px solid var(--border-light); border-radius: 12px;
  font-size: 0.92rem; color: var(--txt-main); font-family: 'Plus Jakarta Sans', sans-serif; outline: none; transition: all 0.2s;
}
.ep-field input:focus, .em-field input:focus { border-color: var(--gd-primary); box-shadow: 0 0 0 4px rgba(26,61,43,0.1); }
.ep-row, .em-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.ep-alert, .em-alert {
  border-radius: 12px; padding: 12px 16px; font-size: 0.85rem; font-weight: 600; margin-bottom: 18px;
  display: flex; align-items: center; gap: 10px;
}
.ep-alert.ok, .em-alert.ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.ep-alert.err, .em-alert.err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

.ep-actions, .em-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; }
.ep-btn-cancel, .em-btn-cancel-modal {
  padding: 11px 24px; border-radius: 50px; border: 1.5px solid var(--border-light); background: #fff;
  color: var(--txt-muted); font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
}
.ep-btn-cancel:hover, .em-btn-cancel-modal:hover { border-color: var(--txt-muted); color: var(--txt-main); }

.ep-btn-save, .em-btn-save {
  padding: 11px 28px; border-radius: 50px; border: none;
  background: linear-gradient(135deg, var(--gd-primary) 0%, var(--gd-medium) 100%);
  color: #fff; font-size: 0.88rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(26,61,43,0.25); transition: all 0.2s;
}
.ep-btn-save:hover, .em-btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26,61,43,0.35); }

/* Add-ons grid inside Edit Booking modal */
.addon-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px; }
.addon-item {
  border: 1.5px solid var(--border-light); border-radius: 12px; padding: 10px 14px;
  display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s; background: #fff;
}
.addon-item:hover { border-color: var(--gd-light); background: #f4fbf6; }
.addon-item.selected { border-color: var(--gd-primary); background: #ecfdf5; }
.addon-item-left { display: flex; align-items: center; gap: 10px; }
.addon-item-icon { font-size: 1.1rem; color: var(--gd-light); }
.addon-item-name { font-size: 0.84rem; font-weight: 700; color: var(--txt-main); }
.addon-item-price { font-size: 0.72rem; color: var(--txt-muted); }
.addon-qty { display: flex; align-items: center; gap: 6px; }
.addon-qty-btn {
  width: 24px; height: 24px; border-radius: 50%; border: 1.5px solid var(--border-light);
  background: #fff; color: var(--gd-primary); font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: 700; transition: all 0.2s;
}
.addon-qty-btn:hover { background: var(--gd-primary); color: #fff; border-color: var(--gd-primary); }
.addon-qty-num { font-size: 0.88rem; font-weight: 800; color: var(--txt-main); min-width: 18px; text-align: center; }

.em-price-preview {
  background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 14px; padding: 12px 18px; margin-top: 16px;
  display: flex; justify-content: space-between; align-items: center;
}
.em-price-preview .lbl { font-size: 0.82rem; color: var(--txt-muted); font-weight: 600; }
.em-price-preview .val { font-size: 1.1rem; font-weight: 800; color: var(--gd-primary); }

/* Empty state */
.empty-state { text-align: center; padding: 60px 20px; color: var(--txt-muted); background: #fff; border-radius: 20px; border: 1px dashed var(--border-light); }
.empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
.empty-state p { font-size: 0.95rem; font-weight: 600; }
.empty-state a {
  display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; padding: 11px 26px; border-radius: 50px;
  background: var(--gd-primary); color: #fff; font-size: 0.88rem; font-weight: 700; text-decoration: none; shadow: 0 4px 12px rgba(26,61,43,0.2);
}

.tab-panel { display: none; }
.tab-panel.active { display: block; animation: fadeIn 0.25s ease; }

@media(max-width: 900px) {
  .gd-sidebar { transform: translateX(-100%); }
  .gd-sidebar.open { transform: translateX(0); }
  .mobile-header { display: flex; }
  .gd-main { margin-left: 0; padding: 80px 18px 30px; }
  .profile-grid { grid-template-columns: 1fr; gap: 12px; }
  .profile-stats-grid { grid-template-columns: 1fr; }
  .addon-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Mobile Header -->
<header class="mobile-header">
  <a href="landing.php" class="mobile-header-brand">
    <img src="images/logo.jpg" alt="Logo">
    <strong style="font-family:'Playfair Display',serif;">Sinulom &amp; Bolao</strong>
  </a>
  <button class="mobile-toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
</header>

<!-- Sidebar -->
<aside class="gd-sidebar" id="gdSidebar">
  <div class="gd-sb-brand">
    <img src="images/logo.jpg" alt="Logo">
    <div class="gd-sb-brand-txt">
      <strong>Sinulom &amp; Bolao</strong>
      <span>Resort Portal</span>
    </div>
  </div>
  <div class="gd-sb-profile-block">
    <div class="gd-sb-avatar-wrap" onclick="openEditProfile()">
      <?= getAvatarHtml($profile_pic, $initials, 'width:56px;height:56px;font-size:1.35rem;') ?>
      <div class="gd-sb-avatar-edit-overlay"><i class="fas fa-camera"></i></div>
    </div>
    <div class="gd-sb-profile-name"><?= htmlspecialchars($guest_name) ?></div>
    <div class="gd-sb-profile-email"><?= htmlspecialchars($guest_email) ?></div>
    <span class="gd-sb-profile-badge"><span class="dot"></span>Verified Guest</span>
  </div>
  <nav class="gd-sb-nav">
    <a href="?tab=cart" class="gd-sb-link <?= $active_tab==='cart'?'active':'' ?>">
      <i class="fas fa-shopping-cart"></i> My Cart
      <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
      <span style="position:absolute; right:15px; top:50%; transform:translateY(-50%); background:var(--red-accent); color:#fff; font-size:0.65rem; padding:2px 8px; border-radius:10px; font-weight:800;"><?= count($_SESSION['cart']) ?></span>
      <?php endif; ?>
    </a>
    <a href="?tab=profile" class="gd-sb-link <?= $active_tab==='profile'?'active':'' ?>">
      <i class="fas fa-user-circle"></i> My Profile
    </a>
    <a href="?tab=upcoming" class="gd-sb-link <?= $active_tab==='upcoming'?'active':'' ?>">
      <i class="fas fa-calendar-alt"></i> Upcoming Bookings
    </a>
    <a href="?tab=history" class="gd-sb-link <?= $active_tab==='history'?'active':'' ?>">
      <i class="fas fa-history"></i> Booking History
    </a>
  </nav>
  <div class="gd-sb-logout">
    <a href="landing2.php"><i class="fas fa-home"></i> Back to Home</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>

<!-- Main content -->
<div class="gd-main">
<div class="gd-content-wrap">

  <!-- MY CART -->
  <div class="tab-panel <?= $active_tab==='cart'?'active':'' ?>" id="tab-cart">
    <div class="gd-page-header">
      <h1 class="gd-page-title">My Cart</h1>
    </div>
    <?php if (isset($_GET['added'])): ?>
    <div class="ep-alert ok" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> Booking added to your cart successfully!</div>
    <?php endif; ?>
    
    <?php if (empty($_SESSION['cart'])): ?>
    <div class="empty-state">
      <i class="fas fa-shopping-cart"></i>
      <p>Your cart is empty.</p>
      <a href="public_booking.php?from=cart"><i class="fas fa-plus"></i> Explore Facilities &amp; Book</a>
    </div>
    <?php else: ?>
      <?php 
      $cart_total = 0;
      $cart_has_unavailable = false;
      $slot_labels = ['8am-12pm'=>'Morning (8AM–12PM)','12pm-5pm'=>'Afternoon (12PM–5PM)','full_day'=>'Full Day','overnight'=>'Overnight'];
      foreach ($_SESSION['cart'] as $item): 
          $avail_ok = true;
          $check_in_date  = $item['check_in_date']  ?? $item['check_in']  ?? '';
          $check_out_date = $item['check_out_date'] ?? $item['check_out'] ?? '';
          $mode           = $item['mode'] ?? 'daytour';
          $time_slot      = $item['time_slot'] ?? '';
          $area_name      = $item['area_name'] ?? '';
          $transport_opt  = $item['transport_opt'] ?? $item['transportation'] ?? 'none';
          $num_adults     = intval($item['num_adults'] ?? 1);
          $num_children   = intval($item['num_children'] ?? 0);
          $num_below5     = intval($item['num_below5'] ?? 0);
          $num_pwd        = intval($item['num_pwd'] ?? $item['num_discounted'] ?? 0);
          $num_guests     = intval($item['num_guests'] ?? ($num_adults + $num_children + $num_below5 + $num_pwd));
          $is_overnight   = ($mode === 'overnight');

          $item_price = floatval($item['total_price'] ?? 0);
          if ($item_price <= 0) {
              $facility_price = floatval($item['facility_price'] ?? 0);
              $nights = 1;
              if ($is_overnight && !empty($check_in_date) && !empty($check_out_date)) {
                  $d1 = DateTime::createFromFormat('Y-m-d', $check_in_date);
                  $d2 = DateTime::createFromFormat('Y-m-d', $check_out_date);
                  if ($d1 && $d2) { $nights = max(1, (int)$d1->diff($d2)->days); }
              }
              $facility_cost = $facility_price * $nights;
              $subtotal = $facility_cost;
              $vat = round($subtotal * 0.12, 2);
              $item_price = $subtotal + $vat;
          }
          
          $facility_ids = array_map('intval', explode(',', $item['facility_id'] ?? ''));
          $avail_ok = true;
          if (!empty($facility_ids)) {
              $placeholders = implode(',', array_fill(0, count($facility_ids), '?'));
              
              if ($is_overnight) {
                  $avail_stmt = $conn->prepare("SELECT id FROM bookings WHERE facility_id IN ($placeholders) AND status IN ('pending','confirmed') AND check_in_date<? AND check_out_date>? LIMIT 1");
                  $types = str_repeat('i', count($facility_ids)) . 'ss';
                  $params = array_merge($facility_ids, [$check_out_date, $check_in_date]);
                  $avail_stmt->bind_param($types, ...$params);
                  $avail_stmt->execute(); $avail_stmt->store_result();
                  if ($avail_stmt->num_rows > 0) { $avail_ok = false; }
                  $avail_stmt->close();
              } else {
                  $avail_stmt = $conn->prepare("SELECT id FROM bookings WHERE facility_id IN ($placeholders) AND status IN ('pending','confirmed') AND mode='overnight' AND check_in_date<=? AND check_out_date>? LIMIT 1");
                  $types = str_repeat('i', count($facility_ids)) . 'ss';
                  $params = array_merge($facility_ids, [$check_in_date, $check_in_date]);
                  $avail_stmt->bind_param($types, ...$params);
                  $avail_stmt->execute(); $avail_stmt->store_result();
                  if ($avail_stmt->num_rows > 0) { $avail_ok = false; }
                  $avail_stmt->close();
                  if ($avail_ok) {
                      $avail_stmt2 = $conn->prepare("SELECT id FROM bookings WHERE facility_id IN ($placeholders) AND status IN ('pending','confirmed') AND mode!='overnight' AND check_in_date=? AND notes LIKE ? LIMIT 1");
                      $slot_like = '%' . ($time_slot ?? '') . '%';
                      $types = str_repeat('i', count($facility_ids)) . 'ss';
                      $params = array_merge($facility_ids, [$check_in_date, $slot_like]);
                      $avail_stmt2->bind_param($types, ...$params);
                      $avail_stmt2->execute(); $avail_stmt2->store_result();
                      if ($avail_stmt2->num_rows > 0) { $avail_ok = false; }
                      $avail_stmt2->close();
                  }
              }
          }
          
          if (!$avail_ok) $cart_has_unavailable = true;
          $cart_total += $item_price;

          $guest_parts = [];
          if ($num_adults > 0)   $guest_parts[] = $num_adults   . ' Adult'   . ($num_adults   > 1 ? 's' : '');
          if ($num_children > 0) $guest_parts[] = $num_children . ' Child'   . ($num_children > 1 ? 'ren' : '');
          if ($num_below5 > 0)   $guest_parts[] = $num_below5   . ' Below 5';
          if ($num_pwd > 0)      $guest_parts[] = $num_pwd      . ' PWD/Senior' . ($num_pwd > 1 ? 's' : '');
          $guest_str = implode(', ', $guest_parts) ?: ($num_guests . ' Guest(s)');

          $slot_label = $is_overnight ? 'Overnight' : ($slot_labels[$time_slot] ?? ucfirst($time_slot));
      ?>
      <div class="bk-card" id="cart-item-<?= $item['id'] ?>" style="<?= !$avail_ok ? 'border-color:var(--red-accent);' : '' ?>">
        <?php if (!$avail_ok): ?>
        <div style="background:var(--red-bg); color:var(--red-accent); padding:10px 16px; font-size:0.85rem; font-weight:700; margin-bottom:14px; border-radius:10px; border:1px solid #fecaca; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-exclamation-triangle"></i> This facility is no longer available for the selected date/time. Please edit or remove this item.
        </div>
        <?php endif; ?>
        <div class="bk-card-top">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="bk-status-badge pending"><i class="fas fa-shopping-bag"></i> In Cart</div>
            <span style="display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;padding:4px 12px;border-radius:50px;<?= $is_overnight ? 'background:#dbeafe;color:#1e40af;' : 'background:#d1fae5;color:#065f46;' ?>">
              <i class="fas <?= $is_overnight ? 'fa-moon' : 'fa-sun' ?>"></i> <?= htmlspecialchars($slot_label) ?>
            </span>
          </div>
          <div class="bk-price">&#8369;<?= number_format($item_price, 2) ?></div>
        </div>
        <h3 class="bk-facility-name"><?= htmlspecialchars($item['facility_name']) ?></h3>
        <?php if (!empty($area_name) && $area_name !== 'N/A'): ?>
        <div style="font-size:.84rem;color:var(--gd-medium);font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-map-marker-alt" style="color:var(--gd-light);"></i>
          <?= htmlspecialchars($area_name) ?> Spring Area
        </div>
        <?php endif; ?>
        <div class="bk-images-container">
          <div class="bk-image-card">
            <img src="<?= getFacilityImage($item['facility_name']) ?>" alt="<?= htmlspecialchars($item['facility_name']) ?>">
            <div class="bk-image-label"><?= htmlspecialchars($item['facility_name']) ?></div>
          </div>
        </div>
        <hr class="bk-divider">
        <div class="bk-meta">
          <div class="bk-meta-item">
            <i class="fas fa-calendar-check"></i>
            <div>
              <span class="bk-meta-label">Check-in</span>
              <span class="bk-meta-value"><?= date('M d, Y', strtotime($check_in_date)) ?></span>
            </div>
          </div>
          <?php if ($is_overnight && !empty($check_out_date)): ?>
          <div class="bk-meta-item">
            <i class="fas fa-calendar-minus"></i>
            <div>
              <span class="bk-meta-label">Check-out</span>
              <span class="bk-meta-value"><?= date('M d, Y', strtotime($check_out_date)) ?></span>
            </div>
          </div>
          <?php endif; ?>
          <div class="bk-meta-item">
            <i class="fas fa-users"></i>
            <div>
              <span class="bk-meta-label">Guests</span>
              <span class="bk-meta-value"><?= htmlspecialchars($guest_str) ?></span>
            </div>
          </div>
          <?php if (!empty($transport_opt) && $transport_opt !== 'none'): ?>
          <div class="bk-meta-item">
            <i class="fas fa-bus"></i>
            <div>
              <span class="bk-meta-label">Transport</span>
              <span class="bk-meta-value"><?= htmlspecialchars(ucfirst($transport_opt)) ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="bk-card-footer">
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <button class="bk-btn-cancel" onclick="removeCartItem('<?= $item['id'] ?>')"><i class="fas fa-trash-alt"></i> Remove</button>
              <a href="public_booking.php?from=cart&edit_cart_id=<?= $item['id'] ?>&facility=<?= $item['facility_id'] ?>" class="bk-btn-edit"><i class="fas fa-edit"></i> Edit</a>
              <?php if ($avail_ok): ?>
              <a href="checkout_cart.php?item_id=<?= $item['id'] ?>" class="bk-btn-rebook" style="padding: 8px 18px;"><i class="fas fa-check-circle"></i> Book Now</a>
              <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="bk-card" style="background:#f4fbf6; border-color:#a7f3d0;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
              <div>
                <div style="font-size:.75rem;color:var(--gd-light);font-weight:700;margin-bottom:2px;text-transform:uppercase;letter-spacing:.5px;">Estimated Total</div>
                <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; font-weight:800; margin:0; color:var(--gd-primary);">&#8369;<?= number_format($cart_total, 2) ?></h2>
                <div style="font-size:.75rem;color:var(--txt-muted);margin-top:4px;"><i class="fas fa-info-circle" style="color:#d97706;"></i> Final price confirmed after payment</div>
              </div>
              <?php if (!$cart_has_unavailable): ?>
              <a href="checkout_cart.php" class="bk-btn-rebook" style="padding:12px 28px;font-size:.9rem;display:inline-flex;align-items:center;gap:8px;">
                <i class="fas fa-shopping-cart"></i> Checkout All
              </a>
              <?php endif; ?>
          </div>
          <div style="display:flex; justify-content:flex-end; margin-top:16px;">
              <a href="public_booking.php?from=cart" class="bk-btn-edit"><i class="fas fa-plus"></i> Add Another Booking</a>
          </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- MY PROFILE TAB -->
  <div class="tab-panel <?= $active_tab==='profile'?'active':'' ?>" id="tab-profile">
    <div class="gd-page-header">
      <h1 class="gd-page-title">My Profile</h1>
    </div>

    <?php if ($profile_success): ?>
    <div class="ep-alert ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($profile_success) ?></div>
    <?php elseif ($profile_error): ?>
    <div class="ep-alert err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($profile_error) ?></div>
    <?php endif; ?>

    <div class="profile-hero-card">
      <div class="profile-hero-banner"></div>
      <div class="profile-avatar-container">
        <div class="profile-avatar-box" onclick="openEditProfile()" title="Click to edit profile photo">
          <?= getAvatarHtml($profile_pic, $initials, 'width:100px;height:100px;font-size:2.2rem;') ?>
          <div class="profile-avatar-overlay">
            <i class="fas fa-camera"></i>
            <span>Change</span>
          </div>
        </div>
        <button class="profile-edit-btn" onclick="openEditProfile()"><i class="fas fa-pen"></i> Edit Profile</button>
      </div>

      <div class="profile-card-body">
        <div style="margin-bottom: 20px;">
          <h2 style="font-family:'Playfair Display',serif; font-size:1.4rem; font-weight:800; color:var(--txt-main);"><?= htmlspecialchars($guest_name) ?></h2>
          <p style="font-size:0.85rem; color:var(--txt-muted); font-weight:500;"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($guest_email) ?></p>
        </div>

        <div class="profile-grid">
          <div class="profile-field-item">
            <span class="profile-field-label">First Name</span>
            <div class="profile-field-value"><?= htmlspecialchars($first_name) ?></div>
          </div>
          <div class="profile-field-item">
            <span class="profile-field-label">Last Name</span>
            <div class="profile-field-value"><?= htmlspecialchars($last_name ?: '—') ?></div>
          </div>
          <div class="profile-field-item">
            <span class="profile-field-label">Email Address</span>
            <div class="profile-field-value"><?= htmlspecialchars($guest_email) ?> <i class="fas fa-lock" style="font-size:0.75rem;color:var(--txt-muted);margin-left:4px;" title="Email cannot be edited"></i></div>
          </div>
          <div class="profile-field-item">
            <span class="profile-field-label">Phone Number</span>
            <div class="profile-field-value"><?= htmlspecialchars($guest_phone ?: '—') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="profile-stats-grid">
      <div class="stat-card">
        <div class="stat-icon-box green">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div>
          <span class="stat-label">Member Since</span>
          <span class="stat-value"><?= $first_booking_date ?></span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-box blue">
          <i class="fas fa-suitcase"></i>
        </div>
        <div>
          <span class="stat-label">Total Visits</span>
          <span class="stat-value"><?= $total_stays ?> Stay<?= $total_stays!=1?'s':'' ?></span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-box gold">
          <i class="fas fa-peso-sign"></i>
        </div>
        <div>
          <span class="stat-label">Total Spent</span>
          <span class="stat-value">&#8369;<?= number_format($total_spent, 2) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- UPCOMING BOOKINGS -->
  <div class="tab-panel <?= $active_tab==='upcoming'?'active':'' ?>" id="tab-upcoming">
    <div class="gd-page-header">
      <h1 class="gd-page-title">Upcoming Bookings</h1>
    </div>

    <?php if ($edit_success): ?>
    <div class="ep-alert ok" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($edit_success) ?></div>
    <?php endif; ?>

    <?php if (empty($upcoming)): ?>
    <div class="empty-state">
      <i class="fas fa-calendar-times"></i>
      <p>No upcoming bookings found.</p>
      <a href="public_booking.php?from=cart"><i class="fas fa-plus"></i> Book a Stay Now</a>
    </div>
    <?php else: foreach ($upcoming as $bk):
      $st = strtolower($bk['status']);
      $nights = max(1, (int)((strtotime($bk['check_out_date']) - strtotime($bk['check_in_date'])) / 86400));
      $dur = $bk['mode'] === 'daytour' ? 'Day Tour' : $nights . ' Night' . ($nights!=1?'s':'');
      $bid = 'SB-' . date('Y', strtotime($bk['created_at'])) . '-' . str_pad($bk['primary_id'], 3, '0', STR_PAD_LEFT);
      $isGrouped = count($bk['all_ids']) > 1;
    ?>
    <div class="bk-card">
      <div class="bk-card-top">
        <div>
          <span class="bk-status-badge <?= $st ?>">
            <?php if($st==='confirmed'): ?><i class="fas fa-check-circle"></i><?php else: ?><i class="fas fa-clock"></i><?php endif; ?>
            <?= ucfirst($st) ?>
          </span>
          <span class="bk-booking-id">Booking ID: #<?= $bid ?></span>
          <?php if ($isGrouped): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;border-radius:20px;padding:3px 10px;font-size:.68rem;font-weight:800;margin-left:6px;">
            <i class="fas fa-layer-group" style="font-size:.65rem;"></i> <?= count($bk['all_ids']) ?> Facilities
          </span>
          <?php endif; ?>
        </div>
        <div style="text-align:right;">
          <div class="bk-price">&#8369;<?= number_format($bk['total_price'], 2) ?></div>
        </div>
      </div>
      <div class="bk-facility-name"><?= htmlspecialchars($bk['facility_name_display']) ?></div>
      <div class="bk-images-container">
        <?php foreach ($bk['facility_names'] as $fname): ?>
        <div class="bk-image-card">
          <img src="<?= getFacilityImage($fname) ?>" alt="<?= htmlspecialchars($fname) ?>">
          <div class="bk-image-label"><?= htmlspecialchars($fname) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <hr class="bk-divider">
      <div class="bk-meta">
        <div class="bk-meta-item">
          <i class="fas fa-calendar"></i>
          <div><span class="bk-meta-label">Check-in</span><span class="bk-meta-value"><?= date('M d, Y', strtotime($bk['check_in_date'])) ?></span></div>
        </div>
        <div class="bk-meta-item">
          <i class="fas fa-clock"></i>
          <div><span class="bk-meta-label">Duration</span><span class="bk-meta-value"><?= $dur ?></span></div>
        </div>
        <div class="bk-meta-item">
          <i class="fas fa-users"></i>
          <div><span class="bk-meta-label">Guests</span><span class="bk-meta-value"><?= intval($bk['num_adults']) + intval($bk['num_children']) ?> Pax</span></div>
        </div>
        <div class="bk-meta-item">
          <i class="fas fa-map-marker-alt"></i>
          <div><span class="bk-meta-label">Location</span><span class="bk-meta-value"><?= htmlspecialchars($bk['area_name'] ?? 'Resort') ?></span></div>
        </div>
      </div>
      <div class="bk-card-footer">
        <?php if ($st === 'pending'): ?>
        <div style="display:flex; gap:10px;">
          <button class="bk-btn-cancel" onclick="confirmCancel(<?= $bk['primary_id'] ?>)"><i class="fas fa-times"></i> Cancel<?= $isGrouped ? ' All' : '' ?></button>
          <?php if (!$isGrouped): ?>
          <button class="bk-btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($bk)) ?>)"><i class="fas fa-edit"></i> Edit</button>
          <?php endif; ?>
        </div>
        <?php elseif ($st === 'confirmed'): ?>
        <div style="display:flex; gap:10px;">
          <button class="bk-btn-edit" onclick="openAddons(<?= htmlspecialchars(json_encode($bk)) ?>)"><i class="fas fa-plus-circle"></i> Add-ons</button>
        </div>
        <?php else: ?>
        <div></div>
        <?php endif; ?>
        <div>
          <a href="guest_receipt.php?booking_id=<?= $bk['primary_id'] ?>" class="bk-btn-view">View Receipt <i class="fas fa-chevron-right" style="font-size:.7rem;"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- BOOKING HISTORY -->
  <div class="tab-panel <?= $active_tab==='history'?'active':'' ?>" id="tab-history">
    <div class="gd-page-header">
      <h1 class="gd-page-title">Booking History</h1>
    </div>

    <?php if (empty($history)): ?>
    <div class="empty-state">
      <i class="fas fa-history"></i>
      <p>No past booking history found.</p>
    </div>
    <?php else: foreach ($history as $bk):
      $st = strtolower($bk['status']);
      $nights = max(1, (int)((strtotime($bk['check_out_date']) - strtotime($bk['check_in_date'])) / 86400));
      $bid = 'SB-' . date('Y', strtotime($bk['created_at'])) . '-' . str_pad($bk['primary_id'], 3, '0', STR_PAD_LEFT);
      $isCancelled = in_array($st, ['cancelled','declined']);
      $isGrouped = count($bk['all_ids']) > 1;
    ?>
    <div class="bk-card">
      <div class="bk-card-top">
        <div>
          <span class="bk-status-badge <?= $isCancelled?'cancelled':($st==='completed'||$st==='checked out'?'completed':'pending') ?>">
            <?php if($isCancelled): ?><i class="fas fa-times-circle"></i><?php else: ?><i class="fas fa-check-circle"></i><?php endif; ?>
            <?= $isCancelled ? 'Cancelled' : 'Completed' ?>
          </span>
          <span class="bk-booking-id">Booking ID: #<?= $bid ?></span>
          <?php if ($isGrouped): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;border-radius:20px;padding:3px 10px;font-size:.68rem;font-weight:800;margin-left:6px;">
            <i class="fas fa-layer-group" style="font-size:.65rem;"></i> <?= count($bk['all_ids']) ?> Facilities
          </span>
          <?php endif; ?>
        </div>
        <div style="text-align:right;">
          <div class="bk-price" style="<?= $isCancelled ? 'text-decoration:line-through;color:var(--txt-muted);' : '' ?>">&#8369;<?= number_format($bk['total_price'], 2) ?></div>
        </div>
      </div>
      <div class="bk-facility-name"><?= htmlspecialchars($bk['facility_name_display']) ?></div>
      <div class="bk-images-container">
        <?php foreach ($bk['facility_names'] as $fname): ?>
        <div class="bk-image-card">
          <img src="<?= getFacilityImage($fname) ?>" alt="<?= htmlspecialchars($fname) ?>">
          <div class="bk-image-label"><?= htmlspecialchars($fname) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <hr class="bk-divider">
      <div class="bk-meta">
        <div class="bk-meta-item">
          <i class="fas fa-calendar"></i>
          <div><span class="bk-meta-label"><?= $isCancelled?'Original Date':'Date' ?></span><span class="bk-meta-value"><?= date('M d', strtotime($bk['check_in_date'])) ?> – <?= date('M d, Y', strtotime($bk['check_out_date'])) ?></span></div>
        </div>
        <div class="bk-meta-item">
          <i class="fas fa-users"></i>
          <div><span class="bk-meta-label">Guests</span><span class="bk-meta-value"><?= intval($bk['num_adults']) + intval($bk['num_children']) ?> Pax</span></div>
        </div>
        <div class="bk-meta-item">
          <i class="fas fa-map-marker-alt"></i>
          <div><span class="bk-meta-label">Location</span><span class="bk-meta-value"><?= htmlspecialchars($bk['area_name'] ?? 'Resort') ?></span></div>
        </div>
      </div>
      <div class="bk-card-footer">
        <?php if($isCancelled): ?>
        <a href="public_booking.php?from=cart" class="bk-btn-rebook"><i class="fas fa-redo"></i> Rebook Stay</a>
        <?php else: ?>
        <div></div>
        <?php endif; ?>
        <a href="guest_receipt.php?booking_id=<?= $bk['primary_id'] ?>" class="bk-btn-view">View Receipt <i class="fas fa-chevron-right" style="font-size:.7rem;"></i></a>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div><!-- /gd-content-wrap -->
</div><!-- /gd-main -->

<!-- Edit Profile Modal with Photo Upload -->
<div class="ep-overlay" id="epOverlay">
  <div class="ep-modal">
    <div class="ep-modal-header">
      <h3>Edit Profile</h3>
      <button type="button" class="modal-close-btn" onclick="closeEditProfile()"><i class="fas fa-times"></i></button>
    </div>
    <div id="epAlert"></div>
    <form method="POST" action="?tab=profile" id="epForm" enctype="multipart/form-data">
      <input type="hidden" name="edit_profile" value="1">
      
      <!-- Avatar Upload Zone -->
      <div class="ep-photo-upload-zone">
        <div class="ep-photo-preview-wrap" onclick="document.getElementById('epPhotoInput').click()" title="Click to choose a new photo">
          <div id="epPhotoContainer">
            <?= getAvatarHtml($profile_pic, $initials, 'width:110px;height:110px;font-size:2.4rem;') ?>
          </div>
          <div class="ep-photo-overlay">
            <i class="fas fa-camera" style="font-size:1.2rem;"></i>
            <span>Upload Photo</span>
          </div>
        </div>
        <div class="ep-photo-hint">Click the avatar to upload a new profile picture (Max: 5MB)</div>
        <input type="file" name="profile_pic" id="epPhotoInput" accept="image/*" style="display:none;" onchange="previewProfilePhoto(this)">
      </div>

      <div class="ep-row">
        <div class="ep-field">
          <label>First Name *</label>
          <input type="text" name="edit_first_name" id="epFirst" value="<?= htmlspecialchars($first_name) ?>" placeholder="Juan" autocapitalize="words" oninput="capFirst(this)" required>
        </div>
        <div class="ep-field">
          <label>Last Name</label>
          <input type="text" name="edit_last_name" id="epLast" value="<?= htmlspecialchars($last_name) ?>" placeholder="Dela Cruz" autocapitalize="words" oninput="capFirst(this)">
        </div>
      </div>
      <div class="ep-field">
        <label>Phone Number</label>
        <input type="tel" name="edit_phone" id="epPhone" value="<?= htmlspecialchars($guest_phone) ?>" placeholder="09XXXXXXXXX" inputmode="numeric" maxlength="11" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
      </div>
      <div class="ep-field">
        <label>Email Address</label>
        <input type="email" value="<?= htmlspecialchars($guest_email) ?>" disabled style="background:#f8faf8;color:#94a3b8;cursor:not-allowed;border-color:#e2e8f0;">
        <div style="font-size:0.75rem;color:var(--txt-muted);margin-top:4px;"><i class="fas fa-info-circle"></i> Email address is linked to your account and cannot be changed.</div>
      </div>
      <div class="ep-actions">
        <button type="button" class="ep-btn-cancel" onclick="closeEditProfile()">Cancel</button>
        <button type="submit" class="ep-btn-save"><i class="fas fa-save me-1"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Cancel confirm form (hidden) -->
<form id="cancelForm" method="POST" action="?tab=upcoming" style="display:none;">
  <input type="hidden" name="cancel_booking_id" id="cancelBookingId">
  <input type="hidden" name="tab" value="upcoming">
</form>

<!-- Edit Booking Modal -->
<div class="em-overlay" id="emOverlay">
  <div class="em-modal">
    <div class="ep-modal-header">
      <h3><i class="fas fa-edit me-2" style="color:var(--gd-primary);font-size:1.1rem;"></i>Edit Booking</h3>
      <button type="button" class="modal-close-btn" onclick="closeEdit()"><i class="fas fa-times"></i></button>
    </div>
    <div id="emAlert"></div>
    <form method="POST" action="?tab=upcoming" id="emForm">
      <input type="hidden" name="edit_booking_id" id="emBookingId">
      <div id="emAddonInputs"></div>

      <!-- Dates -->
      <div class="em-field">
        <label>Check-in Date</label>
        <input type="date" name="edit_check_in" id="emCheckIn" oninput="recalcPrice()">
      </div>
      <div class="em-field" id="emCheckOutGroup">
        <label>Check-out Date</label>
        <input type="date" name="edit_check_out" id="emCheckOut" oninput="recalcPrice()">
      </div>

      <!-- Guests -->
      <div class="em-row">
        <div class="em-field">
          <label>No. of Adults *</label>
          <input type="number" name="edit_num_adults" id="emAdults" min="1" value="1" oninput="recalcPrice()">
        </div>
        <div class="em-field">
          <label>No. of Children</label>
          <input type="number" name="edit_num_children" id="emChildren" min="0" value="0" oninput="recalcPrice()">
        </div>
      </div>

      <!-- Add-ons -->
      <div class="em-field">
        <label><i class="fas fa-plus-circle me-1" style="color:var(--gd-light);"></i>Add-ons <span style="font-size:.72rem;color:var(--txt-muted);font-weight:400;">(optional)</span></label>
        <div class="addon-grid" id="addonGrid">
          <?php foreach ($amenities_js as $am):
            $icon = match(strtolower($am['name'])) {
              'pillow','pillows' => 'fa-bed',
              'bed','beds'       => 'fa-bed',
              'sofa','sofas'     => 'fa-couch',
              'table','tables'   => 'fa-table',
              'chair','chairs'   => 'fa-chair',
              default            => 'fa-box',
            };
          ?>
          <div class="addon-item" id="addon-<?= $am['id'] ?>" data-id="<?= $am['id'] ?>" data-price="<?= $am['price'] ?>" data-name="<?= htmlspecialchars($am['name']) ?>">
            <div class="addon-item-left">
              <i class="fas <?= $icon ?> addon-item-icon"></i>
              <div>
                <div class="addon-item-name"><?= htmlspecialchars($am['name']) ?></div>
                <div class="addon-item-price">₱<?= number_format($am['price'], 2) ?>/pc</div>
              </div>
            </div>
            <div class="addon-qty">
              <button type="button" class="addon-qty-btn" onclick="changeQty(<?= $am['id'] ?>,-1)">−</button>
              <span class="addon-qty-num" id="qty-<?= $am['id'] ?>">0</span>
              <button type="button" class="addon-qty-btn" onclick="changeQty(<?= $am['id'] ?>,1)">+</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Price preview -->
      <div class="em-price-preview">
        <span class="lbl">Estimated Total</span>
        <span class="val" id="emPricePreview">₱0.00</span>
      </div>

      <div class="em-actions">
        <button type="button" class="em-btn-cancel-modal" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="em-btn-save"><i class="fas fa-save me-1"></i>Save &amp; Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Add-ons Modal (for confirmed bookings) -->
<div class="em-overlay" id="aoOverlay">
  <div class="em-modal">
    <div class="ep-modal-header">
      <h3><i class="fas fa-plus-circle me-2" style="color:var(--gd-primary);font-size:1.1rem;"></i>Manage Add-ons</h3>
      <button type="button" class="modal-close-btn" onclick="closeAddons()"><i class="fas fa-times"></i></button>
    </div>
    <p style="font-size:.82rem;color:var(--txt-muted);margin-bottom:18px;">Your booking is confirmed. You can still add or update add-ons below.</p>
    <div id="aoAlert"></div>
    <form method="POST" action="?tab=upcoming" id="aoForm">
      <input type="hidden" name="addon_booking_id" id="aoBookingId">
      <div id="aoAddonInputs"></div>

      <div class="em-field">
        <label><i class="fas fa-plus-circle me-1" style="color:var(--gd-light);"></i>Add-ons</label>
        <div class="addon-grid" id="aoAddonGrid">
          <?php foreach ($amenities_js as $am):
            $icon = match(strtolower($am['name'])) {
              'pillow','pillows' => 'fa-bed',
              'bed','beds'       => 'fa-bed',
              'sofa','sofas'     => 'fa-couch',
              'table','tables'   => 'fa-table',
              'chair','chairs'   => 'fa-chair',
              default            => 'fa-box',
            };
          ?>
          <div class="addon-item" id="ao-addon-<?= $am['id'] ?>" data-id="<?= $am['id'] ?>" data-price="<?= $am['price'] ?>">
            <div class="addon-item-left">
              <i class="fas <?= $icon ?> addon-item-icon"></i>
              <div>
                <div class="addon-item-name"><?= htmlspecialchars($am['name']) ?></div>
                <div class="addon-item-price">₱<?= number_format($am['price'], 2) ?>/pc</div>
              </div>
            </div>
            <div class="addon-qty">
              <button type="button" class="addon-qty-btn" onclick="changeAoQty(<?= $am['id'] ?>,-1)">−</button>
              <span class="addon-qty-num" id="ao-qty-<?= $am['id'] ?>">0</span>
              <button type="button" class="addon-qty-btn" onclick="changeAoQty(<?= $am['id'] ?>,1)">+</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="em-price-preview">
        <span class="lbl">Updated Total</span>
        <span class="val" id="aoPricePreview">₱0.00</span>
      </div>

      <div class="em-actions">
        <button type="button" class="em-btn-cancel-modal" onclick="closeAddons()">Cancel</button>
        <button type="submit" class="em-btn-save"><i class="fas fa-paper-plane me-1"></i>Save &amp; Update</button>
      </div>
    </form>
  </div>
</div>

<script>
const AMENITIES_DATA = <?= json_encode($amenities_js) ?>;
let emCurrentBk = null;
let addonQtys   = {};
let aoCurrentBk = null;
let aoQtys      = {};

function toggleSidebar() {
  document.getElementById('gdSidebar').classList.toggle('open');
}

function capFirst(el){
  const p=el.selectionStart;
  el.value=el.value.replace(/\b\w/g,c=>c.toUpperCase());
  el.setSelectionRange(p,p);
}

// Live Profile Photo Preview in Edit Profile Modal
function previewProfilePhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const container = document.getElementById('epPhotoContainer');
      container.innerHTML = `<img src="${e.target.result}" alt="Photo Preview" style="width:110px;height:110px;border-radius:50%;object-fit:cover;">`;
    }
    reader.readAsDataURL(input.files[0]);
  }
}

function openEditProfile() {
  document.getElementById('epFirst').value = <?= json_encode($first_name) ?>;
  document.getElementById('epLast').value  = <?= json_encode($last_name) ?>;
  document.getElementById('epPhone').value = <?= json_encode($guest_phone) ?>;
  document.getElementById('epOverlay').classList.add('open');
}

function closeEditProfile() {
  document.getElementById('epOverlay').classList.remove('open');
}

document.getElementById('epOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeEditProfile();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeEditProfile();
    closeEdit();
    closeAddons();
  }
});

function confirmCancel(id) {
  if (confirm('Are you sure you want to cancel this booking? This cannot be undone.')) {
    document.getElementById('cancelBookingId').value = id;
    document.getElementById('cancelForm').submit();
  }
}

function changeQty(aid, delta) {
  addonQtys[aid] = Math.max(0, (addonQtys[aid] || 0) + delta);
  document.getElementById('qty-' + aid).textContent = addonQtys[aid];
  const item = document.getElementById('addon-' + aid);
  if (item) item.classList.toggle('selected', addonQtys[aid] > 0);
  recalcPrice();
}

function recalcPrice() {
  if (!emCurrentBk) return;
  const bk = emCurrentBk;
  const adults   = parseInt(document.getElementById('emAdults').value)   || 0;
  const children = parseInt(document.getElementById('emChildren').value) || 0;
  const ci = document.getElementById('emCheckIn').value;
  const co = document.getElementById('emCheckOut').value;

  const areaLc = (bk.area_name || '').toLowerCase();
  const isBoth = areaLc.includes('both') || (areaLc.includes('sinulom') && areaLc.includes('bolao'));
  const rateAdult = isBoth ? 160 : 110;
  const rateChild = isBoth ? 85  : 60;
  const facPrice  = parseFloat(bk.facility_price || bk.total_price || 0);

  let nights = 1;
  if (bk.mode === 'overnight' && ci && co && co > ci) {
    nights = Math.max(1, Math.round((new Date(co) - new Date(ci)) / 86400000));
  }

  const base = (facPrice * nights) + (adults * rateAdult) + (children * rateChild);
  let addonTotal = 0;
  AMENITIES_DATA.forEach(am => {
    const qty = addonQtys[am.id] || 0;
    addonTotal += parseFloat(am.price) * qty;
  });

  const total = base + addonTotal;
  document.getElementById('emPricePreview').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
}

function buildAddonInputs() {
  const container = document.getElementById('emAddonInputs');
  container.innerHTML = '';
  AMENITIES_DATA.forEach(am => {
    const qty = addonQtys[am.id] || 0;
    if (qty > 0) {
      container.innerHTML += `<input type="hidden" name="addon_ids[]" value="${am.id}">`;
      container.innerHTML += `<input type="hidden" name="addon_qtys[]" value="${qty}">`;
    }
  });
}

function openEdit(bk) {
  emCurrentBk = bk;
  addonQtys = {};

  document.getElementById('emBookingId').value = bk.id;
  document.getElementById('emCheckIn').value   = bk.check_in_date || '';
  document.getElementById('emAdults').value    = bk.num_adults || 1;
  document.getElementById('emChildren').value  = bk.num_children || 0;

  const coGroup = document.getElementById('emCheckOutGroup');
  if (bk.mode === 'overnight') {
    coGroup.style.display = 'block';
    document.getElementById('emCheckOut').value = bk.check_out_date || '';
  } else {
    coGroup.style.display = 'none';
    document.getElementById('emCheckOut').value = bk.check_in_date || '';
  }

  AMENITIES_DATA.forEach(am => {
    const el = document.getElementById('qty-' + am.id);
    if (el) el.textContent = '0';
    const item = document.getElementById('addon-' + am.id);
    if (item) item.classList.remove('selected');
  });

  fetch('fetch_booking_addons.php?booking_id=' + bk.id)
    .then(r => r.json())
    .then(addons => {
      addons.forEach(a => {
        if (a.type === 'Amenity' && a.amenity_id) {
          addonQtys[a.amenity_id] = a.quantity || 1;
          const el = document.getElementById('qty-' + a.amenity_id);
          if (el) el.textContent = addonQtys[a.amenity_id];
          const item = document.getElementById('addon-' + a.amenity_id);
          if (item) item.classList.add('selected');
        }
      });
      recalcPrice();
    })
    .catch(() => recalcPrice());

  document.getElementById('emOverlay').classList.add('open');
}

function closeEdit() {
  document.getElementById('emOverlay').classList.remove('open');
}
document.getElementById('emOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

document.getElementById('emForm').addEventListener('submit', function(e) {
  const ci = document.getElementById('emCheckIn').value;
  const co = document.getElementById('emCheckOut').value;
  const adults = parseInt(document.getElementById('emAdults').value);
  if (!ci) { e.preventDefault(); alert('Please select a check-in date.'); return; }
  if (adults < 1) { e.preventDefault(); alert('At least 1 adult is required.'); return; }
  const coGroup = document.getElementById('emCheckOutGroup');
  if (coGroup.style.display !== 'none' && co && co <= ci) {
    e.preventDefault(); alert('Check-out date must be after check-in date.'); return;
  }
  buildAddonInputs();
});

function changeAoQty(aid, delta) {
  aoQtys[aid] = Math.max(0, (aoQtys[aid] || 0) + delta);
  document.getElementById('ao-qty-' + aid).textContent = aoQtys[aid];
  const item = document.getElementById('ao-addon-' + aid);
  if (item) item.classList.toggle('selected', aoQtys[aid] > 0);
  recalcAoPrice();
}

function recalcAoPrice() {
  if (!aoCurrentBk) return;
  const base = aoCurrentBk.base_price !== undefined ? aoCurrentBk.base_price : parseFloat(aoCurrentBk.total_price || 0);
  let addonTotal = 0;
  AMENITIES_DATA.forEach(am => { addonTotal += parseFloat(am.price) * (aoQtys[am.id] || 0); });
  document.getElementById('aoPricePreview').textContent = '₱' + (base + addonTotal).toLocaleString('en-PH', {minimumFractionDigits:2});
}

function openAddons(bk) {
  aoCurrentBk = bk;
  aoQtys = {};
  document.getElementById('aoBookingId').value = bk.id;

  AMENITIES_DATA.forEach(am => {
    const el = document.getElementById('ao-qty-' + am.id);
    if (el) el.textContent = '0';
    const item = document.getElementById('ao-addon-' + am.id);
    if (item) item.classList.remove('selected');
  });

  fetch('fetch_booking_addons.php?booking_id=' + bk.id)
    .then(r => r.json())
    .then(addons => {
      let oldAddonTotal = 0;
      addons.forEach(a => {
        if (a.type === 'Amenity' && a.amenity_id) {
          aoQtys[a.amenity_id] = a.quantity || 1;
          oldAddonTotal += parseFloat(a.price || 0) * aoQtys[a.amenity_id];
          const el = document.getElementById('ao-qty-' + a.amenity_id);
          if (el) el.textContent = aoQtys[a.amenity_id];
          const item = document.getElementById('ao-addon-' + a.amenity_id);
          if (item) item.classList.add('selected');
        }
      });
      aoCurrentBk.base_price = parseFloat(aoCurrentBk.total_price) - oldAddonTotal;
      recalcAoPrice();
    })
    .catch(() => {
      aoCurrentBk.base_price = parseFloat(aoCurrentBk.total_price);
      recalcAoPrice();
    });

  document.getElementById('aoOverlay').classList.add('open');
}

function closeAddons() {
  document.getElementById('aoOverlay').classList.remove('open');
}
document.getElementById('aoOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeAddons();
});

document.getElementById('aoForm').addEventListener('submit', function() {
  const container = document.getElementById('aoAddonInputs');
  container.innerHTML = '';
  AMENITIES_DATA.forEach(am => {
    const qty = aoQtys[am.id] || 0;
    if (qty > 0) {
      container.innerHTML += `<input type="hidden" name="addon_ids[]" value="${am.id}">`;
      container.innerHTML += `<input type="hidden" name="addon_qtys[]" value="${qty}">`;
    }
  });
});

function removeCartItem(cartId) {
    if (!confirm('Are you sure you want to remove this booking from your cart?')) return;
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=remove&cart_id=' + encodeURIComponent(cartId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    });
}
</script>
</body>
</html>