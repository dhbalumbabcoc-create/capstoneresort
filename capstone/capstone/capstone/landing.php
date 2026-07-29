<?php
require_once 'config/db_config.php';
$feedback_success = false;
$feedback_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'], $_POST['rating'])) {
    $guest_name = trim($_POST['guest_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    if ($rating < 1 || $rating > 5) { $feedback_error = 'Invalid rating.'; }
    elseif (empty($comment)) { $feedback_error = 'Comment is required.'; }
    else {
        $stmt = $conn->prepare("INSERT INTO feedback (guest_name, email, rating, comment) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssis', $guest_name, $email, $rating, $comment);
            if ($stmt->execute()) { $feedback_success = true; }
            else { $feedback_error = 'Failed to submit feedback.'; }
            $stmt->close();
        }
    }
}
$all_facilities = $conn->query("SELECT * FROM facilities WHERE status='available' ORDER BY type, name");
$facilities_count = (int)($conn->query("SELECT COUNT(*) as t FROM facilities WHERE status='available'")->fetch_assoc()['t'] ?? 0);
$bookings_count = (int)($conn->query("SELECT COUNT(*) as t FROM bookings WHERE status='confirmed'")->fetch_assoc()['t'] ?? 0);
$feedback_result = $conn->query("SELECT guest_name, rating, comment, created_at FROM feedback ORDER BY created_at DESC LIMIT 6");
$areas_result = $conn->query("SELECT * FROM areas WHERE status='active' ORDER BY name");
?>
