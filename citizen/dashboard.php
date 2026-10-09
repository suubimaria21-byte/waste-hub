<?php
require_once '../includes/auth.php';
requireRole('citizen');
$user = getUser();

$stmt = $pdo->prepare("SELECT p.*, u.full_name AS collector_name, pp.total_amount, pp.currency, pp.payment_timing, pp.payment_method, pp.status AS payment_status FROM pickups p LEFT JOIN users u ON p.collector_id = u.id LEFT JOIN pickup_payments pp ON pp.pickup_id = p.id WHERE p.citizen_id = ? ORDER BY p.created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$pickups = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT c.*, u.full_name AS collector_name FROM complaints c LEFT JOIN users u ON c.collector_id = u.id WHERE c.citizen_id = ? ORDER BY c.created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="dashboard.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub</span></a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white">Welcome, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="../profile.php" class="btn btn-outline-light btn-sm">Edit Profile</a>
            <a href="../config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-0">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="request_pickup.php">Request Pickup</a>
            <a href="payments.php">Invoices and payments</a>
            <a href="submit_complaint.php">Submit Complaint</a>
            <a href="../profile.php">Edit Profile</a>
        </div>
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">My service dashboard</h2>
                    <p class="text-muted mb-0">Pickup activity, issue reporting, and your assigned service area.</p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Pickup area</div>
                                <h5 class="mb-0"><?= htmlspecialchars($user['pickup_area'] ?: 'Kampala Central') ?></h5>
                            </div>
                            <div class="stat-icon text-success">📍</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Open issues</div>
                                <h5 class="mb-0"><?= (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE citizen_id = {$_SESSION['user_id']} AND status != 'resolved'")->fetchColumn() ?></h5>
                            </div>
                            <div class="stat-icon text-warning">⚠️</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Billing category</div>
                                <h5 class="mb-0 text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $user['billing_category'] ?? 'informal household')) ?></h5>
                            </div>
                            <div class="stat-icon text-info">🧹</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">Recent pickup requests</div>
                        <div class="card-body p-0">
                            <?php if (empty($pickups)): ?>
                                <div class="p-3 text-muted">No pickup requests yet.</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($pickups as $p): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= date('d M Y', strtotime($p['requested_date'])) ?></strong><br>
                                                <small class="text-muted d-block"><?= htmlspecialchars($p['collector_name'] ?: 'Awaiting assignment') ?></small>
                                                <?php if ($p['total_amount'] !== null): ?>
                                                    <?php $paymentLabel = $p['payment_status'] === 'demo_paid' ? 'Paid (demo)' : ucfirst(str_replace('_', ' ', $p['payment_status'])); ?>
                                                    <small class="text-muted d-block">Invoice: <?= htmlspecialchars($p['currency']) ?> <?= number_format((float)$p['total_amount']) ?> · <?= htmlspecialchars($paymentLabel) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-<?= $p['status']==='completed'?'success':($p['status']==='pending'?'warning':'info') ?>"><?= ucfirst($p['status']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">Recent complaints</div>
                        <div class="card-body p-0">
                            <?php if (empty($complaints)): ?>
                                <div class="p-3 text-muted">No complaints submitted.</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($complaints as $c): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($c['title']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($c['collector_name'] ?: 'Pending assignment') ?></small>
                                            </div>
                                            <span class="badge bg-<?= $c['status']==='resolved'?'success':($c['status']==='open'?'danger':'warning') ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>