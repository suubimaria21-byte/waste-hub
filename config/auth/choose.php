<?php
session_start();
$mode = $_GET['mode'] ?? 'login';
$actionLabel = $mode === 'register' ? 'Register' : 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($actionLabel) ?> - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img class="auth-logo d-block mx-auto mb-3" src="../../images/waste hub logo.png" alt="Waste Hub logo">
                        <h2 class="text-success mb-2">Choose your account type</h2>
                        <p class="text-muted mb-0">Continue as the user type you want to <?= strtolower($actionLabel) ?> as.</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="login.php?role=citizen" class="btn btn-outline-success w-100 h-100 py-4">
                                <div class="fw-bold mb-1">Citizen</div>
                                <small>Request pickups and raise complaints</small>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="login.php?role=collector" class="btn btn-outline-success w-100 h-100 py-4">
                                <div class="fw-bold mb-1">Collector</div>
                                <small>Apply and manage collection routes</small>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="login.php?role=admin" class="btn btn-outline-success w-100 h-100 py-4">
                                <div class="fw-bold mb-1">Admin</div>
                                <small>Review service operations</small>
                            </a>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="mb-2">Or register a new account</p>
                        <div class="d-flex justify-content-center flex-wrap gap-2">
                            <a href="register.php?role=citizen" class="btn btn-success btn-sm">Citizen</a>
                            <a href="register.php?role=collector" class="btn btn-success btn-sm">Collector</a>
                            <a href="register.php?role=admin" class="btn btn-success btn-sm">Admin</a>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="../../index.php" class="text-decoration-none">Back to home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
