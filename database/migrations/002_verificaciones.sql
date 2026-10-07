-- Safe to run again. Existing vehicles continue to use their plate's last digit.
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS verification_permit TINYINT(1) NOT NULL DEFAULT 0;

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
