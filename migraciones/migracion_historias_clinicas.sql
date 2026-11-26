CREATE TABLE IF NOT EXISTS historias_clinicas (
    id INT NOT NULL AUTO_INCREMENT,
    cita_id INT NOT NULL,

    antecedentes TEXT DEFAULT NULL,
    alergias TEXT DEFAULT NULL,
    evaluacion TEXT NOT NULL,
    diagnostico TEXT NOT NULL,
    tratamiento TEXT DEFAULT NULL,
    recomendaciones TEXT DEFAULT NULL,

    proximo_control DATE DEFAULT NULL,

    fecha_atencion DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    fecha_registro TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_historia_cita (cita_id),

    KEY idx_historia_fecha (fecha_atencion),

    CONSTRAINT fk_historia_cita
        FOREIGN KEY (cita_id)
        REFERENCES citas(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;