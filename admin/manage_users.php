<?php
require_once '../includes/auth.php';
requireRole('admin');
$user = getUser();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pickupArea = trim($_POST['pickup_area'] ?? 'Kampala Central');
    $contractorName = trim($_POST['contractor_name'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($username) || empty($email) || empty($role)) {
        $error = 'Please fill all required fields.';
    } else {
        try {
            if ($id > 0) {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, role=?, phone=?, address=?, pickup_area=?, contractor_name=?, password=? WHERE id=?");
                    $stmt->execute([$full_name, $username, $email, $role, $phone, $address, $pickupArea, $contractorName, $hashed, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, role=?, phone=?, address=?, pickup_area=?, contractor_name=? WHERE id=?");
                    $stmt->execute([$full_name, $username, $email, $role, $phone, $address, $pickupArea, $contractorName, $id]);
                }
                $success = 'User updated successfully!';
            } else {
                if (empty($password)) {
                    $error = 'Password is required for new users.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, role, phone, address, pickup_area, contractor_name) VALUES (?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$full_name, $username, $email, $hashed, $role, $phone, $address, $pickupArea, $contractorName]);
                    $success = 'User created successfully!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Username or email already exists.';
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'User deleted successfully!';
    } else {
        $error = 'You cannot delete your own account.';
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Waste Hub</title>
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
            <a href="manage_users.php" class="active">Manage Users</a>
            <a href="manage_bins.php">Manage Bins</a>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>User management</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">+ Add new user</button>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-success">
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Area</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge bg-<?= $u['role']==='admin'?'danger':($u['role']==='collector'?'primary':'secondary') ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td><?= htmlspecialchars($u['pickup_area'] ?: '-') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u) ?>)'>Edit</button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="userId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role *</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="citizen">Citizen</option>
                            <option value="collector">Collector</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="phone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Service area</label>
                        <input type="text" name="pickup_area" id="pickup_area" class="form-control" placeholder="e.g. Nakawa, Wandegeya, Entebbe">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Contractor name</label>
                        <input type="text" name="contractor_name" id="contractor_name" class="form-control" placeholder="KCCA Waste Team, GreenCity Uganda...">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password <small class="text-muted">(required for new users)</small></label>
                        <input type="password" name="password" id="password" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save user</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetForm() {
    document.getElementById('modalTitle').innerText = 'Add New User';
    document.getElementById('userId').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('role').value = 'citizen';
    document.getElementById('phone').value = '';
    document.getElementById('pickup_area').value = '';
    document.getElementById('contractor_name').value = '';
    document.getElementById('address').value = '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
}

function editUser(u) {
    document.getElementById('modalTitle').innerText = 'Edit User';
    document.getElementById('userId').value = u.id;
    document.getElementById('full_name').value = u.full_name || '';
    document.getElementById('username').value = u.username || '';
    document.getElementById('email').value = u.email || '';
    document.getElementById('role').value = u.role || 'citizen';
    document.getElementById('phone').value = u.phone || '';
    document.getElementById('pickup_area').value = u.pickup_area || '';
    document.getElementById('contractor_name').value = u.contractor_name || '';
    document.getElementById('address').value = u.address || '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>
</body>
</html>
