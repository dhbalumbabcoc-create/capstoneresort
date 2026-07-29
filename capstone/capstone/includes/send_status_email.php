<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendBookingStatusEmail($booking_data, $status) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
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

        // Recipients
        $mail->setFrom('bucod.lyngemae123@gmail.com', 'Sinulom Falls and Bolao Cold Spring');
        $mail->addAddress($booking_data['guest_email'], $booking_data['guest_name']);
        $mail->addReplyTo('bucod.lyngemae123@gmail.com', 'Sinulom Falls and Bolao Cold Spring');

        // Embed resort profile logo
        $logo_path = __DIR__ . '/../images/logo.jpg';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'resort_logo', 'logo.jpg');
        }

        $mail->isHTML(true);
        
        if ($status === 'confirmed') {
            $booking_id_padded = str_pad($booking_data['id'], 6, '0', STR_PAD_LEFT);
            $mail->Subject = 'Booking Confirmed - Sinulom & Bolao Cold Spring Resort #' . $booking_id_padded;
            $emailBody = generateApprovedEmailBody($booking_data);
        } elseif ($status === 'cancelled') {
            $booking_id_padded = str_pad($booking_data['id'], 6, '0', STR_PAD_LEFT);
            $mail->Subject = 'Booking Cancelled - Sinulom & Bolao Cold Spring Resort #' . $booking_id_padded;
            $emailBody = generateCancelledEmailBody($booking_data);
        } else {
            $mail->Subject = 'Booking Update - Sinulom Falls and Bolao Cold Spring';
            $emailBody = generateDeclinedEmailBody($booking_data);
        }
        
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function generateApprovedEmailBody($data) {
    $booking_id  = str_pad($data['id'], 6, '0', STR_PAD_LEFT);
    $check_in    = date('F d, Y', strtotime($data['check_in_date']));
    $check_out   = ($data['mode'] === 'daytour') ? 'Same Day (Day Tour)' : date('F d, Y', strtotime($data['check_out_date'] ?? $data['check_in_date']));
    $total       = number_format($data['total_price'], 2);
    $mode_label  = ucfirst($data['mode'] ?? 'N/A');
    $adults      = intval($data['num_adults'] ?? 0);
    $children    = intval($data['num_children'] ?? 0);
    $phone       = htmlspecialchars($data['guest_phone'] ?? '');
    $name        = htmlspecialchars($data['guest_name'] ?? '');
    $email_addr  = htmlspecialchars($data['guest_email'] ?? '');
    $facility    = htmlspecialchars($data['facility_name'] ?? 'N/A');
    $area        = htmlspecialchars($data['area_name'] ?? 'N/A');
    $now         = date('F d, Y h:i A');

    // Extract time slot from notes if available
    $ts_label = 'N/A';
    if (!empty($data['notes']) && preg_match('/Time Slot:\s*([a-z0-9_\-]+)/i', $data['notes'], $m)) {
        $ts_map   = [
            '8am-12pm'  => '8:00 AM – 12:00 PM (Morning)',
            '12pm-5pm'  => '12:00 PM – 5:00 PM (Afternoon)',
            'full_day'  => '8:00 AM – 5:00 PM (Full Day)',
            '8am-5pm'   => '8:00 AM – 5:00 PM',
            '5pm-10pm'  => '5:00 PM – 10:00 PM',
            'full_night'=> 'Full Night (Overnight)',
            'overnight' => '8:00 AM – 8:00 PM (Overnight)',
        ];
        $ts_label = $ts_map[strtolower($m[1])] ?? $m[1];
    }

    // Price breakdown
    $area_lc    = strtolower($data['area_name'] ?? '');
    $isBoth     = strpos($area_lc,'both')!==false || (strpos($area_lc,'sinulom')!==false && strpos($area_lc,'bolao')!==false);
    $rate_adult = $isBoth ? 160 : 110;
    $rate_child = $isBoth ? 85  : 60;
    $rate_pwd   = round($rate_adult * 0.80, 2);
    $fac_price  = floatval($data['facility_price'] ?? 0);
    $nights = 1;
    if (($data['mode'] ?? '') === 'overnight' && !empty($data['check_in_date']) && !empty($data['check_out_date']) && $data['check_out_date'] > $data['check_in_date']) {
        $nights = max(1, (int)((strtotime($data['check_out_date']) - strtotime($data['check_in_date'])) / 86400));
    }
    $fac_total    = $fac_price * $nights;
    $adult_total  = $adults   * $rate_adult;
    $child_total  = $children * $rate_child;
    $nights_label = $nights > 1 ? ' × ' . $nights . ' night' . ($nights > 1 ? 's' : '') : '';
    // Parse notes for below5, pwd, vat
    $num_below5_e = 0; $num_pwd_e = 0; $vat_val_e = 0.0;
    if (!empty($data['notes'])) {
        if (preg_match('/Below5:\s*(\d+)/i', $data['notes'], $mx)) $num_below5_e = intval($mx[1]);
        if (preg_match('/PWD:\s*(\d+)/i',    $data['notes'], $mx)) $num_pwd_e    = intval($mx[1]);
        if (preg_match('/VAT:\s*([\d.]+)/i', $data['notes'], $mx)) $vat_val_e    = floatval($mx[1]);
    }

    // Add-ons
    $addons_html = '';
    $addon_total = 0.0;
    $bid_int = intval($data['id'] ?? 0);
    if ($bid_int > 0) {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            $astmt = $conn->prepare("SELECT a.name, a.price, ba.quantity FROM booking_addons ba JOIN amenities a ON ba.amenity_id=a.id WHERE ba.booking_id=? AND ba.amenity_id IS NOT NULL");
            if ($astmt) {
                $astmt->bind_param("i", $bid_int); $astmt->execute();
                $ares = $astmt->get_result();
                while ($ar = $ares->fetch_assoc()) {
                    $line = floatval($ar['price']) * intval($ar['quantity'] ?? 1);
                    $addon_total += $line;
                    $addons_html .= '<tr><td class="lbl">&nbsp;&nbsp;' . htmlspecialchars($ar['name']) . ' × ' . intval($ar['quantity'] ?? 1) . '</td><td class="val">&#8369;' . number_format($line, 2) . '</td></tr>';
                }
                $astmt->close();
            }
        }
    }

    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
    $html .= 'body{font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;}';
    $html .= '.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}';
    $html .= '.hdr{background:#1a3d2b;padding:32px 36px;text-align:center;color:#fff;}';
    $html .= '.hdr-icon{font-size:48px;margin-bottom:12px;}';
    $html .= '.hdr h1{font-size:22px;margin:0 0 4px;font-weight:700;}';
    $html .= '.hdr p{font-size:13px;color:rgba(255,255,255,.75);margin:4px 0 0;}';
    $html .= '.body{padding:32px 36px;}';
    $html .= '.greeting{font-size:16px;color:#1a1a1a;margin-bottom:8px;}';
    $html .= '.sub{font-size:14px;color:#6b7280;margin-bottom:24px;line-height:1.6;}';
    $html .= '.ref-box{background:#f0faf4;border:1px solid #c8e6c9;border-radius:10px;padding:14px 20px;margin-bottom:24px;}';
    $html .= '.ref-id{font-size:20px;font-weight:800;color:#1a3d2b;}';
    $html .= '.ref-status{display:inline-block;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:50px;padding:3px 12px;font-size:12px;font-weight:700;margin-top:6px;}';
    $html .= '.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin:20px 0 10px;border-bottom:1px solid #f0f0f0;padding-bottom:6px;}';
    $html .= 'table.dt{width:100%;border-collapse:collapse;}';
    $html .= 'table.dt td{padding:9px 0;border-bottom:1px solid #f5f0e8;font-size:14px;}';
    $html .= 'table.dt .lbl{color:#6b7280;width:45%;}';
    $html .= 'table.dt .val{color:#1a1a1a;font-weight:600;text-align:right;}';
    $html .= '.total-box{background:#1a3d2b;border-radius:10px;padding:18px 20px;margin:24px 0;overflow:hidden;}';
    $html .= '.total-box .tl{color:rgba(255,255,255,.75);font-size:13px;display:block;}';
    $html .= '.total-box .tv{color:#fff;font-size:24px;font-weight:800;display:block;margin-top:4px;}';
    $html .= '.total-box .tn{color:rgba(255,255,255,.6);font-size:12px;display:block;margin-top:4px;}';
    $html .= '.remind{background:#fff8e1;border-left:4px solid #ffc107;border-radius:6px;padding:16px 20px;margin-bottom:24px;}';
    $html .= '.remind h4{font-size:13px;font-weight:700;color:#856404;margin:0 0 8px;}';
    $html .= '.remind li{font-size:13px;color:#856404;margin-bottom:5px;line-height:1.5;}';
    $html .= '.ftr{background:#1a3d2b;padding:24px 36px;text-align:center;}';
    $html .= '.ftr p{color:rgba(255,255,255,.65);font-size:12px;margin:4px 0;}';
    $html .= '</style></head><body>';
    $html .= '<div class="wrap">';
    $html .= '<div class="hdr"><div style="margin-bottom:12px;"><img src="cid:resort_logo" alt="Sinulom Falls &amp; Bolao Cold Spring Resort" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:#ffffff;display:inline-block;"></div><div class="hdr-icon">✅</div><h1>Booking Confirmed!</h1>';
    $html .= '<p>Your reservation has been approved &amp; confirmed</p>';
    $html .= '<p style="font-size:11px;color:rgba(255,255,255,.5);">Issued: ' . $now . '</p></div>';
    $html .= '<div class="body">';
    $html .= '<p class="greeting">Dear <strong>' . $name . '</strong>,</p>';
    $html .= '<p class="sub">Great news! Your booking at <strong>Sinulom Falls &amp; Bolao Cold Spring Resort</strong> has been <strong style="color:#1a3d2b;">approved and confirmed</strong>. We look forward to welcoming you!</p>';
    $html .= '<div class="ref-box"><div class="ref-id">Booking #' . $booking_id . '</div><div class="ref-status">✓ CONFIRMED</div></div>';

    // Guest info
    $html .= '<div class="sec">Guest Information</div>';
    $html .= '<table class="dt">';
    $html .= '<tr><td class="lbl">Full Name</td><td class="val">' . $name . '</td></tr>';
    $html .= '<tr><td class="lbl">Email</td><td class="val">' . $email_addr . '</td></tr>';
    $html .= '<tr><td class="lbl">Contact</td><td class="val">' . $phone . '</td></tr>';
    $html .= '</table>';

    // Booking details
    $html .= '<div class="sec">Booking Details</div>';
    $html .= '<table class="dt">';
    $html .= '<tr><td class="lbl">Booking Mode</td><td class="val">' . $mode_label . '</td></tr>';
    if ($ts_label !== 'N/A') {
        $html .= '<tr><td class="lbl">Time Slot</td><td class="val">' . htmlspecialchars($ts_label) . '</td></tr>';
    }
    $html .= '<tr><td class="lbl">Location</td><td class="val">' . $area . '</td></tr>';
    $html .= '<tr><td class="lbl">Facility</td><td class="val">' . $facility . '</td></tr>';
    $html .= '<tr><td class="lbl">Check-in</td><td class="val">' . $check_in . '</td></tr>';
    if ($data['mode'] !== 'daytour') {
        $html .= '<tr><td class="lbl">Check-out</td><td class="val">' . $check_out . '</td></tr>';
    }
    $html .= '<tr><td class="lbl">Adults</td><td class="val">' . $adults . '</td></tr>';
    $html .= '<tr><td class="lbl">Children</td><td class="val">' . $children . '</td></tr>';
    $html .= '</table>';

    // Price breakdown
    $html .= '<div class="sec">Price Breakdown</div>';
    $html .= '<table class="dt">';
    if ($fac_price > 0) {
        $fac_label = 'Facility (' . $facility . ')' . ($nights > 1 ? $nights_label : '');
        $html .= '<tr><td class="lbl">' . $fac_label . '</td><td class="val">&#8369;' . number_format($fac_total, 2) . '</td></tr>';
    }
    $html .= '<tr><td class="lbl">Adults (' . $adults . ' × &#8369;' . number_format($rate_adult, 2) . '/pax)</td><td class="val">&#8369;' . number_format($adult_total, 2) . '</td></tr>';
    if ($children > 0) {
        $html .= '<tr><td class="lbl">Children Age 5+ (' . $children . ' × &#8369;' . number_format($rate_child, 2) . '/pax)</td><td class="val">&#8369;' . number_format($child_total, 2) . '</td></tr>';
    }
    if ($num_below5_e > 0) {
        $html .= '<tr><td class="lbl">Children Below 5 (' . $num_below5_e . ' pax) <span style="background:#d1fae5;color:#065f46;font-size:11px;padding:1px 7px;border-radius:50px;font-weight:700;">FREE</span></td><td class="val" style="color:#059669;">&#8369;0.00</td></tr>';
    }
    if ($num_pwd_e > 0) {
        $pwd_total_e = $num_pwd_e * $rate_pwd;
        $html .= '<tr><td class="lbl">PWD/Seniors (' . $num_pwd_e . ' × &#8369;' . number_format($rate_pwd, 2) . '/pax) <span style="background:#fef3c7;color:#92400e;font-size:11px;padding:1px 7px;border-radius:50px;font-weight:700;">20% OFF</span></td><td class="val">&#8369;' . number_format($pwd_total_e, 2) . '</td></tr>';
    }
    if ($addons_html) {
        $html .= '<tr style="background:#f0faf4;"><td colspan="2" style="color:#1a3d2b;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.8px;padding:8px 0;">Add-ons</td></tr>';
        $html .= $addons_html;
        $html .= '<tr><td class="lbl" style="color:#1a3d2b;font-weight:600;">Add-ons Subtotal</td><td class="val" style="color:#1a3d2b;">&#8369;' . number_format($addon_total, 2) . '</td></tr>';
    }
    if ($vat_val_e > 0) {
        $subtotal_e = floatval($data['total_price']) - $vat_val_e;
        $html .= '<tr><td class="lbl">Subtotal (before VAT)</td><td class="val">&#8369;' . number_format($subtotal_e, 2) . '</td></tr>';
        $html .= '<tr><td class="lbl">VAT (12%)</td><td class="val">&#8369;' . number_format($vat_val_e, 2) . '</td></tr>';
    }
    $html .= '<tr style="border-top:2px solid #1a3d2b;"><td class="lbl" style="font-size:15px;font-weight:800;color:#1a1a1a;padding-top:12px;">Total Amount (VAT Inclusive)</td><td class="val" style="font-size:15px;font-weight:800;color:#1a3d2b;padding-top:12px;">&#8369;' . $total . '</td></tr>';
    $html .= '</table>';

    // GCash payment summary (grouped by reference_number across all booking IDs)
    $gpay_html = ''; $gpaid_e = 0.0;
    $email_bid_list = [];
    if (!empty($data['booking_ids']) && is_array($data['booking_ids'])) {
        $email_bid_list = array_map('intval', $data['booking_ids']);
    } elseif ($bid_int > 0) {
        $email_bid_list = [$bid_int];
    }
    if (!empty($email_bid_list)) {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            $ref_groups_e = [];
            $all_payments_e = [];
            foreach ($email_bid_list as $ebid) {
                $pq = $conn->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY paid_at ASC");
                if ($pq) {
                    $pq->bind_param("i", $ebid);
                    $pq->execute();
                    $pres = $pq->get_result();
                    while ($pr = $pres->fetch_assoc()) {
                        $ref_key = trim($pr['reference_number']);
                        if ($ref_key !== '') {
                            if (!isset($ref_groups_e[$ref_key])) {
                                $ref_groups_e[$ref_key] = $pr;
                            } else {
                                $ref_groups_e[$ref_key]['amount_paid'] = floatval($ref_groups_e[$ref_key]['amount_paid']) + floatval($pr['amount_paid']);
                                if ($pr['status'] === 'completed') {
                                    $ref_groups_e[$ref_key]['status'] = 'completed';
                                }
                            }
                        } else {
                            $all_payments_e[] = $pr;
                        }
                    }
                    $pq->close();
                }
            }
            foreach ($ref_groups_e as $grouped_p) {
                $all_payments_e[] = $grouped_p;
            }
            foreach ($all_payments_e as $pr) {
                $gpaid_e += floatval($pr['amount_paid']);
                $pst = $pr['status'] === 'completed'
                    ? '<span style="background:#d1fae5;color:#065f46;font-size:11px;padding:2px 9px;border-radius:50px;font-weight:700;border:1px solid #a7f3d0;">&#10003; Payment Received</span>'
                    : '<span style="background:#fff8e1;color:#92400e;font-size:11px;padding:2px 9px;border-radius:50px;font-weight:700;border:1px solid #fde68a;">&#9203; Payment Pending</span>';
                $gpay_html .= '<tr><td class="lbl">GCash Payment</td><td class="val" style="color:#059669;">&#8369;' . number_format($pr['amount_paid'], 2) . '</td></tr>';
                $gpay_html .= '<tr><td class="lbl">Reference No.</td><td class="val" style="font-family:monospace;">' . htmlspecialchars($pr['reference_number']) . '</td></tr>';
                $gpay_html .= '<tr><td class="lbl">Status</td><td class="val">' . $pst . '</td></tr>';
            }
        }
    }
    if ($gpay_html) {
        $grem_e = floatval($data['total_price']) - $gpaid_e;
        $html .= '<div class="sec">GCash Payment</div><table class="dt">' . $gpay_html . '</table>';
        $bcol = $grem_e <= 0 ? '#d4edda;color:#155724;border:1px solid #c3e6cb;' : '#fff8e1;color:#92400e;border:1px solid #ffe082;';
        $blbl = $grem_e <= 0 ? '&#10003; Fully Paid — No Balance Due' : 'Remaining Balance: &#8369;' . number_format($grem_e,2) . ' (Pay at Resort)';
        $html .= '<div style="background:#' . $bcol . 'border-radius:8px;padding:12px 16px;margin-top:12px;font-weight:700;font-size:14px;">' . $blbl . '</div>';
    }

    // Total box
    $html .= '<div class="total-box"><span class="tl">Total Amount</span><span class="tv">&#8369;' . $total . '</span><span class="tn">Payment due upon arrival</span></div>';

    // Reminders
    $html .= '<div class="remind"><h4>📋 Important Reminders</h4><ul>';
    $html .= '<li>Proceed to the <strong>ticketing office</strong> upon arrival to complete payment and claim your wristband.</li>';
    $html .= '<li>If paying via <strong>GCash</strong>, please contact us or message our Facebook page during office hours.</li>';
    $html .= '<li>Please bring a <strong>valid ID</strong> on the day of your visit.</li>';
    $html .= '<li><strong>Cancellation Policy:</strong> NO refunds upon cancellation.</li>';
    $html .= '<li>If you need to modify or cancel, please contact us at least <strong>24 hours in advance</strong>.</li>';
    $html .= '</ul></div>';

    $html .= '<p style="font-size:13px;color:#6b7280;">Questions? Contact us at <a href="mailto:bucod.lyngemae123@gmail.com" style="color:#1a3d2b;">bucod.lyngemae123@gmail.com</a>.</p>';
    $html .= '</div>';
    $html .= '<div class="ftr"><p><strong style="color:#fff;">Sinulom Falls &amp; Bolao Cold Spring Resort</strong></p>';
    $html .= '<p>Tignapoloan, Cagayan de Oro City, Philippines</p>';
    $html .= '<p style="margin-top:10px;font-size:11px;">This is an automated confirmation. Please do not reply directly.</p></div>';
    $html .= '</div></body></html>';

    return $html;
}

