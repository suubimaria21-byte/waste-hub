<?php
require_once '../includes/auth.php';
requireRole('admin');

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pickup_id'])) {
    $pickup_id = (int)$_POST['pickup_id'];
    $collector_id = (int)$_POST['collector_id'];
    $stmt = $pdo->prepare("UPDATE pickups SET collector_id=?, status='assigned', assigned_at=NOW() WHERE id=?");
    $stmt->execute([$collector_id, $pickup_id]);
    $success = 'Pickup assigned successfully!';
}

$pending = $pdo->query("SELECT p.*, u.full_name, u.phone, u.pickup_area FROM pickups p JOIN users u ON p.citizen_id = u.id WHERE p.status = 'pending' ORDER BY p.created_at ASC")->fetchAll();
$collectors = $pdo->query("SELECT id, full_name, contractor_name, pickup_area FROM users WHERE role='collector' ORDER BY contractor_name, full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Pickups - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="dashboard.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub Admin</span></a>
        <a href="../config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
</nav>

<div class="container py-4">
    <h2 class="mb-4">Assign pickups to collection teams</h2>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (empty($pending)): ?>
        <div class="alert alert-info">No pending pickup requests.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-success">
                    <tr>
                        <th>Citizen</th>
                        <th>Area</th>
                        <th>Date</th>
                        <th>Phone</th>
                        <th>Notes</th>
                        <th>Assign To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['full_name']) ?></td>
                            <td><?= htmlspecialchars($p['pickup_area'] ?: 'Kampala Central') ?></td>
                            <td><?= htmlspecialchars($p['requested_date']) ?></td>
                            <td><?= htmlspecialchars($p['phone']) ?></td>
                            <td><?= htmlspecialchars($p['notes'] ?: '-') ?></td>
                            <td>
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="pickup_id" value="<?= $p['id'] ?>">
                                    <select name="collector_id" class="form-select form-select-sm" required>
                                        <option value="">Select collector</option>
                                        <?php foreach ($collectors as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> - <?= htmlspecialchars($c['contractor_name'] ?: 'KCCA Team') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-success">Assign</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>