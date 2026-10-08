<?php
require_once '../includes/auth.php';
requireRole('admin');

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['complaint_id'];
    $status = $_POST['status'];
    $collectorId = isset($_POST['collector_id']) && $_POST['collector_id'] !== '' ? (int)$_POST['collector_id'] : null;
    $notes = trim($_POST['admin_notes'] ?? '');
    $stmt = $pdo->prepare("UPDATE complaints SET status=?, collector_id=?, admin_notes=?, assigned_at=NOW() WHERE id=?");
    $stmt->execute([$status, $collectorId, $notes, $id]);
    $success = 'Complaint updated successfully!';
}

$complaints = $pdo->query("SELECT c.*, u.full_name, co.full_name AS collector_name FROM complaints c JOIN users u ON c.citizen_id = u.id LEFT JOIN users co ON c.collector_id = co.id ORDER BY c.created_at DESC")->fetchAll();
$collectors = $pdo->query("SELECT id, full_name, contractor_name FROM users WHERE role='collector' ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Complaints - Waste Hub</title>
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
    <h2 class="mb-4">Manage complaints and collector assignments</h2>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-success">
                <tr>
                    <th>Citizen</th>
                    <th>Issue</th>
                    <th>Area</th>
                    <th>Assigned collector</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['full_name']) ?></td>
                        <td><?= htmlspecialchars($c['title']) ?></td>
                        <td><?= htmlspecialchars($c['area_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['collector_name'] ?: 'Unassigned') ?></td>
                        <td>
                            <span class="badge bg-<?= $c['status']==='resolved'?'success':($c['status']==='open'?'danger':'warning') ?>">
                                <?= ucfirst(str_replace('_',' ',$c['status'])) ?>
                            </span>
                        </td>
                        <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal<?= $c['id'] ?>">Update</button>
                        </td>
                    </tr>

                    <div class="modal fade" id="modal<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update complaint</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong><?= htmlspecialchars($c['title']) ?></strong></p>
                                        <p><?= nl2br(htmlspecialchars($c['description'])) ?></p>
                                        <?php if ($c['photo']): ?>
                                            <img src="../uploads/<?= htmlspecialchars($c['photo']) ?>" class="img-fluid mb-3" style="max-height:200px">
                                        <?php endif; ?>
                                        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Assign collector</label>
                                            <select name="collector_id" class="form-select">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($collectors as $collector): ?>
                                                    <option value="<?= $collector['id'] ?>" <?= (int)$c['collector_id'] === (int)$collector['id'] ? 'selected' : '' ?>><?= htmlspecialchars($collector['full_name']) ?> - <?= htmlspecialchars($collector['contractor_name'] ?: 'KCCA') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="open" <?= $c['status']==='open'?'selected':'' ?>>Open</option>
                                                <option value="in_progress" <?= $c['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                                                <option value="resolved" <?= $c['status']==='resolved'?'selected':'' ?>>Resolved</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Admin notes</label>
                                            <textarea name="admin_notes" class="form-control" rows="3"><?= htmlspecialchars($c['admin_notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-success">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>