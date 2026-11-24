-- Ejecutar una vez sobre la base de datos existente, antes de copiar los PHP.
ALTER TABLE pacientes
    ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN motivo_baja VARCHAR(160) NULL,
    ADD COLUMN fecha_baja DATETIME NULL;
ALTER TABLE doctores
    ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN motivo_baja VARCHAR(160) NULL,
    ADD COLUMN fecha_baja DATETIME NULL;
