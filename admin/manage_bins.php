<?php
require_once '../includes/auth.php';
requireRole('admin');
$user = getUser();

$success = $error = '';

// Add / Edit bin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
 $location = trim($_POST['location']);
 $capacity = (int)$_POST['capacity'];
 $current_level = (int)$_POST['current_level'];
 $status = $_POST['status'];

 if (empty($location) || $capacity <= 0) {
 $error = 'Location and capacity are required.';
 } else {
 if ($id > 0) {
 $stmt = $pdo->prepare("UPDATE bins SET location=?, capacity=?, current_level=?, status=? WHERE id=?");
 $stmt->execute([$location, $capacity, $current_level, $status, $id]);
 $success = 'Bin updated successfully!';
 } else {
 $stmt = $pdo->prepare("INSERT INTO bins (location, capacity, current_level, status) VALUES (?,?,?,?)");
 $stmt->execute([$location, $capacity, $current_level, $status]);
 $success = 'Bin added successfully!';
 }
 }
}

// Delete bin
if (isset($_GET['delete'])) {
 $id = (int)$_GET['delete'];
 $stmt = $pdo->prepare("DELETE FROM bins WHERE id = ?");
 $stmt->execute([$id]);
 $success = 'Bin deleted successfully!';
}

$bins = $pdo->query("SELECT * FROM bins ORDER BY location")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8">
 <title>Manage Bins - Waste Hub</title>
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
 <a href="manage_bins.php" class="active">Manage Bins</a>
 </div>

 <div class="col-md-10 p-4">
 <div class="d-flex justify-content-between align-items-center mb-4">
 <h2>Bin Management</h2>
 <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#binModal" onclick="resetBinForm()">
 + Add New Bin
 </button>
 </div>

 <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
 <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

 <div class="table-responsive">
 <table class="table table-bordered table-hover">
 <thead class="table-success">
 <tr>
 <th>Location</th>
 <th>Capacity</th>
 <th>Current Level</th>
 <th>Status</th>
 <th>Last Emptied</th>
 <th>Actions</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($bins as $b): ?>
 <tr>
 <td><?= htmlspecialchars($b['location']) ?></td>
 <td><?= $b['capacity'] ?>%</td>
 <td><?= $b['current_level'] ?>%</td>
 <td>
 <?php
 $badge = match($b['status']) {
 'empty' => 'success',
 'half' => 'info',
 'full' => 'warning',
 'overflow' => 'danger',
 default => 'secondary'
 };
 ?>
 <span class="badge bg-<?= $badge ?>"><?= ucfirst($b['status']) ?></span>
 </td>
 <td><?= $b['last_emptied'] ? date('d M Y', strtotime($b['last_emptied'])) : '-' ?></td>
 <td>
 <button class="btn btn-sm btn-outline-primary"
 onclick='editBin(<?= json_encode($b) ?>)'>Edit</button>
 <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger"
 onclick="return confirm('Delete this bin?')">Delete</a>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 </div>
</div>

<!-- Add / Edit Bin Modal -->
<div class="modal fade" id="binModal" tabindex="-1">
 <div class="modal-dialog">
 <form method="POST" class="modal-content">
 <div class="modal-header">
 <h5 class="modal-title" id="binModalTitle">Add New Bin</h5>
 <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
 </div>
 <div class="modal-body">
 <input type="hidden" name="id" id="binId">
 <div class="mb-3">
 <label class="form-label">Location *</label>
 <input type="text" name="location" id="location" class="form-control" required>
 </div>
 <div class="mb-3">
 <label class="form-label">Capacity (%)</label>
 <input type="number" name="capacity" id="capacity" class="form-control" value="100" min="1" max="100">
 </div>
 <div class="mb-3">
 <label class="form-label">Current Level (%)</label>
 <input type="number" name="current_level" id="current_level" class="form-control" value="0" min="0" max="100">
 </div>
 <div class="mb-3">
 <label class="form-label">Status</label>
 <select name="status" id="status" class="form-select">
 <option value="empty">Empty</option>
 <option value="half">Half</option>
 <option value="full">Full</option>
 <option value="overflow">Overflow</option>
 </select>
 </div>
 </div>
 <div class="modal-footer">
 <button type="submit" class="btn btn-success">Save Bin</button>
 </div>
 </form>
 </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetBinForm() {
 document.getElementById('binModalTitle').innerText = 'Add New Bin';
 document.getElementById('binId').value = '';
 document.getElementById('location').value = '';
 document.getElementById('capacity').value = 100;
 document.getElementById('current_level').value = 0;
 document.getElementById('status').value = 'empty';
}

function editBin(b) {
 document.getElementById('binModalTitle').innerText = 'Edit Bin';
 document.getElementById('binId').value = b.id;
 document.getElementById('location').value = b.location;
 document.getElementById('capacity').value = b.capacity;
 document.getElementById('current_level').value = b.current_level;
 document.getElementById('status').value = b.status;
 new bootstrap.Modal(document.getElementById('binModal')).show();
}
</script>
</body>
</html>