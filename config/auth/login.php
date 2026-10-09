<?php
require_once __DIR__ . '/../db.php';
session_start();

$error = '';
$selectedRole = $_GET['role'] ?? ($_POST['role'] ?? 'citizen');
if (!in_array($selectedRole, ['citizen', 'collector', 'admin'], true)) {
    $selectedRole = 'citizen';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
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
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => '../../collector/application.php']);
                    exit;
                }
                header('Location: ../../collector/application.php');
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => $dashboardByRole[$user['role']]]);
                exit;
            }
            header('Location: ' . $dashboardByRole[$user['role']]);
            exit;
        } else {
            $error = 'This account has no valid dashboard role.';
        }
    } else {
        $error = 'Invalid credentials. Check your role and password.';
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
                    <?php if ($error): ?><div class="alert alert-danger" id="formMessage"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <form method="POST" id="loginForm" novalidate>
                        <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">
                        <div class="mb-3">
                            <label class="form-label">Login as</label>
                            <div class="form-control text-capitalize bg-light"><?= htmlspecialchars($selectedRole) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <input type="text" name="login" class="form-control" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="loginPassword" class="form-control" required value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
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

const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', function (event) {
        const loginInput = loginForm.querySelector('input[name="login"]');
        const passwordInput = loginForm.querySelector('input[name="password"]');
        const messageBox = document.getElementById('formMessage');
        let message = '';

        if (!loginInput.value.trim()) {
            message = 'Username or email is required. Please enter the account name you used during registration.';
            loginInput.focus();
        } else if (!passwordInput.value) {
            message = 'Password is required. Please enter your password to continue.';
            passwordInput.focus();
        }

        if (message) {
            event.preventDefault();
            showLoginMessage(message);
            return;
        }

        event.preventDefault();
        const submitButton = loginForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        fetch(loginForm.action || window.location.href, {
            method: 'POST',
            body: new FormData(loginForm),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then((response) => response.json()).then((result) => {
            if (result.success && result.redirect) {
                window.location.assign(result.redirect);
            } else {
                showLoginMessage(result.error || 'Login failed. Please check your details and try again.');
                submitButton.disabled = false;
            }
        }).catch(() => {
            showLoginMessage('Unable to complete login right now. Check your connection and try again.');
            submitButton.disabled = false;
        });
    });
}

function showLoginMessage(message) {
    let box = document.getElementById('formMessage');
    if (!box) {
        box = document.createElement('div');
        box.id = 'formMessage';
        loginForm.insertBefore(box, loginForm.firstChild);
    }
    box.className = 'alert alert-danger';
    box.textContent = message;
}
</script>
</body>
</html>
</script>
</body>
</html>