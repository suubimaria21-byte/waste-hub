<?php
require_once '../includes/auth.php';
requireRole('citizen');
$user = getUser();

$invoiceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare('SELECT * FROM pickup_payments WHERE id = ? AND citizen_id = ? LIMIT 1');
$stmt->execute([$invoiceId ?: 0, $user['id']]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found.');
}

if (in_array($invoice['status'], ['paid', 'demo_paid'], true)) {
    header('Location: payments.php');
    exit;
}

$_SESSION['demo_payment_token'] ??= bin2hex(random_bytes(32));
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
    <title>Demo Checkout - Waste Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand d-inline-flex align-items-center gap-2" href="payments.php"><img class="site-logo" src="../images/waste hub logo.png" alt="Waste Hub logo"><span>Waste Hub Demo Checkout</span></a>
        <a href="payments.php" class="btn btn-outline-light btn-sm">Cancel</a>
    </div>
</nav>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="alert alert-warning" role="status"><strong>Demonstration only.</strong> No money will be moved, no payment provider is contacted, and this will not settle a real bill.</div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-4">Review demo payment</h1>
                    <dl class="row">
                        <dt class="col-6">Payment method</dt><dd class="col-6"><?= htmlspecialchars($methodNames[$invoice['payment_method']] ?? $invoice['payment_method']) ?></dd>
                        <dt class="col-6">Quantity</dt><dd class="col-6"><?= (int)$invoice['quantity'] ?> <?= htmlspecialchars($invoice['unit_type']) ?><?= (int)$invoice['quantity'] === 1 ? '' : 's' ?></dd>
                        <dt class="col-6">Amount</dt><dd class="col-6"><strong><?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float)$invoice['total_amount']) ?></strong></dd>
                        <dt class="col-6">Payment timing</dt><dd class="col-6"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $invoice['payment_timing']))) ?></dd>
                    </dl>
                    <form method="POST" action="demo_complete.php">
                        <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['demo_payment_token']) ?>">
                        <button type="submit" class="btn btn-success w-100">Simulate successful payment</button>
                    </form>
                    <a href="payments.php" class="btn btn-link w-100 mt-2">Return without paying</a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
