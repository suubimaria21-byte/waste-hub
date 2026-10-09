<?php
require_once '../includes/auth.php';
requireRole('admin');
$user = getUser();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $audience = in_array($_POST['audience'] ?? 'all', ['all', 'selected'], true) ? $_POST['audience'] : 'all';
    $selectedCollectorIds = $_POST['collector_ids'] ?? [];
    $scheduledFor = trim($_POST['scheduled_for'] ?? '');

    if (empty($title) || empty($message)) {
        $error = 'Please provide a meeting title and message.';
    } elseif ($audience === 'selected' && empty($selectedCollectorIds)) {
        $error = 'Select at least one collector if the notice is for specific collectors only.';
    } else {
        $collectorIds = $audience === 'selected' ? implode(',', array_map('intval', $selectedCollectorIds)) : '';
        $sql = 'INSERT INTO collector_meetings (title, message, audience, collector_ids, scheduled_for, created_by) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $message, $audience, $collectorIds, $scheduledFor !== '' ? $scheduledFor : null, $user['id']]);
        $success = 'The collector meeting notice has been shared successfully.';
    }
}

$collectors = $pdo->query("SELECT id, full_name, contractor_name FROM users WHERE role = 'collector' ORDER BY full_name")->fetchAll();
$meetings = $pdo->query("SELECT m.*, u.full_name AS admin_name FROM collector_meetings m JOIN users u ON u.id = m.created_by ORDER BY m.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collector Meetings - Waste Hub</title>
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
            <a href="manage_routines.php">Routine Calendar</a>
            <a href="manage_meetings.php" class="active">Collector Meetings</a>
        </div>

        <div class="col-md-10 p-4">
            <h2 class="mb-4">Call a collector meeting</h2>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Meeting title</label>
                                <input type="text" name="title" class="form-control" placeholder="Weekly operations review" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Schedule for</label>
                                <input type="datetime-local" name="scheduled_for" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Audience</label>
                                <select name="audience" class="form-select" id="meetingAudience">
                                    <option value="all">All collectors</option>
                                    <option value="selected">Selected collectors</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message</label>
                                <textarea name="message" class="form-control" rows="5" placeholder="Share agenda, location, reminders, and action points for the meeting." required></textarea>
                            </div>

                            <div class="col-12" id="collectorSelection" style="display:none;">
                                <label class="form-label">Choose collectors</label>
                                <div class="row g-2">
                                    <?php foreach ($collectors as $collector): ?>
                                        <div class="col-md-4">
                                            <label class="d-flex align-items-center gap-2 border rounded p-2">
                                                <input type="checkbox" name="collector_ids[]" value="<?= $collector['id'] ?>">
                                                <span><?= htmlspecialchars($collector['full_name']) ?>
                                                    <?php if (!empty($collector['contractor_name'])): ?>
                                                        <small class="text-muted d-block"><?= htmlspecialchars($collector['contractor_name']) ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4">Send meeting notice</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white">Recent meeting notices</div>
                <div class="card-body">
                    <?php if (empty($meetings)): ?>
                        <div class="text-muted">No meeting notices have been sent yet.</div>
                    <?php else: ?>
                        <?php foreach ($meetings as $meeting): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-1"><?= htmlspecialchars($meeting['title']) ?></h5>
                                    <span class="badge bg-secondary text-capitalize"><?= htmlspecialchars($meeting['audience']) ?></span>
                                </div>
                                <div class="text-muted small mb-2">
                                    Sent by <?= htmlspecialchars($meeting['admin_name']) ?>
                                    <?php if (!empty($meeting['scheduled_for'])): ?>
                                        • Scheduled: <?= date('d M Y, h:i A', strtotime($meeting['scheduled_for'])) ?>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-2"><?= nl2br(htmlspecialchars($meeting['message'])) ?></p>
                                <?php if ($meeting['audience'] === 'selected' && !empty($meeting['collector_ids'])): ?>
                                    <div class="small text-muted">Recipients: <?= htmlspecialchars($meeting['collector_ids']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const audienceSelect = document.getElementById('meetingAudience');
const selection = document.getElementById('collectorSelection');

audienceSelect.addEventListener('change', function () {
    selection.style.display = this.value === 'selected' ? 'block' : 'none';
});
</script>
</body>
</html>
