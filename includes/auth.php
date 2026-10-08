<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function requireRole(string $requiredRole): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: ../config/auth/login.php');
        exit;
    }

    if ($_SESSION['role'] !== $requiredRole) {
        $dashboards = [
            'admin' => '../admin/dashboard.php',
            'collector' => '../collector/dashboard.php',
            'citizen' => '../citizen/dashboard.php',
        ];
        header('Location: ' . ($dashboards[$_SESSION['role']] ?? '../index.php'));
        exit;
    }
}

function getUser(): array
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../config/auth/login.php');
        exit;
    }

    return $user;
}