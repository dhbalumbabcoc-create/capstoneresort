<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_role('owner');

// Auto-add vat_rate column if missing (migration)
$conn->query("ALTER TABLE site_settings ADD COLUMN IF NOT EXISTS vat_rate DECIMAL(5,2) NOT NULL DEFAULT 12.00");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL");

// Handle POST for preferences, business info, and profile update BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['theme'])) {
        $_SESSION['theme'] = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    }
    if (isset($_POST['language'])) {
        $_SESSION['language'] = $_POST['language'] === 'fil' ? 'fil' : 'en';
    }
    // VAT Rate update
    if (isset($_POST['vat_update'])) {
        $vat_rate = floatval($_POST['vat_rate']);
        if ($vat_rate >= 0 && $vat_rate <= 100) {
            $conn->query("UPDATE site_settings SET vat_rate='$vat_rate', updated_at=NOW() WHERE id=1");
        }
        header('Location: settings.php?success=1&section=vat&tab=business');
        exit;
    }
    if (isset($_POST['resort_name'], $_POST['tagline'], $_POST['contact_info'], $_POST['business_hours'])) {
        $resort_name = $conn->real_escape_string(trim($_POST['resort_name']));
        $tagline = $conn->real_escape_string(trim($_POST['tagline']));
        $contact_info = $conn->real_escape_string(trim($_POST['contact_info']));
        $business_hours = $conn->real_escape_string(trim($_POST['business_hours']));
        $conn->query("UPDATE site_settings SET resort_name='$resort_name', tagline='$tagline', contact_info='$contact_info', business_hours='$business_hours', updated_at=NOW() WHERE id=1");
        header('Location: settings.php?success=1&tab=business');
        exit;
    }
    // Profile update
    if (isset($_POST['profile_update'])) {
        $first_name = $conn->real_escape_string(trim($_POST['first_name']));
        $last_name  = $conn->real_escape_string(trim($_POST['last_name']));
        $email      = $conn->real_escape_string(trim($_POST['email']));
        $phone      = $conn->real_escape_string(trim($_POST['phone']));
        $address    = $conn->real_escape_string(trim($_POST['address']));
        $update_photo = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/profile_photos/';
            if (!file_exists($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $filename = 'owner_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
                $update_photo = ", profile_photo='$filename'";
            }
        }
        $update_pass = '';
        if (!empty($_POST['new_password'])) {
            $new_password = $conn->real_escape_string(trim($_POST['new_password']));
            $update_pass = ", password='$new_password'";
        }
        $conn->query("UPDATE users SET first_name='$first_name', last_name='$last_name', email='$email', phone='$phone', address='$address' $update_photo $update_pass WHERE id=" . intval($_SESSION['user_id']));
        header('Location: settings.php?success=1&tab=profile');
        exit;
    }
    // Preferences update
    if (isset($_POST['pref_update'])) {
        header('Location: settings.php?success=1&tab=preferences');
        exit;
    }
    header('Location: settings.php?success=1');
    exit;
}

$user = get_user_info($_SESSION['user_id'], $conn);
$biz  = $conn->query("SELECT * FROM site_settings WHERE id=1")->fetch_assoc();

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
$success    = isset($_GET['success']);
$section    = $_GET['section'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — Owner Panel</title>
    <meta name="description" content="Manage your profile, business info, VAT, notifications, and system preferences.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ─────────────────────────────────────────
   SETTINGS PAGE — PREMIUM REDESIGN
───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

body {
    background: #f0f4f8;
    font-family: 'Inter', sans-serif;
    color: #1e293b;
}

/* ── Page Shell ── */
.settings-shell {
    padding: 32px 36px 60px;
    max-width: 1100px;
}

/* ── Page Header ── */
.settings-page-header {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 36px;
}
.settings-page-header .page-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.35rem;
    box-shadow: 0 4px 14px rgba(27,125,58,.35);
    flex-shrink: 0;
}
.settings-page-header .page-meta h1 {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    line-height: 1.2;
}
.settings-page-header .page-meta p {
    margin: 4px 0 0;
    font-size: .85rem;
    color: #64748b;
}

/* ── Toast notification ── */
.settings-toast {
    position: fixed;
    bottom: 28px; right: 28px;
    background: #1B7D3A;
    color: #fff;
    border-radius: 12px;
    padding: 14px 22px;
    display: flex; align-items: center; gap: 10px;
    font-size: .9rem; font-weight: 600;
    box-shadow: 0 8px 28px rgba(0,0,0,.18);
    z-index: 9999;
    animation: toastIn .4s cubic-bezier(.175,.885,.32,1.275) both;
    cursor: pointer;
}
@keyframes toastIn {
    from { opacity:0; transform: translateY(20px) scale(.95); }
    to   { opacity:1; transform: translateY(0) scale(1); }
}
@keyframes toastOut {
    from { opacity:1; transform: translateY(0) scale(1); }
    to   { opacity:0; transform: translateY(20px) scale(.95); }
}
.settings-toast.hiding { animation: toastOut .3s ease forwards; }