function generateDeclinedEmailBody($data) {
    $booking_id = str_pad($data['id'], 6, '0', STR_PAD_LEFT);
    $check_in = date('F d, Y', strtotime($data['check_in_date']));
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
            .container { max-width: 600px; margin: 20px auto; background: white; }
            .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .header .icon { font-size: 60px; margin-bottom: 15px; }
            .content { padding: 40px 30px; }
            .booking-info { background: #f9f9f9; padding: 25px; margin: 25px 0; border-radius: 8px; border-left: 5px solid #dc3545; }
            .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e0e0e0; }
            .info-row:last-child { border-bottom: none; }
            .label { color: #666; font-weight: 600; }
            .value { color: #333; font-weight: 500; }
            .alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px; color: #721c24; }
            .alert-info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 20px 0; border-radius: 4px; color: #0c5460; }
            .footer { background: #333; color: #999; padding: 30px; text-align: center; font-size: 14px; }
            .footer strong { color: white; }
            .button { display: inline-block; background: #17a2b8; color: white; padding: 15px 35px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div style="margin-bottom:12px;"><img src="cid:resort_logo" alt="Sinulom Falls &amp; Bolao Cold Spring Resort" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:#ffffff;display:inline-block;"></div>
                <div class="icon">ℹ️</div>
                <h1>Booking Update</h1>
                <p style="margin: 10px 0 0 0; font-size: 16px;">Regarding your reservation</p>
            </div>
            
            <div class="content">
                <div class="alert-danger">
                    <strong>We apologize!</strong> Unfortunately, we are unable to confirm your booking at this time. This may be due to facility unavailability or other scheduling conflicts.
                </div>
                
                <h2 style="color: #721c24; margin-top: 30px;">Booking Details</h2>
                <p>Dear <strong>' . htmlspecialchars($data['guest_name']) . '</strong>,</p>
                
                <div class="booking-info">
                    <div class="info-row">
                        <span class="label">Booking ID:</span>
                        <span class="value">#' . $booking_id . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Status:</span>
                        <span class="value" style="color: #dc3545; font-weight: bold;">✗ DECLINED</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Booking Mode:</span>
                        <span class="value">' . ucfirst($data['mode']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Facility:</span>
                        <span class="value">' . htmlspecialchars($data['facility_name']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Requested Date:</span>
                        <span class="value">' . $check_in . '</span>
                    </div>
                </div>
                
                <div class="alert-info">
                    <h3 style="margin-top: 0;">💡 What You Can Do</h3>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Try booking for different dates</li>
                        <li>Choose an alternative facility</li>
                        <li>Contact us directly for assistance: <strong>+63 123 456 7890</strong></li>
                        <li>Visit our website to check availability</li>
                    </ul>
                </div>
                
                <p style="text-align: center; margin: 30px 0;">
                    <a href="http://localhost/capstone/public_booking.php" class="button">Make Another Booking</a>
                </p>
                
                <p style="margin-top: 30px;">We sincerely apologize for any inconvenience. We value your interest in Sinulom Falls and Bolao Cold Spring and hope to serve you in the future.</p>
                
                <p style="margin-top: 20px;"><strong>Questions?</strong> Feel free to contact us at bucod.lyngemae123@gmail.com or call +63 123 456 7890.</p>
            </div>
            
            
        </div>
    </body>
    </html>';
    
    return $html;
}

function generateCancelledEmailBody($data) {
    $booking_id = str_pad($data['id'], 6, '0', STR_PAD_LEFT);
    $check_in   = date('F d, Y', strtotime($data['check_in_date']));
    $name       = htmlspecialchars($data['guest_name'] ?? '');
    $facility   = htmlspecialchars($data['facility_name'] ?? 'N/A');
    $area       = htmlspecialchars($data['area_name'] ?? 'N/A');
    $mode       = ucfirst($data['mode'] ?? 'N/A');
    $now        = date('F d, Y h:i A');

    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
    $html .= 'body{font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;}';
    $html .= '.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}';
    $html .= '.hdr{background:#6b7280;padding:32px 36px;text-align:center;color:#fff;}';
    $html .= '.hdr-icon{font-size:48px;margin-bottom:12px;}';
    $html .= '.hdr h1{font-size:22px;margin:0 0 4px;font-weight:700;}';
    $html .= '.hdr p{font-size:13px;color:rgba(255,255,255,.75);margin:4px 0 0;}';
    $html .= '.body{padding:32px 36px;}';
    $html .= '.greeting{font-size:16px;color:#1a1a1a;margin-bottom:8px;}';
    $html .= '.sub{font-size:14px;color:#6b7280;margin-bottom:24px;line-height:1.6;}';
    $html .= '.ref-box{background:#f5f5f5;border:1px solid #e0e0e0;border-radius:10px;padding:14px 20px;margin-bottom:24px;}';
    $html .= '.ref-id{font-size:20px;font-weight:800;color:#374151;}';
    $html .= '.ref-status{display:inline-block;background:#fdecea;color:#c62828;border:1px solid #f5c6cb;border-radius:50px;padding:3px 12px;font-size:12px;font-weight:700;margin-top:6px;}';
    $html .= '.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin:20px 0 10px;border-bottom:1px solid #f0f0f0;padding-bottom:6px;}';
    $html .= 'table.dt{width:100%;border-collapse:collapse;}';
    $html .= 'table.dt td{padding:9px 0;border-bottom:1px solid #f5f0e8;font-size:14px;}';
    $html .= 'table.dt .lbl{color:#6b7280;width:45%;}';
    $html .= 'table.dt .val{color:#1a1a1a;font-weight:600;text-align:right;}';
    $html .= '.info-box{background:#fff8e1;border-left:4px solid #ffc107;border-radius:6px;padding:16px 20px;margin-bottom:24px;}';
    $html .= '.info-box h4{font-size:13px;font-weight:700;color:#856404;margin:0 0 8px;}';
    $html .= '.info-box li{font-size:13px;color:#856404;margin-bottom:5px;line-height:1.5;}';
    $html .= '.ftr{background:#1a3d2b;padding:24px 36px;text-align:center;}';
    $html .= '.ftr p{color:rgba(255,255,255,.65);font-size:12px;margin:4px 0;}';
    $html .= '</style></head><body>';
    $html .= '<div class="wrap">';
    $html .= '<div class="hdr"><div style="margin-bottom:12px;"><img src="cid:resort_logo" alt="Sinulom Falls &amp; Bolao Cold Spring Resort" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:#ffffff;display:inline-block;"></div><div class="hdr-icon">❌</div><h1>Booking Cancelled</h1>';
    $html .= '<p>Your booking has been cancelled as requested</p>';
    $html .= '<p style="font-size:11px;color:rgba(255,255,255,.5);">Processed: ' . $now . '</p></div>';
    $html .= '<div class="body">';
    $html .= '<p class="greeting">Dear <strong>' . $name . '</strong>,</p>';
    $html .= '<p class="sub">Your booking at <strong>Sinulom Falls &amp; Bolao Cold Spring Resort</strong> has been successfully cancelled. We\'re sorry to see you go!</p>';
    $html .= '<div class="ref-box"><div class="ref-id">Booking #' . $booking_id . '</div><div class="ref-status">✗ CANCELLED</div></div>';
    $html .= '<div class="sec">Cancelled Booking Details</div>';
    $html .= '<table class="dt">';
    $html .= '<tr><td class="lbl">Booking Mode</td><td class="val">' . $mode . '</td></tr>';
    $html .= '<tr><td class="lbl">Location</td><td class="val">' . $area . '</td></tr>';
    $html .= '<tr><td class="lbl">Facility</td><td class="val">' . $facility . '</td></tr>';
    $html .= '<tr><td class="lbl">Check-in Date</td><td class="val">' . $check_in . '</td></tr>';
    $html .= '</table>';
    $html .= '<div class="info-box"><h4>💡 Want to book again?</h4><ul>';
    $html .= '<li>You can make a new booking anytime at our website.</li>';
    $html .= '<li>Check our available facilities and choose a date that works for you.</li>';
    $html .= '<li>For assistance, contact us at <strong>bucod.lyngemae123@gmail.com</strong>.</li>';
    $html .= '</ul></div>';
    $html .= '<p style="font-size:13px;color:#6b7280;">We hope to welcome you at Sinulom Falls &amp; Bolao Cold Spring Resort soon!</p>';
    $html .= '</div>';
    $html .= '<div class="ftr"><p><strong style="color:#fff;">Sinulom Falls &amp; Bolao Cold Spring Resort</strong></p>';
    $html .= '<p>Tignapoloan, Cagayan de Oro City, Philippines</p>';
    $html .= '<p style="margin-top:10px;font-size:11px;">This is an automated notification. Please do not reply directly.</p></div>';
    $html .= '</div></body></html>';

    return $html;
}

function sendCheckoutThankYouEmail($booking_data) {
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

        // Embed resort profile logo
        $logo_path = __DIR__ . '/../images/logo.jpg';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'resort_logo', 'logo.jpg');
        }

        $mail->isHTML(true);
        $booking_id = str_pad($booking_data['id'], 6, '0', STR_PAD_LEFT);
        $mail->Subject = 'Thank You for Staying with Us! — Sinulom & Bolao Cold Spring Resort #' . $booking_id;

        $name     = htmlspecialchars($booking_data['guest_name']    ?? '');
        $facility = htmlspecialchars($booking_data['facility_name'] ?? 'N/A');
        $area     = htmlspecialchars($booking_data['area_name']     ?? 'N/A');
        $phone    = htmlspecialchars($booking_data['guest_phone']   ?? '');
        $adults   = intval($booking_data['num_adults']   ?? 0);
        $children = intval($booking_data['num_children'] ?? 0);
        $mode     = $booking_data['mode'] ?? 'daytour';
        $checkin  = date('F d, Y', strtotime($booking_data['check_in_date']));
        $checkout = ($mode === 'daytour') ? 'Same Day' : date('F d, Y', strtotime($booking_data['check_out_date'] ?? $booking_data['check_in_date']));
        $total    = number_format($booking_data['total_price'] ?? 0, 2);
        $now      = date('F d, Y h:i A');

        // Price breakdown
        $area_lc    = strtolower($booking_data['area_name'] ?? '');
        $isBoth     = strpos($area_lc,'both')!==false || (strpos($area_lc,'sinulom')!==false && strpos($area_lc,'bolao')!==false);
        $rate_adult = $isBoth ? 160 : 110;
        $rate_child = $isBoth ? 85  : 60;
        $rate_pwd_c = round($rate_adult * 0.80, 2);
        $fac_price  = floatval($booking_data['facility_price'] ?? 0);
        $nights = 1;
        if ($mode === 'overnight' && !empty($booking_data['check_in_date']) && !empty($booking_data['check_out_date']) && $booking_data['check_out_date'] > $booking_data['check_in_date']) {
            $nights = max(1, (int)((strtotime($booking_data['check_out_date']) - strtotime($booking_data['check_in_date'])) / 86400));
        }
        $fac_total    = $fac_price * $nights;
        $adult_total  = $adults   * $rate_adult;
        $child_total  = $children * $rate_child;
        $nights_label = $nights > 1 ? ' × ' . $nights . ' night' . ($nights > 1 ? 's' : '') : '';
        $num_below5_c = 0; $num_pwd_c = 0; $vat_val_c = 0.0;
        if (!empty($booking_data['notes'])) {
            if (preg_match('/Below5:\s*(\d+)/i', $booking_data['notes'], $mx)) $num_below5_c = intval($mx[1]);
            if (preg_match('/PWD:\s*(\d+)/i',    $booking_data['notes'], $mx)) $num_pwd_c    = intval($mx[1]);
            if (preg_match('/VAT:\s*([\d.]+)/i', $booking_data['notes'], $mx)) $vat_val_c    = floatval($mx[1]);
        }

        // Add-ons
        $addons_html = '';
        $addon_total = 0.0;
        $bid_int = intval($booking_data['id'] ?? 0);
        if ($bid_int > 0) {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli) {
                $astmt = $conn->prepare("SELECT a.name, a.price, ba.quantity FROM booking_addons ba JOIN amenities a ON ba.amenity_id=a.id WHERE ba.booking_id=? AND ba.amenity_id IS NOT NULL");
                if ($astmt) {
                    $astmt->bind_param("i", $bid_int); $astmt->execute();
                    $ares = $astmt->get_result();
                    while ($ar = $ares->fetch_assoc()) {
                        $line = floatval($ar['price']) * intval($ar['quantity'] ?? 1);
                        $addon_total += $line;
                        $addons_html .= '<tr><td class="lbl">&nbsp;&nbsp;' . htmlspecialchars($ar['name']) . ' × ' . intval($ar['quantity'] ?? 1) . '</td><td class="val">&#8369;' . number_format($line, 2) . '</td></tr>';
                    }
                    $astmt->close();
                }
            }
        }

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
        $html .= 'body{font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;}';
        $html .= '.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}';
        $html .= '.hdr{background:#1a3d2b;padding:32px 36px;text-align:center;color:#fff;}';
        $html .= '.hdr-icon{font-size:52px;margin-bottom:12px;}';
        $html .= '.hdr h1{font-size:22px;margin:0 0 4px;font-weight:700;}';
        $html .= '.hdr p{font-size:13px;color:rgba(255,255,255,.75);margin:4px 0 0;}';
        $html .= '.body{padding:32px 36px;}';
        $html .= '.greeting{font-size:16px;color:#1a1a1a;margin-bottom:12px;}';
        $html .= '.sub{font-size:14px;color:#6b7280;margin-bottom:24px;line-height:1.7;}';
        $html .= '.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin:20px 0 10px;border-bottom:1px solid #f0f0f0;padding-bottom:6px;}';
        $html .= 'table.dt{width:100%;border-collapse:collapse;}';
        $html .= 'table.dt td{padding:9px 0;border-bottom:1px solid #f5f0e8;font-size:14px;}';
        $html .= 'table.dt .lbl{color:#6b7280;width:55%;}';
        $html .= 'table.dt .val{color:#1a1a1a;font-weight:600;text-align:right;}';
        $html .= '.breakdown-hdr td{background:#f0faf4;color:#1a3d2b;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.8px;padding:8px 0 !important;border-bottom:none !important;}';
        $html .= '.total-row td{border-top:2px solid #1a3d2b !important;border-bottom:none !important;padding-top:12px !important;}';
        $html .= '.ref-box{background:#f0faf4;border:1px solid #c8e6c9;border-radius:10px;padding:14px 20px;margin-bottom:24px;}';
        $html .= '.ref-id{font-size:20px;font-weight:800;color:#1a3d2b;}';
        $html .= '.ref-status{display:inline-block;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:50px;padding:3px 12px;font-size:12px;font-weight:700;margin-top:6px;}';
        $html .= '.ftr{background:#1a3d2b;padding:24px 36px;text-align:center;}';
        $html .= '.ftr p{color:rgba(255,255,255,.65);font-size:12px;margin:4px 0;}';
        $html .= '</style></head><body>';
        $html .= '<div class="wrap">';
        $html .= '<div class="hdr"><div style="margin-bottom:12px;"><img src="cid:resort_logo" alt="Sinulom Falls &amp; Bolao Cold Spring Resort" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:#ffffff;display:inline-block;"></div><div class="hdr-icon">&#127807;</div><h1>Thank You for Staying with Us!</h1>';
        $html .= '<p>We hope you had a wonderful experience</p>';
        $html .= '<p style="font-size:11px;color:rgba(255,255,255,.5);">Checked out: ' . $now . '</p></div>';
        $html .= '<div class="body">';
        $html .= '<p class="greeting">Dear <strong>' . $name . '</strong>,</p>';
        $html .= '<p class="sub">Thank you for choosing <strong>Sinulom Falls &amp; Bolao Cold Spring Resort</strong> for your getaway! It was our pleasure to host you. We hope you enjoyed the refreshing cold springs and the natural beauty of our resort.</p>';

        $html .= '<div class="ref-box"><div class="ref-id">Booking #' . $booking_id . '</div><div class="ref-status">&#10003; COMPLETED</div></div>';

        // Visit summary
        $html .= '<div class="sec">Visit Summary</div>';
        $html .= '<table class="dt">';
        $html .= '<tr><td class="lbl">Facility</td><td class="val">' . $facility . '</td></tr>';
        $html .= '<tr><td class="lbl">Location</td><td class="val">' . $area . '</td></tr>';
        $html .= '<tr><td class="lbl">Booking Mode</td><td class="val">' . ucfirst($mode) . '</td></tr>';
        $html .= '<tr><td class="lbl">Check-in</td><td class="val">' . $checkin . '</td></tr>';
        $html .= '<tr><td class="lbl">Check-out</td><td class="val">' . $checkout . '</td></tr>';
        $html .= '<tr><td class="lbl">Adults</td><td class="val">' . $adults . '</td></tr>';
        if ($children > 0) {
            $html .= '<tr><td class="lbl">Children</td><td class="val">' . $children . '</td></tr>';
        }
        $html .= '</table>';

        // Price breakdown
        $html .= '<div class="sec">Payment Summary</div>';
        $html .= '<table class="dt">';
        if ($fac_price > 0) {
            $fac_label = 'Facility (' . $facility . ')' . ($nights > 1 ? $nights_label : '');
            $html .= '<tr><td class="lbl">' . $fac_label . '</td><td class="val">&#8369;' . number_format($fac_total, 2) . '</td></tr>';
        }
        $html .= '<tr><td class="lbl">Adults (' . $adults . ' × &#8369;' . number_format($rate_adult, 2) . '/pax)</td><td class="val">&#8369;' . number_format($adult_total, 2) . '</td></tr>';
        if ($children > 0) {
            $html .= '<tr><td class="lbl">Children Age 5+ (' . $children . ' × &#8369;' . number_format($rate_child, 2) . '/pax)</td><td class="val">&#8369;' . number_format($child_total, 2) . '</td></tr>';
        }
        if ($num_below5_c > 0) {
            $html .= '<tr><td class="lbl">Children Below 5 (' . $num_below5_c . ' pax) <span style="background:#d1fae5;color:#065f46;font-size:11px;padding:1px 7px;border-radius:50px;font-weight:700;">FREE</span></td><td class="val" style="color:#059669;">&#8369;0.00</td></tr>';
        }
        if ($num_pwd_c > 0) {
            $pwd_total_c = $num_pwd_c * $rate_pwd_c;
            $html .= '<tr><td class="lbl">PWD/Seniors (' . $num_pwd_c . ' × &#8369;' . number_format($rate_pwd_c,2) . '/pax) <span style="background:#fef3c7;color:#92400e;font-size:11px;padding:1px 7px;border-radius:50px;font-weight:700;">20% OFF</span></td><td class="val">&#8369;' . number_format($pwd_total_c,2) . '</td></tr>';
        }
        if ($addons_html) {
            $html .= '<tr class="breakdown-hdr"><td colspan="2">Add-ons</td></tr>';
            $html .= $addons_html;
            $html .= '<tr><td class="lbl" style="color:#1a3d2b;font-weight:600;">Add-ons Subtotal</td><td class="val" style="color:#1a3d2b;">&#8369;' . number_format($addon_total, 2) . '</td></tr>';
        }
        if ($vat_val_c > 0) {
            $subtotal_c = floatval($booking_data['total_price']) - $vat_val_c;
            $html .= '<tr><td class="lbl">Subtotal (before VAT)</td><td class="val">&#8369;' . number_format($subtotal_c,2) . '</td></tr>';
            $html .= '<tr><td class="lbl">VAT (12%)</td><td class="val">&#8369;' . number_format($vat_val_c,2) . '</td></tr>';
        }
        $html .= '<tr class="total-row"><td class="lbl" style="font-size:15px;font-weight:800;color:#1a1a1a;">Total Amount (VAT Inclusive)</td><td class="val" style="font-size:15px;font-weight:800;color:#1a3d2b;">&#8369;' . $total . '</td></tr>';
        $html .= '</table>';

        // GCash payment summary (grouped by reference number)
        $gpay_c = ''; $gpaid_c = 0.0;
        $email_bid_list = [];
        if (!empty($booking_data['booking_ids']) && is_array($booking_data['booking_ids'])) {
            $email_bid_list = array_map('intval', $booking_data['booking_ids']);
        } elseif ($bid_int > 0) {
            $email_bid_list = [$bid_int];
        }
        if (!empty($email_bid_list)) {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli) {
                $ref_groups_c = [];
                $all_payments_c = [];
                foreach ($email_bid_list as $ebid) {
                    $pqc = $conn->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY paid_at ASC");
                    if ($pqc) {
                        $pqc->bind_param("i", $ebid);
                        $pqc->execute();
                        $presc = $pqc->get_result();
                        while ($prc = $presc->fetch_assoc()) {
                            $ref_key = trim($prc['reference_number']);
                            if ($ref_key !== '') {
                                if (!isset($ref_groups_c[$ref_key])) {
                                    $ref_groups_c[$ref_key] = $prc;
                                } else {
                                    $ref_groups_c[$ref_key]['amount_paid'] = floatval($ref_groups_c[$ref_key]['amount_paid']) + floatval($prc['amount_paid']);
                                    if ($prc['status'] === 'completed') {
                                        $ref_groups_c[$ref_key]['status'] = 'completed';
                                    }
                                }
                            } else {
                                $all_payments_c[] = $prc;
                            }
                        }
                        $pqc->close();
                    }
                }
                foreach ($ref_groups_c as $grouped_p) {
                    $all_payments_c[] = $grouped_p;
                }
                foreach ($all_payments_c as $prc) {
                    $gpaid_c += floatval($prc['amount_paid']);
                    $pstc = $prc['status'] === 'completed'
                        ? '<span style="background:#d1fae5;color:#065f46;font-size:11px;padding:2px 9px;border-radius:50px;font-weight:700;border:1px solid #a7f3d0;">&#10003; Payment Received</span>'
                        : '<span style="background:#fff8e1;color:#92400e;font-size:11px;padding:2px 9px;border-radius:50px;font-weight:700;border:1px solid #fde68a;">&#9203; Payment Pending</span>';
                    $gpay_c .= '<tr><td class="lbl">GCash Payment</td><td class="val" style="color:#059669;">&#8369;' . number_format($prc['amount_paid'], 2) . '</td></tr>';
                    $gpay_c .= '<tr><td class="lbl">Reference No.</td><td class="val" style="font-family:monospace;">' . htmlspecialchars($prc['reference_number']) . '</td></tr>';
                    $gpay_c .= '<tr><td class="lbl">Status</td><td class="val">' . $pstc . '</td></tr>';
                }
            }
        }
        if ($gpay_c) {
            $gremc = floatval($booking_data['total_price']) - $gpaid_c;
            $html .= '<div class="sec">GCash Payment</div><table class="dt">' . $gpay_c . '</table>';
            $bcolc = $gremc <= 0 ? '#d4edda;color:#155724;border:1px solid #c3e6cb;' : '#fff8e1;color:#92400e;border:1px solid #ffe082;';
            $blblc = $gremc <= 0 ? '&#10003; Fully Paid — No Balance Due' : 'Remaining Balance: &#8369;' . number_format($gremc,2) . ' (Pay at Resort)';
            $html .= '<div style="background:#' . $bcolc . 'border-radius:8px;padding:12px 16px;margin-top:12px;font-weight:700;font-size:14px;">' . $blblc . '</div>';
        }



        // ── Feedback CTA ──
        $feedback_url = 'http://localhost/capstone/capstone/submit_feedback.php?booking_id=' . intval($booking_data['id']);
        $html .= '<div style="background:linear-gradient(135deg,#f0faf4 0%,#e8f5e9 100%);border:1.5px solid #c8e6c9;border-radius:14px;padding:24px 28px;margin:24px 0;text-align:center;">';
        $html .= '<div style="font-size:28px;margin-bottom:10px;">⭐</div>';
        $html .= '<h3 style="font-size:17px;font-weight:800;color:#1a3d2b;margin:0 0 8px;">How Was Your Experience?</h3>';
        $html .= '<p style="font-size:13px;color:#4b7c5e;line-height:1.6;margin:0 0 18px;">Your feedback helps us improve and serve future guests better. It only takes a minute!</p>';
        $html .= '<a href="' . $feedback_url . '" style="display:inline-block;background:#1a3d2b;color:#fff;text-decoration:none;padding:13px 32px;border-radius:50px;font-size:14px;font-weight:700;letter-spacing:.3px;box-shadow:0 4px 14px rgba(26,61,43,.35);">&#9733; Leave a Review</a>';
        $html .= '<p style="font-size:11px;color:#9ca3af;margin:12px 0 0;">Click the button above to share your rating and comments.</p>';
        $html .= '</div>';

        $html .= '<p style="font-size:13px;color:#6b7280;">We look forward to welcoming you back soon. For questions, contact us at <a href="mailto:bucod.lyngemae123@gmail.com" style="color:#1a3d2b;">bucod.lyngemae123@gmail.com</a>.</p>';
        $html .= '</div>';
        $html .= '<div class="ftr"><p><strong style="color:#fff;">Sinulom Falls &amp; Bolao Cold Spring Resort</strong></p>';
        $html .= '<p>Tignapoloan, Cagayan de Oro City, Philippines</p>';
        $html .= '<p style="margin-top:10px;font-size:11px;">This is an automated message. Please do not reply directly.</p></div>';
        $html .= '</div></body></html>';

        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Checkout email error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendPaymentCorrectionEmail($booking_data, $payment_data) {
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
        $mail->isHTML(true);

        $booking_id    = str_pad($booking_data['id'], 6, '0', STR_PAD_LEFT);
        $mail->Subject = 'Payment Amount Updated — Booking #' . $booking_id . ' | Sinulom & Bolao Cold Spring Resort';

        $name        = htmlspecialchars($booking_data['guest_name'] ?? '');
        $facility    = htmlspecialchars($booking_data['facility_name'] ?? 'N/A');
        $area        = htmlspecialchars($booking_data['area_name'] ?? 'N/A');
        $checkin     = date('F d, Y', strtotime($booking_data['check_in_date']));
        $new_amount  = number_format(floatval($payment_data['new_amount']), 2);
        $old_amount  = number_format(floatval($payment_data['old_amount']), 2);
        $ref_no      = htmlspecialchars($payment_data['reference_number'] ?? 'N/A');
        $total       = number_format(floatval($booking_data['total_price']), 2);
        $remaining   = number_format(max(0, floatval($booking_data['total_price']) - floatval($payment_data['new_amount'])), 2);
        $now         = date('F d, Y h:i A');

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
        $html .= 'body{font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;}';
        $html .= '.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}';
        $html .= '.hdr{background:#1a3d2b;padding:32px 36px;text-align:center;color:#fff;}';
        $html .= '.hdr-icon{font-size:48px;margin-bottom:12px;}';
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
        $html .= '.alert-box{background:#fff8e1;border-left:4px solid #ffc107;border-radius:6px;padding:16px 20px;margin:20px 0;}';
        $html .= '.alert-box h4{font-size:13px;font-weight:700;color:#856404;margin:0 0 6px;}';
        $html .= '.alert-box p{font-size:13px;color:#856404;margin:0;line-height:1.6;}';
        $html .= '.change-box{background:#f0faf4;border:1.5px solid #c8e6c9;border-radius:10px;padding:16px 20px;margin:20px 0;display:flex;justify-content:space-between;align-items:center;}';
        $html .= '.change-old{text-decoration:line-through;color:#9ca3af;font-size:13px;}';
        $html .= '.change-arrow{font-size:20px;color:#1a3d2b;font-weight:700;}';
        $html .= '.change-new{color:#1a3d2b;font-size:18px;font-weight:800;}';
        $html .= '.ftr{background:#1a3d2b;padding:24px 36px;text-align:center;}';
        $html .= '.ftr p{color:rgba(255,255,255,.65);font-size:12px;margin:4px 0;}';
        $html .= '</style></head><body>';
        $html .= '<div class="wrap">';
        $html .= '<div class="hdr"><div class="hdr-icon">📝</div><h1>Payment Amount Updated</h1>';
        $html .= '<p>Your GCash payment record has been corrected by our staff</p>';
        $html .= '<p style="font-size:11px;color:rgba(255,255,255,.5);">Updated: ' . $now . '</p></div>';
        $html .= '<div class="body">';
        $html .= '<p class="greeting">Dear <strong>' . $name . '</strong>,</p>';
        $html .= '<p class="sub">Our staff has reviewed your GCash payment submission and updated the recorded payment amount based on the <strong>actual amount sent</strong> matching your reference number below.</p>';
        $html .= '<div class="ref-box"><div class="ref-id">Booking #' . $booking_id . '</div>';
        $html .= '<div class="ref-status">⚙ Payment Corrected</div></div>';

        // Amount change visual
        $html .= '<div class="sec">Payment Correction</div>';
        $html .= '<div class="change-box">';
        $html .= '<div><div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">Previous Amount</div><div class="change-old">&#8369;' . $old_amount . '</div></div>';
        $html .= '<div class="change-arrow">→</div>';
        $html .= '<div><div style="font-size:11px;color:#1a3d2b;margin-bottom:2px;">Corrected Amount</div><div class="change-new">&#8369;' . $new_amount . '</div></div>';
        $html .= '</div>';

        // Payment details
        $html .= '<div class="sec">Payment Details</div>';
        $html .= '<table class="dt">';
        $html .= '<tr><td class="lbl">GCash Reference No.</td><td class="val" style="font-family:monospace;">' . $ref_no . '</td></tr>';
        $html .= '<tr><td class="lbl">Corrected Amount Paid</td><td class="val" style="color:#059669;">&#8369;' . $new_amount . '</td></tr>';
        $html .= '<tr><td class="lbl">Total Booking Amount</td><td class="val">&#8369;' . $total . '</td></tr>';
        $html .= '<tr><td class="lbl">Remaining Balance</td><td class="val" style="color:' . (floatval($payment_data['new_amount']) >= floatval($booking_data['total_price']) ? '#059669' : '#e65100') . ';">' . (floatval($payment_data['new_amount']) >= floatval($booking_data['total_price']) ? '&#10003; Fully Paid' : '&#8369;' . $remaining) . '</td></tr>';
        $html .= '</table>';

        // Booking details
        $html .= '<div class="sec">Booking Details</div>';
        $html .= '<table class="dt">';
        $html .= '<tr><td class="lbl">Facility</td><td class="val">' . $facility . '</td></tr>';
        $html .= '<tr><td class="lbl">Location</td><td class="val">' . $area . '</td></tr>';
        $html .= '<tr><td class="lbl">Check-in Date</td><td class="val">' . $checkin . '</td></tr>';
        $html .= '</table>';

        $html .= '<div class="alert-box"><h4>&#8505; What This Means</h4>';
        $html .= '<p>This is purely a correction to the payment amount we recorded. If the remaining balance is greater than zero, it will need to be settled upon your arrival at the resort. Please contact us if you have any concerns.</p></div>';

        $html .= '<p style="font-size:13px;color:#6b7280;">Questions? Contact us at <a href="mailto:bucod.lyngemae123@gmail.com" style="color:#1a3d2b;">bucod.lyngemae123@gmail.com</a>.</p>';
        $html .= '</div>';
        $html .= '<div class="ftr"><p><strong style="color:#fff;">Sinulom Falls &amp; Bolao Cold Spring Resort</strong></p>';
        $html .= '<p>Tignapoloan, Cagayan de Oro City, Philippines</p>';
        $html .= '<p style="margin-top:10px;font-size:11px;">This is an automated notification. Please do not reply directly.</p></div>';
        $html .= '</div></body></html>';

        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Payment correction email error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
