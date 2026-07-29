<?php
session_start();
require_once 'config/db_config.php';

$is_logged_in = !empty($_SESSION['guest_logged_in']);
$cart = $_SESSION['cart'] ?? [];

// Handle remove action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $rid = $_POST['remove_id'];
    $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'] ?? [], fn($i) => $i['id'] !== $rid));
    header("Location: view_cart.php");
    exit();
}

// Handle clear all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    $_SESSION['cart'] = [];
    header("Location: view_cart.php");
    exit();
}

$cart = $_SESSION['cart'] ?? [];
$cart_count = count($cart);

function get_item_price($item) {
    $price = floatval($item['total_price'] ?? 0);
    if ($price <= 0) {
        $facility_price = floatval($item['facility_price'] ?? 0);
        $mode = $item['mode'] ?? 'daytour';
        $nights = 1;
        $check_in = $item['check_in'] ?? $item['check_in_date'] ?? '';
        $check_out = $item['check_out'] ?? $item['check_out_date'] ?? '';
        if ($mode === 'overnight' && !empty($check_in) && !empty($check_out)) {
            $d1 = DateTime::createFromFormat('Y-m-d', $check_in);
            $d2 = DateTime::createFromFormat('Y-m-d', $check_out);
            if ($d1 && $d2) {
                $nights = max(1, (int)$d1->diff($d2)->days);
            }
        }
        $facility_cost = $facility_price * $nights;
        $subtotal = $facility_cost;
        $vat = round($subtotal * 0.12, 2);
        $price = $subtotal + $vat;
    }
    return $price;
}

// Calculate totals
$grand_total = 0;
foreach ($cart as $item) {
    $grand_total += get_item_price($item);
}

