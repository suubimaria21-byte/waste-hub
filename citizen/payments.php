<?php
require_once '../includes/auth.php';
requireRole('citizen');
$user = getUser();

$stmt = $pdo->prepare('SELECT pp.*, p.requested_date, p.area_name FROM pickup_payments pp JOIN pickups p ON p.id = pp.pickup_id WHERE pp.citizen_id = ? ORDER BY pp.created_at DESC');
$stmt->execute([$user['id']]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
$paymentNotice = match ($_GET['payment'] ?? '') {
    'demo' => ['info', 'Demo payment recorded. No money was moved and this is not a real settlement.'],
    'demo_invalid' => ['warning', 'The demo payment could not be completed. Please try again.'],
    'demo_error' => ['danger', 'The demo payment was not recorded. Please try again.'],
    default => null,
};
$methodNames = [
    'airtel_money' => 'Airtel Money',
    'mtn_momo' => 'MTN MoMo',
    'visa' => 'Visa',
    'mastercard' => 'Mastercard',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices - Waste Hub</title>
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
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1">My invoices</h1>
            <p class="text-muted mb-0">Billing category: <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['billing_category'] ?? 'informal household'))) ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-success">Dashboard</a>
    </div>
    <?php if ($paymentNotice): ?><div class="alert alert-<?= htmlspecialchars($paymentNotice[0]) ?>"><?= htmlspecialchars($paymentNotice[1]) ?></div><?php endif; ?>

    <?php if (!$invoices): ?>
        <div class="alert alert-info">No pickup invoices yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-success">
                    <tr><th>Pickup date</th><th>Area</th><th>Quantity</th><th>Unit price</th><th>Total</th><th>Payment timing</th><th>Method</th><th>Due date</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                        $isSettled = in_array($invoice['status'], ['paid', 'demo_paid'], true);
                        $badge = $isSettled ? 'success' : ($invoice['status'] === 'failed' ? 'danger' : 'warning');
                        $statusLabel = $invoice['status'] === 'demo_paid' ? 'Paid (demo)' : ucfirst(str_replace('_', ' ', $invoice['status']));
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($invoice['requested_date']) ?></td>
                            <td><?= htmlspecialchars($invoice['area_name']) ?></td>
                            <td><?= (int)$invoice['quantity'] ?> <?= htmlspecialchars($invoice['unit_type']) ?><?= (int)$invoice['quantity'] === 1 ? '' : 's' ?></td>
                            <td><?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float)$invoice['unit_price']) ?></td>
                            <td><strong><?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float)$invoice['total_amount']) ?></strong></td>
                            <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $invoice['payment_timing']))) ?></td>
                            <td><?= htmlspecialchars($methodNames[$invoice['payment_method']] ?? $invoice['payment_method']) ?></td>
                            <td><?= htmlspecialchars($invoice['due_at'] ?: '-') ?></td>
                            <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                            <td>
                                <?php if (!$isSettled): ?>
                                    <a href="demo_checkout.php?id=<?= (int)$invoice['id'] ?>" class="btn btn-sm btn-success">Simulate payment</a>
                                <?php else: ?>
                                    <span class="text-muted"><?= $invoice['status'] === 'demo_paid' ? 'Demo complete' : 'Paid' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="form-text">Demo mode only: completing a simulated payment moves the invoice to “Paid (demo)”. No payment provider is contacted and no money is collected.</p>
    <?php endif; ?>
</main>
</body>
</html>
