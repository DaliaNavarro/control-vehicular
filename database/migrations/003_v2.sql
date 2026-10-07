-- Segunda versión: usuarios externos, combustible independiente y campos ampliados.
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 driver_name VARCHAR(150) NOT NULL,
 license_number VARCHAR(80) NOT NULL,
 license_expires DATE NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS department VARCHAR(150) NOT NULL DEFAULT '' AFTER responsible;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS model_year SMALLINT UNSIGNED NULL AFTER model;

ALTER TABLE trips ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER vehicle_id;
ALTER TABLE trips ADD COLUMN IF NOT EXISTS requester VARCHAR(150) NOT NULL DEFAULT '' AFTER purpose;
ALTER TABLE trips ADD COLUMN IF NOT EXISTS observations VARCHAR(1000) NOT NULL DEFAULT '' AFTER requester;
ALTER TABLE trips ADD INDEX IF NOT EXISTS ix_trips_user (user_id);

ALTER TABLE reservations ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER vehicle_id;
ALTER TABLE reservations ADD INDEX IF NOT EXISTS ix_reservation_user (user_id);

-- Combustible deja de depender de una bitácora. Se conserva trip_id como referencia histórica opcional.
ALTER TABLE fuel_loads MODIFY trip_id INT UNSIGNED NULL;
ALTER TABLE fuel_loads ADD COLUMN IF NOT EXISTS vehicle_id INT UNSIGNED NULL AFTER reference_key;
UPDATE fuel_loads f JOIN trips t ON t.id=f.trip_id SET f.vehicle_id=t.vehicle_id WHERE f.vehicle_id IS NULL;
ALTER TABLE fuel_loads MODIFY vehicle_id INT UNSIGNED NOT NULL;
ALTER TABLE fuel_loads ADD COLUMN IF NOT EXISTS payment_method ENUM('Efectivo','Tarjeta','Vale') NOT NULL DEFAULT 'Efectivo' AFTER amount;
ALTER TABLE fuel_loads ADD COLUMN IF NOT EXISTS card_last5 CHAR(5) NULL AFTER payment_method;
ALTER TABLE fuel_loads MODIFY COLUMN IF EXISTS card_last4 CHAR(4) NULL;
ALTER TABLE fuel_loads ADD INDEX IF NOT EXISTS ix_fuel_vehicle_date (vehicle_id,fuel_date);

SET @fk_trip := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuel_loads' AND COLUMN_NAME='trip_id' AND REFERENCED_TABLE_NAME='trips' LIMIT 1);
SET @sql := IF(@fk_trip IS NULL,'SELECT 1',CONCAT('ALTER TABLE fuel_loads DROP FOREIGN KEY `',@fk_trip,'`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE fuel_loads ADD CONSTRAINT fk_fuel_trip_v2 FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE SET NULL;
SET @fk_vehicle := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuel_loads' AND COLUMN_NAME='vehicle_id' AND REFERENCED_TABLE_NAME='vehicles' LIMIT 1);
SET @sql := IF(@fk_vehicle IS NULL,'ALTER TABLE fuel_loads ADD CONSTRAINT fk_fuel_vehicle_v2 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
