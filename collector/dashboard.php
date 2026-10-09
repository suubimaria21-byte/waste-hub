<?php
require_once '../includes/auth.php';
requireRole('collector');
$user = getUser();
$success = '';

syncCollectorTrucks($pdo, (int)$user['id'], (string)($user['truck_number_plates'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truck_number_plates'])) {
    $plates = trim($_POST['truck_number_plates'] ?? '');
    $count = max(0, (int)($_POST['truck_count'] ?? $user['truck_count'] ?? 0));
    $stmt = $pdo->prepare('UPDATE users SET truck_number_plates = ?, truck_count = ? WHERE id = ?');
    $stmt->execute([$plates, $count, $_SESSION['user_id']]);
    $user = getUser();
    syncCollectorTrucks($pdo, (int)$user['id'], $plates);
    $success = 'Truck list saved.';
}

$trucksStmt = $pdo->prepare('SELECT id, plate_number FROM collector_trucks WHERE collector_id = ? AND is_active = 1 ORDER BY plate_number');
$trucksStmt->execute([$user['id']]);
$trucks = $trucksStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_pickup_truck'])) {
    $pickupId = (int)($_POST['pickup_id'] ?? 0);
    $truckId = (int)($_POST['truck_id'] ?? 0);
    $validTruck = $pdo->prepare('SELECT id FROM collector_trucks WHERE id = ? AND collector_id = ? AND is_active = 1');
    $validTruck->execute([$truckId, $user['id']]);
    if ($validTruck->fetchColumn()) {
        $stmt = $pdo->prepare("UPDATE pickups SET truck_id = ? WHERE id = ? AND collector_id = ? AND status IN ('assigned', 'in_progress')");
        $stmt->execute([$truckId, $pickupId, $user['id']]);
        $success = $stmt->rowCount() ? 'Truck assigned to pickup.' : 'Pickup could not be updated.';
    } else {
        $success = 'Choose one of your active trucks.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_pickup'])) {
    $stmt = $pdo->prepare("UPDATE pickups SET status = 'completed', completed_at = NOW() WHERE id = ? AND collector_id = ? AND truck_id IS NOT NULL AND status IN ('assigned', 'in_progress')");
    $stmt->execute([(int)$_POST['pickup_id'], $user['id']]);
    $success = $stmt->rowCount() ? 'Pickup marked completed.' : 'Assign a truck before completing this pickup.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_complaint_truck'])) {
    $truckId = (int)($_POST['truck_id'] ?? 0);
    $validTruck = $pdo->prepare('SELECT id FROM collector_trucks WHERE id = ? AND collector_id = ? AND is_active = 1');
    $validTruck->execute([$truckId, $user['id']]);
    if ($validTruck->fetchColumn()) {
        $stmt = $pdo->prepare("UPDATE complaints SET truck_id = ? WHERE id = ? AND collector_id = ? AND status != 'resolved'");
        $stmt->execute([$truckId, (int)$_POST['complaint_id'], $user['id']]);
        $success = $stmt->rowCount() ? 'Truck assigned to complaint.' : 'Complaint could not be updated.';
    } else {
        $success = 'Choose one of your active trucks.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_complaint'])) {
    $stmt = $pdo->prepare("UPDATE complaints SET status = 'resolved', updated_at = NOW() WHERE id = ? AND collector_id = ? AND truck_id IS NOT NULL AND status != 'resolved'");
    $stmt->execute([(int)$_POST['complaint_id'], $user['id']]);
    $success = $stmt->rowCount() ? 'Complaint marked resolved.' : 'Assign a truck before resolving this complaint.';
}

$stmt = $pdo->prepare("SELECT p.*, u.full_name, u.phone, u.address, u.pickup_area, t.plate_number FROM pickups p JOIN users u ON p.citizen_id = u.id LEFT JOIN collector_trucks t ON p.truck_id = t.id WHERE p.collector_id = ? AND p.status IN ('assigned','in_progress') ORDER BY p.requested_date ASC");
$stmt->execute([$_SESSION['user_id']]);
$assigned = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT c.*, u.full_name AS citizen_name, t.plate_number FROM complaints c JOIN users u ON c.citizen_id = u.id LEFT JOIN collector_trucks t ON c.truck_id = t.id WHERE c.collector_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$user['id']]);
$complaints = $stmt->fetchAll();

$meetings = $pdo->prepare("SELECT * FROM collector_meetings WHERE audience = 'all' OR FIND_IN_SET(?, collector_ids) > 0 ORDER BY created_at DESC");
$meetings->execute([$_SESSION['user_id']]);
$meetings = $meetings->fetchAll();

$routines = $pdo->query("SELECT * FROM collector_routines WHERE collector_id = {$_SESSION['user_id']} AND is_active = 1 ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time")->fetchAll();

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
    <?php if ($success): ?><div class="alert alert-info"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <div class="mb-4"><a href="../reports.php" class="btn btn-outline-success">Truck work reports</a></div>

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
                    <?php if (!empty($trucks)): ?>
                        <p><strong>Registered trucks:</strong> <?= count($trucks) ?></p>
                        <ul class="mb-0">
                            <?php foreach ($trucks as $truck): ?><li><?= htmlspecialchars($truck['plate_number']) ?></li><?php endforeach; ?>
                        </ul>
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
                                <th>Truck</th>
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
                                    <td><?= htmlspecialchars($p['plate_number'] ?: 'Not assigned') ?></td>
                                    <td><span class="badge bg-info"><?= ucfirst($p['status']) ?></span></td>
                                    <td>
                                        <form method="POST" class="d-flex gap-2 mb-2">
                                            <input type="hidden" name="pickup_id" value="<?= (int)$p['id'] ?>">
                                            <select name="truck_id" class="form-select form-select-sm" required aria-label="Truck for pickup">
                                                <option value="">Choose truck</option>
                                                <?php foreach ($trucks as $truck): ?>
                                                    <option value="<?= (int)$truck['id'] ?>" <?= (int)$p['truck_id'] === (int)$truck['id'] ? 'selected' : '' ?>><?= htmlspecialchars($truck['plate_number']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="assign_pickup_truck" class="btn btn-sm btn-outline-success">Assign</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="pickup_id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" name="complete_pickup" class="btn btn-sm btn-success" <?= empty($p['truck_id']) ? 'disabled' : '' ?>>Mark completed</button>
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
                                <th>Truck</th>
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
                                    <td><?= htmlspecialchars($c['plate_number'] ?: 'Not assigned') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $c['status']==='resolved' ? 'success' : ($c['status']==='in_progress' ? 'warning' : 'danger') ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($c['status'] !== 'resolved'): ?>
                                            <form method="POST" class="d-flex gap-2 mb-2">
                                                <input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">
                                                <select name="truck_id" class="form-select form-select-sm" required aria-label="Truck for complaint">
                                                    <option value="">Choose truck</option>
                                                    <?php foreach ($trucks as $truck): ?>
                                                        <option value="<?= (int)$truck['id'] ?>" <?= (int)$c['truck_id'] === (int)$truck['id'] ? 'selected' : '' ?>><?= htmlspecialchars($truck['plate_number']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="assign_complaint_truck" class="btn btn-sm btn-outline-success">Assign</button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" name="resolve_complaint" class="btn btn-sm btn-outline-success" <?= empty($c['truck_id']) ? 'disabled' : '' ?>>Resolve</button>
                                            </form>
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