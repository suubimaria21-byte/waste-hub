<?php
require_once __DIR__ . '/../db.php';
session_start();

$error = '';
$success = '';

$selectedRole = in_array($_GET['role'] ?? 'citizen', ['citizen', 'collector', 'admin'], true) ? $_GET['role'] : 'citizen';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pickupArea = trim($_POST['pickup_area'] ?? '');
    $role = $selectedRole;
    $approvalStatus = $role === 'collector' ? 'pending' : 'approved';

    $companyName = trim($_POST['company_name'] ?? '');
    $tradingLicense = trim($_POST['trading_license'] ?? '');
    $nemaLicense = trim($_POST['nema_license'] ?? '');
    $ursbRegistered = $_POST['ursb_registered'] ?? 'no';
    $operationalAreas = trim($_POST['operational_areas'] ?? '');
    $hasTruck = $_POST['has_truck'] ?? 'no';
    $officeAddress = trim($_POST['office_address'] ?? '');
    $disposalPlan = trim($_POST['disposal_plan'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
        $error = 'All required fields must be filled.';
    } elseif ($role === 'collector' && (!$companyName || !$tradingLicense || !$nemaLicense || !$operationalAreas || !$officeAddress || !$disposalPlan)) {
        $error = 'Collector applications require company name, trading license, NEMA license, operational areas, office address, and disposal plan.';
    } else {
        try {
            $existing = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $existing->execute([$username, $email]);
            if ($existing->fetch()) {
                $error = 'A user with that username or email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (username, email, password, full_name, role, approval_status, phone, address, pickup_area) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $username,
                    $email,
                    $hashed,
                    $full_name,
                    $role,
                    $approvalStatus,
                    $phone,
                    $address,
                    $pickupArea
                ]);

                $userId = $pdo->lastInsertId();

                if ($role === 'collector') {
                    $appStmt = $pdo->prepare(
                        'INSERT INTO collector_applications (user_id, company_name, trading_license, nema_license, ursb_registered, operational_areas, has_truck, office_address, disposal_plan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $appStmt->execute([
                        $userId,
                        $companyName,
                        $tradingLicense,
                        $nemaLicense,
                        $ursbRegistered,
                        $operationalAreas,
                        $hasTruck,
                        $officeAddress,
                        $disposalPlan,
                        'pending'
                    ]);
                }

                $success = 'Registration successful! Redirecting to login...';
                header('Location: login.php?role=' . urlencode($selectedRole));
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow">
                <div class="card-body p-4">
                    <img class="auth-logo d-block mx-auto mb-3" src="../../images/waste hub logo.png" alt="Waste Hub logo">
                    <h2 class="text-center text-success mb-4">Create Account</h2>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="registerPassword" class="form-control" required minlength="6">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('registerPassword')">Show</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registering as</label>
                                <div class="form-control bg-light text-capitalize"><?= htmlspecialchars($selectedRole) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <?php if ($selectedRole === 'collector'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Desired operational area(s) *</label>
                                    <input type="text" name="pickup_area" class="form-control" placeholder="Kampala Central, Nakawa, Entebbe" required>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label class="form-label">Pickup location *</label>
                                    <input type="text" name="pickup_area" class="form-control" placeholder="e.g. Kisaasi, Wandegeya, Ntinda" required>
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <label class="form-label">Address / more location details</label>
                                <textarea name="address" class="form-control" rows="2"></textarea>
                            </div>

                            <?php if ($selectedRole === 'collector'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Company / Business Name *</label>
                                    <input type="text" name="company_name" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Trading License Number *</label>
                                    <input type="text" name="trading_license" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">NEMA License Number *</label>
                                    <input type="text" name="nema_license" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Registered with URSB? *</label>
                                    <select name="ursb_registered" class="form-select">
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">operational areas *</label>
                                    <input type="text" name="operational_areas" class="form-control" placeholder="e.g. Kampala Central, Nakawa, Katwe">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Do you have a truck? *</label>
                                    <select name="has_truck" class="form-select">
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Physical office address *</label>
                                    <textarea name="office_address" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Waste disposal plan after collection *</label>
                                    <textarea name="disposal_plan" class="form-control" rows="3"></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mt-4">Register</button>
                    </form>
                    <p class="text-center mt-3">Already have an account? <a href="login.php?role=<?= htmlspecialchars($selectedRole) ?>">Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('button');
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = 'Hide';
    } else {
        input.type = 'password';
        button.textContent = 'Show';
    }
}
</script>
</body>
</html>