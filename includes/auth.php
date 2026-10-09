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

function autoAssignCollector(PDO $pdo, int $citizenId, string $areaName, ?float $latitude = null, ?float $longitude = null): ?int
{
    $areaName = trim($areaName);

    if ($areaName !== '') {
        $stmt = $pdo->prepare("SELECT collector_id FROM collector_routines WHERE is_active = 1 AND area_name = ? ORDER BY start_time, collector_id LIMIT 1");
        $stmt->execute([$areaName]);
        $routineCollectorId = $stmt->fetchColumn();
        if ($routineCollectorId !== false) {
            $approved = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'collector' AND approval_status = 'approved'");
            $approved->execute([(int)$routineCollectorId]);
            $approvedId = $approved->fetchColumn();
            if ($approvedId !== false) {
                return (int)$approvedId;
            }
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'collector' AND approval_status = 'approved' AND LOWER(TRIM(pickup_area)) = LOWER(?) ORDER BY id LIMIT 1");
        $stmt->execute([$areaName]);
        $areaCollectorId = $stmt->fetchColumn();
        if ($areaCollectorId !== false) {
            return (int)$areaCollectorId;
        }
    }

    if ($latitude !== null && $longitude !== null) {
        $stmt = $pdo->prepare("SELECT id, latitude, longitude FROM users WHERE role = 'collector' AND approval_status = 'approved' AND latitude IS NOT NULL AND longitude IS NOT NULL");
        $stmt->execute();
        $nearestCollectorId = null;
        $nearestDistance = INF;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $collector) {
            $latDelta = deg2rad((float)$collector['latitude'] - $latitude);
            $lonDelta = deg2rad((float)$collector['longitude'] - $longitude);
            $a = sin($latDelta / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad((float)$collector['latitude'])) * sin($lonDelta / 2) ** 2;
            $distance = 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestCollectorId = (int)$collector['id'];
            }
        }
        if ($nearestCollectorId !== null) {
            return $nearestCollectorId;
        }
    }

    $stmt = $pdo->prepare("SELECT u.id FROM users u LEFT JOIN pickups p ON p.collector_id = u.id AND p.status IN ('assigned', 'in_progress') WHERE u.role = 'collector' AND u.approval_status = 'approved' GROUP BY u.id ORDER BY COUNT(p.id), u.id LIMIT 1");
    $stmt->execute();
    $fallback = $stmt->fetchColumn();
    return $fallback !== false ? (int)$fallback : null;
}