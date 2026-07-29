<?php
require_once 'config/db_config.php';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$submitted  = false;
$error      = '';

// Pre-fill from booking
$booking = null;
if ($booking_id > 0) {
    $s = $conn->prepare("SELECT guest_name, guest_email FROM bookings WHERE id=? AND status='completed'");
    $s->bind_param("i", $booking_id); $s->execute();
    $booking = $s->get_result()->fetch_assoc(); $s->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating     = intval($_POST['rating']     ?? 0);
    $comment    = trim($_POST['comment']      ?? '');
    $guest_name = trim($_POST['guest_name']   ?? '');
    $email      = trim($_POST['email']        ?? '');

    if (empty($guest_name))          { $error = 'Please enter your name.'; }
    elseif ($rating < 1 || $rating > 5) { $error = 'Please select a star rating.'; }
    elseif (empty($comment))         { $error = 'Please write a comment about your experience.'; }
    else {
        $ins = $conn->prepare("INSERT INTO feedback (guest_name, email, rating, comment) VALUES (?,?,?,?)");
        $ins->bind_param("ssis", $guest_name, $email, $rating, $comment);
        if ($ins->execute()) { $submitted = true; }
        else { $error = 'Could not save feedback. Please try again.'; }
        $ins->close();
    }
}

$pre_name    = htmlspecialchars($booking['guest_name']  ?? $_POST['guest_name'] ?? '');
$pre_email   = htmlspecialchars($booking['guest_email'] ?? $_POST['email']      ?? '');
$pre_comment = htmlspecialchars($_POST['comment'] ?? '');
$pre_rating  = intval($_POST['rating'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Share Your Feedback — Sinulom &amp; Bolao Resort</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gd:#1a3d2b;--gm:#2d5a3d;--cream:#f5f0e8;--txt:#1a1a1a;--muted:#6b7280;--border:#e2ddd5;--red:#c62828}
body{font-family:'Inter',sans-serif;background:var(--cream);min-height:100vh;display:flex;flex-direction:column;}

/* Navbar */
.nb{background:var(--gd);padding:14px 32px;display:flex;align-items:center;gap:12px;}
.nb img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.2);}
.nb-txt strong{display:block;color:#fff;font-size:.88rem;font-weight:700;line-height:1.2;}
.nb-txt span{font-size:.7rem;color:rgba(255,255,255,.65);}

/* Page */
.page{flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;}
.card{background:#fff;border-radius:20px;padding:40px 44px;max-width:520px;width:100%;box-shadow:0 4px 32px rgba(0,0,0,.08);}
.card-icon{width:72px;height:72px;border-radius:50%;background:#f0faf4;border:2px solid #c8e6c9;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 20px;}
.card h2{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:800;color:var(--txt);text-align:center;margin-bottom:8px;}
.card .sub{font-size:.9rem;color:var(--muted);text-align:center;margin-bottom:28px;line-height:1.6;}

/* Fields */
.field{margin-bottom:16px;}
.field label{display:block;font-size:.8rem;font-weight:600;color:var(--txt);margin-bottom:6px;}
.field input,.field textarea{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'Inter',sans-serif;font-size:.9rem;color:var(--txt);outline:none;transition:border-color .2s,box-shadow .2s;}
.field input:focus,.field textarea:focus{border-color:var(--gd);box-shadow:0 0 0 3px rgba(26,61,43,.1);}
.field textarea{resize:vertical;min-height:110px;}

/* ── Interactive star rating ── */
.star-rating{display:flex;justify-content:center;gap:8px;margin:8px 0 4px;}
.star-rating .star{font-size:2.4rem;color:#ddd;cursor:pointer;transition:color .15s,transform .1s;user-select:none;line-height:1;}
.star-rating .star:hover,.star-rating .star.hovered,.star-rating .star.selected{color:#f9a825;}
.star-rating .star:hover{transform:scale(1.15);}
.star-label{text-align:center;font-size:.82rem;color:var(--muted);min-height:20px;margin-bottom:12px;}

/* Alert */
.alert-err{background:#fdecea;border:1.5px solid #f5c6cb;border-radius:10px;padding:11px 14px;font-size:.84rem;color:var(--red);display:flex;align-items:center;gap:8px;margin-bottom:16px;}

/* Buttons */
.btn{width:100%;padding:14px;background:var(--gd);color:#fff;border:none;border-radius:50px;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 4px 16px rgba(26,61,43,.3);}
.btn:hover{background:var(--gm);transform:translateY(-1px);}
.btn-skip{display:block;text-align:center;margin-top:14px;color:var(--muted);font-size:.84rem;text-decoration:none;transition:color .2s;}
.btn-skip:hover{color:var(--gd);}

/* Success */
.success-wrap{text-align:center;}
.success-wrap .big-icon{font-size:3.5rem;margin-bottom:16px;display:block;}
.btn-home{display:inline-flex;align-items:center;gap:8px;background:var(--gd);color:#fff;padding:13px 32px;border-radius:50px;font-weight:700;font-size:.92rem;text-decoration:none;transition:all .2s;box-shadow:0 4px 16px rgba(26,61,43,.3);margin-top:20px;}
.btn-home:hover{background:var(--gm);color:#fff;}

@media(max-width:480px){.card{padding:28px 20px;}}
</style>
</head>
<body>

<nav class="nb">
  <img src="images/logo.jpg" alt="Logo">
  <div class="nb-txt">
    <strong>Sinulom &amp; Bolao</strong>
    <span>Cold Spring Resort</span>
  </div>
</nav>

<div class="page">
  <div class="card">

    <?php if ($submitted): ?>
    <!-- Success state -->
    <div class="success-wrap">
      <span class="big-icon">&#127881;</span>
      <h2>Thank You!</h2>
      <p class="sub" style="margin-bottom:0;">Your feedback has been submitted and will appear on our website. We truly appreciate you sharing your experience!</p>
      <a href="landing.php" class="btn-home"><i class="fas fa-home"></i> Back to Home</a>
    </div>

    <?php else: ?>
    <!-- Feedback form -->
    <div class="card-icon"><i class="fas fa-star" style="color:#f9a825;"></i></div>
    <h2>Share Your Experience</h2>
    <p class="sub">How was your stay at Sinulom &amp; Bolao Cold Spring Resort?<br>Your review helps other guests and helps us improve.</p>

    <?php if ($error): ?>
    <div class="alert-err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="feedbackForm">
      <input type="hidden" name="rating" id="ratingInput" value="<?= $pre_rating ?>">

      <div class="field">
        <label>Your Name <span style="color:var(--red);">*</span></label>
        <input type="text" name="guest_name" value="<?= $pre_name ?>" placeholder="Juan Dela Cruz" required autocapitalize="words">
      </div>

      <div class="field">
        <label>Email Address <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
        <input type="email" name="email" value="<?= $pre_email ?>" placeholder="you@example.com">
      </div>

      <div class="field">
        <label style="text-align:center;display:block;">Your Rating <span style="color:var(--red);">*</span></label>
        <div class="star-rating" id="starRating">
          <span class="star" data-value="1">&#9733;</span>
          <span class="star" data-value="2">&#9733;</span>
          <span class="star" data-value="3">&#9733;</span>
          <span class="star" data-value="4">&#9733;</span>
          <span class="star" data-value="5">&#9733;</span>
        </div>
        <div class="star-label" id="starLabel">Click a star to rate</div>
      </div>

      <div class="field">
        <label>Your Comment <span style="color:var(--red);">*</span></label>
        <textarea name="comment" placeholder="Tell us about your experience — what did you enjoy most?"><?= $pre_comment ?></textarea>
      </div>

      <button type="submit" class="btn"><i class="fas fa-paper-plane"></i>&nbsp; Submit Feedback</button>
    </form>
    <a href="landing.php" class="btn-skip">Skip for now</a>

    <?php endif; ?>
  </div>
</div>

<script>
const labels = ['','Terrible','Poor','Average','Good','Excellent'];
const stars  = document.querySelectorAll('.star');
const input  = document.getElementById('ratingInput');
const lbl    = document.getElementById('starLabel');
let selected = <?= $pre_rating ?>;

function paint(n) {
  stars.forEach((s, i) => {
    s.classList.toggle('selected', i < n);
    s.classList.remove('hovered');
  });
}

// Init from pre-selected (on validation error)
if (selected > 0) { paint(selected); lbl.textContent = labels[selected]; }

stars.forEach(star => {
  const v = parseInt(star.getAttribute('data-value'));

  star.addEventListener('mouseenter', () => {
    stars.forEach((s, i) => s.classList.toggle('hovered', i < v));
    lbl.textContent = labels[v];
  });

  star.addEventListener('mouseleave', () => {
    stars.forEach(s => s.classList.remove('hovered'));
    lbl.textContent = selected > 0 ? labels[selected] : 'Click a star to rate';
  });

  star.addEventListener('click', () => {
    selected = v;
    input.value = v;
    paint(v);
    lbl.textContent = labels[v];
  });
});

// Validate before submit
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
  if (!input.value || input.value == '0') {
    e.preventDefault();
    lbl.textContent = 'Please select a rating!';
    lbl.style.color = 'var(--red)';
    document.getElementById('starRating').scrollIntoView({behavior:'smooth', block:'center'});
  }
});
</script>
</body>
</html>
