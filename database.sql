CREATE DATABASE IF NOT EXISTS wastehub;
USE wastehub;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'collector', 'citizen') DEFAULT 'citizen',
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    phone VARCHAR(20),
    address TEXT,
    pickup_area VARCHAR(100) DEFAULT 'Kampala Central',
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    gps_last_updated DATETIME NULL,
    contractor_name VARCHAR(120) NULL,
    truck_count INT DEFAULT 0,
    truck_number_plates TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pickups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    area_name VARCHAR(100) DEFAULT 'Kampala Central',
    requested_date DATE NOT NULL,
    preferred_time VARCHAR(50),
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    pickup_type ENUM('routine','special') DEFAULT 'special',
    routine_id INT NULL,
    collector_id INT NULL,
    assigned_at DATETIME NULL,
    completed_at DATETIME NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES users(id),
    FOREIGN KEY (collector_id) REFERENCES users(id)
);

CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    collector_id INT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    area_name VARCHAR(100) DEFAULT 'Kampala Central',
    photo VARCHAR(255) NULL,
    status ENUM('open', 'in_progress', 'resolved') DEFAULT 'open',
    admin_notes TEXT,
    assigned_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES users(id),
    FOREIGN KEY (collector_id) REFERENCES users(id)
);

CREATE TABLE collector_applications (
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
);

CREATE TABLE collector_routines (
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
);

CREATE TABLE collector_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    audience ENUM('all', 'selected') DEFAULT 'all',
    collector_ids TEXT NULL,
    scheduled_for DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

INSERT INTO users (
    username, email, password, full_name, role, approval_status, phone, address, pickup_area, latitude, longitude, contractor_name, truck_count, truck_number_plates
) VALUES (
    'admin',
    'admin@wastewise.ug',
    'admin123',
    'National Waste Operations Admin',
    'admin',
    'approved',
    '0770000000',
    'Kampala Coordination Centre, Uganda',
    'Kampala Central',
    0.3163,
    32.5822,
    'National Waste Systems Unit',
    0,
    ''
);
