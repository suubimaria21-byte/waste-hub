<?php
require_once '../includes/auth.php';
requireRole('citizen');
$user = getUser();

$invoiceId = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
$token = (string)($_POST['token'] ?? '');
$sessionToken = (string)($_SESSION['demo_payment_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$invoiceId || !$sessionToken || !hash_equals($sessionToken, $token)) {
    header('Location: payments.php?payment=demo_invalid');
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, status FROM pickup_payments WHERE id = ? AND citizen_id = ? FOR UPDATE');
    $stmt->execute([$invoiceId, $user['id']]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        $pdo->rollBack();
        header('Location: payments.php?payment=demo_invalid');
        exit;
    }
    if (in_array($invoice['status'], ['paid', 'demo_paid'], true)) {
        $pdo->commit();
        header('Location: payments.php?payment=demo');
        exit;
    }

    $demoReference = 'DEMO-' . $invoiceId . '-' . strtoupper(bin2hex(random_bytes(4)));
    $update = $pdo->prepare("UPDATE pickup_payments SET status = 'demo_paid', demo_reference = ?, demo_paid_at = NOW() WHERE id = ? AND citizen_id = ? AND status != 'paid'");
    $update->execute([$demoReference, $invoiceId, $user['id']]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Demo invoice update failed for invoice ' . $invoiceId);
    header('Location: payments.php?payment=demo_error');
    exit;
}

header('Location: payments.php?payment=demo');
exit;
