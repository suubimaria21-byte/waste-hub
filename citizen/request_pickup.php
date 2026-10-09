<?php
require_once '../includes/auth.php';
requireRole('citizen');
$user = getUser();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requested_date = trim($_POST['requested_date'] ?? '');
    $preferred_time = $_POST['preferred_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $areaName = trim($_POST['area_name'] ?? $user['pickup_area'] ?? 'Kampala Central');

    if (empty($requested_date)) {
        $error = 'Please select a pickup date.';
    } else {
        $collectorId = autoAssignCollector($pdo, $_SESSION['user_id'], $areaName, !empty($user['latitude']) ? (float)$user['latitude'] : null, !empty($user['longitude']) ? (float)$user['longitude'] : null);
        $stmt = $pdo->prepare("INSERT INTO pickups (citizen_id, area_name, requested_date, preferred_time, status, pickup_type, collector_id, assigned_at, notes) VALUES (?, ?, ?, ?, ?, 'special', ?, NOW(), ?)");
        $stmt->execute([$_SESSION['user_id'], $areaName, $requested_date, $preferred_time, $collectorId ? 'assigned' : 'pending', $collectorId, $notes]);
        $success = 'Pickup request submitted successfully!';
    }
}

$routines = $pdo->query("SELECT r.*, u.full_name AS collector_name FROM collector_routines r JOIN users u ON u.id = r.collector_id WHERE r.is_active = 1 ORDER BY FIELD(r.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), r.start_time")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Pickup - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="dashboard.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub</span></a>
        <a href="../config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-body p-4">
                    <h3 class="text-success mb-4">Request a waste pickup</h3>
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Preferred date *</label>
                                <input type="date" name="requested_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Preferred time</label>
                                <select name="preferred_time" class="form-select">
                                    <option value="">Any time</option>
                                    <option value="Morning (8-12)">Morning (8-12)</option>
                                    <option value="Afternoon (12-4)">Afternoon (12-4)</option>
                                    <option value="Evening (4-7)">Evening (4-7)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Pickup location</label>
                                <input type="text" name="area_name" class="form-control" value="<?= htmlspecialchars($user['pickup_area'] ?: '') ?>" placeholder="e.g. Kisaasi, Wandegeya, Ntinda">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Additional notes</label>
                                <textarea name="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mt-4">Submit request</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="dashboard.php">← Back to dashboard</a>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header bg-success text-white">Normal collection routine</div>
                <div class="card-body">
                    <?php if (empty($routines)): ?>
                        <p class="text-muted mb-0">No routine has been scheduled yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Collector</th>
                                        <th>Area</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($routines as $routine): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($routine['day_of_week']) ?></td>
                                            <td><?= htmlspecialchars($routine['collector_name']) ?></td>
                                            <td><?= htmlspecialchars($routine['area_name']) ?></td>
                                            <td><?= htmlspecialchars($routine['start_time'] ?: 'Flexible') ?><?= !empty($routine['end_time']) ? ' - ' . htmlspecialchars($routine['end_time']) : '' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>