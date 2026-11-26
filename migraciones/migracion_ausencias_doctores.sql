CREATE TABLE IF NOT EXISTS ausencias_doctores (

    id INT NOT NULL AUTO_INCREMENT,

    doctor_id INT NOT NULL,

    fecha_inicio DATE NOT NULL,

    fecha_fin DATE NOT NULL,

    motivo ENUM(
        'vacaciones',
        'descanso_medico',
        'permiso_personal',
        'capacitacion',
        'otros'
    ) NOT NULL,

    observaciones VARCHAR(500) DEFAULT NULL,

    cancelada TINYINT(1) NOT NULL DEFAULT 0,

    registrado_por INT NOT NULL,

    fecha_registro TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    fecha_cancelacion DATETIME DEFAULT NULL,

    PRIMARY KEY (id),

    INDEX idx_ausencias_disponibilidad (
        doctor_id,
        cancelada,
        fecha_inicio,
        fecha_fin
    ),

    INDEX idx_ausencias_usuario (
        registrado_por
    ),

    CONSTRAINT fk_ausencia_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctores (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_ausencia_usuario
        FOREIGN KEY (registrado_por)
        REFERENCES usuarios (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_ausencia_fechas
        CHECK (fecha_fin >= fecha_inicio),

    CONSTRAINT chk_ausencia_cancelada
        CHECK (cancelada IN (0, 1))

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;