function fmt_price($p) {
    return '₱' . number_format(floatval($p), 2);
}
function fmt_date($d) {
    if (empty($d)) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('M j, Y') : $d;
}
function fmt_mode($m, $slot) {
    if ($m === 'overnight') return 'Overnight';
    $map = ['8am-12pm'=>'Morning (8AM–12PM)', '12pm-5pm'=>'Afternoon (12PM–5PM)', 'full_day'=>'Full Day'];
    return $map[$slot] ?? ucfirst($m);
}
function fmt_guests($item) {
    $parts = [];
    $num_adults = !empty($item['num_adults']) ? intval($item['num_adults']) : 1;
    $num_children = !empty($item['num_children']) ? intval($item['num_children']) : 0;
    $num_below5 = !empty($item['num_below5']) ? intval($item['num_below5']) : 0;
    $num_discounted = !empty($item['num_discounted']) ? intval($item['num_discounted']) : (!empty($item['num_pwd']) ? intval($item['num_pwd']) : 0);
    
    if ($num_adults > 0)    $parts[] = $num_adults   . ' Adult' . ($num_adults>1?'s':'');
    if ($num_children > 0)  $parts[] = $num_children . ' Child' . ($num_children>1?'ren':'');
    if ($num_below5 > 0)    $parts[] = $num_below5   . ' Below 5';
    if ($num_discounted > 0)$parts[] = $num_discounted. ' PWD/Senior';
    return implode(', ', $parts) ?: '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Cart — Sinulom & Bolao Cold Spring Resort</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --gd: #1a3d2b;
      --gm: #2d5a3d;
      --gl: #4a7c59;
      --orange: #ee4d2d;
      --orange2: #ff7337;
      --bg: #f5f5f5;
      --card: #fff;
      --border: #e8e8e8;
      --txt: #1a1a1a;
      --muted: #757575;
      --radius: 10px;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--txt); min-height:100vh; }

    /* NAVBAR */
    .nb { position:sticky; top:0; z-index:1000; background:var(--gd); box-shadow:0 2px 20px rgba(0,0,0,.25); }
    .nb-inner { width:100%; max-width:1100px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; padding:0 40px 0 10px; height:64px; }
    .nb-brand { display:flex; align-items:center; gap:12px; text-decoration:none; flex-shrink:0; }
    .nb-brand img { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,.3); display:block; flex-shrink:0; }
    .nb-brand-txt { display:flex; flex-direction:column; justify-content:center; }
    .nb-brand-txt strong { display:block; font-size:.9rem; font-weight:800; color:#fff; line-height:1.2; }
    .nb-brand-txt span { font-size:.68rem; color:rgba(255,255,255,.6); line-height:1.2; }
    .nb-actions { display:flex; align-items:center; gap:10px; flex-shrink:0; }
    .nb-back { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.12); border:1.5px solid rgba(255,255,255,.28); color:#fff; padding:7px 16px; border-radius:50px; font-size:.82rem; font-weight:600; text-decoration:none; transition:all .2s; white-space:nowrap; }
    .nb-back:hover { background:rgba(255,255,255,.22); color:#fff; }

    /* HERO */
    .cart-hero { background:var(--gd); padding:22px 24px 20px; text-align:center; }
    .cart-hero h1 { font-family:'Playfair Display',serif; font-size:clamp(1.4rem,3vw,1.9rem); color:#fff; font-weight:800; }
    .cart-hero p { color:rgba(255,255,255,.65); font-size:.82rem; margin-top:4px; }

    /* LAYOUT */
    .cart-wrap { max-width:1000px; margin:28px auto; padding:0 18px 60px; display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }
    @media(max-width:760px) { .cart-wrap { grid-template-columns:1fr; } }

    /* EMPTY STATE */
    .empty-cart { background:var(--card); border-radius:var(--radius); padding:64px 24px; text-align:center; box-shadow:0 2px 12px rgba(0,0,0,.06); grid-column:1/-1; }
    .empty-cart .ec-icon { font-size:4rem; color:#e0e0e0; margin-bottom:18px; }
    .empty-cart h2 { font-size:1.2rem; font-weight:700; color:var(--muted); margin-bottom:8px; }
    .empty-cart p { font-size:.87rem; color:#aaa; margin-bottom:24px; }
    .btn-shop { display:inline-flex; align-items:center; gap:8px; background:var(--gd); color:#fff; padding:11px 28px; border-radius:50px; font-weight:700; font-size:.9rem; text-decoration:none; transition:all .2s; }
    .btn-shop:hover { background:var(--gm); transform:translateY(-1px); }

    /* CART ITEMS PANEL */
    .cart-items-panel { display:flex; flex-direction:column; gap:14px; }

    .cart-header-bar { background:var(--card); border-radius:var(--radius); padding:14px 20px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 6px rgba(0,0,0,.05); }
    .cart-header-bar h2 { font-size:1rem; font-weight:700; display:flex; align-items:center; gap:8px; }
    .cart-header-bar h2 i { color:var(--orange); }
    .clear-all-btn { background:none; border:1.5px solid #f5c6c0; color:#e53e3e; padding:5px 14px; border-radius:50px; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .2s; display:flex; align-items:center; gap:6px; }
    .clear-all-btn:hover { background:#fff5f5; border-color:#e53e3e; }

    /* INDIVIDUAL CART ITEM CARD */
    .cart-item-card { background:var(--card); border-radius:var(--radius); box-shadow:0 1px 8px rgba(0,0,0,.06); border:1.5px solid var(--border); overflow:hidden; transition:box-shadow .2s, border-color .2s; cursor:pointer; }
    .cart-item-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.1); }
    .cart-item-card.selected { border:2px solid var(--gd); box-shadow:0 0 0 3px rgba(26,61,43,.12), 0 4px 20px rgba(0,0,0,.1); }
    .cart-item-card .select-indicator { display:flex; width:22px; height:22px; border-radius:6px; border:2px solid rgba(255,255,255,.6); background:rgba(255,255,255,.2); flex-shrink:0; align-items:center; justify-content:center; transition:all .2s; }
    .cart-item-card.selected .select-indicator { border-color:#fff; background:#fff; }
    .cart-item-card .select-indicator i { font-size:.7rem; color:var(--gd); opacity:0; transform:scale(0.5); transition:all .2s; }
    .cart-item-card.selected .select-indicator i { opacity:1; transform:scale(1); }

    .cic-top { display:flex; align-items:stretch; }
    .cic-icon-col { width:76px; min-width:76px; min-height:110px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,var(--gd),var(--gl)); flex-shrink:0; }
    .cic-icon-col i { font-size:1.7rem; color:rgba(255,255,255,.9); }

    .cic-body { flex:1; padding:14px 16px 12px; min-width:0; }
    .cic-name { font-size:.97rem; font-weight:800; color:var(--gd); margin-bottom:4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; line-height:1.3; }
    .cic-area { font-size:.72rem; color:var(--muted); font-weight:500; background:#f0f0f0; padding:2px 9px; border-radius:50px; white-space:nowrap; }
    .cic-mode-badge { display:inline-flex; align-items:center; gap:4px; font-size:.71rem; font-weight:700; padding:3px 10px; border-radius:50px; margin-top:4px; width:fit-content; }
    .cic-mode-badge.overnight { background:#dbeafe; color:#1e40af; }
    .cic-mode-badge.daytour   { background:#d1fae5; color:#065f46; }

    .cic-details { display:grid; grid-template-columns:1fr 1fr; gap:8px 14px; margin-top:10px; }
    @media(max-width:500px) { .cic-details { grid-template-columns:1fr; } }
    .cic-detail { display:flex; align-items:flex-start; gap:7px; font-size:.8rem; color:var(--muted); }
    .cic-detail i { width:15px; color:var(--gl); flex-shrink:0; margin-top:2px; text-align:center; }
    .cic-detail > div { display:flex; flex-direction:column; gap:1px; }
    .cic-detail > div div { font-size:.68rem; color:var(--muted); line-height:1.2; }
    .cic-detail strong { color:var(--txt); font-weight:600; font-size:.82rem; line-height:1.3; }

    .cic-footer { display:flex; align-items:center; justify-content:space-between; padding:10px 16px 12px 16px; border-top:1px solid var(--border); background:#fafafa; flex-wrap:wrap; gap:8px; }
    .cic-price { font-size:1.08rem; font-weight:800; color:var(--orange); display:flex; align-items:baseline; gap:4px; }
    .cic-price span { font-size:.7rem; color:var(--muted); font-weight:500; }
    .cic-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }
    .btn-edit-item { display:inline-flex; align-items:center; gap:5px; background:#f0faf4; border:1.5px solid #a7f3d0; color:var(--gd); padding:6px 13px; border-radius:50px; font-size:.77rem; font-weight:700; text-decoration:none; transition:all .2s; cursor:pointer; white-space:nowrap; }
    .btn-edit-item:hover { background:#d1fae5; }
    .btn-remove-item { display:inline-flex; align-items:center; gap:5px; background:#fff5f5; border:1.5px solid #fecaca; color:#e53e3e; padding:6px 13px; border-radius:50px; font-size:.77rem; font-weight:700; cursor:pointer; transition:all .2s; white-space:nowrap; }
    .btn-remove-item:hover { background:#fee2e2; border-color:#e53e3e; }

    /* ORDER SUMMARY PANEL */
    .order-summary { background:var(--card); border-radius:var(--radius); box-shadow:0 2px 12px rgba(0,0,0,.07); border:1.5px solid var(--border); overflow:hidden; position:sticky; top:80px; }
    .os-header { background:var(--gd); padding:14px 20px; }
    .os-header h3 { color:#fff; font-size:.95rem; font-weight:700; display:flex; align-items:center; gap:8px; }
    .os-body { padding:16px 20px; }
    .os-row { display:flex; justify-content:space-between; align-items:center; padding:7px 0; font-size:.85rem; border-bottom:1px solid #f0f0f0; }
    .os-row:last-child { border-bottom:none; }
    .os-row .lbl { color:var(--muted); }
    .os-row .val { font-weight:600; color:var(--txt); }
    .os-total { display:flex; justify-content:space-between; align-items:center; padding:14px 0 6px; margin-top:4px; border-top:2px solid var(--border); }
    .os-total .lbl { font-weight:700; font-size:.9rem; }
    .os-total .val { font-size:1.25rem; font-weight:800; color:var(--orange); }
    .os-note { font-size:.72rem; color:var(--muted); margin-top:8px; padding:8px 12px; background:#fffbeb; border-radius:8px; border-left:3px solid #f59e0b; }

    .btn-proceed { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; margin-top:16px; padding:14px; border-radius:50px; border:none; background:linear-gradient(135deg,var(--orange) 0%,var(--orange2) 100%); color:#fff; font-size:.95rem; font-weight:800; cursor:pointer; text-decoration:none; transition:all .2s; box-shadow:0 4px 18px rgba(238,77,45,.35); }
    .btn-proceed:hover { transform:translateY(-2px); box-shadow:0 8px 26px rgba(238,77,45,.45); }
    .btn-proceed-book { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; margin-top:10px; padding:13px; border-radius:50px; border:none; background:var(--gd); color:#fff; font-size:.92rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all .2s; box-shadow:0 4px 14px rgba(26,61,43,.3); }
    .btn-proceed-book:hover { background:var(--gm); transform:translateY(-1px); }
    .btn-continue { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; margin-top:8px; padding:10px; border-radius:50px; border:1.5px solid var(--border); background:#fff; color:var(--muted); font-size:.85rem; font-weight:600; text-decoration:none; transition:all .2s; }
    .btn-continue:hover { border-color:var(--gd); color:var(--gd); }

    /* ITEM COUNT CHIP */
    .count-chip { background:var(--orange); color:#fff; font-size:.7rem; font-weight:800; padding:2px 8px; border-radius:50px; margin-left:4px; }

    /* REMOVE CONFIRM OVERLAY */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; }
    .modal-overlay.show { display:flex; }
    .modal-box { background:#fff; border-radius:16px; padding:28px 28px 24px; max-width:360px; width:90%; box-shadow:0 16px 48px rgba(0,0,0,.2); text-align:center; }
    .modal-box .modal-icon { font-size:2.4rem; margin-bottom:12px; }
    .modal-box h3 { font-size:1.1rem; font-weight:800; margin-bottom:8px; }
    .modal-box p { font-size:.87rem; color:var(--muted); margin-bottom:20px; }
    .modal-btns { display:flex; gap:10px; justify-content:center; }
    .modal-btns .mbtn { flex:1; padding:10px; border-radius:50px; font-weight:700; font-size:.88rem; cursor:pointer; transition:all .2s; border:none; }
    .mbtn-cancel { background:#f5f5f5; color:var(--muted); }
    .mbtn-cancel:hover { background:#ebebeb; }
    .mbtn-remove { background:linear-gradient(135deg,#e53e3e,#fc8181); color:#fff; box-shadow:0 4px 14px rgba(229,62,62,.3); }
    .mbtn-remove:hover { transform:translateY(-1px); }
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
      <a href="public_booking.php" class="nb-back"><i class="fas fa-arrow-left"></i> Continue Booking</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="cart-hero">
  <h1><i class="fas fa-shopping-cart" style="margin-right:10px;font-size:.9em;opacity:.85;"></i>My Cart</h1>
  <p><?php echo $cart_count; ?> item<?php echo $cart_count !== 1 ? 's' : ''; ?> saved for booking</p>
</div>

<!-- MAIN -->
<div class="cart-wrap">

  <?php if (empty($cart)): ?>
  <!-- EMPTY STATE -->
  <div class="empty-cart">
    <div class="ec-icon"><i class="fas fa-shopping-cart"></i></div>
    <h2>Your cart is empty</h2>
    <p>You haven't added any facilities yet. Start browsing and add your favorites!</p>
    <a href="public_booking.php" class="btn-shop"><i class="fas fa-search"></i> Browse Facilities</a>
  </div>

  <?php else: ?>

  <!-- CART ITEMS -->
  <div class="cart-items-panel">

    <!-- Header bar -->
    <div class="cart-header-bar">
      <div style="display:flex; align-items:center; gap:12px;">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.85rem; font-weight:700; color:var(--gd); user-select:none;">
          <input type="checkbox" id="selectAllCheckbox" checked onchange="toggleSelectAll(this.checked)" style="width:17px; height:17px; accent-color:var(--gd); cursor:pointer;">
          <span>Select All</span>
        </label>
        <span style="color:#e2e8f0; font-size:.9rem;">|</span>
        <h2><i class="fas fa-shopping-cart"></i> Cart Items <span class="count-chip" id="cartCountBadge"><?php echo $cart_count; ?></span></h2>
      </div>
      <form method="POST" style="display:inline;" onsubmit="return confirmClearAll()">
        <input type="hidden" name="clear_all" value="1">
        <button type="submit" class="clear-all-btn"><i class="fas fa-trash"></i> Clear All</button>
      </form>
    </div>

    <!-- Cart Item Cards -->
    <?php foreach ($cart as $index => $item):
      $price = get_item_price($item);
      $mode  = $item['mode'] ?? 'daytour';
      $slot  = $item['time_slot'] ?? '';
      $isOvernight = ($mode === 'overnight');
      $facType = 'cottage';
      if (stripos($item['facility_name'] ?? '', 'villa') !== false) $facType = 'villa';
      elseif (stripos($item['facility_name'] ?? '', 'hall') !== false || stripos($item['facility_name'] ?? '', 'function') !== false) $facType = 'hall';
      elseif (stripos($item['facility_name'] ?? '', 'room') !== false) $facType = 'room';
      $icons = ['villa'=>'fa-home','hall'=>'fa-building','room'=>'fa-bed','cottage'=>'fa-umbrella-beach'];
      $icon = $icons[$facType] ?? 'fa-umbrella-beach';
      
      $check_in_val = $item['check_in'] ?? $item['check_in_date'] ?? '';
      $check_out_val = $item['check_out'] ?? $item['check_out_date'] ?? '';
      $transport_opt = $item['transport_opt'] ?? $item['transportation'] ?? 'none';
    ?>
    <div class="cart-item-card selected" id="card-<?php echo htmlspecialchars($item['id']); ?>" data-cart-id="<?php echo htmlspecialchars($item['id']); ?>" data-price="<?php echo $price; ?>" data-price-fmt="<?php echo htmlspecialchars(fmt_price($price)); ?>" onclick="toggleCartItem(this, event)">
      <div class="cic-top">
        <!-- Icon column -->
        <div class="cic-icon-col" style="position:relative;">
          <i class="fas <?php echo $icon; ?>"></i>
          <div class="select-indicator" style="position:absolute;top:8px;right:8px;"><i class="fas fa-check"></i></div>
        </div>

        <!-- Body -->
        <div class="cic-body">
          <div class="cic-name">
            <?php echo htmlspecialchars($item['facility_name'] ?? 'Facility'); ?>
            <?php if (!empty($item['area_name']) && $item['area_name'] !== 'N/A'): ?>
              <span class="cic-area"><i class="fas fa-map-marker-alt" style="font-size:.65rem;"></i> <?php echo htmlspecialchars($item['area_name']); ?></span>
            <?php endif; ?>
          </div>

          <span class="cic-mode-badge <?php echo $isOvernight ? 'overnight' : 'daytour'; ?>">
            <i class="fas <?php echo $isOvernight ? 'fa-moon' : 'fa-sun'; ?>"></i>
            <?php echo fmt_mode($mode, $slot); ?>
          </span>

          <div class="cic-details">
            <div class="cic-detail">
              <i class="fas fa-calendar-check"></i>
              <div><div style="font-size:.7rem;margin-bottom:1px;">Check-in</div><strong><?php echo fmt_date($check_in_val); ?></strong></div>
            </div>
            <?php if ($isOvernight && !empty($check_out_val)): ?>
            <div class="cic-detail">
              <i class="fas fa-calendar-minus"></i>
              <div><div style="font-size:.7rem;margin-bottom:1px;">Check-out</div><strong><?php echo fmt_date($check_out_val); ?></strong></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['area_name']) && $item['area_name'] !== 'N/A'): ?>
            <div class="cic-detail">
              <i class="fas fa-map-marker-alt"></i>
              <div><div style="font-size:.7rem;margin-bottom:1px;">Spring Area</div><strong><?php echo htmlspecialchars($item['area_name']); ?></strong></div>
            </div>
            <?php endif; ?>
            <div class="cic-detail">
              <i class="fas fa-users"></i>
              <div><div style="font-size:.7rem;margin-bottom:1px;">Guests</div><strong><?php echo fmt_guests($item); ?></strong></div>
            </div>
            <?php if (!empty($transport_opt) && $transport_opt !== 'none'): ?>
            <div class="cic-detail">
              <i class="fas fa-bus"></i>
              <div><div style="font-size:.7rem;margin-bottom:1px;">Transport</div><strong><?php echo ucfirst($transport_opt); ?></strong></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="cic-footer">
        <div class="cic-price">
          <?php echo fmt_price($price); ?>
          <?php if (empty($item['total_price']) || floatval($item['total_price']) <= 0): ?><span>(calculated at checkout)</span><?php endif; ?>
        </div>
        <div class="cic-actions">
          <a href="public_booking.php?from=cart&edit_cart_id=<?php echo urlencode($item['id']); ?>" class="btn-edit-item">
            <i class="fas fa-pen"></i> Edit
          </a>
          <button type="button" class="btn-remove-item" onclick="openRemoveModal('<?php echo htmlspecialchars($item['id']); ?>', '<?php echo htmlspecialchars(addslashes($item['facility_name'] ?? 'this item')); ?>')">
            <i class="fas fa-trash"></i> Remove
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

  </div><!-- /cart-items-panel -->

  <!-- ORDER SUMMARY -->
  <div class="order-summary">
    <div class="os-header">
      <h3><i class="fas fa-receipt"></i> Order Summary</h3>
    </div>
    <div class="os-body">
      <?php foreach ($cart as $item):
        $price = get_item_price($item);
      ?>
      <div class="os-row" data-cart-id="<?php echo htmlspecialchars($item['id']); ?>" style="transition:all .2s;border-radius:6px;padding:7px 6px;">
        <span class="lbl" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;" title="<?php echo htmlspecialchars($item['facility_name'] ?? ''); ?>">
          <i class="fas fa-check-circle os-row-check" style="font-size:.75rem;color:var(--gd);transition:opacity .2s;"></i>
          <?php echo htmlspecialchars($item['facility_name'] ?? 'Facility'); ?>
        </span>
        <span class="val"><?php echo $price > 0 ? fmt_price($price) : '—'; ?></span>
      </div>
      <?php endforeach; ?>

      <div class="os-total">
        <span class="lbl">Estimated Total</span>
        <span class="val" id="estimated-total"><?php echo fmt_price($grand_total); ?></span>
      </div>

      <div class="os-note">
        <i class="fas fa-info-circle" style="color:#f59e0b;margin-right:4px;"></i>
        Final price is confirmed after completing your booking details.
      </div>

      <!-- Selected item info -->
      <div id="selected-item-info" style="font-size:.78rem;color:var(--muted);text-align:center;margin-top:12px;margin-bottom:2px;">
        <i class="fas fa-check-square" style="margin-right:4px;color:var(--gd);"></i>
        <span id="selected-item-name">All items selected (<?php echo $cart_count; ?>)</span>
      </div>

      <!-- Proceed buttons -->
      <a href="#" id="btn-proceed-booking" class="btn-proceed">
        <i class="fas fa-calendar-check"></i> Proceed to Booking
      </a>
      <a href="public_booking.php" class="btn-continue">
        <i class="fas fa-plus"></i> Add More Facilities
      </a>
    </div>
  </div>

  <?php endif; ?>
</div><!-- /cart-wrap -->

<!-- REMOVE CONFIRM MODAL -->
<div class="modal-overlay" id="removeModal">
  <div class="modal-box">
    <div class="modal-icon">🗑️</div>
    <h3>Remove Item?</h3>
    <p id="removeModalMsg">Are you sure you want to remove this item from your cart?</p>
    <div class="modal-btns">
      <button class="mbtn mbtn-cancel" onclick="closeRemoveModal()">Cancel</button>
      <form method="POST" id="removeForm" style="flex:1;">
        <input type="hidden" name="remove_id" id="removeItemId" value="">
        <button type="submit" class="mbtn mbtn-remove" style="width:100%;">
          <i class="fas fa-trash" style="margin-right:6px;"></i> Remove
        </button>
      </form>
    </div>
  </div>
</div>

<script>
  function toggleCartItem(card, event) {
    if (event && (event.target.closest('.btn-edit-item') || event.target.closest('.btn-remove-item') || event.target.closest('.cic-actions'))) {
      return;
    }
    card.classList.toggle('selected');
    updateCartSelectionState();
  }

  function updateCartSelectionState() {
    var cards = document.querySelectorAll('.cart-item-card');
    var selectedCards = document.querySelectorAll('.cart-item-card.selected');
    var totalSelectedPrice = 0;
    var selectedIds = [];

    var selectAllCb = document.getElementById('selectAllCheckbox');
    if (selectAllCb) {
      selectAllCb.checked = (cards.length > 0 && selectedCards.length === cards.length);
      selectAllCb.indeterminate = (selectedCards.length > 0 && selectedCards.length < cards.length);
    }

    cards.forEach(function(card) {
      var cid = card.getAttribute('data-cart-id');
      var isSel = card.classList.contains('selected');
      var price = parseFloat(card.getAttribute('data-price') || 0);

      var summaryRow = document.querySelector('.os-row[data-cart-id="' + cid + '"]');
      if (summaryRow) {
        var checkIcon = summaryRow.querySelector('.os-row-check');
        if (isSel) {
          summaryRow.style.opacity = '1';
          summaryRow.style.background = 'rgba(26,61,43,.06)';
          summaryRow.style.fontWeight = '700';
          if (checkIcon) checkIcon.style.opacity = '1';
        } else {
          summaryRow.style.opacity = '0.45';
          summaryRow.style.background = 'transparent';
          summaryRow.style.fontWeight = 'normal';
          if (checkIcon) checkIcon.style.opacity = '0';
        }
      }

      if (isSel) {
        totalSelectedPrice += price;
        selectedIds.push(cid);
      }
    });

    var totalEl = document.getElementById('estimated-total');
    if (totalEl) {
      totalEl.textContent = '₱' + totalSelectedPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      totalEl.style.transition = 'transform .15s, opacity .15s';
      totalEl.style.transform = 'scale(1.08)';
      setTimeout(function() {
        totalEl.style.transform = 'scale(1)';
      }, 150);
    }

    var infoEl = document.getElementById('selected-item-name');
    if (infoEl) {
      if (cards.length === 0) {
        infoEl.textContent = 'Cart is empty';
      } else if (selectedCards.length === 0) {
        infoEl.textContent = 'No items selected';
      } else if (selectedCards.length === cards.length) {
        infoEl.textContent = 'All items selected (' + selectedCards.length + ')';
      } else {
        infoEl.textContent = selectedCards.length + ' of ' + cards.length + ' items selected';
      }
    }

    var btn = document.getElementById('btn-proceed-booking');
    if (btn) {
      if (selectedIds.length > 0) {
        var isLoggedIn = <?php echo json_encode($is_logged_in); ?>;
        if (isLoggedIn) {
          btn.href = 'checkout_cart.php?item_ids=' + encodeURIComponent(selectedIds.join(','));
        } else {
          btn.href = 'public_booking.php?from=cart&cart_ids=' + encodeURIComponent(selectedIds.join(','));
        }
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        btn.innerHTML = '<i class="fas fa-calendar-check"></i> Proceed to Booking (' + selectedIds.length + ')';
      } else {
        btn.href = '#';
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.5';
        btn.innerHTML = '<i class="fas fa-calendar-check"></i> Select Items to Proceed';
      }
    }
  }

  function toggleSelectAll(checked) {
    document.querySelectorAll('.cart-item-card').forEach(function(card) {
      if (checked) {
        card.classList.add('selected');
      } else {
        card.classList.remove('selected');
      }
    });
    updateCartSelectionState();
  }

  window.addEventListener('DOMContentLoaded', function() {
    updateCartSelectionState();
  });

  function openRemoveModal(itemId, name) {
    document.getElementById('removeItemId').value = itemId;
    document.getElementById('removeModalMsg').textContent = 'Remove "' + name + '" from your cart?';
    document.getElementById('removeModal').classList.add('show');
  }
  function closeRemoveModal() {
    document.getElementById('removeModal').classList.remove('show');
  }
  document.getElementById('removeModal').addEventListener('click', function(e) {
    if (e.target === this) closeRemoveModal();
  });
  function confirmClearAll() {
    return confirm('Clear all items from your cart?');
  }
</script>
</body>
</html>
