<?php
require_once __DIR__ . '/includes/auth.php';
$user = getUser();

$success = $error = '';
$billingCategories = ['informal_household', 'apartment', 'commercial_entity', 'institution'];
$billingCategory = $user['billing_category'] ?? 'informal_household';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pickupArea = trim($_POST['pickup_area'] ?? $user['pickup_area'] ?? 'Kampala Central');
    $latitude = trim($_POST['latitude'] ?? ($user['latitude'] ?? ''));
    $longitude = trim($_POST['longitude'] ?? ($user['longitude'] ?? ''));
    $billingCategory = $_POST['billing_category'] ?? ($user['billing_category'] ?? 'informal_household');
    if (!in_array($billingCategory, $billingCategories, true)) {
        $billingCategory = $user['billing_category'] ?? 'informal_household';
    }
    $truckCount = max(0, (int)($_POST['truck_count'] ?? ($user['truck_count'] ?? 0)));
    $truckNumberPlates = trim($_POST['truck_number_plates'] ?? ($user['truck_number_plates'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($latitude !== '' && (!is_numeric($latitude) || $latitude < -90 || $latitude > 90)) {
        $error = 'Latitude is invalid. Please enter a value between -90 and 90.';
    } elseif ($longitude !== '' && (!is_numeric($longitude) || $longitude < -180 || $longitude > 180)) {
        $error = 'Longitude is invalid. Please enter a value between -180 and 180.';
    } elseif (empty($username) || empty($email)) {
        $error = 'Username and email are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1');
        $check->execute([$username, $email, $user['id']]);

        if ($check->fetch()) {
            $error = 'That username or email is already in use.';
        } else {
            if ($password !== '' && strlen($password) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } else {
                $sql = 'UPDATE users SET username = ?, email = ?, phone = ?, address = ?, pickup_area = ?, latitude = ?, longitude = ?, gps_last_updated = NOW()';
                $params = [$username, $email, $phone, $address, $pickupArea, $latitude !== '' ? (float)$latitude : null, $longitude !== '' ? (float)$longitude : null];

                if ($user['role'] === 'citizen') {
                    $sql .= ', billing_category = ?';
                    $params[] = $billingCategory;
                }

                if ($user['role'] === 'collector') {
                    $sql .= ', truck_count = ?, truck_number_plates = ?';
                    $params[] = $truckCount;
                    $params[] = $truckNumberPlates;
                }

                if ($password !== '') {
                    $sql .= ', password = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql .= ' WHERE id = ?';
                $params[] = $user['id'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $success = 'Your profile was updated successfully.';
                $user = getUser();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="<?= htmlspecialchars($user['role'] === 'admin' ? 'admin/dashboard.php' : ($user['role'] === 'collector' ? 'collector/dashboard.php' : 'citizen/dashboard.php')) ?>">
            <img class="site-logo" src="images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white">Welcome, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h3 class="text-success mb-4">Edit profile</h3>
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                    <form method="POST" id="profileForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control text-capitalize" value="<?= htmlspecialchars($user['role']) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Service area</label>
                                <input type="text" name="pickup_area" class="form-control" value="<?= htmlspecialchars($user['pickup_area'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                            <?php if ($user['role'] === 'citizen'): ?>
                            <div class="col-md-6">
                                <label class="form-label">Household or organization type</label>
                                <select name="billing_category" class="form-select">
                                    <option value="informal_household" <?= ($billingCategory === 'informal_household') ? 'selected' : '' ?>>Informal household: UGX 1,000 per bag</option>
                                    <option value="apartment" <?= ($billingCategory === 'apartment') ? 'selected' : '' ?>>Apartment: UGX 3,000 per bag</option>
                                    <option value="commercial_entity" <?= ($billingCategory === 'commercial_entity') ? 'selected' : '' ?>>Commercial entity: UGX 8,000 per bag</option>
                                    <option value="institution" <?= ($billingCategory === 'institution') ? 'selected' : '' ?>>Institution: UGX 90,000 per load</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-success" data-location-lookup>Find location on map</button>
                                <p class="form-text" data-location-message><?= !empty($user['latitude']) && !empty($user['longitude']) ? 'A saved map point exists. Find location again after changing your area or address.' : 'Find an approximate Uganda map point from your area and address.' ?></p>
                                <input type="hidden" name="latitude" value="<?= htmlspecialchars((string)($user['latitude'] ?? '')) ?>">
                                <input type="hidden" name="longitude" value="<?= htmlspecialchars((string)($user['longitude'] ?? '')) ?>">
                            </div>
                            <?php if ($user['role'] === 'collector'): ?>
                            <div class="col-md-6">
                                <label class="form-label">Truck count</label>
                                <input type="number" name="truck_count" min="0" class="form-control" value="<?= htmlspecialchars((string)($user['truck_count'] ?? 0)) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Truck number plates</label>
                                <textarea name="truck_number_plates" class="form-control" rows="2"><?= htmlspecialchars($user['truck_number_plates'] ?? '') ?></textarea>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="form-label">New password</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="profilePassword" class="form-control" placeholder="Leave blank to keep current password">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('profilePassword')">Show</button>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address / more details</label>
                                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mt-4">Save profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/location.js"></script>
<script>
setupUgandaLocationLookup('profileForm');

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const btn = input.parentElement.querySelector('button');
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Hide';
    } else {
        input.type = 'password';
        btn.textContent = 'Show';
    }
}
</script>
</body>
</html>
