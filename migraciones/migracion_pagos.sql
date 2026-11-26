CREATE TABLE IF NOT EXISTS pagos (
    id INT NOT NULL AUTO_INCREMENT,
    cita_id INT NOT NULL,

    monto DECIMAL(10,2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'PEN',

    proveedor VARCHAR(30) NOT NULL DEFAULT 'mercadopago',
    ambiente ENUM('test', 'production')
        NOT NULL DEFAULT 'test',

    estado ENUM(
        'pendiente',
        'en_proceso',
        'aprobado',
        'rechazado',
        'cancelado',
        'expirado',
        'reembolsado',
        'revision'
    ) NOT NULL DEFAULT 'pendiente',

    referencia_externa VARCHAR(100) NOT NULL,
    id_orden_mp VARCHAR(100) DEFAULT NULL,
    id_pago_mp VARCHAR(100) DEFAULT NULL,

    clave_idempotencia VARCHAR(64) NOT NULL,
    estado_mp VARCHAR(80) DEFAULT NULL,
    detalle_estado VARCHAR(150) DEFAULT NULL,
    url_checkout TEXT DEFAULT NULL,

    fecha_expiracion DATETIME DEFAULT NULL,
    fecha_pago DATETIME DEFAULT NULL,

    fecha_registro TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_actualizacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_pago_cita (cita_id),
    UNIQUE KEY uk_pago_referencia (referencia_externa),
    UNIQUE KEY uk_orden_mp (id_orden_mp),
    UNIQUE KEY uk_pago_mp (id_pago_mp),
    UNIQUE KEY uk_pago_idempotencia (clave_idempotencia),

    KEY idx_pago_estado (estado),
    KEY idx_pago_expiracion (estado, fecha_expiracion),

    CONSTRAINT fk_pago_cita
        FOREIGN KEY (cita_id)
        REFERENCES citas(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT chk_pago_monto
        CHECK (monto > 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;