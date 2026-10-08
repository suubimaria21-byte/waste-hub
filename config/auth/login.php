<?php
require_once __DIR__ . '/../db.php';
session_start();

$error = '';
$selectedRole = $_GET['role'] ?? ($_POST['role'] ?? 'citizen');
if (!in_array($selectedRole, ['citizen', 'collector', 'admin'], true)) {
    $selectedRole = 'citizen';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $submittedRole = $_POST['role'] ?? $selectedRole;
    $selectedRole = in_array($submittedRole, ['citizen', 'collector', 'admin'], true) ? $submittedRole : $selectedRole;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ? LIMIT 1");
    $stmt->execute([$login, $login, $selectedRole]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $validPassword = $user && (
        (isset($user['password']) && password_verify($password, $user['password'])) ||
        (isset($user['password']) && $user['password'] === $password)
    );

    if ($validPassword) {
        if (isset($user['password']) && !password_verify($password, $user['password']) && $user['password'] === $password) {
            $updateHash = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $updateHash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        $dashboardByRole = [
            'admin' => '../../admin/dashboard.php',
            'collector' => '../../collector/dashboard.php',
            'citizen' => '../../citizen/dashboard.php',
        ];

        if (isset($dashboardByRole[$user['role']])) {
            $approvalStatus = $user['approval_status'] ?? 'approved';
            if (($user['role'] ?? '') === 'collector' && $approvalStatus !== 'approved') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                header('Location: ../../collector/application.php');
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            header('Location: ' . $dashboardByRole[$user['role']]);
            exit;
        } else {
            $error = 'This account has no valid dashboard role.';
        }
    } else {
        $error = 'Invalid credentials. Check your role and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <img class="auth-logo d-block mx-auto mb-3" src="../../images/waste hub logo.png" alt="Waste Hub logo">
                    <h2 class="text-center text-success mb-4">Waste Hub Login</h2>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">
                        <div class="mb-3">
                            <label class="form-label">Login as</label>
                            <div class="form-control text-capitalize bg-light"><?= htmlspecialchars($selectedRole) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <input type="text" name="login" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="loginPassword" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('loginPassword')">Show</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Login</button>
                    </form>
                    <p class="text-center mt-3">Need another account? <a href="choose.php">Choose role</a></p>
                    <p class="text-center mt-2">No account? <a href="register.php?role=<?= htmlspecialchars($selectedRole) ?>">Register as <?= htmlspecialchars($selectedRole) ?></a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling || input.parentElement.querySelector('button');
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