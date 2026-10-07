-- V2.1: administración de cuentas externas y soporte de conservación/eliminación de registros.
-- user_id en trips/reservations es nullable por diseño, por lo que al conservar registros
-- se desvinculan antes de eliminar la cuenta.
ALTER TABLE users ADD INDEX IF NOT EXISTS ix_users_active (active);
