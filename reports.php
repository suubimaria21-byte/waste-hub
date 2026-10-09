<?php
require_once __DIR__ . '/includes/auth.php';
$user = getUser();
if (!in_array($user['role'], ['admin', 'collector'], true)) {
    header('Location: ' . ($user['role'] === 'citizen' ? 'citizen/dashboard.php' : 'index.php'));
    exit;
}

$period = in_array($_GET['period'] ?? 'week', ['week', 'month', 'year'], true) ? $_GET['period'] : 'week';
$dateInput = $_GET['date'] ?? date('Y-m-d');
$anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
if (!$anchor || $anchor->format('Y-m-d') !== $dateInput) {
    $anchor = new DateTimeImmutable('today');
    $dateInput = $anchor->format('Y-m-d');
}

if ($period === 'week') {
    $start = $anchor->modify('monday this week');
    $end = $start->modify('+1 week');
} elseif ($period === 'month') {
    $start = $anchor->modify('first day of this month');
    $end = $start->modify('+1 month');
} else {
    $start = $anchor->setDate((int)$anchor->format('Y'), 1, 1);
    $end = $start->modify('+1 year');
}
$startDate = $start->format('Y-m-d');
$endDate = $end->format('Y-m-d');

$collectors = [];
$collectorFilter = (int)$user['id'];
if ($user['role'] === 'admin') {
    $collectors = $pdo->query("SELECT id, full_name FROM users WHERE role = 'collector' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    $legacyCollectors = $pdo->query("SELECT id, truck_number_plates FROM users WHERE role = 'collector' AND truck_number_plates IS NOT NULL AND truck_number_plates != ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($legacyCollectors as $legacyCollector) {
        syncCollectorTrucks($pdo, (int)$legacyCollector['id'], (string)$legacyCollector['truck_number_plates']);
    }
    $requestedCollector = (int)($_GET['collector_id'] ?? 0);
    $collectorFilter = $requestedCollector > 0 ? $requestedCollector : 0;
    if ($collectorFilter > 0 && !in_array($collectorFilter, array_map(static fn($collector) => (int)$collector['id'], $collectors), true)) {
        $collectorFilter = 0;
    }
} else {
    syncCollectorTrucks($pdo, (int)$user['id'], (string)($user['truck_number_plates'] ?? ''));
}

$sql = "SELECT t.id, t.collector_id, t.plate_number, u.full_name AS collector_name FROM collector_trucks t JOIN users u ON u.id = t.collector_id WHERE t.is_active = 1";
$params = [];
if ($collectorFilter > 0) {
    $sql .= ' AND t.collector_id = ?';
    $params[] = $collectorFilter;
}
$sql .= ' ORDER BY u.full_name, t.plate_number';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$truckIds = array_map(static fn($truck) => (int)$truck['id'], $trucks);
$pickupsByTruck = [];
$complaintsByTruck = [];

if ($truckIds) {
    $placeholders = implode(',', array_fill(0, count($truckIds), '?'));
    $pickupSql = "SELECT p.truck_id, p.requested_date, p.completed_at, p.area_name, COALESCE(NULLIF(r.route_name, ''), p.area_name) AS route_name, c.full_name AS client_name FROM pickups p JOIN users c ON c.id = p.citizen_id LEFT JOIN collector_routines r ON r.id = p.routine_id WHERE p.truck_id IN ($placeholders) AND p.status = 'completed' AND ((p.completed_at >= ? AND p.completed_at < ?) OR (p.completed_at IS NULL AND p.requested_date >= ? AND p.requested_date < ?)) ORDER BY COALESCE(p.completed_at, p.requested_date), c.full_name";
    $stmt = $pdo->prepare($pickupSql);
    $stmt->execute([...$truckIds, $startDate, $endDate, $startDate, $endDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pickup) {
        $pickupsByTruck[(int)$pickup['truck_id']][] = $pickup;
    }

    $complaintSql = "SELECT c.truck_id, c.created_at, c.title, c.status, c.area_name, u.full_name AS client_name FROM complaints c JOIN users u ON u.id = c.citizen_id WHERE c.truck_id IN ($placeholders) AND c.created_at >= ? AND c.created_at < ? ORDER BY c.created_at, u.full_name";
    $stmt = $pdo->prepare($complaintSql);
    $stmt->execute([...$truckIds, $startDate, $endDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $complaint) {
        $complaintsByTruck[(int)$complaint['truck_id']][] = $complaint;
    }
}

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="truck-work-' . $period . '-' . $startDate . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Collector', 'Truck plate', 'Work type', 'Date', 'Route or area', 'Client', 'Details', 'Status']);
    foreach ($trucks as $truck) {
        foreach ($pickupsByTruck[(int)$truck['id']] ?? [] as $pickup) {
            fputcsv($output, [$truck['collector_name'], $truck['plate_number'], 'Completed pickup', $pickup['completed_at'] ?: $pickup['requested_date'], $pickup['route_name'], $pickup['client_name'], '', 'completed']);
        }
        foreach ($complaintsByTruck[(int)$truck['id']] ?? [] as $complaint) {
            fputcsv($output, [$truck['collector_name'], $truck['plate_number'], 'Complaint', $complaint['created_at'], $complaint['area_name'], $complaint['client_name'], $complaint['title'], $complaint['status']]);
        }
    }
    fclose($output);
    exit;
}

function reportEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truck Work Reports - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @media print { .no-print, nav { display: none !important; } body { background: #fff !important; } }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-success no-print">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="<?= $user['role'] === 'admin' ? 'admin/dashboard.php' : 'collector/dashboard.php' ?>"><img class="site-logo" src="images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub Reports</span></a>
        <a href="<?= $user['role'] === 'admin' ? 'admin/dashboard.php' : 'collector/dashboard.php' ?>" class="btn btn-outline-light btn-sm">Back to dashboard</a>
    </div>
</nav>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Truck work report</h1>
            <p class="text-muted mb-0"><?= reportEscape($startDate) ?> to <?= reportEscape($end->modify('-1 day')->format('Y-m-d')) ?></p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a class="btn btn-outline-success" href="?<?= http_build_query(['period' => $period, 'date' => $dateInput, 'collector_id' => $collectorFilter, 'format' => 'csv']) ?>">Download CSV</a>
            <button class="btn btn-success" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-4 no-print">
        <div class="col-sm-4 col-md-3">
            <label for="period" class="form-label">Reporting period</label>
            <select name="period" id="period" class="form-select">
                <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Weekly</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Monthly</option>
                <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Yearly</option>
            </select>
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="date" class="form-label">Date in period</label>
            <input type="date" name="date" id="date" class="form-control" value="<?= reportEscape($dateInput) ?>" required>
        </div>
        <?php if ($user['role'] === 'admin'): ?>
            <div class="col-sm-4 col-md-4">
                <label for="collector_id" class="form-label">Collector</label>
                <select name="collector_id" id="collector_id" class="form-select">
                    <option value="0">All collectors</option>
                    <?php foreach ($collectors as $collector): ?>
                        <option value="<?= (int)$collector['id'] ?>" <?= $collectorFilter === (int)$collector['id'] ? 'selected' : '' ?>><?= reportEscape($collector['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-sm-4 col-md-2"><button type="submit" class="btn btn-success w-100">Generate</button></div>
    </form>

    <div class="table-responsive mb-4">
        <table class="table table-bordered table-hover bg-white">
            <thead class="table-success"><tr><th>Collector</th><th>Truck plate</th><th>Completed pickups</th><th>Clients served</th><th>Complaints</th></tr></thead>
            <tbody>
                <?php if (!$trucks): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No active trucks are registered for this selection.</td></tr>
                <?php else: ?>
                    <?php foreach ($trucks as $truck): ?>
                        <?php $truckPickups = $pickupsByTruck[(int)$truck['id']] ?? []; $truckComplaints = $complaintsByTruck[(int)$truck['id']] ?? []; ?>
                        <tr>
                            <td><?= reportEscape($truck['collector_name']) ?></td>
                            <td><?= reportEscape($truck['plate_number']) ?></td>
                            <td><?= count($truckPickups) ?></td>
                            <td><?= count(array_unique(array_column($truckPickups, 'client_name'))) ?></td>
                            <td><?= count($truckComplaints) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($trucks as $truck): ?>
        <?php $truckPickups = $pickupsByTruck[(int)$truck['id']] ?? []; $truckComplaints = $complaintsByTruck[(int)$truck['id']] ?? []; ?>
        <section class="mb-5">
            <h2 class="h4 border-bottom pb-2"><?= reportEscape($truck['collector_name']) ?> / <?= reportEscape($truck['plate_number']) ?></h2>
            <h3 class="h6 mt-3">Routes and clients served</h3>
            <?php if (!$truckPickups): ?>
                <p class="text-muted">No completed pickups for this truck in the selected period.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Date completed</th><th>Route</th><th>Area</th><th>Client</th></tr></thead>
                        <tbody>
                            <?php foreach ($truckPickups as $pickup): ?>
                                <tr><td><?= reportEscape($pickup['completed_at'] ?: $pickup['requested_date']) ?></td><td><?= reportEscape($pickup['route_name'] ?: 'Standard route') ?></td><td><?= reportEscape($pickup['area_name']) ?></td><td><?= reportEscape($pickup['client_name']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <h3 class="h6 mt-4">Complaints associated with this truck</h3>
            <?php if (!$truckComplaints): ?>
                <p class="text-muted">No complaints associated with this truck in the selected period.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Reported</th><th>Client</th><th>Area</th><th>Complaint</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($truckComplaints as $complaint): ?>
                                <tr><td><?= reportEscape($complaint['created_at']) ?></td><td><?= reportEscape($complaint['client_name']) ?></td><td><?= reportEscape($complaint['area_name']) ?></td><td><?= reportEscape($complaint['title']) ?></td><td><?= reportEscape(ucfirst(str_replace('_', ' ', $complaint['status']))) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
