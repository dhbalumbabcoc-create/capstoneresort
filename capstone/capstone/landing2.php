<?php
session_start();
require_once 'config/db_config.php';

$is_logged_in = !empty($_SESSION['guest_logged_in']);
$session_name = $_SESSION['guest_name'] ?? '';

// Fetch site settings
$settings_row = [];
$s = $conn->query("SELECT resort_name, tagline, contact_info, business_hours, logo FROM site_settings WHERE id=1 LIMIT 1");
if ($s && $row = $s->fetch_assoc()) $settings_row = $row;
$resort_name    = $settings_row['resort_name']    ?? 'Sinulom & Bolao Cold Spring Resort';
$tagline        = $settings_row['tagline']         ?? 'Cold Spring';
$contact_info   = $settings_row['contact_info']   ?? '0917-123-4567';
$business_hours = $settings_row['business_hours'] ?? '8:00 AM - 5:00 PM';
$logo_file      = $settings_row['logo']            ?? 'logo.jpg';

// Fetch available facilities for gallery
$fac_res = $conn->query("SELECT id, name, type, description, capacity, price, amenities, image_path FROM facilities WHERE status='available' ORDER BY type, name LIMIT 9");
$facilities = [];
if ($fac_res) { while ($r = $fac_res->fetch_assoc()) $facilities[] = $r; }

// Handle Feedback Submission
$feedback_success = '';
$feedback_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_guest_feedback'])) {
    $fb_name    = trim($_POST['guest_name'] ?? '');
    $fb_rating  = intval($_POST['rating'] ?? 0);
    $fb_comment = trim($_POST['comment'] ?? '');
    $fb_email   = trim($_POST['email'] ?? '');

    if (empty($fb_name)) {
        $feedback_error = 'Please enter your name.';
    } elseif ($fb_rating < 1 || $fb_rating > 5) {
        $feedback_error = 'Please select a star rating.';
    } elseif (empty($fb_comment)) {
        $feedback_error = 'Please write your review.';
    } else {
        $fb_stmt = $conn->prepare("INSERT INTO feedback (guest_name, email, rating, comment) VALUES (?, ?, ?, ?)");
        if ($fb_stmt) {
            $fb_stmt->bind_param("ssis", $fb_name, $fb_email, $fb_rating, $fb_comment);
            if ($fb_stmt->execute()) {
                $feedback_success = 'Thank you! Your feedback has been submitted.';
            } else {
                $feedback_error = 'Could not save feedback. Please try again.';
            }
            $fb_stmt->close();
        } else {
            $feedback_error = 'Database query failed.';
        }
    }
}