/* ── Tab Navigation ── */
.settings-tabs {
    display: flex;
    gap: 6px;
    background: #fff;
    border-radius: 16px;
    padding: 6px;
    margin-bottom: 28px;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
    flex-wrap: wrap;
}
.settings-tab-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: #64748b;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.settings-tab-btn i { font-size: .85rem; }
.settings-tab-btn:hover { background: #f1f5f9; color: #334155; }
.settings-tab-btn.active {
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    color: #fff;
    box-shadow: 0 3px 10px rgba(27,125,58,.3);
}

/* ── Settings Card ── */
.settings-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    overflow: hidden;
    margin-bottom: 24px;
    border: 1px solid #e8edf3;
}
.settings-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 22px 28px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafcfe;
}
.settings-card-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.settings-card-icon {
    width: 42px; height: 42px;
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.icon-green  { background: #dcfce7; color: #15803d; }
.icon-blue   { background: #dbeafe; color: #1d4ed8; }
.icon-purple { background: #f3e8ff; color: #7c3aed; }
.icon-amber  { background: #fef3c7; color: #b45309; }
.icon-red    { background: #fee2e2; color: #dc2626; }
.icon-slate  { background: #f1f5f9; color: #475569; }

.settings-card-header h2 {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.settings-card-header p {
    font-size: .78rem;
    color: #94a3b8;
    margin: 2px 0 0;
}
.settings-card-body { padding: 28px; }

/* ── Form elements ── */
.form-group { margin-bottom: 20px; }
.form-label-styled {
    display: block;
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    margin-bottom: 7px;
}
.form-control-styled {
    width: 100%;
    padding: 11px 15px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: .9rem;
    color: #1e293b;
    background: #f8fafc;
    transition: border-color .2s, box-shadow .2s, background .2s;
    outline: none;
}
.form-control-styled:focus {
    border-color: #27A457;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(39,164,87,.12);
}
.form-control-styled::placeholder { color: #cbd5e1; }

select.form-control-styled { cursor: pointer; }

.input-icon-wrap { position: relative; }
.input-icon-wrap .input-icon {
    position: absolute;
    left: 13px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .85rem;
    pointer-events: none;
}
.input-icon-wrap .form-control-styled { padding-left: 38px; }

/* ── Buttons ── */
.btn-primary-styled {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px;
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    font-weight: 700;
    cursor: pointer;
    transition: transform .15s, box-shadow .2s, filter .2s;
    box-shadow: 0 3px 12px rgba(27,125,58,.3);
}
.btn-primary-styled:hover {
    filter: brightness(1.06);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(27,125,58,.35);
}
.btn-primary-styled:active { transform: translateY(0); }

.btn-outline-styled {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px;
    background: transparent;
    color: #1B7D3A;
    border: 1.5px solid #1B7D3A;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}
.btn-outline-styled:hover {
    background: #1B7D3A;
    color: #fff;
}

.btn-danger-styled {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px;
    background: transparent;
    color: #dc2626;
    border: 1.5px solid #fca5a5;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}
.btn-danger-styled:hover { background: #dc2626; color: #fff; border-color: #dc2626; }

/* ── Profile Photo ── */
.avatar-upload-wrap {
    display: flex;
    align-items: center;
    gap: 22px;
    margin-bottom: 28px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 14px;
    border: 1.5px dashed #e2e8f0;
}
.avatar-preview {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.9rem; font-weight: 800;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(27,125,58,.3);
    border: 3px solid #fff;
}
.avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
.avatar-upload-meta h3 { font-size: .95rem; font-weight: 700; color: #1e293b; margin: 0 0 4px; }
.avatar-upload-meta p  { font-size: .78rem; color: #94a3b8; margin: 0 0 10px; }
.avatar-file-input { display: none; }

/* ── Two-column grid ── */
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 640px) { .form-row-2 { grid-template-columns: 1fr; } }

/* ── VAT display ── */
.vat-display-card {
    display: flex; align-items: center; gap: 20px;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1.5px solid #86efac;
    border-radius: 16px;
    padding: 22px 26px;
}
.vat-display-icon {
    width: 60px; height: 60px;
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.5rem;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(27,125,58,.3);
}
.vat-display-info .label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #16a34a; }
.vat-display-info .value { font-size: 2.6rem; font-weight: 900; color: #0f172a; line-height: 1.1; }
.vat-display-info .sub   { font-size: .78rem; color: #64748b; margin-top: 2px; }

/* ── Toggle Switches ── */
.toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid #f1f5f9;
}
.toggle-row:last-child { border-bottom: none; }
.toggle-row-meta h4 { font-size: .9rem; font-weight: 600; color: #1e293b; margin: 0; }
.toggle-row-meta p  { font-size: .78rem; color: #94a3b8; margin: 3px 0 0; }
.toggle-switch { position: relative; width: 46px; height: 25px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer;
    inset: 0; background: #cbd5e1;
    border-radius: 25px; transition: .3s;
}
.toggle-slider::before {
    content: '';
    position: absolute;
    width: 19px; height: 19px;
    background: #fff;
    border-radius: 50%;
    left: 3px; bottom: 3px;
    transition: .3s;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.toggle-switch input:checked + .toggle-slider { background: #27A457; }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(21px); }

/* ── Backup Buttons ── */
.backup-btn-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; }
.backup-action-btn {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 20px 16px;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    background: #fafcfe;
    cursor: pointer;
    transition: all .2s;
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    color: #475569;
}
.backup-action-btn:hover { border-color: #27A457; background: #f0fdf4; color: #1B7D3A; transform: translateY(-2px); }
.backup-action-btn.danger:hover { border-color: #dc2626; background: #fef2f2; color: #dc2626; }
.backup-action-btn i { font-size: 1.6rem; }
.backup-action-btn span { font-size: .82rem; font-weight: 700; text-align: center; }

/* ── Security item ── */
.security-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 0;
    border-bottom: 1px solid #f1f5f9;
}
.security-item:last-child { border-bottom: none; }
.security-item-meta h4 { font-size: .9rem; font-weight: 600; color: #1e293b; margin: 0; }
.security-item-meta p  { font-size: .78rem; color: #94a3b8; margin: 3px 0 0; }
.badge-coming-soon {
    font-size: .7rem; font-weight: 700;
    padding: 4px 10px; border-radius: 20px;
    background: #f1f5f9; color: #94a3b8;
    white-space: nowrap;
}

/* ── Integration item ── */
.integration-item {
    display: flex; align-items: center; gap: 14px;
    padding: 16px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    margin-bottom: 12px;
    background: #fafcfe;
    transition: border-color .2s;
}
.integration-item:last-child { margin-bottom: 0; }
.integration-item:hover { border-color: #93c5fd; }
.integration-icon {
    width: 44px; height: 44px;
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.integration-meta { flex: 1; }
.integration-meta h4 { font-size: .9rem; font-weight: 700; color: #1e293b; margin: 0; }
.integration-meta p  { font-size: .78rem; color: #94a3b8; margin: 2px 0 0; }

/* ── Tab panels ── */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── Password strength ── */
.pw-strength-bar-wrap {
    height: 5px; background: #e2e8f0;
    border-radius: 5px; margin-top: 8px; overflow: hidden;
}
.pw-strength-bar { height: 100%; border-radius: 5px; width: 0; transition: width .3s, background .3s; }

/* ── Theme selector ── */
.theme-selector { display: flex; gap: 12px; flex-wrap: wrap; }
.theme-option {
    flex: 1; min-width: 130px;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 14px;
    cursor: pointer;
    transition: all .2s;
    text-align: center;
    position: relative;
}
.theme-option:hover { border-color: #93c5fd; }
.theme-option.selected { border-color: #27A457; background: #f0fdf4; }
.theme-option.selected::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute; top: 8px; right: 10px;
    color: #27A457; font-size: .75rem;
}
.theme-swatch { width: 100%; height: 52px; border-radius: 9px; margin-bottom: 8px; }
.theme-light-swatch { background: linear-gradient(135deg, #f8fafc 40%, #e2e8f0 100%); border: 1px solid #e2e8f0; }
.theme-dark-swatch  { background: linear-gradient(135deg, #1e293b 40%, #0f172a 100%); }
.theme-option-label { font-size: .82rem; font-weight: 700; color: #475569; }
.theme-option input { position: absolute; opacity: 0; pointer-events: none; }

/* ── Modal overlay ── */
.vat-modal-backdrop {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.5);
    backdrop-filter: blur(4px);
    z-index: 9000;
    align-items: center; justify-content: center;
}
.vat-modal-backdrop.open { display: flex; }
.vat-modal-box {
    background: #fff;
    border-radius: 22px;
    width: 460px; max-width: 95vw;
    box-shadow: 0 24px 64px rgba(0,0,0,.22);
    animation: modalIn .3s cubic-bezier(.175,.885,.32,1.275) both;
}
@keyframes modalIn {
    from { opacity:0; transform: scale(.9) translateY(12px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}
.vat-modal-head {
    padding: 22px 26px;
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    border-radius: 22px 22px 0 0;
    display: flex; align-items: center; justify-content: space-between;
}
.vat-modal-head h3 { color: #fff; font-size: 1.05rem; font-weight: 700; margin: 0; }
.vat-modal-close {
    background: rgba(255,255,255,.2); border: none;
    color: #fff; width: 30px; height: 30px;
    border-radius: 50%; cursor: pointer; font-size: .85rem;
    display: flex; align-items: center; justify-content: center;
    transition: background .2s;
}
.vat-modal-close:hover { background: rgba(255,255,255,.35); }
.vat-modal-body { padding: 26px; }
.vat-preview-box {
    background: #fefce8;
    border: 1px solid #fde68a;
    border-radius: 12px;
    padding: 14px 18px;
    font-size: .82rem;
    color: #78350f;
    margin-top: 18px;
}
.vat-modal-foot {
    padding: 18px 26px;
    border-top: 1px solid #f1f5f9;
    display: flex; gap: 10px; justify-content: flex-end;
}

</style>
</head>

<body>
<div class="main-container">
    <?php require_once '../includes/owner_sidebar.php'; ?>
</div>

<div class="content">
<div class="settings-shell">

    <!-- ── Page Header ── -->
    <div class="settings-page-header">
        <div class="page-icon"><i class="fas fa-cog"></i></div>
        <div class="page-meta">
            <h1>Settings</h1>
            <p>Manage your account, business info, and system preferences</p>
        </div>
    </div>

    <!-- ── Tab Navigation ── -->
    <div class="settings-tabs" role="tablist">
        <button class="settings-tab-btn <?= $active_tab === 'profile'      ? 'active' : '' ?>" onclick="switchTab('profile')"      role="tab" id="tab-profile">
            <i class="fas fa-user-circle"></i> Profile
        </button>
        <button class="settings-tab-btn <?= $active_tab === 'business'     ? 'active' : '' ?>" onclick="switchTab('business')"     role="tab" id="tab-business">
            <i class="fas fa-building"></i> Business
        </button>
        <button class="settings-tab-btn <?= $active_tab === 'preferences'  ? 'active' : '' ?>" onclick="switchTab('preferences')"  role="tab" id="tab-preferences">
            <i class="fas fa-sliders-h"></i> Preferences
        </button>
        <button class="settings-tab-btn <?= $active_tab === 'notifications' ? 'active' : '' ?>" onclick="switchTab('notifications')" role="tab" id="tab-notifications">
            <i class="fas fa-bell"></i> Notifications
        </button>
        <button class="settings-tab-btn <?= $active_tab === 'security'     ? 'active' : '' ?>" onclick="switchTab('security')"     role="tab" id="tab-security">
            <i class="fas fa-shield-alt"></i> Security
        </button>
        <button class="settings-tab-btn <?= $active_tab === 'data'         ? 'active' : '' ?>" onclick="switchTab('data')"         role="tab" id="tab-data">
            <i class="fas fa-database"></i> Data
        </button>
    </div>

    <!-- ══════════════════════════
         TAB: PROFILE
    ══════════════════════════ -->
    <div class="tab-panel <?= $active_tab === 'profile' ? 'active' : '' ?>" id="panel-profile">

        <!-- Profile Photo -->
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-green"><i class="fas fa-camera"></i></div>
                    <div>
                        <h2>Profile Photo</h2>
                        <p>Upload a photo to personalize your account</p>
                    </div>
                </div>
            </div>
            <div class="settings-card-body">
                <form method="post" enctype="multipart/form-data" id="profileForm" autocomplete="off">
                <input type="hidden" name="profile_update" value="1">

                <div class="avatar-upload-wrap">
                    <div class="avatar-preview" id="avatarPreview">
                        <?php
                        $initials = strtoupper(substr($user['first_name'] ?? 'O',0,1).substr($user['last_name'] ?? 'W',0,1));
                        if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../uploads/profile_photos/' . $user['profile_photo'])): ?>
                            <img src="../uploads/profile_photos/<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-upload-meta">
                        <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                        <p>JPG, PNG or GIF — max 2 MB</p>
                        <button type="button" class="btn-outline-styled" onclick="document.getElementById('photoInput').click()">
                            <i class="fas fa-upload"></i> Upload Photo
                        </button>
                    </div>
                    <input type="file" id="photoInput" name="profile_photo" class="avatar-file-input" accept="image/*">
                </div>

                <!-- Personal Info -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label-styled">First Name</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-control-styled" name="first_name"
                                value="<?= htmlspecialchars($user['first_name']) ?>" required placeholder="First name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-styled">Last Name</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-control-styled" name="last_name"
                                value="<?= htmlspecialchars($user['last_name']) ?>" required placeholder="Last name">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label-styled">Email Address</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" class="form-control-styled" name="email"
                            value="<?= htmlspecialchars($user['email']) ?>" required placeholder="you@example.com">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label-styled">Phone Number</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="text" class="form-control-styled" name="phone"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+63 9XX XXX XXXX">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-styled">Address</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-map-marker-alt input-icon"></i>
                            <input type="text" class="form-control-styled" name="address"
                                value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Your address">
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="settings-card" style="margin-bottom:0;box-shadow:none;border:1.5px solid #f1f5f9;border-radius:14px;">
                    <div class="settings-card-header" style="border-radius:14px 14px 0 0;">
                        <div class="settings-card-header-left">
                            <div class="settings-card-icon icon-red"><i class="fas fa-lock"></i></div>
                            <div>
                                <h2>Change Password</h2>
                                <p>Leave blank to keep your current password</p>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-body" style="padding-bottom:0;">
                        <div class="form-group">
                            <label class="form-label-styled">New Password</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-key input-icon"></i>
                                <input type="password" class="form-control-styled" name="new_password"
                                    id="newPasswordInput" placeholder="Enter new password (optional)">
                            </div>
                            <div class="pw-strength-bar-wrap">
                                <div class="pw-strength-bar" id="pwStrengthBar"></div>
                            </div>
                            <div style="font-size:.74rem;color:#94a3b8;margin-top:4px;" id="pwStrengthLabel"></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:24px;">
                    <button type="submit" class="btn-primary-styled">
                        <i class="fas fa-save"></i> Save Profile
                    </button>
                </div>
                </form>
            </div>
        </div>

    </div><!-- /panel-profile -->

    <!-- ══════════════════════════
         TAB: BUSINESS
    ══════════════════════════ -->
    <div class="tab-panel <?= $active_tab === 'business' ? 'active' : '' ?>" id="panel-business">

        <!-- Business Info -->
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-blue"><i class="fas fa-building"></i></div>
                    <div>
                        <h2>Business Information</h2>
                        <p>Details shown on receipts, booking confirmations, and public pages</p>
                    </div>
                </div>
            </div>
            <div class="settings-card-body">
                <form method="post" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label-styled">Resort Name</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-umbrella-beach input-icon"></i>
                            <input type="text" class="form-control-styled" name="resort_name"
                                value="<?= htmlspecialchars($biz['resort_name'] ?? '') ?>" required placeholder="Resort name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-styled">Tagline</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-quote-left input-icon"></i>
                            <input type="text" class="form-control-styled" name="tagline"
                                value="<?= htmlspecialchars($biz['tagline'] ?? '') ?>" placeholder="Your resort tagline">
                        </div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label-styled">Contact Info</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-phone input-icon"></i>
                                <input type="text" class="form-control-styled" name="contact_info"
                                    value="<?= htmlspecialchars($biz['contact_info'] ?? '') ?>" placeholder="Phone / email">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label-styled">Business Hours</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-clock input-icon"></i>
                                <input type="text" class="form-control-styled" name="business_hours"
                                    value="<?= htmlspecialchars($biz['business_hours'] ?? '') ?>" placeholder="e.g. 7am – 10pm daily">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-styled">
                        <i class="fas fa-save"></i> Save Business Info
                    </button>
                </form>
            </div>
        </div>

        <!-- VAT Settings -->
        <div class="settings-card" id="vatSettingsCard">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-amber"><i class="fas fa-percent"></i></div>
                    <div>
                        <h2>VAT Settings</h2>
                        <p>Applied to all bookings during checkout</p>
                    </div>
                </div>
                <button type="button" class="btn-outline-styled" onclick="openVatModal()">
                    <i class="fas fa-edit"></i> Edit VAT
                </button>
            </div>
            <div class="settings-card-body">
                <div class="vat-display-card">
                    <div class="vat-display-icon"><i class="fas fa-percent"></i></div>
                    <div class="vat-display-info">
                        <div class="label">Current VAT Rate</div>
                        <div class="value" id="currentVatDisplay"><?= number_format(floatval($biz['vat_rate'] ?? 12), 2) ?>%</div>
                        <div class="sub">Applied to every booking subtotal at checkout</div>
                    </div>
                </div>
                <?php if ($success && $section === 'vat'): ?>
                <div style="margin-top:14px;padding:12px 16px;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;display:flex;align-items:center;gap:10px;font-size:.85rem;color:#15803d;font-weight:600;">
                    <i class="fas fa-check-circle"></i> VAT rate updated successfully!
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /panel-business -->

    <!-- ══════════════════════════
         TAB: PREFERENCES
    ══════════════════════════ -->
    <div class="tab-panel <?= $active_tab === 'preferences' ? 'active' : '' ?>" id="panel-preferences">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-purple"><i class="fas fa-palette"></i></div>
                    <div>
                        <h2>Theme</h2>
                        <p>Choose how the dashboard looks</p>
                    </div>
                </div>
            </div>
            <div class="settings-card-body">
                <form method="post" id="preferencesForm" autocomplete="off">
                    <input type="hidden" name="pref_update" value="1">
                    <div class="form-group">
                        <label class="form-label-styled">Appearance</label>
                        <div class="theme-selector" id="themeSelector">
                            <label class="theme-option <?= (!isset($_SESSION['theme']) || $_SESSION['theme'] === 'light') ? 'selected' : '' ?>">
                                <input type="radio" name="theme" value="light" <?= (!isset($_SESSION['theme']) || $_SESSION['theme'] === 'light') ? 'checked' : '' ?>>
                                <div class="theme-swatch theme-light-swatch"></div>
                                <div class="theme-option-label">Light Mode</div>
                            </label>
                            <label class="theme-option <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') ? 'selected' : '' ?>">
                                <input type="radio" name="theme" value="dark" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') ? 'checked' : '' ?>>
                                <div class="theme-swatch theme-dark-swatch"></div>
                                <div class="theme-option-label">Dark Mode</div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-styled" for="languageSelect">Language</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-globe input-icon"></i>
                            <select class="form-control-styled" id="languageSelect" name="language">
                                <option value="en" <?= (!isset($_SESSION['language']) || $_SESSION['language'] === 'en') ? 'selected' : '' ?>>🇺🇸 English</option>
                                <option value="fil" <?= (isset($_SESSION['language']) && $_SESSION['language'] === 'fil') ? 'selected' : '' ?>>🇵🇭 Filipino</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary-styled">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </form>
            </div>
        </div>
    </div><!-- /panel-preferences -->

    <!-- ══════════════════════════
         TAB: NOTIFICATIONS
    ══════════════════════════ -->
    <div class="tab-panel <?= $active_tab === 'notifications' ? 'active' : '' ?>" id="panel-notifications">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-amber"><i class="fas fa-bell"></i></div>
                    <div>
                        <h2>Notification Preferences</h2>
                        <p>Control how and when you receive alerts</p>
                    </div>
                </div>
            </div>
            <div class="settings-card-body">
                <div class="toggle-row">
                    <div class="toggle-row-meta">
                        <h4>Email notifications for bookings</h4>
                        <p>Get an email when a new booking is made or updated</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked disabled>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div class="toggle-row-meta">
                        <h4>SMS notifications <span style="font-size:.7rem;background:#f1f5f9;color:#94a3b8;padding:2px 8px;border-radius:10px;font-weight:700;">Coming Soon</span></h4>
                        <p>Receive SMS for urgent booking updates</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" disabled>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div class="toggle-row-meta">
                        <h4>Daily summary report <span style="font-size:.7rem;background:#f1f5f9;color:#94a3b8;padding:2px 8px;border-radius:10px;font-weight:700;">Coming Soon</span></h4>
                        <p>Receive a daily digest of bookings and revenue</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" disabled>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div><!-- /panel-notifications -->

    <!-- ══════════════════════════
         TAB: SECURITY
    ══════════════════════════ -->
    <div class="tab-panel <?= $active_tab === 'security' ? 'active' : '' ?>" id="panel-security">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-red"><i class="fas fa-shield-alt"></i></div>
                    <div>
                        <h2>Security Settings</h2>
                        <p>Keep your account protected</p>
                    </div>
                </div>
            </div>
            <div class="settings-card-body">
                <div class="security-item">
                    <div class="security-item-meta">
                        <h4><i class="fas fa-mobile-alt" style="color:#6366f1;margin-right:8px;"></i>Two-Factor Authentication</h4>
                        <p>Add an extra layer of security to your account</p>
                    </div>
                    <span class="badge-coming-soon">Coming Soon</span>
                </div>
                <div class="security-item">
                    <div class="security-item-meta">
                        <h4><i class="fas fa-history" style="color:#0ea5e9;margin-right:8px;"></i>Recent Login Activity</h4>
                        <p>View devices and locations that have logged into your account</p>
                    </div>
                    <span class="badge-coming-soon">Coming Soon</span>
                </div>
                <div class="security-item">
                    <div class="security-item-meta">
                        <h4><i class="fas fa-sign-out-alt" style="color:#f59e0b;margin-right:8px;"></i>Revoke Other Sessions</h4>
                        <p>Log out of all other devices</p>
                    </div>
                    <span class="badge-coming-soon">Coming Soon</span>
                </div>
            </div>
        </div>

        <!-- Integrations -->
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-blue"><i class="fas fa-plug"></i></div>
                    <div>
                        <h2>Integrations</h2>
                        <p>Connect external services to your resort system</p>
                    </div>
                </div>
            </div>
            <div class="settings-card-body">
                <div class="integration-item">
                    <div class="integration-icon icon-blue"><i class="fas fa-credit-card"></i></div>
                    <div class="integration-meta">
                        <h4>Payment Gateway</h4>
                        <p>Connect GCash, PayMaya, or bank transfer providers</p>
                    </div>
                    <span class="badge-coming-soon">Coming Soon</span>
                </div>
                <div class="integration-item">
                    <div class="integration-icon icon-amber"><i class="fas fa-envelope"></i></div>
                    <div class="integration-meta">
                        <h4>Email Provider</h4>
                        <p>Configure SMTP or use a transactional email service</p>
                    </div>
                    <span class="badge-coming-soon">Coming Soon</span>
                </div>
                <div class="integration-item">
                    <div class="integration-icon icon-purple"><i class="fas fa-key"></i></div>
                    <div class="integration-meta">
                        <h4>API Key Management</h4>
                        <p>Generate and revoke API keys for third-party access</p>
                    </div>
                    <span class="badge-coming-soon">Coming Soon</span>
                </div>
            </div>
        </div>
    </div><!-- /panel-security -->

    <!-- ══════════════════════════
         TAB: DATA
    ══════════════════════════ -->
    <div class="tab-panel <?= $active_tab === 'data' ? 'active' : '' ?>" id="panel-data">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-header-left">
                    <div class="settings-card-icon icon-slate"><i class="fas fa-database"></i></div>
                    <div>
                        <h2>Backup &amp; Data</h2>
                        <p>Export, import, or request deletion of your resort data</p>
                    </div>
                </div>
            </div>
            <div class="settings-card-body">
                <div class="backup-btn-grid">
                    <form method="post" action="export_data.php" target="_blank" style="display:contents;">
                        <button type="submit" class="backup-action-btn">
                            <i class="fas fa-download"></i>
                            <span>Export Data</span>
                        </button>
                    </form>

                    <form method="post" action="import_data.php" enctype="multipart/form-data" id="importDataForm" style="display:contents;">
                        <button type="button" class="backup-action-btn" id="importDataBtn">
                            <i class="fas fa-upload"></i>
                            <span>Import Data</span>
                        </button>
                        <input type="file" name="import_file" id="importFileInput" accept=".json,.csv,.zip" style="display:none;">
                    </form>

                    <form method="post" action="request_data_deletion.php" onsubmit="return confirm('Are you sure you want to request data deletion? This action cannot be undone.');" style="display:contents;">
                        <button type="submit" class="backup-action-btn danger">
                            <i class="fas fa-trash-alt"></i>
                            <span>Request Deletion</span>
                        </button>
                    </form>
                </div>

                <div style="margin-top:24px;padding:16px 18px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;font-size:.82rem;color:#64748b;line-height:1.7;">
                    <i class="fas fa-info-circle" style="color:#3b82f6;margin-right:6px;"></i>
                    <strong>Export</strong> downloads all booking, facility, and guest data as a file.
                    <strong>Import</strong> allows restoring from a previously exported file.
                    <strong>Request Deletion</strong> initiates a permanent data removal request — this cannot be undone.
                </div>
            </div>
        </div>
    </div><!-- /panel-data -->

</div><!-- /settings-shell -->
</div><!-- /content -->

<!-- ── VAT Edit Modal ── -->
<div class="vat-modal-backdrop" id="vatModalBackdrop" onclick="closeVatModal(event)">
    <div class="vat-modal-box" id="vatModalBox">
        <div class="vat-modal-head">
            <h3><i class="fas fa-percent me-2"></i>Edit VAT Rate</h3>
            <button class="vat-modal-close" onclick="closeVatModal(true)"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="vat_update" value="1">
            <div class="vat-modal-body">
                <p style="color:#64748b;font-size:.85rem;margin-bottom:18px;">
                    Set the VAT percentage applied to all booking subtotals. The current rate is <strong><?= number_format(floatval($biz['vat_rate'] ?? 12), 2) ?>%</strong>.
                </p>
                <label class="form-label-styled" for="vatRateInput">VAT Rate (%)</label>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="number" class="form-control-styled" id="vatRateInput" name="vat_rate"
                        value="<?= number_format(floatval($biz['vat_rate'] ?? 12), 2) ?>"
                        min="0" max="100" step="0.01" required
                        style="font-size:1.4rem;font-weight:800;text-align:center;">
                    <span style="font-size:1.4rem;font-weight:800;color:#475569;">%</span>
                </div>
                <div style="font-size:.75rem;color:#94a3b8;margin-top:6px;">Valid range: 0% – 100%. Enter 0 to disable VAT.</div>
                <div class="vat-preview-box">
                    <i class="fas fa-calculator" style="color:#b45309;margin-right:6px;"></i>
                    <strong>Preview:</strong> On a ₱10,000 subtotal — VAT: <strong id="vatPreviewAmt">—</strong>, Total: <strong id="vatPreviewTotal">—</strong>
                </div>
            </div>
            <div class="vat-modal-foot">
                <button type="button" class="btn-outline-styled" onclick="closeVatModal(true)">Cancel</button>
                <button type="submit" class="btn-primary-styled"><i class="fas fa-save"></i> Save VAT Rate</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Success Toast ── -->
<?php if ($success): ?>
<div class="settings-toast" id="settingsToast" onclick="hideToast()">
    <i class="fas fa-check-circle"></i>
    <?php
    if ($section === 'vat') echo 'VAT rate updated!';
    elseif ($active_tab === 'profile') echo 'Profile saved successfully!';
    elseif ($active_tab === 'business') echo 'Business info updated!';
    elseif ($active_tab === 'preferences') echo 'Preferences saved!';
    else echo 'Settings saved!';
    ?>
    <i class="fas fa-times" style="margin-left:6px;opacity:.7;font-size:.75rem;"></i>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Tab switching ── */
function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + name).classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
    // update URL without reload
    const url = new URL(window.location.href);
    url.searchParams.set('tab', name);
    url.searchParams.delete('success');
    url.searchParams.delete('section');
    history.replaceState(null, '', url.toString());
}

/* ── Toast auto-dismiss ── */
(function() {
    var toast = document.getElementById('settingsToast');
    if (!toast) return;
    setTimeout(function() { hideToast(); }, 4000);
})();
function hideToast() {
    var toast = document.getElementById('settingsToast');
    if (!toast) return;
    toast.classList.add('hiding');
    setTimeout(function() { toast.remove(); }, 300);
}

/* ── VAT Modal ── */
function openVatModal() {
    document.getElementById('vatModalBackdrop').classList.add('open');
    updateVatPreview();
}
function closeVatModal(force) {
    if (force === true) {
        document.getElementById('vatModalBackdrop').classList.remove('open');
    } else if (force && force.target === document.getElementById('vatModalBackdrop')) {
        document.getElementById('vatModalBackdrop').classList.remove('open');
    }
}
function updateVatPreview() {
    var inp = document.getElementById('vatRateInput');
    var previewAmt   = document.getElementById('vatPreviewAmt');
    var previewTotal = document.getElementById('vatPreviewTotal');
    if (!inp || !previewAmt || !previewTotal) return;
    var rate = parseFloat(inp.value) || 0;
    var subtotal = 10000;
    var vat   = Math.round(subtotal * (rate / 100) * 100) / 100;
    var total = subtotal + vat;
    previewAmt.textContent   = '₱' + vat.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    previewTotal.textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}
document.addEventListener('DOMContentLoaded', function() {
    var inp = document.getElementById('vatRateInput');
    if (inp) inp.addEventListener('input', updateVatPreview);
    updateVatPreview();
});

/* ── Profile photo preview ── */
document.addEventListener('DOMContentLoaded', function() {
    var photoInput = document.getElementById('photoInput');
    if (!photoInput) return;
    photoInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
        };
        reader.readAsDataURL(file);
    });
});

/* ── Password strength ── */
document.addEventListener('DOMContentLoaded', function() {
    var pw = document.getElementById('newPasswordInput');
    var bar = document.getElementById('pwStrengthBar');
    var label = document.getElementById('pwStrengthLabel');
    if (!pw) return;
    pw.addEventListener('input', function() {
        var v = pw.value;
        var strength = 0;
        if (v.length >= 8) strength++;
        if (/[A-Z]/.test(v)) strength++;
        if (/[0-9]/.test(v)) strength++;
        if (/[^A-Za-z0-9]/.test(v)) strength++;
        var pct = v.length === 0 ? 0 : Math.max(1, strength) * 25;
        var colors = ['#ef4444','#f97316','#eab308','#22c55e'];
        var labels = ['Weak','Fair','Good','Strong'];
        bar.style.width = pct + '%';
        bar.style.background = v.length === 0 ? 'transparent' : colors[strength - 1] || '#ef4444';
        label.textContent = v.length === 0 ? '' : (labels[strength - 1] || 'Very Weak');
        label.style.color = v.length === 0 ? '' : colors[strength - 1] || '#ef4444';
    });
});

/* ── Theme selector ── */
document.addEventListener('DOMContentLoaded', function() {
    var opts = document.querySelectorAll('.theme-option input');
    opts.forEach(function(opt) {
        opt.addEventListener('change', function() {
            document.querySelectorAll('.theme-option').forEach(function(o){ o.classList.remove('selected'); });
            opt.closest('.theme-option').classList.add('selected');
        });
    });
});

/* ── Import data trigger ── */
document.addEventListener('DOMContentLoaded', function() {
    var importBtn   = document.getElementById('importDataBtn');
    var importInput = document.getElementById('importFileInput');
    var importForm  = document.getElementById('importDataForm');
    if (importBtn && importInput && importForm) {
        importBtn.addEventListener('click', function() {
            importInput.value = '';
            importInput.click();
        });
        importInput.addEventListener('change', function() {
            if (importInput.files.length > 0) importForm.submit();
        });
    }
});

/* ── Sidebar toggle (carried from other pages) ── */
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    const sidebarCol = document.getElementById('sidebarCol');
    const navbarBrand = document.getElementById('navbarBrand');
    const sidebarState = localStorage.getItem('ownerSidebarCollapsed');
    if (sidebarState === 'true') {
        sidebarCol.classList.add('collapsed');
        sidebarToggle.classList.add('collapsed');
        if (navbarBrand) navbarBrand.classList.add('collapsed');
    }
    sidebarToggle.addEventListener('click', function() {
        const isCollapsed = sidebarCol.classList.toggle('collapsed');
        this.classList.toggle('collapsed');
        if (navbarBrand) navbarBrand.classList.toggle('collapsed');
        localStorage.setItem('ownerSidebarCollapsed', isCollapsed);
    });
}
</script>
</body>
</html>
