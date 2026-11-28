-- Mejora 03: Control de versiones de sesiones.

ALTER TABLE usuarios
ADD COLUMN version_sesion INT UNSIGNED NOT NULL DEFAULT 1;