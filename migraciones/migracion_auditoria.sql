CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT DEFAULT NULL,
    rol TINYINT DEFAULT NULL,
    modulo VARCHAR(50) NOT NULL,
    accion VARCHAR(80) NOT NULL,
    entidad VARCHAR(50) DEFAULT NULL,
    entidad_id INT DEFAULT NULL,
    resultado ENUM('exitoso','fallido','denegado') NOT NULL,
    fecha_evento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_fecha (fecha_evento),
    KEY idx_auditoria_usuario (usuario_id, fecha_evento),
    KEY idx_auditoria_rol (rol, fecha_evento),
    KEY idx_auditoria_modulo (modulo, accion, fecha_evento),
    KEY idx_auditoria_resultado (resultado, fecha_evento),
    KEY idx_auditoria_entidad (entidad, entidad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
