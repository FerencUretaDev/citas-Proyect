-- Ejecutar una sola vez, previa comprobación de que doctores.dni no existe.
-- NULL conserva los perfiles históricos; la aplicación exige 8 dígitos al guardar.
ALTER TABLE doctores
    ADD COLUMN dni CHAR(8) DEFAULT NULL AFTER usuario_id,
    ADD UNIQUE KEY uq_doctores_dni (dni);
