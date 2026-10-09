<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $dashboardByRole = [
        'admin' => 'admin/dashboard.php',
        'collector' => 'collector/dashboard.php',
        'citizen' => 'citizen/dashboard.php',
    ];
    if (isset($dashboardByRole[$_SESSION['role'] ?? ''])) {
        header('Location: ' . $dashboardByRole[$_SESSION['role']]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Hub – Smart Waste Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-inline-flex align-items-center gap-2" href="index.php">
            <img class="site-logo" src="images/waste hub logo.png" alt="Waste Hub logo">
            <span>Waste Hub</span>
        </a>
        <div class="d-flex">
            <a href="config/auth/choose.php" class="btn btn-outline-light me-2">Login</a>
            <a href="config/auth/choose.php?mode=register" class="btn btn-light text-success">Register</a>
        </div>
    </div>
</nav>

<section class="hero py-5">
    <div class="container text-center">
        <span class="hero-badge">National waste operations platform</span>
        <h1 class="display-4 fw-bold mb-3">Cleaner communities across Uganda</h1>
        <p class="lead mb-4">Request pickups, track collection routes, and report service issues across Ugandan towns, cities, and municipal districts.</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="config/auth/choose.php?mode=register" class="btn btn-light btn-lg text-success">Get Started</a>
            <a href="config/auth/choose.php" class="btn btn-outline-light btn-lg">Login</a>
        </div>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-md-4 feature-item">
                <img class="feature-image" src="images/waste.jpg" alt="Waste collection truck in Uganda" loading="lazy">
                <h4>Pickup Scheduling</h4>
                <p>Residents can book regular collection, bulky waste removals, and area-specific pickup services with ease.</p>
            </div>
            <div class="col-md-4 feature-item">
                <img class="feature-image" src="images/GARBAGE.jpg" alt="Citizen reporting waste issues in a city" loading="lazy">
                <h4>Complaint Tracking</h4>
                <p>Report illegal dumping, blocked drains, or missed collections with photo evidence and status updates.</p>
            </div>
            <div class="col-md-4 feature-item">
                <img class="feature-image" src="images/plastics.jpg" alt="Map and route planning for waste service areas" loading="lazy">
                <h4>Area-Based Operations</h4>
                <p>Local authorities and contractors monitor service zones, route density, and neighborhood coverage across the country.</p>
            </div>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="row g-4 align-items-center">
        <div class="col-lg-6">
            <h2 class="section-title mb-3">Built for Uganda’s local waste service network</h2>
            <p class="text-muted">Waste Hub helps municipal teams, resident communities, and private contractors coordinate clean-up work with clearer assignments, faster reporting, and full visibility over collection areas nationwide.</p>
            <ul class="check-list">
                <li>Live pickup assignment and route visibility</li>
                <li>Collector-specific complaint resolution</li>
                <li>Area mapping for citizens and service coverage</li>
                <li>Professional operations dashboards for local government and contractors</li>
            </ul>
        </div>
        <div class="col-lg-6">
            <div class="promo-card">
                <img src="images/gathering waste.jpg" alt="Waste collection team working in Uganda" class="promo-photo">
            </div>
        </div>
    </div>
</section>

<footer class="bg-dark text-white text-center py-4">
    <div class="container">
        <p class="mb-0">&copy; <?= date('Y') ?> Waste Hub. National waste management for cleaner communities across Uganda.</p>
    </div>
</footer>
</body>
</html>