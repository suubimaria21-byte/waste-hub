<?php
require_once '../includes/auth.php';
requireRole('citizen');
$user = getUser();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location'] ?? '');
    $areaName = trim($_POST['area_name'] ?? $user['pickup_area'] ?? 'Kampala Central');
    $photo = null;

    if (empty($title) || empty($description)) {
        $error = 'Title and description are required.';
    } else {
        if (!empty($_FILES['photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($ext, $allowed) && $_FILES['photo']['size'] < 2 * 1024 * 1024) {
                $uploadDirectory = dirname(__DIR__) . '/uploads';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $error = 'Unable to create the photo upload directory.';
                } else {
                    $photo = uniqid('comp_') . '.' . $ext;
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDirectory . '/' . $photo)) {
                        $photo = null;
                        $error = 'Unable to save the uploaded photo.';
                    }
                }
            } else {
                $error = 'Invalid image (max 2MB, jpg/png/gif only).';
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare(
                "INSERT INTO complaints (citizen_id, title, description, location, area_name, photo) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $description,
                $location,
                $areaName,
                $photo,
            ]);
            $success = 'Complaint submitted successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Complaint - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="dashboard.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub</span></a>
        <a href="../config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="text-success mb-4">Report Waste or Service Issue</h3>
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Issue Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Pickup location</label>
                                <input type="text" name="area_name" class="form-control" value="<?= htmlspecialchars($user['pickup_area'] ?: '') ?>" placeholder="e.g. Kisaasi, Wandegeya, Ntinda">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location / Landmark</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g. near Taxi Park, road side, market...">
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Photo (optional)</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-success w-100">Submit Complaint</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="dashboard.php">← Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>