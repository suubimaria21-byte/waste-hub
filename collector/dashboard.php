<?php
require_once '../includes/auth.php';
requireRole('collector');
$user = getUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truck_number_plates'])) {
    $plates = trim($_POST['truck_number_plates'] ?? '');
    $count = max(0, (int)($_POST['truck_count'] ?? $user['truck_count'] ?? 0));
    $stmt = $pdo->prepare('UPDATE users SET truck_number_plates = ?, truck_count = ? WHERE id = ?');
    $stmt->execute([$plates, $count, $_SESSION['user_id']]);
    $user = getUser();
}

$stmt = $pdo->prepare("SELECT p.*, u.full_name, u.phone, u.address, u.pickup_area FROM pickups p JOIN users u ON p.citizen_id = u.id WHERE p.collector_id = ? AND p.status IN ('assigned','in_progress') ORDER BY p.requested_date ASC");
$stmt->execute([$_SESSION['user_id']]);
$assigned = $stmt->fetchAll();

$complaints = $pdo->query("SELECT c.*, u.full_name AS citizen_name FROM complaints c JOIN users u ON c.citizen_id = u.id WHERE c.collector_id = {$_SESSION['user_id']} ORDER BY c.created_at DESC")->fetchAll();

$meetings = $pdo->prepare("SELECT * FROM collector_meetings WHERE audience = 'all' OR FIND_IN_SET(?, collector_ids) > 0 ORDER BY created_at DESC");
$meetings->execute([$_SESSION['user_id']]);
$meetings = $meetings->fetchAll();

$routines = $pdo->query("SELECT * FROM collector_routines WHERE collector_id = {$_SESSION['user_id']} AND is_active = 1 ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time")->fetchAll();

if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $stmt = $pdo->prepare("UPDATE pickups SET status='completed', completed_at=NOW() WHERE id=? AND collector_id=?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['resolveComplaint'])) {
    $id = (int)$_GET['resolveComplaint'];
    $stmt = $pdo->prepare("UPDATE complaints SET status='resolved', updated_at=NOW() WHERE id=? AND collector_id=?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collector Dashboard - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="dashboard.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub - Collector</span></a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white">Welcome, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="../profile.php" class="btn btn-outline-light btn-sm">Edit Profile</a>
            <a href="../config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <h2 class="mb-4">Operations overview</h2>

    <?php if (!empty($meetings)): ?>
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning text-dark">Event notices</div>
            <div class="card-body">
                <?php foreach ($meetings as $meeting): ?>
                    <div class="border rounded p-3 mb-3">
                        <h5 class="mb-1"><?= htmlspecialchars($meeting['title']) ?></h5>
                        <div class="text-muted small mb-2"><?= !empty($meeting['scheduled_for']) ? date('d M Y, h:i A', strtotime($meeting['scheduled_for'])) : 'Internal notice' ?></div>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($meeting['message'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Assigned pickups</div>
                        <h3 class="mb-0"><?= count($assigned) ?></h3>
                    </div>
                    <div class="stat-icon text-success">🧺</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Open complaints</div>
                        <h3 class="mb-0 text-warning"><?= (int)count(array_filter($complaints, fn($c) => $c['status'] !== 'resolved')) ?></h3>
                    </div>
                    <div class="stat-icon text-warning">📣</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Service area</div>
                        <h3 class="mb-0"><?= htmlspecialchars($user['pickup_area'] ?: 'Kampala Central') ?></h3>
                    </div>
                    <div class="stat-icon text-info">📍</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">Truck details</div>
                <div class="card-body">
                    <p><strong>Truck count:</strong> <?= (int)($user['truck_count'] ?? 0) ?></p>
                    <p><strong>Number plates:</strong></p>
                    <?php if (!empty($user['truck_number_plates'])): ?>
                        <div class="bg-light p-3 rounded"><?= nl2br(htmlspecialchars($user['truck_number_plates'])) ?></div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No trucks recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">Update truck list</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">How many trucks</label>
                            <input type="number" name="truck_count" class="form-control" min="0" value="<?= htmlspecialchars((string)($user['truck_count'] ?? 0)) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Truck number plates</label>
                            <textarea name="truck_number_plates" rows="5" class="form-control" placeholder="UBA 123A, UBG 876X"><?= htmlspecialchars($user['truck_number_plates'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">Normal routing calendar</div>
        <div class="card-body">
            <?php if (empty($routines)): ?>
                <p class="text-muted mb-0">No recurring route has been set for you yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Area</th>
                                <th>Route</th>
                                <th>Time</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routines as $routine): ?>
                                <tr>
                                    <td><?= htmlspecialchars($routine['day_of_week']) ?></td>
                                    <td><?= htmlspecialchars($routine['area_name']) ?></td>
                                    <td><?= htmlspecialchars($routine['route_name'] ?: 'Standard route') ?></td>
                                    <td><?= htmlspecialchars($routine['start_time'] ?: 'Flexible') ?><?= !empty($routine['end_time']) ? ' - ' . htmlspecialchars($routine['end_time']) : '' ?></td>
                                    <td><?= nl2br(htmlspecialchars($routine['notes'] ?: '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">Assigned pickups</div>
        <div class="card-body p-0">
            <?php if (empty($assigned)): ?>
                <div class="p-3 text-muted">No active pickups assigned to you.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Citizen</th>
                                <th>Phone</th>
                                <th>Area</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['requested_date']) ?></td>
                                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                                    <td><?= htmlspecialchars($p['phone']) ?></td>
                                    <td><?= htmlspecialchars($p['pickup_area'] ?: 'Kampala Central') ?></td>
                                    <td><?= htmlspecialchars($p['address']) ?></td>
                                    <td><span class="badge bg-info"><?= ucfirst($p['status']) ?></span></td>
                                    <td>
                                        <a href="?complete=<?= $p['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Mark this pickup as completed?')">Mark completed</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">Assigned complaints</div>
        <div class="card-body p-0">
            <?php if (empty($complaints)): ?>
                <div class="p-3 text-muted">No complaints assigned to you yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Citizen</th>
                                <th>Issue</th>
                                <th>Area</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['citizen_name']) ?></td>
                                    <td><?= htmlspecialchars($c['title']) ?></td>
                                    <td><?= htmlspecialchars($c['area_name'] ?: 'Kampala Central') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $c['status']==='resolved' ? 'success' : ($c['status']==='in_progress' ? 'warning' : 'danger') ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($c['status'] !== 'resolved'): ?>
                                            <a href="?resolveComplaint=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Mark this complaint as resolved?')">Resolve</a>
                                        <?php else: ?>
                                            <span class="text-muted">Completed</span>
                                        <?php endif; ?>
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
</body>
</html>