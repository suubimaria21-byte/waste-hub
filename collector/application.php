<?php
require_once '../includes/auth.php';
requireRole('collector');

$user = getUser();

if (($user['approval_status'] ?? 'approved') === 'approved') {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

$application = $pdo->prepare('SELECT * FROM collector_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$application->execute([$user['id']]);
$app = $application->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $tradingLicense = trim($_POST['trading_license'] ?? '');
    $nemaLicense = trim($_POST['nema_license'] ?? '');
    $ursbRegistered = $_POST['ursb_registered'] ?? 'no';
    $operationalAreas = trim($_POST['operational_areas'] ?? '');
    $hasTruck = $_POST['has_truck'] ?? 'no';
    $truckCount = max(0, (int)($_POST['truck_count'] ?? 0));
    $truckNumberPlates = trim($_POST['truck_number_plates'] ?? '');
    $officeAddress = trim($_POST['office_address'] ?? '');
    $disposalPlan = trim($_POST['disposal_plan'] ?? '');

    if (!$companyName || !$tradingLicense || !$nemaLicense || !$operationalAreas || !$officeAddress || !$disposalPlan) {
        $error = 'Please fill in all required fields.';
    } else {
        $status = 'pending';
        if ($app) {
            $stmt = $pdo->prepare(
                'UPDATE collector_applications SET company_name=?, trading_license=?, nema_license=?, ursb_registered=?, operational_areas=?, has_truck=?, truck_count=?, truck_number_plates=?, office_address=?, disposal_plan=?, status=?, admin_notes=NULL, reviewed_at=NULL WHERE id=?'
            );
            $stmt->execute([$companyName, $tradingLicense, $nemaLicense, $ursbRegistered, $operationalAreas, $hasTruck, $truckCount, $truckNumberPlates, $officeAddress, $disposalPlan, $status, $app['id']]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO collector_applications (user_id, company_name, trading_license, nema_license, ursb_registered, operational_areas, has_truck, truck_count, truck_number_plates, office_address, disposal_plan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'], $companyName, $tradingLicense, $nemaLicense, $ursbRegistered, $operationalAreas, $hasTruck, $truckCount, $truckNumberPlates, $officeAddress, $disposalPlan, $status]);
        }

        $userUpdate = $pdo->prepare('UPDATE users SET approval_status = ?, truck_count = ?, truck_number_plates = ? WHERE id = ?');
        $userUpdate->execute(['pending', $truckCount, $truckNumberPlates, $user['id']]);
        $success = 'Your collector application has been submitted successfully and is awaiting admin review.';
        $application = $pdo->prepare('SELECT * FROM collector_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $application->execute([$user['id']]);
        $app = $application->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collector Application - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="dashboard.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub - Collector</span></a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white">Welcome, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="../config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span>Collector onboarding request</span>
                    <span class="badge bg-light text-success">
                        <?= ucfirst($user['approval_status'] ?? 'pending') ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                    <?php if (!empty($app) && $app['status'] !== 'pending'): ?>
                        <div class="alert alert-<?= $app['status'] === 'approved' ? 'success' : 'danger' ?> mb-4">
                            <strong>Admin decision:</strong> <?= htmlspecialchars($app['status'] === 'approved' ? 'Approved' : 'Rejected') ?>
                            <?php if (!empty($app['admin_notes'])): ?>
                                <div class="mt-2"><?= nl2br(htmlspecialchars($app['admin_notes'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($app) && $app['status'] === 'rejected'): ?>
                        <div class="alert alert-warning">You may update your information and resubmit your application for review.</div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Company / Business Name *</label>
                                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($app['company_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Trading License Number *</label>
                                <input type="text" name="trading_license" class="form-control" value="<?= htmlspecialchars($app['trading_license'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NEMA License Number *</label>
                                <input type="text" name="nema_license" class="form-control" value="<?= htmlspecialchars($app['nema_license'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registered with URSB? *</label>
                                <select name="ursb_registered" class="form-select" required>
                                    <option value="yes" <?= (($app['ursb_registered'] ?? 'yes') === 'yes') ? 'selected' : '' ?>>Yes</option>
                                    <option value="no" <?= (($app['ursb_registered'] ?? 'yes') === 'no') ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Do you have a truck? *</label>
                                <select name="has_truck" class="form-select" required>
                                    <option value="yes" <?= (($app['has_truck'] ?? 'yes') === 'yes') ? 'selected' : '' ?>>Yes</option>
                                    <option value="no" <?= (($app['has_truck'] ?? 'yes') === 'no') ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">How many trucks do you operate? *</label>
                                <input type="number" name="truck_count" min="0" class="form-control" value="<?= htmlspecialchars($app['truck_count'] ?? $user['truck_count'] ?? 1) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Truck number plates *</label>
                                <textarea name="truck_number_plates" class="form-control" rows="2" placeholder="e.g. UBA 123A, UBG 876X"><?= htmlspecialchars($app['truck_number_plates'] ?? $user['truck_number_plates'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Desired operational area(s) *</label>
                                <input type="text" name="operational_areas" class="form-control" value="<?= htmlspecialchars($app['operational_areas'] ?? '') ?>" placeholder="e.g. Kampala Central, Nakawa, Kabalagala" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Physical office address *</label>
                                <textarea name="office_address" class="form-control" rows="2" required><?= htmlspecialchars($app['office_address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Waste disposal plan after collection *</label>
                                <textarea name="disposal_plan" class="form-control" rows="4" required><?= htmlspecialchars($app['disposal_plan'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4 w-100">Submit collector request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
