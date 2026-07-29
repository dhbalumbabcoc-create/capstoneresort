<?php
session_start();
require_once 'config/db_config.php';
require_once 'includes/functions.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $facility_id_input = $_POST['facility_id'] ?? '';
        $facility_ids = array_map('intval', array_filter(explode(',', $facility_id_input)));
        
        if (empty($facility_ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid facility']);
            exit();
        }

        // Fetch facility details
        $placeholders = implode(',', array_fill(0, count($facility_ids), '?'));
        $stmt = $conn->prepare("SELECT id, name, price FROM facilities WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($facility_ids));
        $stmt->bind_param($types, ...$facility_ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $fac_list = [];
        while ($row = $res->fetch_assoc()) {
            $fac_list[] = $row;
        }
        $stmt->close();

        if (empty($fac_list)) {
            echo json_encode(['success' => false, 'message' => 'Facility not found']);
            exit();
        }

        $facility_price = 0;
        $facility_names = [];
        foreach ($fac_list as $fac) {
            $facility_price += floatval($fac['price']);
            $facility_names[] = $fac['name'];
        }
        $facility_name = implode(' + ', $facility_names);

        $area_id = !empty($_POST['area_id']) ? intval($_POST['area_id']) : null;
        $area_name = 'N/A';
        if ($area_id) {
            $stmt = $conn->prepare("SELECT name FROM areas WHERE id = ?");
            $stmt->bind_param("i", $area_id);
            $stmt->execute();
            $a = $stmt->get_result()->fetch_assoc();
            if ($a) $area_name = $a['name'];
            $stmt->close();
        }

        // Calculate baseline total price if not provided or 0
        $check_in_date = $_POST['check_in_date'] ?? '';
        $check_out_date = $_POST['check_out_date'] ?? '';
        $mode = $_POST['mode'] ?? 'daytour';
        
        $nights = 1;
        if ($mode === 'overnight' && !empty($check_in_date) && !empty($check_out_date)) {
            $d1 = DateTime::createFromFormat('Y-m-d', $check_in_date);
            $d2 = DateTime::createFromFormat('Y-m-d', $check_out_date);
            if ($d1 && $d2) {
                $nights = max(1, (int)$d1->diff($d2)->days);
            }
        }
        $facility_cost = $facility_price * $nights;
        
        $area_price_per_person = 0.0;
        if ($area_id) {
            $astmt = $conn->prepare("SELECT name, price_regular, price_discounted, price_children FROM areas WHERE id=? AND status='active'");
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
                    $num_adults = intval($_POST['num_adults'] ?? 1);
                    $num_children = intval($_POST['num_children'] ?? 0);
                    $num_pwd = intval($_POST['num_discounted'] ?? 0);
                    $area_price_per_person = ($num_adults * $rates['regular']) + ($num_children * $rates['children']) + ($num_pwd * $rate_pwd);
                }
                $astmt->close();
            }
        }

        $transportation = $_POST['transport_option'] ?? 'none';
        $transport_cost = 0;
        $num_pwd = intval($_POST['num_discounted'] ?? 0);
        $num_adults = intval($_POST['num_adults'] ?? 1);
        $transport_guests = max(0, $num_adults + $num_pwd);
        if ($transportation === 'tignapoloan') {
            $transport_cost = $transport_guests * 50;
        } elseif ($transportation === 'cdo') {
            $transport_cost = $transport_guests * 250;
        } elseif ($transportation === 'private') {
            $transport_cost = 3500;
        }

        $subtotal = $facility_cost + $area_price_per_person + $transport_cost;
        $vat = round($subtotal * 0.12, 2);
        $calculated_total = $subtotal + $vat;

        $total_price = floatval($_POST['total_price'] ?? 0);
        if ($total_price <= 0) {
            $total_price = $calculated_total;
        }

        // Handle uploaded PWD/Senior ID photos
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

        $cart_notes = $_POST['notes'] ?? '';
        if (!empty($uploaded_pwd_photos)) {
            $cart_notes .= ' | PWD_IDs: ' . implode(',', $uploaded_pwd_photos);
        }

        // Build item array with duplicate keys for backwards and cross compatibility
        $item = [
            'id'             => uniqid('cart_'),
            'facility_id'    => implode(',', $facility_ids),
            'facility_name'  => $facility_name,
            'facility_price' => $facility_price,
            'facilities'     => $fac_list,
            'area_id'        => $area_id,
            'area_name'      => $area_name,
            'check_in'       => $check_in_date,
            'check_in_date'  => $check_in_date,
            'check_out'      => $check_out_date,
            'check_out_date' => $check_out_date,
            'mode'           => $mode,
            'time_slot'      => $_POST['time_slot'] ?? '',
            'num_adults'     => $num_adults,
            'num_children'   => intval($_POST['num_children'] ?? 0),
            'num_below5'     => intval($_POST['num_below5'] ?? 0),
            'num_discounted' => $num_pwd,
            'num_pwd'        => $num_pwd,
            'num_guests'     => $num_adults + intval($_POST['num_children'] ?? 0) + $num_pwd,
            'transport_opt'  => $transportation,
            'transportation' => $transportation,
            'transport_fee'  => $transport_cost,
            'transport_cost' => $transport_cost,
            'total_price'    => $total_price,
            'vat'            => $vat,
            'notes'          => $cart_notes,
            'pwd_id_photos'  => $uploaded_pwd_photos,
            'addon_ids'      => $_POST['addon_ids'] ?? [],
            'addon_qtys'     => $_POST['addon_qtys'] ?? [],
        ];

        $_SESSION['cart'][] = $item;

        echo json_encode([
            'success' => true,
            'message' => 'Booking added to cart successfully!',
            'cart_count' => count($_SESSION['cart'])
        ]);
        exit();
    }

    if ($action === 'remove') {
        $cart_id = $_POST['cart_id'] ?? '';
        foreach ($_SESSION['cart'] as $k => $item) {
            if ($item['id'] === $cart_id) {
                unset($_SESSION['cart'][$k]);
                break;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']); // re-index
        echo json_encode(['success' => true, 'cart_count' => count($_SESSION['cart'])]);
        exit();
    }

    if ($action === 'get') {
        echo json_encode([
            'success' => true,
            'cart' => $_SESSION['cart'],
            'cart_count' => count($_SESSION['cart'])
        ]);
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit();
