<?php
require_once __DIR__ . '/includes/auth.php';
$user = getUser();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email)) {
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
                $sql = 'UPDATE users SET username = ?, email = ?, phone = ?, address = ?';
                $params = [$username, $email, $phone, $address];

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

                    <form method="POST">
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
                                <label class="form-label">Phone number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
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

<script>
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
