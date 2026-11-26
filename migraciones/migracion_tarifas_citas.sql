CREATE TABLE IF NOT EXISTS tarifas_citas (
    id INT NOT NULL AUTO_INCREMENT,
    modalidad ENUM('presencial', 'virtual') NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'PEN',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_actualizacion TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY unique_modalidad (modalidad),
    CONSTRAINT chk_tarifa_positiva CHECK (precio > 0)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tarifas_citas (modalidad, precio, moneda)
SELECT 'presencial', 80.00, 'PEN'
WHERE NOT EXISTS (
    SELECT 1 FROM tarifas_citas
    WHERE modalidad = 'presencial'
);

INSERT INTO tarifas_citas (modalidad, precio, moneda)
SELECT 'virtual', 50.00, 'PEN'
WHERE NOT EXISTS (
    SELECT 1 FROM tarifas_citas
    WHERE modalidad = 'virtual'
);