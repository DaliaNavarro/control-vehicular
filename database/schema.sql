CREATE TABLE IF NOT EXISTS vehicles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 brand VARCHAR(80) NOT NULL, model VARCHAR(100) NOT NULL,
 plates VARCHAR(30) NOT NULL UNIQUE, economic_number VARCHAR(30) NOT NULL UNIQUE,
 responsible VARCHAR(150) NOT NULL,
 baseline_date DATE NOT NULL, baseline_km DECIMAL(12,2) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CHECK (baseline_km >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS trips (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference_key VARCHAR(64) NOT NULL UNIQUE,
 vehicle_id INT UNSIGNED NOT NULL,
 request_date DATE NOT NULL, departure_at DATETIME NOT NULL, arrival_at DATETIME NOT NULL,
 km_start DECIMAL(12,2) NOT NULL, km_end DECIMAL(12,2) NOT NULL,
 driver VARCHAR(150) NOT NULL, purpose VARCHAR(500) NOT NULL,
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
 trip_id INT UNSIGNED NOT NULL,
 fuel_date DATE NOT NULL, liters DECIMAL(12,3) NOT NULL, amount DECIMAL(12,2) NOT NULL,
 card_last4 CHAR(4) NOT NULL,
 version INT NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE,
 INDEX ix_fuel_date(fuel_date), CHECK(liters > 0 AND amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS inactive_periods (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 vehicle_id INT UNSIGNED NOT NULL,
 start_date DATE NOT NULL, end_date DATE NULL,
 reason ENUM('Descompuesto','En servicio','No disponible') NOT NULL,
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
 scheduled_at DATETIME NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_reservation_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
 UNIQUE KEY uq_reservation_time (vehicle_id, scheduled_at),
 INDEX ix_reservation_date (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
