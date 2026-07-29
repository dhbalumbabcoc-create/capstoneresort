<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendBookingConfirmationEmail($booking_data) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bucod.lyngemae123@gmail.com';
        $mail->Password   = 'oqjt slmc lmsv kmis';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->setFrom('bucod.lyngemae123@gmail.com', 'Sinulom Falls and Bolao Cold Spring');
        $mail->addAddress($booking_data['guest_email'], $booking_data['guest_name']);
        $mail->addReplyTo('bucod.lyngemae123@gmail.com', 'Sinulom Falls and Bolao Cold Spring');

        // Embed resort profile logo
        $logo_path = __DIR__ . '/../images/logo.jpg';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'resort_logo', 'logo.jpg');
        }

        $mail->isHTML(true);
        $mail->Subject = 'Booking Receipt - Sinulom & Bolao Cold Spring Resort #' . str_pad($booking_data['booking_id'], 6, '0', STR_PAD_LEFT);
        $emailBody = generateEmailBody($booking_data);
        $mail->Body    = $emailBody;
        $mail->AltBody = strip_tags($emailBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function generateEmailBody($data) {
    $booking_id = str_pad($data['booking_id'], 6, '0', STR_PAD_LEFT);
    $check_in   = date('F d, Y', strtotime($data['check_in_date']));
    $check_out  = ($data['mode'] === 'daytour') ? 'Same Day' : date('F d, Y', strtotime($data['check_out_date']));
    $total      = number_format($data['total_price'], 2);
    $mode_label = ucfirst($data['mode'] ?? 'N/A');
    $ts_map     = [
        '8am-12pm'  => '8:00 AM – 12:00 PM (Morning)',
        '12pm-5pm'  => '12:00 PM – 5:00 PM (Afternoon)',
        'full_day'  => '8:00 AM – 5:00 PM (Full Day)',
        '8am-5pm'   => '8:00 AM – 5:00 PM',
        '5pm-10pm'  => '5:00 PM – 10:00 PM',
        'full_night'=> 'Full Night (Overnight)',
        'overnight' => '8:00 AM - 8:00 PM (Overnight)',
    ];
    $ts_label   = $ts_map[$data['time_slot'] ?? ''] ?? ($data['time_slot'] ?: 'N/A');
    $now        = date('F d, Y h:i A');
    $adults     = intval($data['num_adults']   ?? 0);
    $children   = intval($data['num_children'] ?? 0);
    $phone      = htmlspecialchars($data['guest_phone']    ?? '');
    $name       = htmlspecialchars($data['guest_name']     ?? '');
    $email      = htmlspecialchars($data['guest_email']    ?? '');
    $facility   = htmlspecialchars($data['facility_name']  ?? 'N/A');
    $area       = htmlspecialchars($data['area_name']      ?? 'N/A');

    // ── Parse notes for extra parameters if not explicitly passed ────────────
    $notes_raw = $data['notes'] ?? '';
    $below5    = intval($data['num_below5'] ?? 0);
    $num_pwd   = intval($data['num_pwd']    ?? 0);
    if (!empty($notes_raw)) {
        if ($below5 == 0 && preg_match('/Below5:\s*(\d+)/i', $notes_raw, $m)) {
            $below5 = intval($m[1]);
        }
        if ($num_pwd == 0 && preg_match('/PWD:\s*(\d+)/i', $notes_raw, $m)) {
            $num_pwd = intval($m[1]);
        }
    }

    $area_lc    = strtolower($data['area_name'] ?? '');
    $isBoth     = strpos($area_lc,'both')!==false ||
                  (strpos($area_lc,'sinulom')!==false && strpos($area_lc,'bolao')!==false);
    $rate_adult = $isBoth ? 160 : 110;
    $rate_child = $isBoth ? 85  : 60;
    $rate_pwd   = round($rate_adult * 0.80, 2);

    // Facility price (from data or fallback)
    $fac_price  = floatval($data['facility_price'] ?? 0);
    // If facility_price not passed, try to derive from total (best effort)
    $nights = 1;
    if (($data['mode'] ?? '') === 'overnight' &&
        !empty($data['check_in_date']) && !empty($data['check_out_date']) &&
        $data['check_out_date'] > $data['check_in_date']) {
        $nights = max(1, (int)((strtotime($data['check_out_date']) - strtotime($data['check_in_date'])) / 86400));
    }
    $fac_total    = $fac_price * $nights;
    $adult_total  = $adults   * $rate_adult;
    $child_total  = $children * $rate_child;
    $pwd_total_email = $num_pwd * $rate_pwd;
    $location_sub = $adult_total + $child_total + $pwd_total_email;

    // Add-ons from booking_addons table (if booking_id available)
    $addons_html  = '';
    $addon_total  = 0.0;
    if (!empty($data['booking_id'])) {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            $astmt = $conn->prepare(
                "SELECT a.name, a.price, ba.quantity
                 FROM booking_addons ba
                 JOIN amenities a ON ba.amenity_id = a.id
                 WHERE ba.booking_id = ? AND ba.amenity_id IS NOT NULL"
            );
            if ($astmt) {
                $bid_int = intval($data['booking_id']);
                $astmt->bind_param("i", $bid_int);
                $astmt->execute();
                $ares = $astmt->get_result();
                while ($ar = $ares->fetch_assoc()) {
                    $line = floatval($ar['price']) * intval($ar['quantity'] ?? 1);
                    $addon_total += $line;
                    $addons_html .= '<tr><td class="lbl">&nbsp;&nbsp;' . htmlspecialchars($ar['name']) .
                        ' × ' . intval($ar['quantity'] ?? 1) . '</td><td class="val">&#8369;' .
                        number_format($line, 2) . '</td></tr>';
                }
                $astmt->close();
            }
        }
    }

    $nights_label = $nights > 1 ? ' × ' . $nights . ' night' . ($nights > 1 ? 's' : '') : '';

    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
    $html .= 'body{font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;}';
    $html .= '.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}';
    $html .= '.hdr{background:#1a3d2b;padding:32px 36px;text-align:center;color:#fff;}';
    $html .= '.hdr h1{font-size:22px;margin:0 0 4px;font-weight:700;}';
    $html .= '.hdr p{font-size:13px;color:rgba(255,255,255,.75);margin:4px 0 0;}';
    $html .= '.body{padding:32px 36px;}';
    $html .= '.greeting{font-size:16px;color:#1a1a1a;margin-bottom:8px;}';
    $html .= '.sub{font-size:14px;color:#6b7280;margin-bottom:24px;line-height:1.6;}';
    $html .= '.ref-box{background:#f0faf4;border:1px solid #c8e6c9;border-radius:10px;padding:14px 20px;margin-bottom:24px;}';
    $html .= '.ref-id{font-size:20px;font-weight:800;color:#1a3d2b;}';
    $html .= '.ref-status{display:inline-block;background:#fff8e1;color:#e65100;border:1px solid #ffe082;border-radius:50px;padding:3px 12px;font-size:12px;font-weight:700;margin-top:6px;}';
    $html .= '.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin:20px 0 10px;border-bottom:1px solid #f0f0f0;padding-bottom:6px;}';
    $html .= 'table.dt{width:100%;border-collapse:collapse;}';
    $html .= 'table.dt td{padding:9px 0;border-bottom:1px solid #f5f0e8;font-size:14px;}';
    $html .= 'table.dt .lbl{color:#6b7280;width:55%;}';
    $html .= 'table.dt .val{color:#1a1a1a;font-weight:600;text-align:right;}';
    $html .= 'table.dt .sub-lbl{color:#9ca3af;font-size:12px;padding-left:12px;}';
    $html .= 'table.dt .sub-val{color:#6b7280;font-size:12px;text-align:right;}';
    $html .= '.breakdown-hdr td{background:#f0faf4;color:#1a3d2b;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.8px;padding:8px 0 !important;border-bottom:none !important;}';
    $html .= '.total-row td{border-top:2px solid #1a3d2b !important;border-bottom:none !important;padding-top:12px !important;}';
    $html .= '.total-box{background:#1a3d2b;border-radius:10px;padding:18px 20px;margin:24px 0;overflow:hidden;}';
    $html .= '.total-box .tl{color:rgba(255,255,255,.75);font-size:13px;display:block;}';
    $html .= '.total-box .tv{color:#fff;font-size:24px;font-weight:800;display:block;margin-top:4px;}';
    $html .= '.steps{background:#f9fafb;border-radius:10px;padding:20px;margin-bottom:24px;}';
    $html .= '.steps h4{font-size:13px;font-weight:700;color:#1a1a1a;margin:0 0 10px;}';
    $html .= '.steps li{font-size:13px;color:#6b7280;margin-bottom:6px;line-height:1.5;}';
    $html .= '.ftr{background:#1a3d2b;padding:24px 36px;text-align:center;}';
    $html .= '.ftr p{color:rgba(255,255,255,.65);font-size:12px;margin:4px 0;}';
    $html .= '.ftr a{color:#86efac;}';
    $html .= '</style></head><body>';
    $html .= '<div class="wrap">';
    $html .= '<div class="hdr"><div style="margin-bottom:12px;"><img src="cid:resort_logo" alt="Sinulom Falls &amp; Bolao Cold Spring Resort" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:#ffffff;display:inline-block;"></div><h1>Booking Receipt</h1><p>Sinulom Falls &amp; Bolao Cold Spring Resort</p><p style="font-size:11px;color:rgba(255,255,255,.5);">Issued: ' . $now . '</p></div>';
    $html .= '<div class="body">';
    $html .= '<p class="greeting">Dear <strong>' . $name . '</strong>,</p>';

    // Status-aware message and badge
    $booking_status   = strtolower($data['status'] ?? '');
    $booking_type     = strtolower($data['booking_type'] ?? 'online');
    $is_walkin        = ($booking_type === 'walkin');
    $is_confirmed     = ($booking_status === 'confirmed' || $is_walkin);

    if ($is_confirmed) {
        $html .= '<p class="sub">Your booking at <strong>Sinulom Falls &amp; Bolao Cold Spring Resort</strong> has been <strong style="color:#1a3d2b;">confirmed</strong>. We look forward to welcoming you!</p>';
        $html .= '<div class="ref-box"><div class="ref-id">Booking #' . $booking_id . '</div>';
        $html .= '<div class="ref-status" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;">&#10003; Confirmed</div></div>';
    } else {
        $html .= '<p class="sub">Thank you for your booking! Your reservation has been received and is currently <strong>pending confirmation</strong>. Our staff will review and confirm it shortly.</p>';
        $html .= '<div class="ref-box"><div class="ref-id">Booking #' . $booking_id . '</div>';
        $html .= '<div class="ref-status" style="background:#fff8e1;color:#e65100;border:1px solid #ffe082;">⏳ Pending Confirmation</div></div>';
    }

    // Guest Information
    $html .= '<div class="sec">Guest Information</div>';
    $html .= '<table class="dt">';
    $html .= '<tr><td class="lbl">Full Name</td><td class="val">' . $name . '</td></tr>';
    $html .= '<tr><td class="lbl">Email</td><td class="val">' . $email . '</td></tr>';
    $html .= '<tr><td class="lbl">Contact</td><td class="val">' . $phone . '</td></tr>';
    $html .= '</table>';

    // Booking Details
    $html .= '<div class="sec">Booking Details</div>';
    $html .= '<table class="dt">';
    $html .= '<tr><td class="lbl">Booking Mode</td><td class="val">' . $mode_label . '</td></tr>';
    $html .= '<tr><td class="lbl">Time Slot</td><td class="val">' . htmlspecialchars($ts_label) . '</td></tr>';
    $html .= '<tr><td class="lbl">Location</td><td class="val">' . $area . '</td></tr>';
    $html .= '<tr><td class="lbl">Facility</td><td class="val">' . $facility . '</td></tr>';
    $html .= '<tr><td class="lbl">Check-in</td><td class="val">' . $check_in . '</td></tr>';
    $html .= '<tr><td class="lbl">Check-out</td><td class="val">' . $check_out . '</td></tr>';
    $html .= '<tr><td class="lbl">Adults</td><td class="val">' . $adults . '</td></tr>';
    $html .= '<tr><td class="lbl">Children</td><td class="val">' . $children . '</td></tr>';
    $html .= '</table>';

    // Price Breakdown
    $html .= '<div class="sec">Price Breakdown</div>';
    $html .= '<table class="dt">';
    if ($fac_price > 0) {
        $fac_label = 'Facility (' . $facility . ')' . ($nights > 1 ? $nights_label : '');
        $html .= '<tr><td class="lbl">' . $fac_label . '</td><td class="val">&#8369;' . number_format($fac_total, 2) . '</td></tr>';
    }
    $html .= '<tr><td class="lbl">Adults (' . $adults . ' × &#8369;' . number_format($rate_adult, 2) . '/pax)</td><td class="val">&#8369;' . number_format($adult_total, 2) . '</td></tr>';
    if ($children > 0) {
        $html .= '<tr><td class="lbl">Children Ages 6&ndash;17 (' . $children . ' &times; &#8369;' . number_format($rate_child, 2) . '/pax)</td><td class="val">&#8369;' . number_format($child_total, 2) . '</td></tr>';
    }
    $rate_pwd = round($rate_adult * 0.80, 2);
    if ($below5 > 0) {
        $html .= '<tr><td class="lbl">Children Age 5 &amp; Below (' . $below5 . ' pax) <span style="background:#d1fae5;color:#065f46;font-size:11px;padding:1px 7px;border-radius:50px;font-weight:700;">FREE</span></td><td class="val" style="color:#059669;">&#8369;0.00</td></tr>';
    }
    if ($num_pwd > 0) {
        $pwd_total_email = $num_pwd * $rate_pwd;
        $html .= '<tr><td class="lbl">PWD/Seniors (' . $num_pwd . ' × &#8369;' . number_format($rate_pwd, 2) . '/pax) <span style="background:#fef3c7;color:#92400e;font-size:11px;padding:1px 7px;border-radius:50px;font-weight:700;">20% OFF</span></td><td class="val">&#8369;' . number_format($pwd_total_email, 2) . '</td></tr>';
    }
    $html .= '<tr><td class="lbl" style="color:#1a3d2b;font-weight:600;">Location Subtotal</td><td class="val" style="color:#1a3d2b;">&#8369;' . number_format($location_sub, 2) . '</td></tr>';
    
    $transport_cost = floatval($data['transport_cost'] ?? 0);
    $transportation = strtolower(trim($data['transportation'] ?? 'none'));
    if (($transport_cost == 0 || $transportation === 'none') && !empty($notes_raw)) {
        if (preg_match('/Transport:\s*([a-z0-9_\-]+)/i', $notes_raw, $m)) {
            $transportation = strtolower($m[1]);
            $trans_guests = max(0, $adults + $num_pwd); // Children travel FREE
            if ($transportation === 'tignapoloan') {
                $transport_cost = $trans_guests * 50;
            } elseif ($transportation === 'cdo') {
                $transport_cost = $trans_guests * 250;
            } elseif ($transportation === 'private') {
                $transport_cost = 3500;
            }
        }
    }
    if ($transport_cost > 0) {
        $trans_label = $transportation === 'tignapoloan' ? 'Tignapoloan Crossing' : ($transportation === 'cdo' ? 'Cagayan De Oro' : 'Private Vehicle Rental');
        $html .= '<tr><td class="lbl">Transportation (' . $trans_label . ')</td><td class="val">&#8369;' . number_format($transport_cost, 2) . '</td></tr>';
    }
    if ($addons_html) {
        $html .= '<tr class="breakdown-hdr"><td colspan="2">Add-ons</td></tr>';
        $html .= $addons_html;
        $html .= '<tr><td class="lbl" style="color:#1a3d2b;font-weight:600;">Add-ons Subtotal</td><td class="val" style="color:#1a3d2b;">&#8369;' . number_format($addon_total, 2) . '</td></tr>';
    }
    // VAT
    $vat_val = floatval($data['vat'] ?? 0);
    if ($vat_val > 0) {
        $subtotal_before_vat = floatval($data['total_price']) - $vat_val;
        $html .= '<tr><td class="lbl">Subtotal (before VAT)</td><td class="val">&#8369;' . number_format($subtotal_before_vat, 2) . '</td></tr>';
        $html .= '<tr><td class="lbl">VAT (12%)</td><td class="val">&#8369;' . number_format($vat_val, 2) . '</td></tr>';
    }
    $html .= '<tr class="total-row"><td class="lbl" style="font-size:15px;font-weight:800;color:#1a1a1a;">Total Amount (VAT Inclusive)</td><td class="val" style="font-size:15px;font-weight:800;color:#1a3d2b;">&#8369;' . $total . '</td></tr>';
    $html .= '</table>';

    // GCash Payment Summary (fetch from DB — deduplicated across all booking IDs)
    $pay_html = '';
    $total_paid_email = 0.0;
    // Collect all booking IDs to query (supports multi-booking groups)
    $email_bid_list = [];
    if (!empty($data['booking_ids']) && is_array($data['booking_ids'])) {
        $email_bid_list = array_map('intval', $data['booking_ids']);
    } elseif (!empty($data['booking_id'])) {
        $email_bid_list = [intval($data['booking_id'])];
    }
    if (!empty($email_bid_list)) {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            $ref_groups_email = [];
            $all_payments_email = [];
            foreach ($email_bid_list as $ebid) {
                $pq = $conn->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY paid_at ASC");
                if ($pq) {
                    $pq->bind_param("i", $ebid);
                    $pq->execute();
                    $pres = $pq->get_result();
                    while ($pr = $pres->fetch_assoc()) {
                        $ref_key = trim($pr['reference_number']);
                        if ($ref_key !== '') {
                            if (!isset($ref_groups_email[$ref_key])) {
                                $ref_groups_email[$ref_key] = $pr;
                            } else {
                                $ref_groups_email[$ref_key]['amount_paid'] = floatval($ref_groups_email[$ref_key]['amount_paid']) + floatval($pr['amount_paid']);
                                if ($pr['status'] === 'completed') {
                                    $ref_groups_email[$ref_key]['status'] = 'completed';
                                }
                            }
                        } else {
                            $all_payments_email[] = $pr;
                        }
                    }
                    $pq->close();
                }
            }
            foreach ($ref_groups_email as $grouped_p) {
                $all_payments_email[] = $grouped_p;
            }
            foreach ($all_payments_email as $pr) {
                $total_paid_email += floatval($pr['amount_paid']);
                $pstatus = $pr['status'] === 'completed'
                    ? '<span style="background:#d1fae5;color:#065f46;font-size:11px;padding:2px 9px;border-radius:50px;font-weight:700;border:1px solid #a7f3d0;">&#10003; Payment Received</span>'
                    : '<span style="background:#fff8e1;color:#92400e;font-size:11px;padding:2px 9px;border-radius:50px;font-weight:700;border:1px solid #fde68a;">&#9203; Payment Pending</span>';
                $pay_html .= '<tr><td class="lbl">GCash Payment</td><td class="val" style="color:#059669;">&#8369;' . number_format($pr['amount_paid'], 2) . '</td></tr>';
                $pay_html .= '<tr><td class="lbl">Reference No.</td><td class="val" style="font-family:monospace;">' . htmlspecialchars($pr['reference_number']) . '</td></tr>';
                $pay_html .= '<tr><td class="lbl">Status</td><td class="val">' . $pstatus . '</td></tr>';
            }
        }
    }
    if ($pay_html) {
        $remaining_email = floatval($data['total_price']) - $total_paid_email;
        $html .= '<div class="sec">GCash Payment</div>';
        $html .= '<table class="dt">' . $pay_html . '</table>';
        $bal_color = $remaining_email <= 0 ? '#d4edda;color:#155724;border:1px solid #c3e6cb;' : '#fff8e1;color:#92400e;border:1px solid #ffe082;';
        $bal_label = $remaining_email <= 0 ? '&#10003; Fully Paid — No Balance Due' : 'Remaining Balance: &#8369;' . number_format($remaining_email, 2) . ' (Pay at Resort)';
        $html .= '<div style="background:#' . $bal_color . 'border-radius:8px;padding:12px 16px;margin-top:12px;font-weight:700;font-size:14px;">' . $bal_label . '</div>';
    }

    $html .= '<div class="steps"><h4>What happens next?</h4><ul>';
    if ($is_confirmed) {
        $html .= '<li>Proceed to the <strong>ticketing office</strong> upon arrival to complete payment and claim your wristband.</li>';
        $html .= '<li>Please bring a <strong>valid ID</strong> on the day of your visit.</li>';
        $html .= '<li>Check-in time: 8:00 AM onwards.</li>';
        $html .= '<li>For questions, contact us at <strong>' . $phone . '</strong>.</li>';
    } else {
        $html .= '<li>Our staff will review your booking and confirm it shortly.</li>';
        $html .= '<li>You will receive a confirmation call or message at <strong>' . $phone . '</strong>.</li>';
        $html .= '<li>Please bring a valid ID on the day of your visit.</li>';
        $html .= '<li>Check-in time: 8:00 AM onwards.</li>';
    }
    $html .= '</ul></div>';
    $html .= '<p style="font-size:13px;color:#6b7280;">If you have questions, contact us at <a href="mailto:bucod.lyngemae123@gmail.com" style="color:#1a3d2b;">bucod.lyngemae123@gmail.com</a>.</p>';
    $html .= '</div>';
    $html .= '<div class="ftr"><p><strong style="color:#fff;">Sinulom Falls &amp; Bolao Cold Spring Resort</strong></p>';
    $html .= '<p>Tignapoloan, Cagayan de Oro City, Philippines</p>';
    $html .= '<p style="margin-top:10px;font-size:11px;">This is an automated receipt. Please do not reply directly.</p></div>';
    $html .= '</div></body></html>';
    return $html;
}
?>