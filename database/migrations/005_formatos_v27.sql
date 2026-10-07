-- V2.7: normalización de razones de inactividad existentes y nuevas opciones
ALTER TABLE inactive_periods MODIFY COLUMN reason ENUM('DESCOMPUESTO Y EN LAS INSTALACIONES DEL IMPLAN','RECIBIENDO SERVICIO EN EL TALLER MECÁNICO','NO DISPONIBLE') NOT NULL;
UPDATE inactive_periods SET reason='DESCOMPUESTO Y EN LAS INSTALACIONES DEL IMPLAN' WHERE reason='Descompuesto';
UPDATE inactive_periods SET reason='RECIBIENDO SERVICIO EN EL TALLER MECÁNICO' WHERE reason='En servicio';
UPDATE inactive_periods SET reason='NO DISPONIBLE' WHERE reason='No disponible';
