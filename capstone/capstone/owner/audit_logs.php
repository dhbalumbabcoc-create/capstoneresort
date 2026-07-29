<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';

require_role('owner');

$user = get_user_info($_SESSION['user_id'], $conn);

// ── Delete single log ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_log') {
        $log_id = intval($_POST['log_id']);
        $stmt = $conn->prepare("DELETE FROM audit_logs_v2 WHERE id = ?");
        $stmt->bind_param("i", $log_id);
        $stmt->execute() ? set_success_message('Log entry deleted.') : set_error_message('Failed to delete log.');
        $stmt->close();
    } elseif ($_POST['action'] === 'delete_all') {
        $conn->query("TRUNCATE TABLE audit_logs_v2");
        set_success_message('All audit logs cleared.');
    } elseif ($_POST['action'] === 'delete_filtered') {
        // Build same WHERE as the filter
        $fe = trim($_POST['filter_event'] ?? '');
        $fu = trim($_POST['filter_user']  ?? '');
        $fd = trim($_POST['filter_date']  ?? '');
        $fi = trim($_POST['filter_ip']    ?? '');
        $w=[]; $p=[]; $t='';
        if ($fe!=='') { $w[]='event_type=?'; $p[]=$fe; $t.='s'; }
        if ($fu!=='') { $like='%'.$conn->real_escape_string($fu).'%'; $w[]='(username LIKE ? OR role LIKE ?)'; $p[]=$like; $p[]=$like; $t.='ss'; }
        if ($fd!=='') { $w[]='DATE(created_at)=?'; $p[]=$fd; $t.='s'; }
        if ($fi!=='') { $w[]='ip_address LIKE ?'; $p[]='%'.$conn->real_escape_string($fi).'%'; $t.='s'; }
        if ($w) {
            $del = $conn->prepare("DELETE FROM audit_logs_v2 WHERE ".implode(' AND ',$w));
            $del->bind_param($t,...$p); $del->execute();
            set_success_message($del->affected_rows.' filtered log(s) deleted.');
            $del->close();
        } else {
            $conn->query("TRUNCATE TABLE audit_logs_v2");
            set_success_message('All audit logs cleared.');
        }
    }
    // Redirect back preserving filters
    $qs = http_build_query(array_filter(['event'=>$_GET['event']??'','user'=>$_GET['user']??'','date'=>$_GET['date']??'','ip'=>$_GET['ip']??'','p'=>$_GET['p']??'']));
    header("Location: audit_logs.php" . ($qs ? "?$qs" : ''));
    exit();
}

$user = get_user_info($_SESSION['user_id'], $conn);

// ── Filters ──────────────────────────────────────────────────────────────────
$filter_event  = isset($_GET['event'])  ? trim($_GET['event'])  : '';
$filter_user   = isset($_GET['user'])   ? trim($_GET['user'])   : '';
$filter_date   = isset($_GET['date'])   ? trim($_GET['date'])   : '';
$filter_ip     = isset($_GET['ip'])     ? trim($_GET['ip'])     : '';
$page_num      = max(1, intval($_GET['p'] ?? 1));
$per_page      = 25;
$offset        = ($page_num - 1) * $per_page;

// Build WHERE
$where   = [];
$params  = [];
$types   = '';

