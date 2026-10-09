<?php
require_once __DIR__ . '/../db.php';
session_start();

$error = '';
$success = '';

$selectedRole = in_array($_GET['role'] ?? 'citizen', ['citizen', 'collector', 'admin'], true) ? $_GET['role'] : 'citizen';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pickupArea = trim($_POST['pickup_area'] ?? '');
    $billingCategory = $_POST['billing_category'] ?? 'informal_household';
    $billingCategories = ['informal_household', 'apartment', 'commercial_entity', 'institution'];
    if (!in_array($billingCategory, $billingCategories, true)) {
        $billingCategory = 'informal_household';
    }
    $latitude = trim((string)($_POST['latitude'] ?? ''));
    $longitude = trim((string)($_POST['longitude'] ?? ''));
    $role = $selectedRole;
    $approvalStatus = $role === 'collector' ? 'pending' : 'approved';

    $companyName = trim($_POST['company_name'] ?? '');
    $tradingLicense = trim($_POST['trading_license'] ?? '');
    $nemaLicense = trim($_POST['nema_license'] ?? '');
    $ursbRegistered = $_POST['ursb_registered'] ?? 'no';
    $operationalAreas = trim($_POST['operational_areas'] ?? '');
    $hasTruck = $_POST['has_truck'] ?? 'no';
    $truckCount = isset($_POST['truck_count']) ? max(0, (int)$_POST['truck_count']) : 0;
    $truckNumberPlates = trim($_POST['truck_number_plates'] ?? '');
    $officeAddress = trim($_POST['office_address'] ?? '');
    $disposalPlan = trim($_POST['disposal_plan'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($pickupArea)) {
        $error = 'Please complete the required fields and add your pickup area before continuing.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'The email address is not valid. Please enter a working email format like name@example.com.';
    } elseif (strlen($password) < 6) {
        $error = 'The password must be at least 6 characters long. Please choose a longer password.';
    } elseif ($latitude !== '' && (!is_numeric($latitude) || $latitude < -90 || $latitude > 90)) {
        $error = 'Latitude is invalid. Please enter a value between -90 and 90.';
    } elseif ($longitude !== '' && (!is_numeric($longitude) || $longitude < -180 || $longitude > 180)) {
        $error = 'Longitude is invalid. Please enter a value between -180 and 180.';
    } elseif ($role === 'collector' && (!$companyName || !$tradingLicense || !$nemaLicense || !$operationalAreas || !$officeAddress || !$disposalPlan)) {
        $error = 'Collector applications require company name, trading license, NEMA license, operational areas, office address, and disposal plan.';
    } else {
        try {
            $existing = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $existing->execute([$username, $email]);
            if ($existing->fetch()) {
                $error = 'A user with that username or email already exists. Please choose a different username or email.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (username, email, password, full_name, role, approval_status, phone, address, billing_category, pickup_area, latitude, longitude, gps_last_updated, truck_count, truck_number_plates) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)"
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
                    $role === 'citizen' ? $billingCategory : 'informal_household',
                    $pickupArea,
                    $latitude !== '' ? (float)$latitude : null,
                    $longitude !== '' ? (float)$longitude : null,
                    $role === 'collector' ? $truckCount : 0,
                    $role === 'collector' ? $truckNumberPlates : null,
                ]);

                $userId = $pdo->lastInsertId();

                if ($role === 'collector') {
                    $appStmt = $pdo->prepare(
                        'INSERT INTO collector_applications (user_id, company_name, trading_license, nema_license, ursb_registered, operational_areas, has_truck, truck_count, truck_number_plates, office_address, disposal_plan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $appStmt->execute([
                        $userId,
                        $companyName,
                        $tradingLicense,
                        $nemaLicense,
                        $ursbRegistered,
                        $operationalAreas,
                        $hasTruck,
                        $truckCount,
                        $truckNumberPlates,
                        $officeAddress,
                        $disposalPlan,
                        'pending'
                    ]);
                }

                $success = 'Registration successful! Redirecting to login...';
                $redirectUrl = 'login.php?role=' . urlencode($selectedRole);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
                    exit;
                }
                header('Location: ' . $redirectUrl);
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Registration failed because of a database error. Please review the details and try again.';
        }
    }

    if ($isAjax) {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
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
                    <?php if ($error): ?><div class="alert alert-danger" id="formMessage"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <form method="POST" id="registerForm" novalidate>
                        <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="registerPassword" class="form-control" required minlength="6" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('registerPassword')">Show</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registering as</label>
                                <div class="form-control bg-light text-capitalize"><?= htmlspecialchars($selectedRole) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                            <?php if ($selectedRole === 'citizen'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Household or organization type *</label>
                                    <select name="billing_category" class="form-select" required>
                                        <option value="informal_household" <?= (($_POST['billing_category'] ?? 'informal_household') === 'informal_household') ? 'selected' : '' ?>>Informal household: UGX 1,000 per bag</option>
                                        <option value="apartment" <?= (($_POST['billing_category'] ?? '') === 'apartment') ? 'selected' : '' ?>>Apartment: UGX 3,000 per bag</option>
                                        <option value="commercial_entity" <?= (($_POST['billing_category'] ?? '') === 'commercial_entity') ? 'selected' : '' ?>>Commercial entity: UGX 8,000 per bag</option>
                                        <option value="institution" <?= (($_POST['billing_category'] ?? '') === 'institution') ? 'selected' : '' ?>>Institution: UGX 90,000 per load</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <?php if ($selectedRole === 'collector'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Desired operational area(s) *</label>
                                    <input type="text" name="pickup_area" class="form-control" placeholder="Kampala Central, Nakawa, Entebbe" value="<?= htmlspecialchars($_POST['pickup_area'] ?? '') ?>" required>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label class="form-label">Pickup location *</label>
                                    <input type="text" name="pickup_area" class="form-control" placeholder="e.g. Kisaasi, Wandegeya, Ntinda" value="<?= htmlspecialchars($_POST['pickup_area'] ?? '') ?>" required>
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <label class="form-label">Address / more location details</label>
                                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>

                            <?php if ($selectedRole === 'collector'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Company / Business Name *</label>
                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Trading License Number *</label>
                                    <input type="text" name="trading_license" class="form-control" value="<?= htmlspecialchars($_POST['trading_license'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">NEMA License Number *</label>
                                    <input type="text" name="nema_license" class="form-control" value="<?= htmlspecialchars($_POST['nema_license'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Registered with URSB? *</label>
                                    <select name="ursb_registered" class="form-select">
                                        <option value="yes" <?= (($_POST['ursb_registered'] ?? 'yes') === 'yes') ? 'selected' : '' ?>>Yes</option>
                                        <option value="no" <?= (($_POST['ursb_registered'] ?? 'yes') === 'no') ? 'selected' : '' ?>>No</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Operational areas *</label>
                                    <input type="text" name="operational_areas" class="form-control" placeholder="e.g. Kampala Central, Nakawa, Katwe" value="<?= htmlspecialchars($_POST['operational_areas'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Do you have a truck? *</label>
                                    <select name="has_truck" class="form-select">
                                        <option value="yes" <?= (($_POST['has_truck'] ?? 'yes') === 'yes') ? 'selected' : '' ?>>Yes</option>
                                        <option value="no" <?= (($_POST['has_truck'] ?? 'yes') === 'no') ? 'selected' : '' ?>>No</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">How many trucks do you operate? *</label>
                                    <input type="number" name="truck_count" min="0" class="form-control" value="<?= htmlspecialchars($_POST['truck_count'] ?? '1') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Truck number plates *</label>
                                    <textarea name="truck_number_plates" class="form-control" rows="2" placeholder="e.g. UBA 123A, UBG 876X"><?= htmlspecialchars($_POST['truck_number_plates'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Physical office address *</label>
                                    <textarea name="office_address" class="form-control" rows="2"><?= htmlspecialchars($_POST['office_address'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Waste disposal plan after collection *</label>
                                    <textarea name="disposal_plan" class="form-control" rows="3"><?= htmlspecialchars($_POST['disposal_plan'] ?? '') ?></textarea>
                                </div>
                            <?php endif; ?>
                            <?php if ($selectedRole === 'citizen'): ?>
                                <div class="col-12">
                                    <button type="button" class="btn btn-outline-success" data-location-lookup>Find my location</button>
                                    <p class="form-text" data-location-message>Enter your area or address above, then find its approximate point on the Uganda map. The search is sent to OpenStreetMap.</p>
                                    <input type="hidden" name="latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>">
                                    <input type="hidden" name="longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="0.000001" name="latitude" class="form-control" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>" placeholder="0.3163">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="0.000001" name="longitude" class="form-control" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>" placeholder="32.5822">
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
<script src="../../assets/js/location.js"></script>
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

const form = document.getElementById('registerForm');
<?php if ($selectedRole === 'citizen'): ?>
setupUgandaLocationLookup('registerForm');
<?php endif; ?>
if (form) {
    form.addEventListener('submit', function (event) {
        const email = form.querySelector('input[name="email"]');
        const password = form.querySelector('input[name="password"]');
        const pickupArea = form.querySelector('input[name="pickup_area"]');
        const longitude = form.querySelector('input[name="longitude"]');
        const latitude = form.querySelector('input[name="latitude"]');

        let message = '';

        if (!email.value.trim() || !email.validity.valid) {
            message = 'Please enter a valid email address such as name@example.com.';
            email.focus();
        } else if (!password.value || password.value.length < 6) {
            message = 'Password must be at least 6 characters long. Please update it before submitting.';
            password.focus();
        } else if (!pickupArea.value.trim()) {
            message = 'Pickup area is required. Please add the area where you live or work.';
            pickupArea.focus();
        } else if (latitude && latitude.value && (Number(latitude.value) < -90 || Number(latitude.value) > 90)) {
            message = 'Latitude is invalid. Please enter a value between -90 and 90.';
            latitude.focus();
        } else if (longitude && longitude.value && (Number(longitude.value) < -180 || Number(longitude.value) > 180)) {
            message = 'Longitude is invalid. Please enter a value between -180 and 180.';
            longitude.focus();
        }

        if (message) {
            event.preventDefault();
            showRegisterMessage(message);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        fetch(form.action || window.location.href, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then((response) => response.json()).then((result) => {
            if (result.success && result.redirect) {
                window.location.assign(result.redirect);
            } else {
                showRegisterMessage(result.error || 'Registration failed. Please review your details and try again.');
                submitButton.disabled = false;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }).catch(() => {
            showRegisterMessage('Unable to complete registration right now. Check your connection and try again.');
            submitButton.disabled = false;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
}

function showRegisterMessage(message) {
    let box = document.getElementById('formMessage');
    if (!box) {
        box = document.createElement('div');
        box.id = 'formMessage';
        form.insertBefore(box, form.firstChild);
    }
    box.className = 'alert alert-danger';
    box.textContent = message;
}
</script>
</body>
</html>