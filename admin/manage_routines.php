<?php
require_once '../includes/auth.php';
requireRole('admin');
$user = getUser();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $pdo->prepare('DELETE FROM collector_routines WHERE id = ?')->execute([$deleteId]);
        $success = 'Routine deleted successfully.';
    } else {
        $routineId = isset($_POST['routine_id']) ? (int)$_POST['routine_id'] : 0;
        $collectorId = (int)($_POST['collector_id'] ?? 0);
        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $areaName = trim($_POST['area_name'] ?? '');
        $routeName = trim($_POST['route_name'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($collectorId <= 0 || $dayOfWeek === '' || $areaName === '') {
            $error = 'Collector, day, and area are required.';
        } else {
            if ($routineId > 0) {
                $stmt = $pdo->prepare('UPDATE collector_routines SET collector_id = ?, day_of_week = ?, area_name = ?, route_name = ?, start_time = ?, end_time = ?, notes = ?, is_active = 1 WHERE id = ?');
                $stmt->execute([$collectorId, $dayOfWeek, $areaName, $routeName, $startTime, $endTime, $notes, $routineId]);
                $success = 'Routine updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO collector_routines (collector_id, day_of_week, area_name, route_name, start_time, end_time, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->execute([$collectorId, $dayOfWeek, $areaName, $routeName, $startTime, $endTime, $notes]);
                $success = 'Routine created successfully.';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editing = $pdo->prepare('SELECT * FROM collector_routines WHERE id = ? LIMIT 1');
    $editing->execute([$editId]);
    $routineToEdit = $editing->fetch(PDO::FETCH_ASSOC);
} else {
    $routineToEdit = null;
}

$collectors = $pdo->query("SELECT id, full_name, pickup_area FROM users WHERE role = 'collector' ORDER BY full_name")->fetchAll();
$routines = $pdo->query("SELECT r.*, u.full_name AS collector_name FROM collector_routines r JOIN users u ON u.id = r.collector_id ORDER BY FIELD(r.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), r.start_time")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Routine Calendar - Waste Hub</title>
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

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-0">
            <a href="dashboard.php">Dashboard</a>
            <a href="assign_pickups.php">Assign Pickups</a>
            <a href="manage_complaints.php">Complaints</a>
            <a href="manage_users.php">Manage Users</a>
            <a href="manage_collector_applications.php">Collector Requests</a>
            <a href="manage_routines.php" class="active">Routine Calendar</a>
            <a href="manage_meetings.php">Upcoming events</a>
            <a href="../profile.php">Edit Profile</a>
        </div>

        <div class="col-md-10 p-4">
            <h2 class="mb-4">Normal collection routine</h2>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-success text-white"><?= $routineToEdit ? 'Edit routine' : 'Create a routine' ?></div>
                <div class="card-body">
                    <form method="POST">
                        <?php if ($routineToEdit): ?>
                            <input type="hidden" name="routine_id" value="<?= (int)$routineToEdit['id'] ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Collector *</label>
                                <select name="collector_id" class="form-select" required>
                                    <option value="">Select collector</option>
                                    <?php foreach ($collectors as $collector): ?>
                                        <option value="<?= (int)$collector['id'] ?>" <?= ($routineToEdit && (int)$routineToEdit['collector_id'] === (int)$collector['id']) ? 'selected' : '' ?>><?= htmlspecialchars($collector['full_name']) ?> (<?= htmlspecialchars($collector['pickup_area'] ?: 'Area not set') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Day of week *</label>
                                <select name="day_of_week" class="form-select" required>
                                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                                        <option value="<?= $day ?>" <?= ($routineToEdit && $routineToEdit['day_of_week'] === $day) ? 'selected' : '' ?>><?= $day ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Area name *</label>
                                <input type="text" name="area_name" class="form-control" value="<?= htmlspecialchars($routineToEdit['area_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Route name</label>
                                <input type="text" name="route_name" class="form-control" value="<?= htmlspecialchars($routineToEdit['route_name'] ?? '') ?>" placeholder="North route">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start time</label>
                                <input type="time" name="start_time" class="form-control" value="<?= htmlspecialchars($routineToEdit['start_time'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End time</label>
                                <input type="time" name="end_time" class="form-control" value="<?= htmlspecialchars($routineToEdit['end_time'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($routineToEdit['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4"><?= $routineToEdit ? 'Update routine' : 'Create routine' ?></button>
                        <?php if ($routineToEdit): ?>
                            <a href="manage_routines.php" class="btn btn-outline-secondary mt-4 ms-2">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white">Routine schedule</div>
                <div class="card-body p-0">
                    <?php if (empty($routines)): ?>
                        <div class="p-3 text-muted">No recurring routines have been set yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Collector</th>
                                        <th>Day</th>
                                        <th>Area</th>
                                        <th>Route</th>
                                        <th>Time</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($routines as $routine): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($routine['collector_name']) ?></td>
                                            <td><?= htmlspecialchars($routine['day_of_week']) ?></td>
                                            <td><?= htmlspecialchars($routine['area_name']) ?></td>
                                            <td><?= htmlspecialchars($routine['route_name'] ?: 'Standard route') ?></td>
                                            <td><?= htmlspecialchars($routine['start_time'] ?: 'Flexible') ?><?= !empty($routine['end_time']) ? ' - ' . htmlspecialchars($routine['end_time']) : '' ?></td>
                                            <td><?= nl2br(htmlspecialchars($routine['notes'] ?: '-')) ?></td>
                                            <td>
                                                <a href="?edit=<?= (int)$routine['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="delete_id" value="<?= (int)$routine['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this routine?')">Delete</button>
                                                </form>
                                            </td>
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
