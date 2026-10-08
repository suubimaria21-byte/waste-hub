<?php
$host = 'localhost';
$dbname = 'wastehub';
$username = 'root';
$password = '';

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    return $stmt && $stmt->fetch() !== false;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $stmt && $stmt->fetch() !== false;
}

function ensureDatabaseSchema(PDO $pdo): void
{
    if (columnExists($pdo, 'users', 'latitude')) {
        $pdo->exec('ALTER TABLE users DROP COLUMN latitude');
    }
    if (columnExists($pdo, 'users', 'longitude')) {
        $pdo->exec('ALTER TABLE users DROP COLUMN longitude');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS collector_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        company_name VARCHAR(150) NOT NULL,
        trading_license VARCHAR(150) NOT NULL,
        nema_license VARCHAR(150) NOT NULL,
        ursb_registered ENUM('yes', 'no') NOT NULL,
        operational_areas TEXT NOT NULL,
        has_truck ENUM('yes', 'no') NOT NULL,
        office_address TEXT NOT NULL,
        disposal_plan TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS collector_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        audience ENUM('all', 'selected') DEFAULT 'all',
        collector_ids TEXT NULL,
        scheduled_for DATETIME NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    if (!columnExists($pdo, 'users', 'approval_status')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER role");
    }
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ensureDatabaseSchema($pdo);
} catch (PDOException $e) {
    $message = $e->getMessage();
    die(
        '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 24px; border: 1px solid #f0c36d; border-radius: 12px; background: #fff8e6; color: #3a2c0d; line-height: 1.6;">' .
        '<h2 style="margin-top: 0; color: #8a4b00;">Database connection failed</h2>' .
        '<p>The app is trying to connect to the <strong>wastehub</strong> database, but it is not available yet.</p>' .
        '<p>Please do these two checks:</p>' .
        '<ol>' .
        '<li>Start MySQL in XAMPP.</li>' .
        '<li>Open phpMyAdmin and import <strong>database.sql</strong> to create the <strong>wastehub</strong> database.</li>' .
        '</ol>' .
        '<p><strong>Technical detail:</strong> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>' .
        '</div>'
    );
}
?>