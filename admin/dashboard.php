<?php
require_once '../includes/auth.php';
requireRole('admin');
$user = getUser();

$pickupsThisMonth = $pdo->query("SELECT COUNT(*) FROM pickups WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$pendingPickups   = $pdo->query("SELECT COUNT(*) FROM pickups WHERE status = 'pending'")->fetchColumn();
$completedPickups = $pdo->query("SELECT COUNT(*) FROM pickups WHERE status = 'completed'")->fetchColumn();
$openComplaints   = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'open'")->fetchColumn();
$totalCitizens    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'citizen'")->fetchColumn();
$totalCollectors  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'collector'")->fetchColumn();
$routeCount       = $pdo->query("SELECT COUNT(*) FROM collector_routines WHERE is_active = 1")->fetchColumn();

$areaBreakdown = $pdo->query("SELECT pickup_area AS area, COUNT(*) AS total FROM users WHERE role='citizen' GROUP BY pickup_area ORDER BY total DESC")->fetchAll();
$collectorLoad = $pdo->query("SELECT u.full_name, u.contractor_name, COUNT(p.id) AS assignments FROM users u LEFT JOIN pickups p ON p.collector_id = u.id WHERE u.role='collector' GROUP BY u.id, u.full_name, u.contractor_name ORDER BY assignments DESC")->fetchAll();

$recentPickups = $pdo->query("SELECT p.*, u.full_name FROM pickups p JOIN users u ON p.citizen_id = u.id ORDER BY p.created_at DESC LIMIT 6")->fetchAll();
$recentComplaints = $pdo->query("SELECT c.*, u.full_name FROM complaints c JOIN users u ON c.citizen_id = u.id ORDER BY c.created_at DESC LIMIT 5")->fetchAll();
$mappedCitizens = $pdo->query("SELECT full_name, pickup_area, latitude, longitude FROM users WHERE role = 'citizen' AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-inline-flex align-items-center gap-2" href="dashboard.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub Admin</span></a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white">Welcome, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="../profile.php" class="btn btn-outline-light btn-sm">Edit Profile</a>
            <a href="../config/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-0">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="assign_pickups.php">Assign Pickups</a>
            <a href="manage_complaints.php">Complaints</a>
            <a href="manage_users.php">Manage Users</a>
            <a href="manage_collector_applications.php">Collector Requests</a>
            <a href="manage_routines.php">Routine Calendar</a>
            <a href="../reports.php">Truck Reports</a>
            <a href="manage_meetings.php">Upcoming events</a>
            <a href="../profile.php">Edit Profile</a>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Kampala Waste Operations Dashboard</h2>
                    <p class="text-muted mb-0">Live view of collection, complaints, and pickup coverage across Uganda’s city service areas.</p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Pickups this month</div>
                                <h3 class="mb-0"><?= $pickupsThisMonth ?></h3>
                            </div>
                            <div class="stat-icon text-success">📅</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Pending pickups</div>
                                <h3 class="mb-0 text-warning"><?= $pendingPickups ?></h3>
                            </div>
                            <div class="stat-icon text-warning">⏳</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Open complaints</div>
                                <h3 class="mb-0 text-danger"><?= $openComplaints ?></h3>
                            </div>
                            <div class="stat-icon text-danger">📢</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Routine coverage</div>
                                <h3 class="mb-0 text-info"><?= $routeCount ?></h3>
                            </div>
                            <div class="stat-icon text-info">🗓️</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card map-panel p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">Citizen coverage map</h4>
                            <span class="badge bg-success-subtle text-success">Service zones</span>
                        </div>
                        <div id="citizenMap"></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 h-100">
                        <h4 class="mb-3">Pickup area overview</h4>
                        <?php if ($areaBreakdown): ?>
                            <?php foreach ($areaBreakdown as $area): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= htmlspecialchars($area['area']) ?></span>
                                    <span class="badge bg-info-subtle text-info"><?= (int)$area['total'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">No citizen area data available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <span>Recent pickup requests</span>
                            <a href="assign_pickups.php" class="btn btn-sm btn-light">Manage</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Citizen</th>
                                            <th>Date</th>
                                            <th>Area</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentPickups)): ?>
                                            <tr><td colspan="4" class="text-center text-muted py-3">No pickup requests yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentPickups as $p): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                                                    <td><?= date('d M Y', strtotime($p['requested_date'])) ?></td>
                                                    <td><?= htmlspecialchars($p['area_name'] ?: 'Kampala Central') ?></td>
                                                    <td>
                                                        <?php
                                                        $badgeClass = match($p['status']) {
                                                            'pending' => 'warning',
                                                            'assigned' => 'info',
                                                            'in_progress' => 'primary',
                                                            'completed' => 'success',
                                                            default => 'secondary'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($p['status']) ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <span>KCCA/contractor load</span>
                        </div>
                        <div class="card-body">
                            <?php if ($collectorLoad): ?>
                                <?php foreach ($collectorLoad as $collector): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <strong><?= htmlspecialchars($collector['full_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($collector['contractor_name'] ?: 'KCCA Waste Team') ?></small>
                                        </div>
                                        <span class="badge bg-success"><?= (int)$collector['assignments'] ?> jobs</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No collector assignments yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <span>Recent complaints</span>
                            <a href="manage_complaints.php" class="btn btn-sm btn-light">Review</a>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (empty($recentComplaints)): ?>
                                    <li class="list-group-item text-center text-muted py-3">No complaints submitted.</li>
                                <?php else: ?>
                                    <?php foreach ($recentComplaints as $c): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($c['title']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($c['full_name']) ?></small>
                                            </div>
                                            <?php
                                            $statusClass = match($c['status']) {
                                                'open' => 'danger',
                                                'in_progress' => 'warning',
                                                'resolved' => 'success',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst(str_replace('_', ' ', $c['status'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    const ugandaBounds = L.latLngBounds([-1.6, 29.5], [4.3, 35.1]);
    const map = L.map('citizenMap', { maxBounds: ugandaBounds, maxBoundsViscosity: 1, minZoom: 6 }).setView([1.3733, 32.2903], 7);
    map.fitBounds(ugandaBounds);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const citizens = <?= json_encode($mappedCitizens, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const citizenBounds = [];
    citizens.forEach((citizen) => {
        const position = [Number(citizen.latitude), Number(citizen.longitude)];
        if (!Number.isFinite(position[0]) || !Number.isFinite(position[1])) return;
        L.marker(position).addTo(map).bindPopup(
            `<strong>${escapeHtml(citizen.full_name)}</strong><br>${escapeHtml(citizen.pickup_area || 'Area not set')}`
        );
        citizenBounds.push(position);
    });
    if (citizenBounds.length) map.fitBounds(L.latLngBounds(citizenBounds).pad(0.15), { padding: [24, 24], maxZoom: 13 });

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[character]);
    }
</script>
</body>
</html>