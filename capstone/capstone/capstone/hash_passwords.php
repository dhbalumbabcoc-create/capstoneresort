<?php
require_once 'config/db_config.php';

// Only allow from localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Access denied.');
}

$r = $conn->query("SELECT id, username, role, password FROM users");
$updated = 0;
$already = 0;
$rows = [];

while ($row = $r->fetch_assoc()) {
    $info = password_get_info($row['password']);
    if ($info['algo'] === null || $info['algo'] === 0) {
        // Plain text — hash it now
        $hashed = password_hash($row['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $row['id']);
        $stmt->execute();
        $stmt->close();
        $rows[] = ['id'=>$row['id'], 'username'=>$row['username'], 'role'=>$row['role'], 'status'=>'✅ Hashed'];
        $updated++;
    } else {
        $rows[] = ['id'=>$row['id'], 'username'=>$row['username'], 'role'=>$row['role'], 'status'=>'Already hashed'];
        $already++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Password Hash Utility</title>
<style>
body{font-family:Inter,sans-serif;background:#f4f6f9;padding:32px;}
.card{background:#fff;border-radius:16px;padding:28px;max-width:700px;margin:0 auto;box-shadow:0 4px 20px rgba(0,0,0,.08);}
h2{color:#1B7D3A;margin-bottom:4px;}
.summary{display:flex;gap:16px;margin:20px 0;}
.badge{padding:8px 18px;border-radius:10px;font-weight:700;font-size:.9rem;}
.green{background:#e8f5e9;color:#1B7D3A;}
.blue{background:#e3f2fd;color:#1565c0;}
table{width:100%;border-collapse:collapse;margin-top:16px;}
th{background:linear-gradient(135deg,#1B7D3A,#27A457);color:#fff;padding:10px 14px;text-align:left;font-size:.82rem;}
td{padding:10px 14px;border-bottom:1px solid #f0f0f0;font-size:.88rem;}
tr:hover td{background:#f8fffe;}
.ok{color:#1B7D3A;font-weight:700;}
.done{color:#1565c0;}
.warn{background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:14px;margin-top:20px;font-size:.88rem;color:#e65100;}
</style>
</head>
<body>
<div class="card">
    <h2>🔐 Password Hash Utility</h2>
    <p style="color:#888;font-size:.88rem;">Encrypts all plain-text passwords in the <code>users</code> table using bcrypt.</p>

    <div class="summary">
        <span class="badge green">✅ Hashed now: <?= $updated ?></span>
        <span class="badge blue">Already hashed: <?= $already ?></span>
    </div>

    <table>
        <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
            <td><?= htmlspecialchars($row['role']) ?></td>
            <td class="<?= $row['status']==='✅ Hashed' ? 'ok' : 'done' ?>"><?= $row['status'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($updated > 0): ?>
    <div class="warn">
        ⚠️ <strong><?= $updated ?> password(s) were hashed.</strong>
        Delete this file immediately after use: <code>hash_passwords.php</code>
    </div>
    <?php else: ?>
    <div style="background:#e8f5e9;border-radius:10px;padding:14px;margin-top:20px;font-size:.88rem;color:#1B7D3A;">
        ✅ All passwords are already hashed. No changes made. You can delete this file.
    </div>
    <?php endif; ?>
</div>
</body>
</html>
