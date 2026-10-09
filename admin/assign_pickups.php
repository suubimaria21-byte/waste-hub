<?php
require_once '../includes/auth.php';
requireRole('admin');

$unassigned = $pdo->query("SELECT p.id, p.citizen_id, p.area_name, u.latitude, u.longitude FROM pickups p JOIN users u ON u.id = p.citizen_id WHERE p.status = 'pending' AND p.collector_id IS NULL ORDER BY p.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
$assignedCount = 0;
foreach ($unassigned as $pickup) {
    $collectorId = autoAssignCollector(
        $pdo,
        (int)$pickup['citizen_id'],
        (string)($pickup['area_name'] ?: ''),
        $pickup['latitude'] !== null ? (float)$pickup['latitude'] : null,
        $pickup['longitude'] !== null ? (float)$pickup['longitude'] : null
    );
    if ($collectorId !== null) {
        $stmt = $pdo->prepare("UPDATE pickups SET collector_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ? AND status = 'pending' AND collector_id IS NULL");
        $stmt->execute([$collectorId, (int)$pickup['id']]);
        $assignedCount += $stmt->rowCount();
    }
}

$pending = $pdo->query("SELECT p.*, u.full_name, u.phone, u.pickup_area, c.full_name AS collector_name FROM pickups p JOIN users u ON u.id = p.citizen_id LEFT JOIN users c ON c.id = p.collector_id WHERE p.status = 'pending' OR p.status = 'assigned' ORDER BY p.requested_date ASC, p.created_at ASC")->fetchAll();
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
    <h2 class="mb-4">Automatic pickup assignments</h2>
    <?php if ($assignedCount > 0): ?><div class="alert alert-success"><?= (int)$assignedCount ?> pending pickup(s) automatically assigned.</div><?php endif; ?>

    <?php if (empty($pending)): ?>
        <div class="alert alert-info">No pending or assigned pickup requests.</div>
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
                        <th>Collector</th>
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
                                    <td><?= htmlspecialchars($p['collector_name'] ?: 'No approved collector available') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>