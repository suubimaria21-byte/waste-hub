<?php
require_once '../includes/auth.php';
requireRole('admin');
$user = getUser();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['decision'])) {
    $applicationId = (int)$_POST['application_id'];
    $decision = $_POST['decision'] === 'approved' ? 'approved' : 'rejected';
    $notes = trim($_POST['admin_notes'] ?? '');

    $stmt = $pdo->prepare('SELECT user_id FROM collector_applications WHERE id = ? LIMIT 1');
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        $error = 'Application not found.';
    } else {
        $pdo->prepare('UPDATE collector_applications SET status = ?, admin_notes = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute([$decision, $notes, $applicationId]);
        $pdo->prepare('UPDATE users SET approval_status = ? WHERE id = ?')
            ->execute([$decision, $app['user_id']]);
        $success = 'Collector application ' . $decision . ' successfully.';
    }
}

$applications = $pdo->query(
    "SELECT a.*, u.full_name, u.email, u.phone, u.approval_status FROM collector_applications a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collector Applications - Waste Hub</title>
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
            <a href="manage_collector_applications.php" class="active">Collector Requests</a>
            <a href="manage_bins.php">Manage Bins</a>
        </div>

        <div class="col-md-10 p-4">
            <h2 class="mb-4">Collector onboarding requests</h2>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if (empty($applications)): ?>
                <div class="alert alert-info">No collector applications have been submitted yet.</div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($app['company_name']) ?></strong>
                            <span class="badge bg-<?= $app['status'] === 'approved' ? 'success' : ($app['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($app['status']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><strong>Contact:</strong> <?= htmlspecialchars($app['full_name']) ?></div>
                                <div class="col-md-6"><strong>Email:</strong> <?= htmlspecialchars($app['email']) ?></div>
                                <div class="col-md-6"><strong>Phone:</strong> <?= htmlspecialchars($app['phone']) ?></div>
                                <div class="col-md-6"><strong>Trading Licence:</strong> <?= htmlspecialchars($app['trading_license']) ?></div>
                                <div class="col-md-6"><strong>NEMA License:</strong> <?= htmlspecialchars($app['nema_license']) ?></div>
                                <div class="col-md-6"><strong>URSB Registered:</strong> <?= htmlspecialchars($app['ursb_registered'] === 'yes' ? 'Yes' : 'No') ?></div>
                                <div class="col-md-6"><strong>Operational Areas:</strong> <?= htmlspecialchars($app['operational_areas']) ?></div>
                                <div class="col-md-6"><strong>Truck Available:</strong> <?= htmlspecialchars($app['has_truck'] === 'yes' ? 'Yes' : 'No') ?></div>
                                <div class="col-12"><strong>Office Address:</strong> <?= nl2br(htmlspecialchars($app['office_address'])) ?></div>
                                <div class="col-12"><strong>Waste Disposal Plan:</strong> <?= nl2br(htmlspecialchars($app['disposal_plan'])) ?></div>
                                <?php if (!empty($app['admin_notes'])): ?>
                                    <div class="col-12"><strong>Admin Notes:</strong> <?= nl2br(htmlspecialchars($app['admin_notes'])) ?></div>
                                <?php endif; ?>
                            </div>

                            <form method="POST" class="mt-4">
                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label">Review notes</label>
                                        <textarea name="admin_notes" class="form-control" rows="2" placeholder="Add reasons for approval or rejection."><?= htmlspecialchars($app['admin_notes'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-4 d-flex gap-2">
                                        <button type="submit" name="decision" value="approved" class="btn btn-success flex-fill">Approve</button>
                                        <button type="submit" name="decision" value="rejected" class="btn btn-outline-danger flex-fill">Reject</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