// Fetch feedback list for testimonials section
$feedback_list = [];
$fb_res_list = $conn->query("SELECT guest_name, rating, comment, created_at FROM feedback ORDER BY id DESC LIMIT 10");
if ($fb_res_list) {
    while ($r = $fb_res_list->fetch_assoc()) {
        $feedback_list[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sinulom &amp; Bolao Cold Spring Resort – Your Nature Retreat</title>
<meta name="description" content="Experience the refreshing cold springs and natural beauty of Sinulom &amp; Bolao Cold Spring Resort. Book your stay at our cottages, villas and function halls.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --green-dark:   #0d3d22;
    --green-mid:    #1B7D3A;
    --green-accent: #27A457;
    --green-light:  #e8f5ee;
    --gold:         #c9a84c;
    --gold-light:   #f5e9c4;
    --white:        #ffffff;
    --gray-50:      #f9fafb;
    --gray-100:     #f3f4f6;
    --gray-600:     #4b5563;
    --gray-800:     #1f2937;
    --shadow-sm:    0 1px 3px rgba(0,0,0,.1);
    --shadow-md:    0 4px 20px rgba(0,0,0,.12);
    --shadow-lg:    0 12px 40px rgba(0,0,0,.18);
    --radius:       16px;
    --radius-sm:    10px;
  }

  html { scroll-behavior: smooth; }
  body { font-family: 'Inter', sans-serif; color: var(--gray-800); background: var(--white); overflow-x: hidden; }

  /* ── NAVBAR ── */
  .navbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 5%; height: 72px;
    background: transparent;
    transition: background .35s ease, box-shadow .35s ease;
  }
  .navbar.scrolled {
    background: rgba(13,61,34,.96);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 20px rgba(0,0,0,.25);
  }
  .nb-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
  .nb-brand img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.35); }
  .nb-brand-txt strong { display: block; font-family: 'Playfair Display', serif; font-size: .95rem; color: #fff; line-height: 1.2; }
  .nb-brand-txt span   { font-size: .7rem; color: rgba(255,255,255,.65); }
  .nb-links { display: flex; gap: 32px; list-style: none; }
  .nb-links a { color: rgba(255,255,255,.85); text-decoration: none; font-size: .88rem; font-weight: 500; transition: color .2s; }
  .nb-links a:hover { color: #fff; }
  .nb-right { display: flex; align-items: center; gap: 14px; }
  .nb-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 22px; border-radius: 50px; font-size: .85rem; font-weight: 600;
    text-decoration: none; transition: all .25s; border: none; cursor: pointer;
  }
  .nb-btn-outline { border: 1.5px solid rgba(255,255,255,.6); color: #fff; background: transparent; }
  .nb-btn-outline:hover,
  .nb-btn-outline:focus,
  .nb-btn-outline:active { border-color: var(--green-accent); background: var(--green-accent); color: #fff; box-shadow: 0 4px 14px rgba(39,164,87,.45); }
  .nb-btn-solid { background: var(--green-accent); color: #fff; }
  .nb-btn-solid:hover,
  .nb-btn-solid:focus,
  .nb-btn-solid:active { background: var(--green-mid); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(39,164,87,.45); }

  /* ── HERO ── */
  .hero {
    position: relative; min-height: 100vh;
    display: flex; flex-direction: column; justify-content: center; align-items: center;
    text-align: center; overflow: hidden;
  }
  .hero-bg {
    position: absolute; inset: 0;
    background: url('images/booking-bg.jpg') center/cover no-repeat;
  }
  .hero-bg::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(to bottom, rgba(15,35,20,.45) 0%, rgba(10,25,14,.65) 60%, rgba(5,20,10,.82) 100%);
  }
  .hero-content { position: relative; z-index: 1; max-width: 1100px; padding: 0 24px; }
  .hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    color: rgba(255,255,255,.9); font-size: .78rem; font-weight: 700; letter-spacing: 4px; text-transform: uppercase;
    margin-bottom: 22px;
    animation: fadeInUp .8s ease both;
  }
  .hero-eyebrow::before, .hero-eyebrow::after {
    content: '—'; opacity: .7;
  }
  .hero-title {
    font-family: 'Playfair Display', serif; font-size: clamp(1.6rem, 3.6vw, 3.2rem); font-weight: 700;
    color: #fff; line-height: 1.15; margin-bottom: 22px; white-space: nowrap;
    animation: fadeInUp .8s .15s ease both;
  }
  .hero-title em {
    color: #52c47a; font-style: italic; font-weight: 600;
  }
  .hero-subtitle {
    font-size: clamp(.95rem, 2vw, 1.15rem); color: rgba(255,255,255,.85);
    line-height: 1.7; margin-bottom: 28px; max-width: 720px; margin-left: auto; margin-right: auto;
    animation: fadeInUp .8s .3s ease both;
  }
  .hero-loc-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.18); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.32); border-radius: 50px;
    color: #fff; font-size: .85rem; font-weight: 600;
    padding: 7px 22px; margin-bottom: 34px;
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    animation: fadeInUp .8s .4s ease both;
  }
  .hero-actions {
    display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;
    animation: fadeInUp .8s .5s ease both;
  }
  .btn-hero-primary {
    display: inline-flex; align-items: center; gap: 10px;
    background: #17532d; color: #fff;
    padding: 15px 36px; border-radius: 50px; font-size: .95rem; font-weight: 700;
    text-decoration: none; transition: all .25s;
    box-shadow: 0 6px 20px rgba(0,0,0,.3);
  }
  .btn-hero-primary:hover { background: #227541; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
  .btn-hero-secondary {
    display: inline-flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.15); color: #fff; backdrop-filter: blur(8px);
    padding: 15px 36px; border-radius: 50px; font-size: .95rem; font-weight: 600;
    text-decoration: none; border: 1.5px solid rgba(255,255,255,.55); transition: all .25s;
  }
  .btn-hero-secondary:hover { background: rgba(255,255,255,.28); border-color: #fff; transform: translateY(-2px); }

  /* Stats strip */
  .hero-stats {
    position: absolute; bottom: 0; left: 0; right: 0; z-index: 1;
    display: flex; justify-content: center; gap: 0;
    background: rgba(0,0,0,.4); backdrop-filter: blur(14px);
    border-top: 1px solid rgba(255,255,255,.15);
    padding: 6px 0;
  }
  .hero-stat {
    flex: 0 1 auto; min-width: 140px; max-width: 220px; padding: 10px 22px; text-align: center;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    border-right: 1px solid rgba(255,255,255,.15);
  }
  .hero-stat:last-child { border-right: none; }
  .hero-stat strong { font-family: 'Playfair Display', serif; font-size: 1.05rem; color: #fff; line-height: 1; white-space: nowrap; }
  .hero-stat span { font-size: .7rem; color: rgba(255,255,255,.8); text-transform: uppercase; letter-spacing: .8px; line-height: 1.1; }

  /* Wave divider */
  .wave-divider { display: block; width: 100%; overflow: hidden; line-height: 0; }
  .wave-divider svg { display: block; width: 100%; }

  /* ── SECTION COMMONS ── */
  section { padding: 90px 5%; }
  .section-label {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--green-accent); font-size: .72rem; font-weight: 700;
    letter-spacing: 3px; text-transform: uppercase; margin-bottom: 14px;
  }
  .section-label::before { content: ''; width: 28px; height: 2px; background: var(--green-accent); border-radius: 2px; }
  .section-title {
    font-family: 'Playfair Display', serif; font-size: clamp(1.9rem, 4vw, 2.8rem);
    font-weight: 700; color: var(--green-dark); line-height: 1.25; margin-bottom: 16px;
  }
  .section-sub { font-size: 1rem; color: var(--gray-600); line-height: 1.7; max-width: 560px; }
  .section-header { margin-bottom: 52px; }
  .text-center { text-align: center; }
  .text-center .section-sub { margin: 0 auto; }
  .text-center .section-label { justify-content: center; }

  /* ── ABOUT ── */
  .about-section { background: var(--white); }
  .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; max-width: 1200px; margin: 0 auto; }
  .about-img-wrap {
    position: relative; border-radius: 24px; overflow: hidden;
    box-shadow: var(--shadow-lg);
  }
  .about-img-wrap img { width: 100%; height: 500px; object-fit: cover; display: block; }
  .about-badge {
    position: absolute; bottom: 24px; left: 24px;
    background: var(--white); border-radius: var(--radius-sm);
    padding: 16px 22px; box-shadow: var(--shadow-md);
    display: flex; align-items: center; gap: 14px;
  }
  .about-badge-icon { font-size: 1.6rem; color: var(--green-accent); }
  .about-badge strong { display: block; font-size: .95rem; color: var(--green-dark); }
  .about-badge span { font-size: .75rem; color: var(--gray-600); }
  .about-text p { color: var(--gray-600); line-height: 1.8; margin-bottom: 16px; }
  .about-features { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 28px 0; }
  .about-feat {
    display: flex; align-items: center; gap: 10px;
    background: var(--green-light); border-radius: var(--radius-sm);
    padding: 12px 16px; font-size: .85rem; font-weight: 600; color: var(--green-dark);
  }
  .about-feat i { color: var(--green-accent); width: 18px; }

  /* ── FACILITIES ── */
  .facilities-section { background: var(--gray-50); position: relative; }
  .fac-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 28px; max-width: 1200px; margin: 0 auto; }
  .fac-card {
    background: var(--white); border-radius: var(--radius); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: transform .35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow .35s ease;
    cursor: pointer; text-decoration: none; color: inherit; display: flex; flex-direction: column;
    position: relative;
  }
  .fac-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: 0 16px 36px rgba(27,125,58,0.18); }
  .fac-card:active { transform: scale(0.98); }

  .fac-card-img-wrapper {
    position: relative; width: 100%; height: 220px; overflow: hidden;
    background: linear-gradient(135deg, #c8e6c9, #a5d6a7);
  }
  .fac-card-img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform 0.5s ease;
  }
  .fac-card:hover .fac-card-img { transform: scale(1.09); }
  .fac-card-img-placeholder {
    width: 100%; height: 220px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--green-light), #c8e6c9);
    font-size: 3rem; color: var(--green-accent);
  }
  
  /* Card hover quick-view overlay */
  .fac-card-overlay {
    position: absolute; inset: 0;
    background: rgba(13, 61, 34, 0.45); backdrop-filter: blur(2px);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.3s ease;
  }
  .fac-card:hover .fac-card-overlay { opacity: 1; }
  .fac-quick-view-badge {
    background: #ffffff; color: var(--green-dark);
    font-size: 0.82rem; font-weight: 700; padding: 10px 20px;
    border-radius: 50px; box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    display: inline-flex; align-items: center; gap: 8px;
    transform: translateY(10px) scale(0.9); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  .fac-card:hover .fac-quick-view-badge { transform: translateY(0) scale(1); }

  .fac-card-body { padding: 22px; flex: 1; display: flex; flex-direction: column; }
  .fac-type-badge {
    display: inline-block; background: var(--green-light); color: var(--green-mid);
    font-size: .68rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
    padding: 3px 10px; border-radius: 50px; margin-bottom: 10px; align-self: flex-start;
  }
  .fac-card-name { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 600; color: var(--green-dark); margin-bottom: 8px; }
  .fac-card-meta { display: flex; gap: 16px; font-size: .8rem; color: var(--gray-600); margin-bottom: 14px; flex-wrap: wrap; }
  .fac-card-meta span { display: flex; align-items: center; gap: 5px; }
  .fac-card-price { font-size: 1.1rem; font-weight: 700; color: var(--green-accent); margin-top: auto; }
  .fac-card-price small { font-size: .72rem; font-weight: 400; color: var(--gray-600); }
  
  .btn-book-fac {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--green-accent); color: #fff; border: none;
    padding: 10px 22px; border-radius: 50px; font-size: .85rem; font-weight: 600;
    text-decoration: none; margin-top: 14px; transition: all .2s; cursor: pointer;
  }
  .btn-book-fac:hover { background: var(--green-mid); transform: translateY(-1px); }

  /* ── FACILITY ZOOM MODAL ── */
  .fac-modal-backdrop {
    position: fixed; inset: 0; z-index: 10000;
    background: rgba(10, 25, 16, 0.78);
    backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px; opacity: 0; visibility: hidden;
    transition: opacity 0.35s ease, visibility 0.35s ease;
  }
  .fac-modal-backdrop.active {
    opacity: 1; visibility: visible;
  }
  .fac-modal-container {
    background: #ffffff;
    width: 100%; max-width: 660px; max-height: 90vh;
    border-radius: 24px; overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,0.38);
    display: flex; flex-direction: column;
    transform: scale(0.72) translateY(30px);
    opacity: 0;
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
    position: relative;
  }
  .fac-modal-backdrop.active .fac-modal-container {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
  .fac-modal-header {
    position: relative; height: 300px; overflow: hidden; flex-shrink: 0;
    background: #0f2d19;
  }
  .fac-modal-img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform 0.6s ease;
  }
  .fac-modal-backdrop.active .fac-modal-img {
    animation: facModalImgZoom 0.7s cubic-bezier(0.25, 1, 0.5, 1) forwards;
  }
  @keyframes facModalImgZoom {
    from { transform: scale(1.18); }
    to   { transform: scale(1); }
  }
  .fac-modal-close-btn {
    position: absolute; top: 16px; right: 16px;
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(6px);
    color: #fff; border: 1px solid rgba(255,255,255,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; cursor: pointer; transition: all 0.25s ease;
    z-index: 10;
  }
  .fac-modal-close-btn:hover {
    background: #dc2626; color: #fff; transform: rotate(90deg) scale(1.1); border-color: #dc2626;
  }
  .fac-modal-badge-row {
    position: absolute; bottom: 16px; left: 24px; display: flex; gap: 10px; flex-wrap: wrap; z-index: 2;
  }
  .fac-modal-type-badge {
    background: var(--green-accent); color: #fff;
    font-size: 0.72rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
    padding: 6px 14px; border-radius: 50px; box-shadow: 0 4px 12px rgba(0,0,0,0.25);
  }
  .fac-modal-img-gradient {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(13, 61, 34, 0.75) 0%, transparent 60%);
  }

  .fac-modal-body {
    padding: 28px; overflow-y: auto; flex: 1;
  }
  .fac-modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.85rem; font-weight: 700; color: var(--green-dark);
    margin-bottom: 14px; line-height: 1.2;
  }
  .fac-modal-meta-grid {
    display: flex; flex-wrap: wrap; align-items: center; gap: 20px; margin-bottom: 22px;
    padding-bottom: 18px; border-bottom: 1px solid var(--gray-100);
  }
  .fac-modal-meta-item {
    display: flex; align-items: center; gap: 8px; font-size: 0.9rem;
    color: var(--gray-600); font-weight: 500;
  }
  .fac-modal-meta-item i {
    color: var(--green-accent); font-size: 1.1rem;
  }
  .fac-modal-price-tag {
    font-size: 1.4rem; font-weight: 800; color: var(--green-accent);
    margin-left: auto;
  }
  .fac-modal-price-tag small {
    font-size: 0.78rem; font-weight: 400; color: var(--gray-600);
  }

  .fac-modal-section-title {
    font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--green-mid); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;
  }
  .fac-modal-desc {
    font-size: 0.94rem; color: var(--gray-600); line-height: 1.7; margin-bottom: 22px;
    background: var(--gray-50); padding: 14px 18px; border-radius: 12px; border-left: 4px solid var(--green-accent);
  }
  .fac-modal-amenities {
    display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;
  }
  .fac-amenity-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--green-light); color: var(--green-dark);
    font-size: 0.83rem; font-weight: 600; padding: 8px 15px;
    border-radius: 10px; border: 1px solid rgba(27,125,58,0.18);
  }
  .fac-amenity-chip i {
    color: var(--green-accent); font-size: 0.85rem;
  }

  .fac-modal-footer {
    padding: 18px 28px; background: var(--gray-50); border-top: 1px solid var(--gray-100);
    display: flex; align-items: center; justify-content: center;
  }
  .btn-modal-book {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    background: linear-gradient(135deg, var(--green-mid), var(--green-accent));
    color: #fff; border: none; padding: 15px 32px; border-radius: 50px;
    font-size: 0.98rem; font-weight: 700; text-decoration: none; width: 100%;
    box-shadow: 0 6px 20px rgba(27,125,58,0.3); transition: all 0.25s ease;
    text-align: center;
  }
  .btn-modal-book:hover {
    transform: translateY(-2px); box-shadow: 0 8px 25px rgba(27,125,58,0.45);
    background: linear-gradient(135deg, #15622e, #22934d); color: #fff;
  }
  .btn-modal-close-sec {
    background: transparent; border: 1.5px solid var(--gray-600); color: var(--gray-600);
    padding: 12px 24px; border-radius: 50px; font-size: 0.9rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s ease;
  }
  .btn-modal-close-sec:hover {
    background: var(--gray-100); color: var(--gray-800); border-color: var(--gray-800);
  }

  /* ── EXPERIENCE ── */
  .experience-section { background: var(--green-dark); overflow: hidden; }
  .exp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; max-width: 1200px; margin: 0 auto; }
  .exp-text .section-label { color: var(--gold); }
  .exp-text .section-label::before { background: var(--gold); }
  .exp-text .section-title { color: #fff; }
  .exp-text .section-sub { color: rgba(255,255,255,.7); }
  .exp-perks { margin-top: 36px; display: flex; flex-direction: column; gap: 20px; }
  .exp-perk { display: flex; gap: 18px; align-items: flex-start; }
  .exp-perk-icon {
    width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0;
    background: rgba(39,164,87,.2); border: 1px solid rgba(39,164,87,.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: var(--green-accent);
  }
  .exp-perk-txt strong { display: block; color: #fff; font-size: .95rem; margin-bottom: 4px; }
  .exp-perk-txt span  { color: rgba(255,255,255,.6); font-size: .83rem; line-height: 1.5; }
  .exp-images { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .exp-img-tall { grid-row: span 2; border-radius: var(--radius); overflow: hidden; }
  .exp-img-sm   { border-radius: var(--radius-sm); overflow: hidden; }
  .exp-img-tall img, .exp-img-sm img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .exp-img-tall { height: 380px; }
  .exp-img-sm   { height: 180px; }

  /* ── HOW IT WORKS ── */
  .how-section { background: var(--white); }
  .steps-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; max-width: 1100px; margin: 0 auto; }
  .step-card { text-align: center; padding: 36px 20px; border-radius: var(--radius); background: var(--gray-50); position: relative; }
  .step-card::after {
    content: '→'; position: absolute; right: -20px; top: 50%; transform: translateY(-50%);
    font-size: 1.4rem; color: var(--green-accent); opacity: .4;
  }
  .step-card:last-child::after { display: none; }
  .step-num {
    width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 18px;
    background: linear-gradient(135deg, var(--green-mid), var(--green-accent));
    color: #fff; font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(27,125,58,.35);
  }
  .step-card h3 { font-size: .95rem; font-weight: 700; color: var(--green-dark); margin-bottom: 8px; }
  .step-card p  { font-size: .82rem; color: var(--gray-600); line-height: 1.6; }

  /* ── GALLERY ── */
  .gallery-section { background: var(--gray-50); }
  .gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: auto auto;
    gap: 12px; max-width: 1200px; margin: 0 auto;
  }
  .g-item { border-radius: var(--radius-sm); overflow: hidden; cursor: pointer; position: relative; }
  .g-item img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s ease; }
  .g-item:hover img { transform: scale(1.07); }
  .g-item:nth-child(1) { grid-column: span 2; grid-row: span 2; }
  .g-item:nth-child(1) { min-height: 380px; }
  .g-item:not(:nth-child(1)) { min-height: 180px; }

  /* ── PRICING / ENTRANCE RATES ── */
  .pricing-section {
    padding: 90px 5%;
    background: #f5f0e8;
    position: relative;
  }
  .pricing-header {
    text-align: center;
    margin-bottom: 56px;
  }
  .pricing-header .section-label {
    color: var(--green-mid);
    justify-content: center;
    display: inline-flex;
    margin-bottom: 12px;
  }
  .pricing-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.8rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 14px;
    line-height: 1.15;
  }
  .pricing-header p {
    color: var(--gray-600);
    font-size: 1rem;
    max-width: 480px;
    margin: 0 auto;
    line-height: 1.65;
  }
  .pricing-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 28px;
    max-width: 1100px;
    margin: 0 auto;
    align-items: start;
  }
  .pricing-card {
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 8px 32px rgba(0,0,0,0.07);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
  }
  .pricing-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 44px rgba(0,0,0,0.12);
  }
  .pricing-card-header {
    padding: 36px 32px 28px;
    text-align: center;
    position: relative;
  }
  .pricing-card.sinulom .pricing-card-header  { background: #1a4a2e; }
  .pricing-card.combo   .pricing-card-header  { background: #c9a12a; }
  .pricing-card.bolao   .pricing-card-header  { background: #1a5c5c; }
  .pricing-popular-badge {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255,255,255,0.22);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 4px 14px;
    border-radius: 50px;
  }
  .pricing-card-icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    font-size: 1.3rem;
    color: #fff;
  }
  .pricing-card-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.55rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 6px;
  }
  .pricing-card-sub {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.75);
    letter-spacing: 0.3px;
  }
  .pricing-card-body {
    padding: 28px 32px;
  }
  .pricing-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 14px 0;
    border-bottom: 1px solid #f0ece4;
  }
  .pricing-row:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
  }
  .pricing-row-label strong {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 2px;
  }
  .pricing-row-label span {
    font-size: 0.76rem;
    color: var(--gray-600);
  }
  .pricing-row-price {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gray-800);
    white-space: nowrap;
    padding-top: 2px;
  }
  .pricing-row-price.free {
    color: #c9a12a;
    font-size: 0.95rem;
  }
  .pricing-card.combo .pricing-row-price { color: #c9a12a; }
  .pricing-card.combo .pricing-row-price.free { color: #c9a12a; }
  .pricing-features {
    background: #f7f9f7;
    border-radius: 12px;
    padding: 16px 20px;
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .pricing-card.combo .pricing-features { background: #fffbf0; }
  .pricing-card.bolao .pricing-features  { background: #f0f8f8; }
  .pricing-feature {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.84rem;
    color: var(--gray-600);
  }
  .pricing-feature::before {
    content: '✓';
    color: var(--green-accent);
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
  }
  .pricing-footnote {
    text-align: center;
    margin-top: 36px;
    font-size: 0.82rem;
    color: var(--gray-600);
    font-style: italic;
  }
  @media (max-width: 900px) {
    .pricing-grid { grid-template-columns: 1fr; max-width: 420px; }
    .pricing-header h2 { font-size: 2rem; }
  }

  /* ── RESORT LOCATION & MAP ── */
  .resort-location-section {
    padding: 80px 5%;
    background: #ffffff;
  }
  .resort-loc-container {
    max-width: 1200px;
    margin: 0 auto;
  }
  .resort-loc-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
    align-items: stretch;
  }
  .resort-info-card {
    background: var(--green-dark, #0d3d22);
    border-radius: 24px;
    padding: 44px 40px;
    color: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 12px 36px rgba(13,61,34,0.18);
  }
  .resort-info-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.1rem;
    font-weight: 700;
    margin-bottom: 12px;
    color: #ffffff;
    line-height: 1.25;
  }
  .resort-info-sub {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.72);
    margin-bottom: 36px;
    line-height: 1.55;
  }
  .resort-info-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }
  .resort-info-item {
    display: flex;
    align-items: flex-start;
    gap: 18px;
  }
  .resort-info-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #ffffff;
    transition: background 0.3s ease;
  }
  .resort-info-item:hover .resort-info-icon {
    background: var(--green-accent, #27A457);
  }
  .resort-info-text {
    display: flex;
    flex-direction: column;
    gap: 3px;
  }
  .resort-info-text strong {
    font-size: 1.05rem;
    font-weight: 600;
    color: #ffffff;
  }
  .resort-info-text span {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.75);
    line-height: 1.45;
  }

  .resort-map-card {
    border-radius: 24px;
    overflow: hidden;
    min-height: 460px;
    box-shadow: 0 12px 36px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.06);
    background: #e5e7eb;
  }
  .resort-map-card iframe {
    width: 100%;
    height: 100%;
    min-height: 460px;
    border: none;
    display: block;
  }

  .directions-btn-wrap {
    text-align: center;
    margin-top: 36px;
  }
  .btn-get-directions {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: var(--green-dark, #0d3d22);
    color: #ffffff;
    padding: 16px 38px;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(13,61,34,0.3);
  }
  .btn-get-directions:hover {
    background: var(--green-mid, #1B7D3A);
    transform: translateY(-3px);
    box-shadow: 0 10px 28px rgba(27,125,58,0.45);
    color: #ffffff;
  }
  .btn-get-directions i {
    font-size: 1.1rem;
    transform: rotate(-15deg);
  }

  /* ── TESTIMONIALS & GUEST FEEDBACK ── */
  .testimonials-section {
    padding: 90px 5%;
    background: #f7f4ed;
    position: relative;
  }
  .testimonials-container {
    max-width: 1140px;
    margin: 0 auto;
  }
  .testimonials-header {
    text-align: center;
    margin-bottom: 50px;
  }
  .testimonials-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 2.5px;
    color: var(--green-dark, #0d3d22);
    text-transform: uppercase;
    margin-bottom: 12px;
  }
  .testimonials-badge::before,
  .testimonials-badge::after {
    content: '';
    display: inline-block;
    width: 24px;
    height: 1.5px;
    background: var(--green-dark, #0d3d22);
    opacity: 0.7;
  }
  .testimonials-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.8rem;
    font-weight: 800;
    color: #1a1a1a;
    margin-bottom: 12px;
  }
  .testimonials-sub {
    font-size: 0.95rem;
    color: var(--gray-600, #4b5563);
    max-width: 580px;
    margin: 0 auto;
    line-height: 1.6;
  }

  .testimonials-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    align-items: stretch;
  }

  /* Left Side: Reviews List / Empty State */
  .testimonials-left-col {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 440px;
  }

  .feedback-empty-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 60px 20px;
    text-align: center;
  }
  .empty-icon-box {
    font-size: 2.5rem;
    color: #b0b7c0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .empty-text {
    font-size: 1.1rem;
    font-weight: 500;
    color: #8c95a1;
  }

  .feedback-cards-container {
    display: flex;
    flex-direction: column;
    gap: 18px;
    max-height: 480px;
    overflow-y: auto;
    padding-right: 8px;
  }
  .feedback-cards-container::-webkit-scrollbar {
    width: 6px;
  }
  .feedback-cards-container::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
  }

  .guest-review-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 22px 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.05);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
  }
  .guest-review-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  }
  .review-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
  }
  .review-author-name {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2937;
  }
  .review-date {
    font-size: 0.76rem;
    color: #9ca3af;
    margin-top: 2px;
  }
  .review-stars {
    color: #f9a825;
    font-size: 0.88rem;
    display: flex;
    gap: 3px;
  }
  .review-comment-text {
    font-size: 0.9rem;
    color: #4b5563;
    line-height: 1.6;
  }

  /* Right Side: Share Your Experience Form Card */
  .share-feedback-card {
    background: var(--green-dark, #0d3d22);
    border-radius: 24px;
    padding: 40px 38px;
    color: #ffffff;
    box-shadow: 0 12px 36px rgba(13,61,34,0.22);
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .share-card-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.85rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 8px;
  }
  .share-card-sub {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.72);
    margin-bottom: 24px;
    line-height: 1.5;
  }

  .fb-group {
    margin-bottom: 18px;
  }
  .fb-group label {
    display: block;
    font-size: 0.84rem;
    font-weight: 600;
    color: #ffffff;
    margin-bottom: 8px;
  }
  .fb-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 12px;
    padding: 12px 16px;
    color: #ffffff;
    font-family: 'Inter', sans-serif;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.2s ease;
  }
  .fb-input::placeholder {
    color: rgba(255, 255, 255, 0.45);
  }
  .fb-input:focus {
    border-color: rgba(255, 255, 255, 0.65);
    background: rgba(255, 255, 255, 0.18);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.12);
  }
  textarea.fb-input {
    min-height: 100px;
    resize: vertical;
  }

  .star-select-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 4px;
  }
  .star-select-row .star-btn {
    font-size: 1.7rem;
    color: rgba(255, 255, 255, 0.28);
    cursor: pointer;
    transition: color 0.15s ease, transform 0.15s ease;
    user-select: none;
  }
  .star-select-row .star-btn:hover,
  .star-select-row .star-btn.hovered,
  .star-select-row .star-btn.selected {
    color: #f9a825;
  }
  .star-select-row .star-btn:hover {
    transform: scale(1.18);
  }

  .btn-submit-fb {
    width: 100%;
    background: #ffffff;
    color: var(--green-dark, #0d3d22);
    border: none;
    border-radius: 50px;
    padding: 14px 24px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.25s ease;
    margin-top: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  }
  .btn-submit-fb:hover {
    background: #f8fafc;
    transform: translateY(-2px);
    box-shadow: 0 6px 22px rgba(0,0,0,0.22);
  }

  .fb-alert-ok {
    background: rgba(39, 164, 87, 0.22);
    border: 1px solid rgba(39, 164, 87, 0.45);
    color: #86efac;
    border-radius: 10px;
    padding: 11px 16px;
    font-size: 0.86rem;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .fb-alert-err {
    background: rgba(220, 38, 38, 0.22);
    border: 1px solid rgba(220, 38, 38, 0.45);
    color: #fca5a5;
    border-radius: 10px;
    padding: 11px 16px;
    font-size: 0.86rem;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  @media (max-width: 900px) {
    .testimonials-grid {
      grid-template-columns: 1fr;
      gap: 36px;
    }
  }

  /* ── CTA ── */
  .cta-section {
    background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-dark) 100%);
    padding: 100px 5%; text-align: center; position: relative; overflow: hidden;
  }
  .cta-section::before {
    content: ''; position: absolute; inset: 0;
    background: url('images/booking-header.jpg') center/cover no-repeat;
    opacity: .12;
  }
  .cta-section > * { position: relative; }
  .cta-section .section-title { color: #fff; }
  .cta-section .section-sub { color: rgba(255,255,255,.75); margin: 0 auto 44px; }
  .cta-btn {
    display: inline-flex; align-items: center; gap: 12px;
    background: var(--gold); color: var(--green-dark);
    padding: 18px 44px; border-radius: 50px; font-size: 1.05rem; font-weight: 700;
    text-decoration: none; transition: all .25s; box-shadow: 0 6px 24px rgba(201,168,76,.45);
  }
  .cta-btn:hover { background: #e2be65; transform: translateY(-2px); box-shadow: 0 10px 32px rgba(201,168,76,.5); }

  /* ── FOOTER ── */
  footer {
    background: #060f09; color: rgba(255,255,255,.6);
    padding: 60px 5% 30px; font-size: .85rem;
  }
  .footer-grid { display: grid; grid-template-columns: 1.6fr 1fr 1fr; gap: 50px; margin-bottom: 48px; max-width: 1100px; margin-left: auto; margin-right: auto; }
  .footer-brand img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; margin-bottom: 14px; }
  .footer-brand strong { display: block; font-family: 'Playfair Display', serif; font-size: 1.1rem; color: #fff; margin-bottom: 8px; }
  .footer-brand p { line-height: 1.7; max-width: 280px; }
  .footer-col h4 { color: #fff; font-size: .88rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 18px; }
  .footer-col ul { list-style: none; }
  .footer-col ul li { margin-bottom: 10px; }
  .footer-col ul a { color: rgba(255,255,255,.55); text-decoration: none; transition: color .2s; }
  .footer-col ul a:hover { color: var(--green-accent); }
  .footer-col .contact-item { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px; }
  .footer-col .contact-item i { color: var(--green-accent); margin-top: 2px; }
  .footer-bottom {
    border-top: 1px solid rgba(255,255,255,.08); padding-top: 24px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
    max-width: 1100px; margin: 0 auto;
  }
  .footer-bottom p { font-size: .78rem; }
  .social-links { display: flex; gap: 12px; }
  .social-links a {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,.08); color: rgba(255,255,255,.6);
    display: flex; align-items: center; justify-content: center; font-size: .85rem;
    text-decoration: none; transition: all .2s;
  }
  .social-links a:hover { background: var(--green-accent); color: #fff; }

  /* ── ANIMATIONS ── */
  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .reveal { opacity: 0; transform: translateY(30px); transition: opacity .7s ease, transform .7s ease; }
  .reveal.visible { opacity: 1; transform: translateY(0); }

  /* ── RESPONSIVE ── */
  @media (max-width: 900px) {
    .nb-links { display: none; }
    .about-grid, .exp-grid { grid-template-columns: 1fr; }
    .exp-images { display: none; }
    .steps-grid { grid-template-columns: 1fr 1fr; }
    .step-card::after { display: none; }
    .gallery-grid { grid-template-columns: 1fr 1fr; }
    .g-item:nth-child(1) { grid-column: span 2; }
    .footer-grid { grid-template-columns: 1fr; gap: 36px; }
  }
  @media (max-width: 600px) {
    section { padding: 60px 5%; }
    .hero-title { white-space: normal; }
    .steps-grid { grid-template-columns: 1fr; }
    .gallery-grid { grid-template-columns: 1fr; }
    .g-item:nth-child(1) { grid-column: span 1; }
    .hero-stats { display: none; }
    .info-strip-inner { gap: 32px; }
  }
</style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar" id="mainNav">
  <a href="landing.php" class="nb-brand">
    <img src="images/<?= htmlspecialchars($logo_file) ?>" alt="Logo">
    <div class="nb-brand-txt">
      <strong>Sinulom &amp; Bolao</strong>
      <span>Cold Spring Resort</span>
    </div>
  </a>
  <ul class="nb-links">
    <li><a href="#hero">Home</a></li>
    <li><a href="#about">About</a></li>
    <li><a href="#facilities">Facilities</a></li>
    
    <li><a href="#pricing">Pricing</a></li>
    <li><a href="#testimonials">Reviews</a></li>
    <li><a href="#location">Location</a></li>
  </ul>
  <div class="nb-right">
    <?php if ($is_logged_in): ?>
      <a href="guest_dashboard.php?tab=upcoming" class="nb-btn nb-btn-outline" id="myBookingsBtn"><i class="fas fa-calendar-check"></i> My Bookings</a>
      <a href="guest_dashboard.php" class="nb-btn nb-btn-outline"><i class="fas fa-user"></i> <?= htmlspecialchars($session_name) ?></a>
      <a href="logout.php" class="nb-btn nb-btn-solid"><i class="fas fa-sign-out-alt"></i> Logout</a>
    <?php else: ?>
      <a href="login.php" class="nb-btn nb-btn-outline"><i class="fas fa-user"></i> Login</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero" id="hero">
  <div class="hero-bg"></div>
  <div class="hero-content">
    <div class="hero-eyebrow">NATURAL COLD SPRING</div>
    <h1 class="hero-title">
      Welcome to <em>Sinulom &amp; Bolao</em> Cold Spring Resort
    </h1>
    <p class="hero-subtitle">
      Escape to nature's embrace. Discover crystal-clear cold springs, lush greenery, and tranquil surroundings — your perfect sanctuary for rest and rejuvenation.
    </p>
    <div class="hero-loc-pill">
      <i class="fas fa-map-marker-alt"></i> Philippines &middot; Nature's Hidden Gem
    </div>
    <div class="hero-actions">
      <a href="public_booking.php" class="btn-hero-primary"><i class="fas fa-calendar-alt"></i> Book Now</a>
      <a href="#facilities" class="btn-hero-secondary"><i class="fas fa-compass"></i> Explore Facilities</a>
    </div>
  </div>
  <div class="hero-stats">
    <div class="hero-stat"><strong>100%</strong><span>Natural Cold Spring</span></div>
    <div class="hero-stat"><strong>10+</strong><span>Facilities</span></div>
    <div class="hero-stat"><strong>24/7</strong><span>Guest Support</span></div>
    <div class="hero-stat"><strong>⭐ 4.9</strong><span>Guest Rating</span></div>
  </div>
</section>

<!-- Wave -->
<div class="wave-divider">
  <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
    <path d="M0,40 C360,0 1080,80 1440,20 L1440,60 L0,60 Z" fill="#ffffff"/>
  </svg>
</div>

<!-- ── ABOUT ── -->
<section class="about-section" id="about">
  <div class="about-grid">
    <div class="about-img-wrap reveal">
      <img src="images/bolao.jpg" alt="Resort spring water">
      <div class="about-badge">
        <div class="about-badge-icon"><i class="fas fa-water"></i></div>
        <div>
          <strong>Natural Cold Spring</strong>
          <span>Pure spring water, year-round</span>
        </div>
      </div>
    </div>
    <div class="about-text reveal">
      <div class="section-label">About Us</div>
      <h2 class="section-title">A Sanctuary of Nature &amp; Relaxation</h2>
      <p>Welcome to Sinulom &amp; Bolao Cold Spring Resort — where the cool embrace of natural spring water meets the warmth of Filipino hospitality. Set amidst lush greenery, our resort is the perfect escape from the city's hustle.</p>
      <p>Whether you're planning a family outing, a romantic getaway, or a corporate event, we offer a range of facilities designed to make your stay truly memorable.</p>
      <div class="about-features">
        <div class="about-feat"><i class="fas fa-check-circle"></i> Crystal Clear Springs</div>
        <div class="about-feat"><i class="fas fa-check-circle"></i> Private Cottages</div>
        <div class="about-feat"><i class="fas fa-check-circle"></i> Function Halls</div>
        <div class="about-feat"><i class="fas fa-check-circle"></i> Easy Online Booking</div>
        <div class="about-feat"><i class="fas fa-check-circle"></i> Kids-Friendly</div>
        <div class="about-feat"><i class="fas fa-check-circle"></i> Scenic Views</div>
      </div>
      <a href="public_booking.php" class="btn-book-fac"><i class="fas fa-arrow-right"></i> Reserve Your Spot</a>
    </div>
  </div>
</section>

<!-- ── FACILITIES ── -->
<section class="facilities-section" id="facilities">
  <div class="section-header text-center reveal">
    <div class="section-label">Our Facilities</div>
    <h2 class="section-title">Find the Perfect Space for You</h2>
    <p class="section-sub">From cozy cottages to spacious function halls, we have everything you need for a perfect stay.</p>
  </div>
  <div class="fac-grid">
    <?php if (empty($facilities)): ?>
      <?php
      // Fallback static cards if no DB facilities
      $static_facs = [
        ['id'=>1,'name'=>'Sinulom Cottage','type'=>'cottage','capacity'=>10,'price'=>'800','img'=>'images/Sinulom Cottage 1.jpg','icon'=>'fa-home','description'=>'A spacious open-air cottage surrounded by native greenery, perfect for family picnics and day gatherings near the spring.','amenities'=>'Tables, Chairs, Shade, Water Access'],
        ['id'=>2,'name'=>'Wooden Cottage','type'=>'cottage','capacity'=>15,'price'=>'1000','img'=>'images/Wooden Cottage.jpg','icon'=>'fa-tree','description'=>'Handcrafted wooden structure offering natural ventilation and generous seating capacity for large groups.','amenities'=>'Wooden Bench, Large Table, Overhead Light'],
        ['id'=>3,'name'=>'Bamboo Cottage','type'=>'cottage','capacity'=>12,'price'=>'900','img'=>'images/Bamboo.jpg','icon'=>'fa-leaf','description'=>'Authentic bamboo craftsmanship designed to keep you cool during warm tropical afternoons.','amenities'=>'Bamboo Seats, Table, Electricity Access'],
        ['id'=>4,'name'=>'Umbrella Kubo','type'=>'kubo','capacity'=>8,'price'=>'600','img'=>'images/Umbrella Kubo.jpg','icon'=>'fa-umbrella','description'=>'Cozy umbrella-style kubo ideal for small intimate gatherings near the cold spring pool.','amenities'=>'Round Table, Integrated Benches'],
        ['id'=>5,'name'=>'Function Hall 1','type'=>'hall','capacity'=>100,'price'=>'5000','img'=>'images/fhall1.jpg','icon'=>'fa-building','description'=>'Grand function hall equipped for corporate events, weddings, and grand celebrations with audio setup options.','amenities'=>'Chairs, Tables, Sound System Ready, Stage Area'],
        ['id'=>6,'name'=>'Villa Candida','type'=>'villa','capacity'=>20,'price'=>'2500','img'=>'images/villa-candida.jpg','icon'=>'fa-star','description'=>'Premium villa with air conditioning, private restful beds, and scenic views of the resort landscape.','amenities'=>'Aircon, Beds, Private Bath, Sofa, TV'],
      ];
      foreach ($static_facs as $f): ?>
        <div class="fac-card reveal"
          data-id="<?= $f['id'] ?>"
          data-name="<?= htmlspecialchars($f['name']) ?>"
          data-type="<?= htmlspecialchars(ucfirst($f['type'])) ?>"
          data-capacity="<?= $f['capacity'] ?>"
          data-price="<?= number_format((float)$f['price'], 0) ?>"
          data-description="<?= htmlspecialchars($f['description']) ?>"
          data-amenities="<?= htmlspecialchars($f['amenities']) ?>"
          data-img="<?= htmlspecialchars($f['img']) ?>"
          data-booking-url="public_booking.php">
          <div class="fac-card-img-wrapper">
            <img class="fac-card-img" src="<?= htmlspecialchars($f['img']) ?>" alt="<?= htmlspecialchars($f['name']) ?>" onerror="this.style.display='none';this.parentElement.querySelector('.fac-card-img-placeholder').style.display='flex'">
            <div class="fac-card-img-placeholder" style="display:none"><i class="fas <?= $f['icon'] ?>"></i></div>
            <div class="fac-card-overlay">
              <span class="fac-quick-view-badge"><i class="fas fa-search-plus"></i> View Details</span>
            </div>
          </div>
          <div class="fac-card-body">
            <span class="fac-type-badge"><?= ucfirst($f['type']) ?></span>
            <div class="fac-card-name"><?= htmlspecialchars($f['name']) ?></div>
            <div class="fac-card-meta">
              <span><i class="fas fa-users"></i> Up to <?= $f['capacity'] ?> guests</span>
            </div>
            <div class="fac-card-price">₱<?= number_format((float)$f['price'], 0) ?> <small>/ booking</small></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <?php
      $type_icons = ['cottage'=>'fa-home','room'=>'fa-door-open','villa'=>'fa-star','function_hall'=>'fa-building','kubo'=>'fa-umbrella','pool'=>'fa-swimming-pool'];
      // Fallback images for facilities without image_path, keyed by name fragment or type
      $fallback_by_name = [
        'villa gracia'    => 'images/villa-gracia.jpg',
        'villa candida'   => 'images/villa-candida.jpg',
        'villa carolina'  => 'images/villa-carolina.jpg',
        'cottage 1'       => 'images/Sinulom Cottage 1.jpg',
        'sinulom cottage' => 'images/Sinulom Cottage 1.jpg',
        'umbrella kubo'   => 'images/Umbrella Kubo.jpg',
        'umbrella'        => 'images/umbrella.jpg',
        'wooden cottage'  => 'images/Wooden Cottage.jpg',
        'bamboo'          => 'images/Bamboo.jpg',
        'function hall 1' => 'images/fhall1.jpg',
        'function hall 2' => 'images/fhall2.jpg',
        'function hall 3' => 'images/fhall3.jpg',
      ];
      $fallback_by_type = [
        'cottage'       => 'images/cottage1.jpg',
        'room'          => 'images/villa-candida.jpg',
        'function_hall' => 'images/fhall1.jpg',
        'kubo'          => 'images/Umbrella Kubo.jpg',
      ];
      foreach ($facilities as $f):
        $icon = $type_icons[strtolower($f['type'])] ?? 'fa-hotel';
        // Resolve image src
        if (!empty($f['image_path'])) {
          $img_src = 'images/' . $f['image_path'];
        } else {
          $name_lc = strtolower(trim($f['name']));
          $img_src = null;
          foreach ($fallback_by_name as $key => $path) {
            if (strpos($name_lc, $key) !== false) { $img_src = $path; break; }
          }
          if (!$img_src) $img_src = $fallback_by_type[strtolower($f['type'])] ?? null;
        }

        // Build fallback description if missing
        $desc = !empty($f['description']) ? trim($f['description']) : '';
        if (empty($desc)) {
          $t = strtolower($f['type']);
          if (strpos($t, 'room') !== false || strpos($t, 'villa') !== false) {
            $desc = "Comfortable, serene accommodation equipped with cozy resting space, ideal for relaxing after a refreshing day at the cold springs.";
          } elseif (strpos($t, 'cottage') !== false) {
            $desc = "Spacious, shaded cottage offering fresh air and comfortable seating, situated near natural spring water pools for day outings.";
          } elseif (strpos($t, 'hall') !== false) {
            $desc = "Grand event space designed for large gatherings, celebrations, corporate retreats, and special functions surrounded by nature.";
          } elseif (strpos($t, 'kubo') !== false) {
            $desc = "Traditional, breezy kubo providing an intimate, comfortable shelter for small groups enjoying the resort.";
          } else {
            $desc = "A high-quality facility designed to ensure a tranquil and memorable stay at Sinulom & Bolao Cold Spring Resort.";
          }
        }
      ?>
        <div class="fac-card reveal"
          data-id="<?= htmlspecialchars($f['id']) ?>"
          data-name="<?= htmlspecialchars($f['name']) ?>"
          data-type="<?= htmlspecialchars(ucfirst(str_replace('_',' ', $f['type']))) ?>"
          data-capacity="<?= (int)$f['capacity'] ?>"
          data-price="<?= number_format((float)$f['price'], 0) ?>"
          data-description="<?= htmlspecialchars($desc) ?>"
          data-amenities="<?= htmlspecialchars($f['amenities'] ?? '') ?>"
          data-img="<?= htmlspecialchars($img_src ?? '') ?>"
          data-booking-url="public_booking.php?facility=<?= $f['id'] ?>">
          <div class="fac-card-img-wrapper">
            <?php if ($img_src): ?>
              <img class="fac-card-img" src="<?= htmlspecialchars($img_src) ?>" alt="<?= htmlspecialchars($f['name']) ?>" onerror="this.style.display='none';this.parentElement.querySelector('.fac-card-img-placeholder').style.display='flex'">
              <div class="fac-card-img-placeholder" style="display:none"><i class="fas <?= $icon ?>"></i></div>
            <?php else: ?>
              <div class="fac-card-img-placeholder"><i class="fas <?= $icon ?>"></i></div>
            <?php endif; ?>
            <div class="fac-card-overlay">
              <span class="fac-quick-view-badge"><i class="fas fa-search-plus"></i> View Details</span>
            </div>
          </div>
          <div class="fac-card-body">
            <span class="fac-type-badge"><?= ucfirst(str_replace('_',' ', htmlspecialchars($f['type']))) ?></span>
            <div class="fac-card-name"><?= htmlspecialchars($f['name']) ?></div>
            <div class="fac-card-meta">
              <span><i class="fas fa-users"></i> Up to <?= (int)$f['capacity'] ?> guests</span>
              <?php if (!empty($f['amenities'])): ?>
                <span><i class="fas fa-concierge-bell"></i> Amenities included</span>
              <?php endif; ?>
            </div>
            <div class="fac-card-price">₱<?= number_format((float)$f['price'], 0) ?> <small>/ booking</small></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div style="text-align:center;margin-top:44px" class="reveal">
    <a href="public_booking.php" class="btn-hero-primary" style="display:inline-flex">
      <i class="fas fa-th-large"></i> View All &amp; Book Now
    </a>
  </div>
</section>

<!-- ── EXPERIENCE ── -->
<section class="experience-section">
  <div class="exp-grid">
    <div class="exp-text reveal">
      <div class="section-label">The Experience</div>
      <h2 class="section-title" style="color:#fff">Why Guests Love Us</h2>
      <p class="section-sub">We go beyond just a place to stay — we craft memories that last a lifetime.</p>
      <div class="exp-perks">
        <div class="exp-perk">
          <div class="exp-perk-icon"><i class="fas fa-water"></i></div>
          <div class="exp-perk-txt">
            <strong>Natural Cold Spring Water</strong>
            <span>Experience the refreshing, untreated spring water that flows naturally through our resort year-round.</span>
          </div>
        </div>
        <div class="exp-perk">
          <div class="exp-perk-icon"><i class="fas fa-utensils"></i></div>
          <div class="exp-perk-txt">
            <strong>In-Resort Bar &amp; Dining</strong>
            <span>Enjoy fresh food and cool drinks at our resort bar, surrounded by nature and good vibes.</span>
          </div>
        </div>
        <div class="exp-perk">
          <div class="exp-perk-icon"><i class="fas fa-shield-alt"></i></div>
          <div class="exp-perk-txt">
            <strong>Safe &amp; Secure Environment</strong>
            <span>Our trained staff ensures your safety and comfort throughout your entire visit.</span>
          </div>
        </div>
        <div class="exp-perk">
          <div class="exp-perk-icon"><i class="fas fa-leaf"></i></div>
          <div class="exp-perk-txt">
            <strong>Eco-Friendly &amp; Serene</strong>
            <span>Embrace the tranquil atmosphere of unspoiled nature — perfect for unwinding and reconnecting.</span>
          </div>
        </div>
      </div>
    </div>
    <div class="exp-images reveal">
      <div class="exp-img-tall"><img src="images/bolao.jpg" alt="Bolao cold spring"></div>
      <div class="exp-img-sm"><img src="images/bar.jpg" alt="Resort bar"></div>
      <div class="exp-img-sm"><img src="images/cottage1.jpg" alt="Cottage"></div>
    </div>
  </div>
</section>



<!-- ── PRICING / ENTRANCE RATES ── -->
<section class="pricing-section" id="pricing">
  <div class="pricing-header reveal">
    <div class="section-label"><i class="fas fa-tags" style="margin-right:8px"></i>Pricing</div>
    <h2>Entrance Rates</h2>
    <p>Affordable rates for an unforgettable experience. Choose the package that suits your group best.</p>
  </div>

  <div class="pricing-grid">

    <!-- Sinulom Falls -->
    <div class="pricing-card sinulom reveal">
      <div class="pricing-card-header">
        <div class="pricing-card-icon"><i class="fas fa-water"></i></div>
        <div class="pricing-card-name">Sinulom Falls</div>
        <div class="pricing-card-sub">Waterfall Experience</div>
      </div>
      <div class="pricing-card-body">
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Adults</strong><span>11 years &amp; above</span></div>
          <div class="pricing-row-price">&#8369;110</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>PWD / Seniors</strong><span>With valid ID</span></div>
          <div class="pricing-row-price">&#8369;90</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Children</strong><span>6–10 years old</span></div>
          <div class="pricing-row-price">&#8369;60</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Young Children</strong><span>5 years &amp; below</span></div>
          <div class="pricing-row-price free">FREE</div>
        </div>
        <div class="pricing-features">
          <div class="pricing-feature">Waterfall access</div>
          <div class="pricing-feature">Swimming area</div>
          <div class="pricing-feature">Picnic spots</div>
          <div class="pricing-feature">All-day access</div>
        </div>
      </div>
    </div>

    <!-- Combo Package -->
    <div class="pricing-card combo reveal">
      <div class="pricing-card-header">
        <div class="pricing-popular-badge">Most Popular</div>
        <div class="pricing-card-icon"><i class="fas fa-crown"></i></div>
        <div class="pricing-card-name">Combo Package</div>
        <div class="pricing-card-sub">Falls + Cold Spring</div>
      </div>
      <div class="pricing-card-body">
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Adults</strong><span>11 years &amp; above</span></div>
          <div class="pricing-row-price">&#8369;160</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>PWD / Seniors</strong><span>With valid ID</span></div>
          <div class="pricing-row-price">&#8369;130</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Children</strong><span>6–10 years old</span></div>
          <div class="pricing-row-price">&#8369;85</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Young Children</strong><span>5 years &amp; below</span></div>
          <div class="pricing-row-price free">FREE</div>
        </div>
        <div class="pricing-features">
          <div class="pricing-feature">Both Falls &amp; Spring</div>
          <div class="pricing-feature">Full facility access</div>
          <div class="pricing-feature">All amenities included</div>
          <div class="pricing-feature">Unlimited all-day access</div>
        </div>
      </div>
    </div>

    <!-- Bolao Cold Spring -->
    <div class="pricing-card bolao reveal">
      <div class="pricing-card-header">
        <div class="pricing-card-icon"><i class="fas fa-snowflake"></i></div>
        <div class="pricing-card-name">Bolao Cold Spring</div>
        <div class="pricing-card-sub">Refreshing Spring Water</div>
      </div>
      <div class="pricing-card-body">
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Adults</strong><span>11 years &amp; above</span></div>
          <div class="pricing-row-price">&#8369;110</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>PWD / Seniors</strong><span>With valid ID</span></div>
          <div class="pricing-row-price">&#8369;90</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Children</strong><span>6–10 years old</span></div>
          <div class="pricing-row-price">&#8369;60</div>
        </div>
        <div class="pricing-row">
          <div class="pricing-row-label"><strong>Young Children</strong><span>5 years &amp; below</span></div>
          <div class="pricing-row-price free">FREE</div>
        </div>
        <div class="pricing-features">
          <div class="pricing-feature">Cold spring access</div>
          <div class="pricing-feature">Natural spring water</div>
          <div class="pricing-feature">Relaxation areas</div>
          <div class="pricing-feature">All-day access</div>
        </div>
      </div>
    </div>

  </div>
  <p class="pricing-footnote">* Rates may vary on holidays and peak seasons. Contact us for group discounts.</p>
</section>

<!-- ── TESTIMONIALS & GUEST FEEDBACK ── -->
<section class="testimonials-section" id="testimonials">
  <div class="testimonials-container">
    
    <div class="testimonials-header reveal">
      <div class="testimonials-badge">TESTIMONIALS</div>
      <h2 class="testimonials-title">Guest Feedback</h2>
      <p class="testimonials-sub">
        Read what our guests have to say about their experience at Sinulom &amp; Bolao Cold Spring Resort.
      </p>
    </div>

    <div class="testimonials-grid reveal">
      
      <!-- Left Column: Reviews / Empty State -->
      <div class="testimonials-left-col">
        <?php if (empty($feedback_list)): ?>
          <div class="feedback-empty-wrap">
            <div class="empty-icon-box">
              <i class="fas fa-comment-slash"></i>
            </div>
            <div class="empty-text">No reviews yet. Be the first!</div>
          </div>
        <?php else: ?>
          <div class="feedback-cards-container">
            <?php foreach ($feedback_list as $fb): ?>
              <div class="guest-review-card">
                <div class="review-card-top">
                  <div>
                    <div class="review-author-name"><?= htmlspecialchars($fb['guest_name'] ?: 'Anonymous Guest') ?></div>
                    <?php if (!empty($fb['created_at'])): ?>
                      <div class="review-date"><?= date('F j, Y', strtotime($fb['created_at'])) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="review-stars">
                    <?php 
                      $r_val = intval($fb['rating']);
                      for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $r_val ? '<i class="fas fa-star"></i>' : '<i class="far fa-star" style="opacity:0.4;"></i>';
                      }
                    ?>
                  </div>
                </div>
                <div class="review-comment-text">
                  <?= nl2br(htmlspecialchars($fb['comment'])) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Right Column: Share Your Experience Card -->
      <div class="share-feedback-card">
        <h3 class="share-card-title">Share Your Experience</h3>
        <p class="share-card-sub">Your feedback helps us improve and inspires other guests.</p>

        <?php if ($feedback_success): ?>
          <div class="fb-alert-ok">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($feedback_success) ?>
          </div>
        <?php endif; ?>

        <?php if ($feedback_error): ?>
          <div class="fb-alert-err">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($feedback_error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="#testimonials" id="guestFeedbackForm">
          <input type="hidden" name="rating" id="fbRatingInput" value="0">

          <div class="fb-group">
            <label>Your Name</label>
            <input type="text" name="guest_name" class="fb-input" placeholder="e.g. Juan dela Cruz" required>
          </div>

          <div class="fb-group">
            <label>Your Rating</label>
            <div class="star-select-row" id="fbStarRating">
              <span class="star-btn" data-val="1">&#9733;</span>
              <span class="star-btn" data-val="2">&#9733;</span>
              <span class="star-btn" data-val="3">&#9733;</span>
              <span class="star-btn" data-val="4">&#9733;</span>
              <span class="star-btn" data-val="5">&#9733;</span>
            </div>
          </div>

          <div class="fb-group">
            <label>Your Review</label>
            <textarea name="comment" class="fb-input" placeholder="Share your experience..." required></textarea>
          </div>

          <button type="submit" name="submit_guest_feedback" class="btn-submit-fb">
            <i class="fas fa-paper-plane"></i> Submit Review
          </button>
        </form>
      </div>

    </div>

  </div>
</section>

<!-- ── RESORT LOCATION & MAP ── -->
<section class="resort-location-section" id="location">
  <div class="resort-loc-container">
    <div class="resort-loc-grid reveal">
      
      <!-- Left Card: Resort Information -->
      <div class="resort-info-card">
        <h2 class="resort-info-title">Resort Information</h2>
        <p class="resort-info-sub">We are located in the beautiful mountains of Tignapoloan, Cagayan de Oro City.</p>
        
        <div class="resort-info-list">
          <div class="resort-info-item">
            <div class="resort-info-icon">
              <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="resort-info-text">
              <strong>Location</strong>
              <span>Sinulom &amp; Bolao Cold Spring Resort</span>
              <span>Philippines</span>
            </div>
          </div>
          
          <div class="resort-info-item">
            <div class="resort-info-icon">
              <i class="fas fa-phone-alt"></i>
            </div>
            <div class="resort-info-text">
              <strong>Phone</strong>
              <span>+63 912 345 6789</span>
              <span>+63 998 765 4321</span>
            </div>
          </div>
          
          <div class="resort-info-item">
            <div class="resort-info-icon">
              <i class="fas fa-envelope"></i>
            </div>
            <div class="resort-info-text">
              <strong>Email</strong>
              <span>info@sinulombolao.com</span>
              <span>reservations@sinulombolao.com</span>
            </div>
          </div>
          
          <div class="resort-info-item">
            <div class="resort-info-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="resort-info-text">
              <strong>Operating Hours</strong>
              <span>Monday – Sunday</span>
              <span>8:00 AM – 5:00 PM</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Right Card: Map -->
      <div class="resort-map-card">
        <iframe 
          src="https://maps.google.com/maps?q=Sinulom%20%26%20Bolao%20Cold%20Spring%20Resort%20Tignapoloan%20Cagayan%20de%20Oro&t=&z=14&ie=UTF8&iwloc=&output=embed"
          width="100%" 
          height="100%" 
          style="border:0;" 
          allowfullscreen="" 
          loading="lazy" 
          referrerpolicy="no-referrer-when-downgrade"
          title="Resort Location Map">
        </iframe>
      </div>

    </div>

    <!-- Get Directions Button -->
    <div class="directions-btn-wrap reveal">
      <a href="https://www.google.com/maps/dir/?api=1&destination=Sinulom+%26+Bolao+Cold+Spring+Resort+Tignapoloan+Cagayan+de+Oro" target="_blank" rel="noopener noreferrer" class="btn-get-directions">
        <i class="fas fa-location-arrow"></i> Get Directions
      </a>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section">
  <div class="section-label" style="color:var(--gold);justify-content:center;display:inline-flex;margin-bottom:16px">
    <i class="fas fa-calendar-check" style="margin-right:8px"></i> Ready to Escape?
  </div>
  <h2 class="section-title">Book Your Refreshing Retreat Today</h2>
  <p class="section-sub">Secure your spot at Sinulom &amp; Bolao Cold Spring Resort. Limited slots available — book early!</p>
  <a href="public_booking.php" class="cta-btn"><i class="fas fa-calendar-check"></i> Reserve Now – It's Easy!</a>
</section>

<!-- ── FOOTER ── -->
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <img src="images/<?= htmlspecialchars($logo_file) ?>" alt="Logo">
      <strong>Sinulom &amp; Bolao Cold Spring Resort</strong>
      <p>Your premier cold spring destination in the Philippines. Experience nature, relaxation, and Filipino hospitality at its finest.</p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="#hero">Home</a></li>
        <li><a href="#about">About Us</a></li>
        <li><a href="#facilities">Facilities</a></li>
        
        <li><a href="#testimonials">Guest Reviews</a></li>
        <li><a href="#location">Location</a></li>
        <li><a href="public_booking.php">Book Now</a></li>
        <li><a href="login.php">Staff Login</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <div class="contact-item"><i class="fas fa-phone"></i><span><?= htmlspecialchars($contact_info) ?></span></div>
      <div class="contact-item"><i class="fas fa-clock"></i><span><?= htmlspecialchars($business_hours) ?></span></div>
      <div class="contact-item"><i class="fas fa-envelope"></i><span>info@sinulombolao.com</span></div>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> Sinulom &amp; Bolao Cold Spring Resort. All rights reserved.</p>
    <div class="social-links">
      <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
      <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
      <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
    </div>
  </div>
</footer>

<!-- ── FACILITY DETAIL ZOOM MODAL ── -->
<div class="fac-modal-backdrop" id="facDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="fac-modal-container">
    <button class="fac-modal-close-btn" onclick="closeFacModal()" aria-label="Close Modal">
      <i class="fas fa-times"></i>
    </button>
    <div class="fac-modal-header">
      <img id="facModalImg" src="" alt="Facility Preview" class="fac-modal-img">
      <div class="fac-modal-img-gradient"></div>
      <div class="fac-modal-badge-row">
        <span class="fac-modal-type-badge" id="facModalType">Room</span>
      </div>
    </div>
    <div class="fac-modal-body">
      <h3 class="fac-modal-title" id="facModalName">Facility Name</h3>
      <div class="fac-modal-meta-grid">
        <div class="fac-modal-meta-item">
          <i class="fas fa-users"></i>
          <span id="facModalCapacity">Up to 5 guests</span>
        </div>
        <div class="fac-modal-meta-item">
          <i class="fas fa-shield-alt"></i>
          <span>Available for Booking</span>
        </div>
        <div class="fac-modal-price-tag">
          <span id="facModalPrice">₱0</span> <small>/ booking</small>
        </div>
      </div>

      <div class="fac-modal-section-title">
        <i class="fas fa-info-circle"></i> Facility Details &amp; Description
      </div>
      <p class="fac-modal-desc" id="facModalDesc">
        Facility details will be shown here.
      </p>

      <div class="fac-modal-section-title">
        <i class="fas fa-concierge-bell"></i> Included Amenities
      </div>
      <div class="fac-modal-amenities" id="facModalAmenities">
        <!-- Amenity chips rendered dynamically -->
      </div>
    </div>
  </div>
</div>

<script>
  // Navbar scroll effect
  const nav = document.getElementById('mainNav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 60);
  });

  // Scroll reveal
  const reveals = document.querySelectorAll('.reveal');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((e, i) => {
      if (e.isIntersecting) {
        e.target.style.transitionDelay = (i % 4) * 0.1 + 's';
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });
  reveals.forEach(r => observer.observe(r));

  // Testimonials Interactive Star Rating
  const fbStars = document.querySelectorAll('#fbStarRating .star-btn');
  const fbRatingInput = document.getElementById('fbRatingInput');

  if (fbStars.length > 0) {
    fbStars.forEach(star => {
      const val = parseInt(star.getAttribute('data-val'));

      star.addEventListener('mouseenter', () => {
        fbStars.forEach((s, i) => {
          s.classList.toggle('hovered', i < val);
        });
      });

      star.addEventListener('mouseleave', () => {
        fbStars.forEach(s => s.classList.remove('hovered'));
      });

      star.addEventListener('click', () => {
        fbRatingInput.value = val;
        fbStars.forEach((s, i) => {
          s.classList.toggle('selected', i < val);
        });
      });
    });
  }

  const fbForm = document.getElementById('guestFeedbackForm');
  if (fbForm) {
    fbForm.addEventListener('submit', function(e) {
      if (!fbRatingInput.value || fbRatingInput.value === '0') {
        e.preventDefault();
        alert('Please click to select a star rating (1 to 5 stars) before submitting!');
      }
    });
  }

  // ── Facility Detail Zoom Modal JS ──
  const facModal = document.getElementById('facDetailModal');
  const facModalImg = document.getElementById('facModalImg');
  const facModalType = document.getElementById('facModalType');
  const facModalName = document.getElementById('facModalName');
  const facModalCapacity = document.getElementById('facModalCapacity');
  const facModalPrice = document.getElementById('facModalPrice');
  const facModalDesc = document.getElementById('facModalDesc');
  const facModalAmenities = document.getElementById('facModalAmenities');
  const facModalBookBtn = document.getElementById('facModalBookBtn');

  function openFacModal(card) {
    if (!facModal || !card) return;
    
    const name = card.getAttribute('data-name') || 'Facility Details';
    const type = card.getAttribute('data-type') || 'Facility';
    const cap = parseInt(card.getAttribute('data-capacity') || '0');
    const price = card.getAttribute('data-price') || '0';
    const desc = card.getAttribute('data-description') || 'No description available.';
    const amenitiesStr = card.getAttribute('data-amenities') || '';
    const img = card.getAttribute('data-img') || '';
    const bookUrl = card.getAttribute('data-booking-url') || 'public_booking.php';

    if (facModalName) facModalName.textContent = name;
    if (facModalType) facModalType.textContent = type;
    if (facModalCapacity) facModalCapacity.textContent = cap > 0 ? `Up to ${cap} guests` : 'Flexible capacity';
    if (facModalPrice) facModalPrice.textContent = `₱${price}`;
    if (facModalDesc) facModalDesc.textContent = desc;
    if (facModalBookBtn) {
      facModalBookBtn.href = bookUrl;
      facModalBookBtn.innerHTML = `<i class="fas fa-calendar-check"></i> Book Now – ₱${price}`;
    }

    if (facModalImg) {
      if (img) {
        facModalImg.src = img;
        facModalImg.alt = name;
        facModalImg.style.display = 'block';
      } else {
        facModalImg.style.display = 'none';
      }
    }

    if (facModalAmenities) {
      facModalAmenities.innerHTML = '';
      if (amenitiesStr.trim()) {
        const items = amenitiesStr.split(/,|\n|;/).map(i => i.trim()).filter(i => i.length > 0);
        items.forEach(item => {
          const chip = document.createElement('span');
          chip.className = 'fac-amenity-chip';
          chip.innerHTML = `<i class="fas fa-check-circle"></i> ${item}`;
          facModalAmenities.appendChild(chip);
        });
      } else {
        facModalAmenities.innerHTML = '<span style="color:var(--gray-600);font-size:0.88rem;font-style:italic">Basic resort amenities included</span>';
      }
    }

    facModal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeFacModal() {
    if (!facModal) return;
    facModal.classList.remove('active');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.fac-card').forEach(card => {
    card.addEventListener('click', function(e) {
      e.preventDefault();
      openFacModal(this);
    });
  });

  if (facModal) {
    facModal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeFacModal();
      }
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && facModal && facModal.classList.contains('active')) {
      closeFacModal();
    }
  });

  // Auto-open modal if url query contains facility parameter
  const urlParams = new URLSearchParams(window.location.search);
  const targetFacId = urlParams.get('facility');
  if (targetFacId) {
    const targetCard = document.querySelector(`.fac-card[data-id="${targetFacId}"]`);
    if (targetCard) {
      setTimeout(() => {
        targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        openFacModal(targetCard);
      }, 400);
    }
  }
</script>
</body>
</html>