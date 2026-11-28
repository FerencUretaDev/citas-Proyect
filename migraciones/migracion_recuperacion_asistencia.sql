-- Mejora 03: Recuperacion mediante asistencia administrativa.
-- Aplicar una sola vez, despues de realizar un respaldo.

CREATE TABLE solicitudes_recuperacion (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    administrador_id INT NULL,
    token_id BIGINT UNSIGNED NULL,

    estado ENUM(
        'pendiente',
        'en_atencion',
        'resuelta',
        'rechazada'
    ) NOT NULL DEFAULT 'pendiente',

    fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_atencion DATETIME NULL,
    identidad_verificada_at DATETIME NULL,
    fecha_resolucion DATETIME NULL,

    usuario_activo INT GENERATED ALWAYS AS (
        CASE
            WHEN estado IN ('pendiente', 'en_atencion')
            THEN usuario_id
            ELSE NULL
        END
    ) STORED,

    PRIMARY KEY (id),

    UNIQUE KEY uq_recuperacion_activa (usuario_activo),
    UNIQUE KEY uq_recuperacion_token (token_id),

    KEY idx_recuperacion_estado (estado, fecha_solicitud),
    KEY idx_recuperacion_usuario (usuario_id),

    CONSTRAINT fk_recuperacion_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_recuperacion_admin
        FOREIGN KEY (administrador_id)
        REFERENCES usuarios (id)
        ON DELETE SET NULL,

    CONSTRAINT fk_recuperacion_token
        FOREIGN KEY (token_id)
        REFERENCES tokens_cuenta (id)
        ON DELETE SET NULL

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tokens_cuenta
    MODIFY COLUMN finalidad ENUM(
        'verificar_correo',
        'recuperar_password',
        'recuperar_asistencia'
    ) NOT NULL,
    MODIFY COLUMN correo_destino VARCHAR(254) NULL;