if ($filter_event !== '') {
    $where[]  = 'event_type = ?';
    $params[] = $filter_event;
    $types   .= 's';
}
if ($filter_user !== '') {
    $where[]  = '(username LIKE ? OR role LIKE ?)';
    $like     = '%' . $conn->real_escape_string($filter_user) . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($filter_date !== '') {
    $where[]  = 'DATE(created_at) = ?';
    $params[] = $filter_date;
    $types   .= 's';
}
if ($filter_ip !== '') {
    $where[]  = 'ip_address LIKE ?';
    $params[] = '%' . $conn->real_escape_string($filter_ip) . '%';
    $types   .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$count_sql  = "SELECT COUNT(*) AS cnt FROM audit_logs_v2 $whereSQL";
$count_stmt = $conn->prepare($count_sql);
if ($types && $count_stmt) {
    $count_stmt->bind_param($types, ...$params);
}
if ($count_stmt) { $count_stmt->execute(); $total_rows = $count_stmt->get_result()->fetch_assoc()['cnt']; $count_stmt->close(); }
else { $total_rows = 0; }
$total_pages = max(1, ceil($total_rows / $per_page));

// Fetch
$logs_sql  = "SELECT * FROM audit_logs_v2 $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?";
$logs_stmt = $conn->prepare($logs_sql);
$fetch_params = $params;
$fetch_types  = $types . 'ii';
$fetch_params[] = $per_page;
$fetch_params[] = $offset;
if ($logs_stmt) {
    $logs_stmt->bind_param($fetch_types, ...$fetch_params);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
}

// KPI counts — only the 4 event types we record
$kpi = [];
foreach (['login_success','login_failed','unauthorized_access','logout'] as $et) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM audit_logs_v2 WHERE event_type='$et'");
    $kpi[$et] = $r ? $r->fetch_assoc()['c'] : 0;
}
$kpi_today = $conn->query("SELECT COUNT(*) AS c FROM audit_logs_v2 WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Owner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/owner_page_styles.php'; ?>
    <style>
        /* ── Compact KPI cards ── */
        .kpi-card {
            padding: 12px 14px !important;
            border-radius: 12px !important;
            gap: 10px !important;
        }
        .kpi-icon {
            width: 38px !important; height: 38px !important;
            border-radius: 10px !important; font-size: 1rem !important;
        }
        .kpi-num { font-size: 1.25rem !important; }
        .kpi-lbl { font-size: .70rem !important; }

        /* Event type pills */
        .ev-login_success       { background:#e8f5e9; color:#1B7D3A; }
        .ev-login_failed        { background:#fdecea; color:#c62828; }
        .ev-unauthorized_access { background:#fff3e0; color:#e65100; }
        .ev-logout              { background:#f5f5f5; color:#555; }
        .ev-pill { display:inline-block; padding:2px 7px; border-radius:20px; font-size:.68rem; font-weight:700; white-space:nowrap; }

        /* Filter bar */
        .filter-form .form-control, .filter-form .form-select {
            border:1.5px solid #e0e0e0; border-radius:10px; padding:8px 12px; font-size:.85rem;
        }
        .filter-form .form-control:focus, .filter-form .form-select:focus {
            border-color:#1B7D3A; box-shadow:0 0 0 3px rgba(27,125,58,.1);
        }

        /* Pagination */
        .page-link { color:#1B7D3A; border-color:#e0e0e0; }
        .page-item.active .page-link { background:linear-gradient(135deg,#1B7D3A,#27A457); border-color:transparent; }
        .page-link:hover { color:#1B7D3A; background:#e8f5e9; }

        /* ── Activity Log table: fixed layout so widths are enforced ── */
        .table-card { padding: 12px; }
        .table-card .table {
            table-layout: fixed;
            width: 100%;
        }
        .table-card .table thead th {
            padding: 6px 5px;
            font-size: .66rem;
            white-space: nowrap;
            overflow: hidden;
        }
        .table-card .table tbody td {
            padding: 5px 5px;
            font-size: .74rem;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Exact column widths — total ≈ content area width */
        .table-card .table th:nth-child(1),
        .table-card .table td:nth-child(1)  { width: 30px; }   /* # */
        .table-card .table th:nth-child(2),
        .table-card .table td:nth-child(2)  { width: 102px; }  /* Date & Time */
        .table-card .table th:nth-child(3),
        .table-card .table td:nth-child(3)  { width: 94px; }   /* Event */
        .table-card .table th:nth-child(4),
        .table-card .table td:nth-child(4)  { width: 120px; }  /* User */
        .table-card .table th:nth-child(5),
        .table-card .table td:nth-child(5)  { width: 62px; }   /* Role */
        .table-card .table th:nth-child(6),
        .table-card .table td:nth-child(6)  { width: 115px; }  /* Page/URL */
        .table-card .table th:nth-child(7),
        .table-card .table td:nth-child(7)  { width: 58px; }   /* IP Address */
        .table-card .table th:nth-child(8),
        .table-card .table td:nth-child(8)  { width: 145px; }  /* Details */
        .table-card .table th:nth-child(9),
        .table-card .table td:nth-child(9)  { width: 80px; }   /* Browser */
        .table-card .table th:nth-child(10),
        .table-card .table td:nth-child(10) { width: 48px; }   /* Action */

        /* UA / Browser cell */
        .ua-cell {
            display: block;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            font-size: .70rem; color: #888; cursor: help;
        }

        /* Disable outer scroll on large screens */
        .table-responsive { overflow-x: visible; }
        @media (max-width: 1300px) {
            .table-responsive { overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="main-container">
    <?php require_once '../includes/owner_sidebar.php'; ?>
    <div class="content">

        <!-- Topbar -->
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-shield-alt me-2" style="color:#1B7D3A;"></i>Audit Logs</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-crown me-1"></i>Owner</span>
                <span style="font-size:.85rem;color:#888;"><?php echo isset($user) ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : ''; ?></span>
            </div>
        </div>

        <div class="dash-body">

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-2">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-list"></i></div>
                        <div><div class="kpi-num"><?= number_format($total_rows) ?></div><div class="kpi-lbl">Filtered Results</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="kpi-card">
                        <div class="kpi-icon teal"><i class="fas fa-calendar-day"></i></div>
                        <div><div class="kpi-num"><?= $kpi_today ?></div><div class="kpi-lbl">Today's Events</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="kpi-card">
                        <div class="kpi-icon green"><i class="fas fa-sign-in-alt"></i></div>
                        <div><div class="kpi-num"><?= $kpi['login_success'] ?></div><div class="kpi-lbl">Successful Logins</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="kpi-card">
                        <div class="kpi-icon red"><i class="fas fa-times-circle"></i></div>
                        <div><div class="kpi-num"><?= $kpi['login_failed'] ?></div><div class="kpi-lbl">Failed Logins</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="kpi-card">
                        <div class="kpi-icon orange"><i class="fas fa-ban"></i></div>
                        <div><div class="kpi-num"><?= $kpi['unauthorized_access'] ?></div><div class="kpi-lbl">Unauthorized</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="kpi-card">
                        <div class="kpi-icon purple"><i class="fas fa-sign-out-alt"></i></div>
                        <div><div class="kpi-num"><?= $kpi['logout'] ?></div><div class="kpi-lbl">Logouts</div></div>
                    </div>
                </div>
            </div>

            <!-- Bulk delete actions -->
            <div class="d-flex gap-2 mb-4 flex-wrap">
                <?php if ($filter_event || $filter_user || $filter_date || $filter_ip): ?>
                <form method="POST" onsubmit="return confirm('Delete all <?= number_format($total_rows) ?> filtered log(s)? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_filtered">
                    <input type="hidden" name="filter_event" value="<?= htmlspecialchars($filter_event) ?>">
                    <input type="hidden" name="filter_user"  value="<?= htmlspecialchars($filter_user) ?>">
                    <input type="hidden" name="filter_date"  value="<?= htmlspecialchars($filter_date) ?>">
                    <input type="hidden" name="filter_ip"    value="<?= htmlspecialchars($filter_ip) ?>">
                    <button type="submit" style="background:#e65100;color:#fff;border:none;border-radius:10px;padding:9px 18px;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;">
                        <i class="fas fa-filter"></i> Delete Filtered (<?= number_format($total_rows) ?>)
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Clear ALL audit logs? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_all">
                    <button type="submit" style="background:#c62828;color:#fff;border:none;border-radius:10px;padding:9px 18px;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;">
                        <i class="fas fa-trash-alt"></i> Clear All Logs
                    </button>
                </form>
            </div>

            <!-- Filter bar -->
            <div class="table-card mb-4">
                <form method="GET" class="filter-form">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.82rem;">Event Type</label>
                            <select name="event" class="form-select">
                                <option value="">All Events</option>
                                <?php foreach ([
                                    'login_success'       => 'Login Success',
                                    'login_failed'        => 'Login Failed',
                                    'unauthorized_access' => 'Unauthorized Access',
                                    'logout'              => 'Logout',
                                ] as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= $filter_event===$val?'selected':'' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.82rem;">Username / Role</label>
                            <input type="text" name="user" class="form-control" placeholder="e.g. admin, frontdesk" value="<?= htmlspecialchars($filter_user) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold" style="font-size:.82rem;">Date</label>
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold" style="font-size:.82rem;">IP Address</label>
                            <input type="text" name="ip" class="form-control" placeholder="e.g. 127.0.0.1" value="<?= htmlspecialchars($filter_ip) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn-add flex-fill" style="justify-content:center;"><i class="fas fa-search"></i> Filter</button>
                            <a href="audit_logs.php" class="btn-del" style="padding:9px 14px;border-radius:10px;display:flex;align-items:center;"><i class="fas fa-times"></i></a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="section-hdr">
                <h5><i class="fas fa-list me-2" style="color:#1B7D3A;"></i>Activity Log</h5>
                <p>Showing <?= number_format($total_rows) ?> record<?= $total_rows!=1?'s':'' ?> — Page <?= $page_num ?> of <?= $total_pages ?></p>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Date &amp; Time</th>
                                <th>Event</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Page / URL</th>
                                <th>IP Address</th>
                                <th>Details</th>
                                <th>Browser</th>
                                <th style="width:70px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (isset($logs_result) && $logs_result->num_rows > 0):
                            $row_num = $offset + 1;
                            while ($log = $logs_result->fetch_assoc()):
                                $et = $log['event_type'];
                                $etLabels = [
                                    'login_success'      => 'Login Success',
                                    'login_failed'       => 'Login Failed',
                                    'unauthorized_access'=> 'Unauthorized',
                                    'logout'             => 'Logout',
                                ];
                                $etLabel = $etLabels[$et] ?? ucfirst(str_replace('_',' ',$et));
                                // Row highlight for security events
                                $rowStyle = '';
                                if ($et === 'unauthorized_access') $rowStyle = 'background:#fff8f0;';
                                if ($et === 'login_failed')        $rowStyle = 'background:#fff5f5;';
                        ?>
                        <tr style="<?= $rowStyle ?>">
                            <td style="color:#aaa;font-size:.78rem;"><?= $row_num++ ?></td>
                            <td style="white-space:nowrap;font-size:.82rem;">
                                <strong><?= date('M d, Y', strtotime($log['created_at'])) ?></strong><br>
                                <span style="color:#888;"><?= date('h:i:s A', strtotime($log['created_at'])) ?></span>
                            </td>
                            <td>
                                <span class="ev-pill ev-<?= htmlspecialchars($et) ?>"><?= $etLabel ?></span>
                            </td>
                            <td>
                                <?php if ($log['username']): ?>
                                    <strong style="font-size:.88rem;"><?= htmlspecialchars($log['username']) ?></strong>
                                <?php else: ?>
                                    <span style="color:#aaa;font-size:.82rem;">Guest</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['role']): ?>
                                    <span class="pill pill-blue" style="font-size:.72rem;"><?= ucfirst(htmlspecialchars($log['role'])) ?></span>
                                <?php else: ?>
                                    <span style="color:#aaa;">—</span>
                                <?php endif; ?>
                            </td>
                            <td title="<?= htmlspecialchars($log['page'] ?? '') ?>">
                                <?= htmlspecialchars($log['page'] ?? '—') ?>
                            </td>
                            <td style="font-size:.82rem;white-space:nowrap;">
                                <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                            </td>
                            <td title="<?= htmlspecialchars($log['details'] ?? '') ?>">
                                <?= htmlspecialchars($log['details'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="ua-cell" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                    <?= htmlspecialchars($log['user_agent'] ?? '—') ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this log entry?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_log">
                                    <input type="hidden" name="log_id" value="<?= (int)$log['id'] ?>">
                                    <?php
                                    $qs2 = http_build_query(array_filter(['event'=>$filter_event,'user'=>$filter_user,'date'=>$filter_date,'ip'=>$filter_ip,'p'=>$page_num]));
                                    ?>
                                    <input type="hidden" name="redirect_qs" value="<?= htmlspecialchars($qs2) ?>">
                                    <button type="submit" style="background:#fdecea;color:#c62828;border:1.5px solid #f5c6cb;border-radius:8px;padding:5px 10px;font-size:.78rem;cursor:pointer;font-weight:600;transition:all .2s;" onmouseover="this.style.background='#c62828';this.style.color='#fff';" onmouseout="this.style.background='#fdecea';this.style.color='#c62828';">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                                No audit log entries found.
                            </td>
                        </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div style="font-size:.82rem;color:#888;">
                        Showing <?= $offset+1 ?>–<?= min($offset+$per_page, $total_rows) ?> of <?= number_format($total_rows) ?> records
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $qs = http_build_query(array_filter(['event'=>$filter_event,'user'=>$filter_user,'date'=>$filter_date,'ip'=>$filter_ip]));
                            $base = 'audit_logs.php?' . ($qs ? $qs.'&' : '');
                            ?>
                            <li class="page-item <?= $page_num<=1?'disabled':'' ?>">
                                <a class="page-link" href="<?= $base ?>p=<?= $page_num-1 ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php
                            $start = max(1, $page_num-2);
                            $end   = min($total_pages, $page_num+2);
                            if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            for ($i=$start; $i<=$end; $i++):
                            ?>
                            <li class="page-item <?= $i===$page_num?'active':'' ?>">
                                <a class="page-link" href="<?= $base ?>p=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor;
                            if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            ?>
                            <li class="page-item <?= $page_num>=$total_pages?'disabled':'' ?>">
                                <a class="page-link" href="<?= $base ?>p=<?= $page_num+1 ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.dash-body -->
    </div><!-- /.content -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
