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
    $billingCategories = ['informal_household', 'apartment', 'commercial_entity', 'institution'];
    $billingCategory = $_POST['billing_category'] ?? 'informal_household';
    if (!in_array($billingCategory, $billingCategories, true)) {
        $billingCategory = 'informal_household';
    }
    $contractorName = trim($_POST['contractor_name'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $truckCount = max(0, (int)($_POST['truck_count'] ?? 0));
    $truckNumberPlates = trim($_POST['truck_number_plates'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $tradingLicense = trim($_POST['trading_license'] ?? '');
    $nemaLicense = trim($_POST['nema_license'] ?? '');
    $ursbRegistered = $_POST['ursb_registered'] ?? 'no';
    $operationalAreas = trim($_POST['operational_areas'] ?? '');
    $hasTruck = $_POST['has_truck'] ?? 'no';
    $officeAddress = trim($_POST['office_address'] ?? '');
    $disposalPlan = trim($_POST['disposal_plan'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($latitude !== '' && (!is_numeric($latitude) || $latitude < -90 || $latitude > 90)) {
        $error = 'Latitude is invalid. Please enter a value between -90 and 90.';
    } elseif ($longitude !== '' && (!is_numeric($longitude) || $longitude < -180 || $longitude > 180)) {
        $error = 'Longitude is invalid. Please enter a value between -180 and 180.';
    }

    if (!$error && (empty($full_name) || empty($username) || empty($email) || empty($role))) {
        $error = 'Please fill all required fields.';
    } elseif (!$error && $id === 0 && $role === 'collector' && (!$companyName || !$tradingLicense || !$nemaLicense || !$operationalAreas || !$officeAddress || !$disposalPlan)) {
        $error = 'Collector creation requires company name, trading license, NEMA license, operational areas, office address, and disposal plan.';
    } elseif (!$error) {
        try {
            if ($id > 0) {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, role=?, phone=?, address=?, billing_category=?, pickup_area=?, contractor_name=?, latitude=?, longitude=?, truck_count=?, truck_number_plates=?, password=? WHERE id=?");
                    $stmt->execute([$full_name, $username, $email, $role, $phone, $address, $billingCategory, $pickupArea, $contractorName, $latitude !== '' ? (float)$latitude : null, $longitude !== '' ? (float)$longitude : null, $truckCount, $truckNumberPlates, $hashed, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, role=?, phone=?, address=?, billing_category=?, pickup_area=?, contractor_name=?, latitude=?, longitude=?, truck_count=?, truck_number_plates=? WHERE id=?");
                    $stmt->execute([$full_name, $username, $email, $role, $phone, $address, $billingCategory, $pickupArea, $contractorName, $latitude !== '' ? (float)$latitude : null, $longitude !== '' ? (float)$longitude : null, $truckCount, $truckNumberPlates, $id]);
                }
                $success = 'User updated successfully!';
            } else {
                if (empty($password)) {
                    $error = 'Password is required for new users.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->beginTransaction();
                    $approvalStatus = $role === 'collector' ? 'pending' : 'approved';
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, role, approval_status, phone, address, billing_category, pickup_area, contractor_name, latitude, longitude, truck_count, truck_number_plates) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$full_name, $username, $email, $hashed, $role, $approvalStatus, $phone, $address, $billingCategory, $pickupArea, $role === 'collector' ? ($contractorName ?: $companyName) : $contractorName, $latitude !== '' ? (float)$latitude : null, $longitude !== '' ? (float)$longitude : null, $role === 'collector' ? $truckCount : 0, $role === 'collector' ? $truckNumberPlates : null]);
                    $createdUserId = (int)$pdo->lastInsertId();
                    if ($role === 'collector') {
                        $application = $pdo->prepare('INSERT INTO collector_applications (user_id, company_name, trading_license, nema_license, ursb_registered, operational_areas, has_truck, truck_count, truck_number_plates, office_address, disposal_plan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $application->execute([$createdUserId, $companyName, $tradingLicense, $nemaLicense, $ursbRegistered, $operationalAreas, $hasTruck, $truckCount, $truckNumberPlates, $officeAddress, $disposalPlan, 'pending']);
                    }
                    $pdo->commit();
                    $success = 'User created successfully!';
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
            <a href="manage_routines.php">Routine Calendar</a>
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
                    <div class="col-md-6 d-none" id="userBillingFields">
                        <label class="form-label">Citizen billing class</label>
                        <select name="billing_category" id="billing_category" class="form-select">
                            <option value="informal_household">Informal household: UGX 1,000 per bag</option>
                            <option value="apartment">Apartment: UGX 3,000 per bag</option>
                            <option value="commercial_entity">Commercial entity: UGX 8,000 per bag</option>
                            <option value="institution">Institution: UGX 90,000 per load</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="0.000001" name="latitude" id="latitude" class="form-control" placeholder="0.3163">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="0.000001" name="longitude" id="longitude" class="form-control" placeholder="32.5822">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Truck count</label>
                        <input type="number" name="truck_count" id="truck_count" min="0" class="form-control" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Truck number plates</label>
                        <textarea name="truck_number_plates" id="truck_number_plates" class="form-control" rows="2" placeholder="UBA 123A, UBG 876X"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Contractor name</label>
                        <input type="text" name="contractor_name" id="contractor_name" class="form-control" placeholder="KCCA Waste Team, GreenCity Uganda...">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12 d-none" id="collectorApplicationFields">
                        <div class="row g-3 border-top pt-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Company / Business Name *</label>
                                <input type="text" name="company_name" id="company_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Trading License Number *</label>
                                <input type="text" name="trading_license" id="trading_license" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NEMA License Number *</label>
                                <input type="text" name="nema_license" id="nema_license" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registered with URSB? *</label>
                                <select name="ursb_registered" id="ursb_registered" class="form-select">
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Operational areas *</label>
                                <input type="text" name="operational_areas" id="operational_areas" class="form-control" placeholder="Kampala Central, Nakawa, Entebbe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Do you have a truck? *</label>
                                <select name="has_truck" id="has_truck" class="form-select">
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Physical office address *</label>
                                <textarea name="office_address" id="office_address" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Waste disposal plan *</label>
                                <textarea name="disposal_plan" id="disposal_plan" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
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
const roleSelect = document.getElementById('role');
const collectorApplicationFields = document.getElementById('collectorApplicationFields');
const userBillingFields = document.getElementById('userBillingFields');
function syncCollectorFields() {
    const isCollector = roleSelect.value === 'collector' && !document.getElementById('userId').value;
    collectorApplicationFields.classList.toggle('d-none', !isCollector);
    userBillingFields.classList.toggle('d-none', roleSelect.value !== 'citizen');
    collectorApplicationFields.querySelectorAll('input, select, textarea').forEach((field) => {
        field.required = isCollector && ['company_name', 'trading_license', 'nema_license', 'operational_areas', 'office_address', 'disposal_plan'].includes(field.name);
    });
}

roleSelect.addEventListener('change', syncCollectorFields);

function resetForm() {
    document.getElementById('modalTitle').innerText = 'Add New User';
    document.getElementById('userId').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('role').value = 'citizen';
    document.getElementById('phone').value = '';
    document.getElementById('pickup_area').value = '';
    document.getElementById('billing_category').value = 'informal_household';
    document.getElementById('latitude').value = '';
    document.getElementById('longitude').value = '';
    document.getElementById('truck_count').value = '0';
    document.getElementById('truck_number_plates').value = '';
    document.getElementById('contractor_name').value = '';
    document.getElementById('address').value = '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
    syncCollectorFields();
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
    document.getElementById('billing_category').value = u.billing_category || 'informal_household';
    document.getElementById('latitude').value = u.latitude || '';
    document.getElementById('longitude').value = u.longitude || '';
    document.getElementById('truck_count').value = u.truck_count || '0';
    document.getElementById('truck_number_plates').value = u.truck_number_plates || '';
    document.getElementById('contractor_name').value = u.contractor_name || '';
    document.getElementById('address').value = u.address || '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    syncCollectorFields();
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>
</body>
</html>
