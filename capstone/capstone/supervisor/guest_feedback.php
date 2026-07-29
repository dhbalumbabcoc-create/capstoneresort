<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_role('supervisor');

$user = get_user_info($_SESSION['user_id'], $conn);

$feedback_result = $conn->query("SELECT id, guest_name, email, rating, comment, created_at FROM feedback ORDER BY created_at DESC");

$feedbacks = [];
$counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$total = 0;
$sum = 0;

if ($feedback_result && $feedback_result->num_rows > 0) {
    while ($row = $feedback_result->fetch_assoc()) {
        $feedbacks[] = $row;
        $r = (int)$row['rating'];
        if (isset($counts[$r])) {
            $counts[$r]++;
        }
        $sum += $r;
        $total++;
    }
}

$avg = $total > 0 ? round($sum / $total, 1) : 0;
$five_star = $counts[5];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/owner-sidebar.css">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Feedback - Supervisor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once '../includes/supervisor_page_styles.php'; ?>
    <style>
        .rating-filter-group .btn {
            border-color: #e2e8f0;
            color: #4a5568;
            font-weight: 600;
            font-size: .8rem;
            padding: 5px 12px;
            transition: all .2s ease;
            background: #fff;
        }
        .rating-filter-group .btn:hover {
            background: #f0fdf4;
            color: #1B7D3A;
            border-color: #27A457;
        }
        .rating-filter-group .btn.active {
            background: linear-gradient(135deg, #1B7D3A, #27A457);
            color: #fff;
            border-color: #1B7D3A;
            box-shadow: 0 2px 8px rgba(27,125,58,.25);
        }
        .rating-filter-group .btn.active .text-warning {
            color: #ffeb3b !important;
        }
        .kpi-card-clickable {
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="main-container" style="display:flex;min-height:100vh;">
    <div class="sidebar-col" id="sidebarCol"><?php require_once '../includes/supervisor_sidebar.php'; ?></div>
    <div class="content" style="flex:1;min-width:0;">
        <div class="dash-topbar">
            <div>
                <div class="dash-topbar-title"><i class="fas fa-star me-2" style="color:#1B7D3A;"></i>Guest Feedback</div>
                <div class="dash-topbar-sub"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="dash-topbar-badge"><i class="fas fa-hard-hat me-1"></i>Supervisor</span>
            </div>
        </div>
        <div class="dash-body">
            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-4">
                    <div class="kpi-card kpi-card-clickable" onclick="filterRating('all')">
                        <div class="kpi-icon blue"><i class="fas fa-comments"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?= $total ?>"><?= $total ?></div>
                            <div class="kpi-lbl">Total Reviews</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="kpi-card">
                        <div class="kpi-icon yellow"><i class="fas fa-star"></i></div>
                        <div>
                            <div class="kpi-num"><?= $avg ?></div>
                            <div class="kpi-lbl">Average Rating</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="kpi-card kpi-card-clickable" onclick="filterRating('5')">
                        <div class="kpi-icon green"><i class="fas fa-award"></i></div>
                        <div>
                            <div class="kpi-num" data-count="<?= $five_star ?>"><?= $five_star ?></div>
                            <div class="kpi-lbl">5-Star Reviews</div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Feedback Table -->
            <div class="table-card">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div class="section-hdr mb-0">
                        <h5>All Guest Reviews</h5>
                        <p>Ordered by most recent</p>
                    </div>
                    <!-- Rating Filter Click Buttons -->
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fw-bold small"><i class="fas fa-filter me-1"></i>Filter Rating:</span>
                        <div class="btn-group rating-filter-group" role="group" aria-label="Rating Filter">
                            <button type="button" class="btn filter-btn active" data-rating="all">
                                All (<?= $total ?>)
                            </button>
                            <button type="button" class="btn filter-btn" data-rating="5">
                                5 <i class="fas fa-star text-warning"></i> (<?= $counts[5] ?>)
                            </button>
                            <button type="button" class="btn filter-btn" data-rating="4">
                                4 <i class="fas fa-star text-warning"></i> (<?= $counts[4] ?>)
                            </button>
                            <button type="button" class="btn filter-btn" data-rating="3">
                                3 <i class="fas fa-star text-warning"></i> (<?= $counts[3] ?>)
                            </button>
                            <button type="button" class="btn filter-btn" data-rating="2">
                                2 <i class="fas fa-star text-warning"></i> (<?= $counts[2] ?>)
                            </button>
                            <button type="button" class="btn filter-btn" data-rating="1">
                                1 <i class="fas fa-star text-warning"></i> (<?= $counts[1] ?>)
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Guest Name</th>
                                <th>Email</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($feedbacks)): $i = 1; foreach ($feedbacks as $row): ?>
                        <tr class="feedback-row" data-rating="<?= (int)$row['rating'] ?>">
                            <td class="row-num"><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($row['guest_name'] ?: 'Anonymous') ?></strong></td>
                            <td style="font-size:.82rem;color:#888;"><?= htmlspecialchars($row['email'] ?: '—') ?></td>
                            <td>
                                <?php 
                                $r = (int)$row['rating']; 
                                for ($s = 1; $s <= 5; $s++) {
                                    echo '<i class="' . ($s <= $r ? 'fas' : 'far') . ' fa-star" style="color:' . ($s <= $r ? '#f9a825' : '#ddd') . ';font-size:.85rem;"></i>';
                                }
                                ?>
                                <span style="font-size:.78rem;color:#888;margin-left:4px;"><?= $r ?>/5</span>
                            </td>
                            <td style="font-size:.88rem;"><?= nl2br(htmlspecialchars($row['comment'])) ?></td>
                            <td style="font-size:.82rem;color:#888;"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr id="no-data-row"><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No feedback found.</td></tr>
                        <?php endif; ?>
                        <tr id="no-filter-match-row" style="display:none;">
                            <td colspan="6" class="text-center text-muted py-4"><i class="fas fa-filter me-2"></i>No feedback found for the selected rating.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterRating(rating) {
    const btn = document.querySelector(`.filter-btn[data-rating="${rating}"]`);
    if (btn) btn.click();
}

document.addEventListener('DOMContentLoaded', function() {
    // KPI animation
    document.querySelectorAll('.kpi-num[data-count]').forEach((el, i) => {
        const t = parseInt(el.getAttribute('data-count'), 10);
        setTimeout(() => {
            const s = performance.now();
            const u = (n) => {
                const p = Math.min((n - s) / 800, 1);
                el.textContent = Math.round((1 - Math.pow(1 - p, 3)) * t);
                if (p < 1) requestAnimationFrame(u);
            };
            requestAnimationFrame(u);
        }, i * 80);
    });

    // Rating Filter
    const filterBtns = document.querySelectorAll('.filter-btn');
    const feedbackRows = document.querySelectorAll('.feedback-row');
    const noMatchRow = document.getElementById('no-filter-match-row');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const selectedRating = this.getAttribute('data-rating');
            let visibleCount = 0;

            feedbackRows.forEach(row => {
                const rowRating = row.getAttribute('data-rating');
                if (selectedRating === 'all' || rowRating === selectedRating) {
                    row.style.display = '';
                    visibleCount++;
                    const numCell = row.querySelector('.row-num');
                    if (numCell) numCell.textContent = visibleCount;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noMatchRow) {
                noMatchRow.style.display = (visibleCount === 0 && feedbackRows.length > 0) ? '' : 'none';
            }
        });
    });
});
</script>
</body>
</html>