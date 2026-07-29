<?php
session_start();
require_once 'config/db_config.php';
require_once 'includes/send_booking_email.php';

if (empty($_SESSION['guest_logged_in']) || empty($_SESSION['cart'])) {
    header("Location: landing.php");
    exit();
}

$booking_ids = [];
$raw_ids = $_GET['item_ids'] ?? $_GET['item_id'] ?? $_GET['cart_ids'] ?? $_GET['cart_id'] ?? null;
$target_item_ids = [];
if (!empty($raw_ids)) {
    $target_item_ids = array_filter(array_map('trim', explode(',', $raw_ids)));
}
$new_cart = [];

foreach ($_SESSION['cart'] as $item) {
    if (!empty($target_item_ids) && !in_array($item['id'], $target_item_ids, true)) {
        $new_cart[] = $item;
        continue;
    }

    // Determine the guest details based on what's in the cart item, fallback to session
    $guest_name  = $item['guest_name']  ?? $_SESSION['guest_name'] ?? '';
    $guest_email = $item['guest_email'] ?? $_SESSION['guest_email'] ?? '';
    $guest_phone = $item['guest_phone'] ?? '';
    
    // Fallback if missing
    if (empty($guest_name) || empty($guest_email)) {
        continue;
    }

    $facility_id_str = $item['facility_id'] ?? '';
    $facility_ids    = !empty($facility_id_str) ? array_map('intval', explode(',', $facility_id_str)) : [];
    if (empty($facility_ids)) {
        continue;
    }

    $area_id        = !empty($item['area_id']) ? intval($item['area_id']) : null;
    $check_in_date  = $item['check_in_date'] ?? $item['check_in'] ?? '';
    $check_out_date = $item['check_out_date'] ?? $item['check_out'] ?? '';
    $num_adults     = intval($item['num_adults'] ?? 1);
    $num_children   = intval($item['num_children'] ?? 0);
    $num_below5     = intval($item['num_below5'] ?? 0);
    $num_pwd        = intval($item['num_pwd'] ?? $item['num_discounted'] ?? 0);
    $num_guests     = intval($item['num_guests'] ?? ($num_adults + $num_children + $num_pwd));
    $mode           = $item['mode'] ?? 'daytour';

    // Fetch individual facility prices
    $placeholders = implode(',', array_fill(0, count($facility_ids), '?'));
    $fstmt = $conn->prepare("SELECT id, price FROM facilities WHERE id IN ($placeholders)");
    $types = str_repeat('i', count($facility_ids));
    $fstmt->bind_param($types, ...$facility_ids);
    $fstmt->execute();
    $fres = $fstmt->get_result();
    $fac_prices = [];
    while ($frow = $fres->fetch_assoc()) {
        $fac_prices[$frow['id']] = floatval($frow['price']);
    }
    $fstmt->close();

    $nights = 1;
    if ($mode === 'overnight' && !empty($check_in_date) && !empty($check_out_date)) {
        $d1 = DateTime::createFromFormat('Y-m-d', $check_in_date);
        $d2 = DateTime::createFromFormat('Y-m-d', $check_out_date);
        if ($d1 && $d2) {
            $nights = max(1, (int)$d1->diff($d2)->days);
        }
    }

    // Determine area fee and transport fee to distribute
    $area_price_per_person = 0.0;
    if ($area_id) {
        $astmt = $conn->prepare("SELECT name FROM areas WHERE id=? AND status='active'");
        if ($astmt) {
            $astmt->bind_param("i", $area_id);
            $astmt->execute();
            $arow = $astmt->get_result()->fetch_assoc();
            if ($arow) {
                $rates = ['regular' => 110.0, 'discounted' => 90.0, 'children' => 60.0];
                $name_lower = strtolower(trim((string)$arow['name']));
                if (in_array($name_lower, ['both', 'combo', 'combo package', 'sinulom + bolao'], true)) {
                    $rates = ['regular' => 160.0, 'discounted' => 130.0, 'children' => 85.0];
                }
                $rate_pwd = round($rates['regular'] * 0.80, 2);
                $area_price_per_person = ($num_adults * $rates['regular']) + ($num_children * $rates['children']) + ($num_pwd * $rate_pwd);
            }
            $astmt->close();
        }
    }

    $transportation = $item['transport_opt'] ?? $item['transportation'] ?? 'none';
    $transport_cost = 0;
    $transport_guests = max(0, $num_adults + $num_pwd);
    if ($transportation === 'tignapoloan') {
        $transport_cost = $transport_guests * 50;
    } elseif ($transportation === 'cdo') {
        $transport_cost = $transport_guests * 250;
    } elseif ($transportation === 'private') {
        $transport_cost = 3500;
    }

    $first_item = true;
    foreach ($facility_ids as $fid) {
        $facility_price = $fac_prices[$fid] ?? 0.0;
        $facility_cost = $facility_price * $nights;
        $is_first = $first_item;

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

        $subtotal = $facility_cost + $curr_area_fee + $curr_trans_fee;
        $vat = round($subtotal * 0.12, 2);
        $curr_total_price = $subtotal + $vat;
        $curr_notes = 'Time Slot: ' . ($item['time_slot'] ?? '') . ' | Transport: ' . ($curr_trans_fee > 0 ? $transportation : 'none') . ' | Below5: ' . $curr_num_below5 . ' | PWD: ' . $curr_num_pwd . ' | VAT: ' . $vat;
        if (!empty($item['pwd_id_photos'])) {
            $curr_notes .= ' | PWD_IDs: ' . (is_array($item['pwd_id_photos']) ? implode(',', $item['pwd_id_photos']) : $item['pwd_id_photos']);
        } elseif (!empty($item['notes']) && preg_match('/PWD_IDs:\s*([^\s\|]+)/i', $item['notes'], $pmatch)) {
            $curr_notes .= ' | PWD_IDs: ' . $pmatch[1];
        }

        $stmt = $conn->prepare("INSERT INTO bookings (
            facility_id, area_id, guest_name, guest_email, guest_phone,
            check_in_date, check_out_date, num_guests, num_adults, num_children, num_below5, num_discounted,
            mode, booking_type, status, total_price, notes
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'online','unpaid',?,?)");

        $stmt->bind_param("iisssssiiiiisds",
            $fid, $area_id, $guest_name, $guest_email, $guest_phone,
            $check_in_date, $check_out_date, $curr_num_guests, $curr_num_adults,
            $curr_num_children, $curr_num_below5, $curr_num_pwd, $mode,
            $curr_total_price, $curr_notes
        );

        if ($stmt->execute()) {
            $bid = $stmt->insert_id;
            $booking_ids[] = $bid;
            
            // Save addons only for the first booking item
            if ($is_first && !empty($item['addon_ids']) && !empty($item['addon_qtys'])) {
                foreach ($item['addon_ids'] as $idx => $aid) {
                    if ($aid <= 0) continue;
                    $qty = max(1, intval($item['addon_qtys'][$idx]));
                    $ins = $conn->prepare("INSERT INTO booking_addons (booking_id, amenity_id, quantity) VALUES (?,?,?)");
                    $ins->bind_param("iii", $bid, $aid, $qty);
                    $ins->execute(); $ins->close();
                }
            }
        }
        $stmt->close();
    }
}

// Clear or update cart
if (!empty($target_item_ids)) {
    $_SESSION['cart'] = $new_cart;
} else {
    $_SESSION['cart'] = [];
}

if (empty($booking_ids)) {
    header("Location: guest_dashboard.php?tab=cart");
    exit();
}

$ids_param = implode(',', $booking_ids);
header("Location: public_payment.php?booking_ids=$ids_param");
exit();
