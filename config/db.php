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
    if (tableExists($pdo, 'bins')) {
        $fk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pickups' AND REFERENCED_TABLE_NAME = 'bins' LIMIT 1")->fetchColumn();
        if ($fk) {
            $pdo->exec("ALTER TABLE pickups DROP FOREIGN KEY `$fk`");
        }
        if (columnExists($pdo, 'pickups', 'bin_id')) {
            $pdo->exec('ALTER TABLE pickups DROP COLUMN bin_id');
        }
        $pdo->exec('DROP TABLE IF EXISTS bins');
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
        truck_count INT DEFAULT 0,
        truck_number_plates TEXT NULL,
        office_address TEXT NOT NULL,
        disposal_plan TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS collector_routines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        collector_id INT NOT NULL,
        day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
        area_name VARCHAR(150) NOT NULL,
        route_name VARCHAR(150) DEFAULT NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        notes TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (collector_id) REFERENCES users(id)
    )");

    if (!columnExists($pdo, 'collector_applications', 'truck_count')) {
        $pdo->exec('ALTER TABLE collector_applications ADD COLUMN truck_count INT DEFAULT 0 AFTER has_truck');
    }
    if (!columnExists($pdo, 'collector_applications', 'truck_number_plates')) {
        $pdo->exec('ALTER TABLE collector_applications ADD COLUMN truck_number_plates TEXT NULL AFTER truck_count');
    }

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
    if (!columnExists($pdo, 'users', 'latitude')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN latitude DECIMAL(10,8) NULL AFTER pickup_area');
    }
    if (!columnExists($pdo, 'users', 'longitude')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude');
    }
    if (!columnExists($pdo, 'users', 'truck_count')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN truck_count INT DEFAULT 0 AFTER pickup_area');
    }
    if (!columnExists($pdo, 'users', 'truck_number_plates')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN truck_number_plates TEXT NULL AFTER truck_count');
    }
    if (!columnExists($pdo, 'users', 'gps_last_updated')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN gps_last_updated DATETIME NULL AFTER longitude');
    }
    if (!columnExists($pdo, 'users', 'billing_category')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN billing_category ENUM('informal_household','apartment','commercial_entity','institution') NOT NULL DEFAULT 'informal_household' AFTER address");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS collector_trucks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        collector_id INT NOT NULL,
        plate_number VARCHAR(40) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_collector_plate (collector_id, plate_number),
        FOREIGN KEY (collector_id) REFERENCES users(id)
    )");
    if (!columnExists($pdo, 'pickups', 'pickup_type')) {
        $pdo->exec("ALTER TABLE pickups ADD COLUMN pickup_type ENUM('routine','special') DEFAULT 'special' AFTER status");
    }
    if (!columnExists($pdo, 'pickups', 'routine_id')) {
        $pdo->exec('ALTER TABLE pickups ADD COLUMN routine_id INT NULL AFTER pickup_type');
    }
    if (!columnExists($pdo, 'pickups', 'truck_id')) {
        $pdo->exec('ALTER TABLE pickups ADD COLUMN truck_id INT NULL AFTER collector_id, ADD CONSTRAINT fk_pickups_truck FOREIGN KEY (truck_id) REFERENCES collector_trucks(id) ON DELETE SET NULL');
    }
    if (!columnExists($pdo, 'complaints', 'truck_id')) {
        $pdo->exec('ALTER TABLE complaints ADD COLUMN truck_id INT NULL AFTER collector_id, ADD CONSTRAINT fk_complaints_truck FOREIGN KEY (truck_id) REFERENCES collector_trucks(id) ON DELETE SET NULL');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS pickup_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pickup_id INT NOT NULL UNIQUE,
        citizen_id INT NOT NULL,
        billing_category ENUM('informal_household','apartment','commercial_entity','institution') NOT NULL,
        quantity INT UNSIGNED NOT NULL,
        unit_type ENUM('bag','load') NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL,
        currency CHAR(3) NOT NULL DEFAULT 'UGX',
        payment_timing ENUM('before_collection','on_pickup','monthly','annually') NOT NULL,
        payment_method ENUM('airtel_money','mtn_momo','visa','mastercard') NOT NULL,
        status ENUM('awaiting_payment','due_on_pickup','scheduled','paid','demo_paid','failed') NOT NULL DEFAULT 'awaiting_payment',
        due_at DATE NULL,
        external_reference VARCHAR(120) NULL,
        paid_at DATETIME NULL,
        demo_reference VARCHAR(120) NULL,
        demo_paid_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pickup_id) REFERENCES pickups(id),
        FOREIGN KEY (citizen_id) REFERENCES users(id)
    )");
    if (!columnExists($pdo, 'pickup_payments', 'demo_reference')) {
        $pdo->exec("ALTER TABLE pickup_payments ADD COLUMN demo_reference VARCHAR(120) NULL AFTER paid_at, ADD COLUMN demo_paid_at DATETIME NULL AFTER demo_reference, MODIFY COLUMN status ENUM('awaiting_payment','due_on_pickup','scheduled','paid','demo_paid','failed') NOT NULL DEFAULT 'awaiting_payment'");
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