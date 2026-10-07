CREATE TABLE IF NOT EXISTS vehicles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 brand VARCHAR(80) NOT NULL, model VARCHAR(100) NOT NULL, model_year SMALLINT UNSIGNED NULL,
 plates VARCHAR(30) NOT NULL UNIQUE, economic_number VARCHAR(30) NOT NULL UNIQUE,
 responsible VARCHAR(150) NOT NULL, department VARCHAR(150) NOT NULL DEFAULT '',
 verification_permit TINYINT(1) NOT NULL DEFAULT 0,
 baseline_date DATE NOT NULL, baseline_km DECIMAL(12,2) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CHECK (baseline_km >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS trips (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference_key VARCHAR(64) NOT NULL UNIQUE,
 vehicle_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NULL,
 request_date DATE NOT NULL, departure_at DATETIME NOT NULL, arrival_at DATETIME NOT NULL,
 km_start DECIMAL(12,2) NOT NULL, km_end DECIMAL(12,2) NOT NULL,
 driver VARCHAR(150) NOT NULL, purpose VARCHAR(500) NOT NULL, requester VARCHAR(150) NOT NULL DEFAULT '', observations VARCHAR(1000) NOT NULL DEFAULT '',
 version INT NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
 INDEX ix_vehicle_departure(vehicle_id, departure_at),
 CHECK(km_start >= 0 AND km_end >= km_start), CHECK(arrival_at >= departure_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fuel_loads (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference_key VARCHAR(64) NOT NULL UNIQUE,
 vehicle_id INT UNSIGNED NOT NULL, trip_id INT UNSIGNED NULL,
 fuel_date DATE NOT NULL, liters DECIMAL(12,3) NOT NULL, amount DECIMAL(12,2) NOT NULL,
 payment_method ENUM('Efectivo','Tarjeta','Vale') NOT NULL DEFAULT 'Efectivo', card_last5 CHAR(5) NULL,
 version INT NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
 FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE SET NULL,
 INDEX ix_fuel_vehicle_date(vehicle_id,fuel_date), CHECK(liters > 0 AND amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS inactive_periods (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 vehicle_id INT UNSIGNED NOT NULL,
 start_date DATE NOT NULL, end_date DATE NULL,
 reason ENUM('DESCOMPUESTO Y EN LAS INSTALACIONES DEL IMPLAN','RECIBIENDO SERVICIO EN EL TALLER MECÁNICO','NO DISPONIBLE') NOT NULL,
 notes VARCHAR(500) NOT NULL DEFAULT '',
 version INT NOT NULL DEFAULT 1,
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
 INDEX ix_period_vehicle(vehicle_id,start_date),
 CHECK(end_date IS NULL OR end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS suggestions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kind ENUM('driver','purpose') NOT NULL,
 value VARCHAR(500) NOT NULL,
 UNIQUE KEY uq_suggestion(kind,value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programación independiente de las bitácoras (v1.1).
CREATE TABLE IF NOT EXISTS reservations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 vehicle_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NULL,
 scheduled_at DATETIME NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_reservation_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
 UNIQUE KEY uq_reservation_time (vehicle_id, scheduled_at),
 INDEX ix_reservation_date (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_verifications (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 vehicle_id INT UNSIGNED NOT NULL,
 verification_year SMALLINT UNSIGNED NOT NULL,
 period TINYINT UNSIGNED NOT NULL,
 plate_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
 plates VARCHAR(30) NOT NULL,
 by_permit TINYINT(1) NOT NULL DEFAULT 0,
 completed_at DATETIME NULL,
 recorded_by VARCHAR(150) NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_verification_vehicle FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
 UNIQUE KEY uq_vehicle_verification(vehicle_id,verification_year,period,plate_key),
 INDEX ix_verification_year(verification_year),
 CHECK(period IN (1,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 driver_name VARCHAR(150) NOT NULL, license_number VARCHAR(80) NOT NULL, license_expires DATE NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
