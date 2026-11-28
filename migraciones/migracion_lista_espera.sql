-- Mejora N.º 11: Lista de espera de pacientes
-- Base de datos: sistema

CREATE TABLE lista_espera (
    id INT NOT NULL AUTO_INCREMENT,
    paciente_id INT NOT NULL,
    doctor_id INT NOT NULL,
    fecha_solicitada DATE NOT NULL,

    modalidad ENUM(
        'presencial',
        'virtual'
    ) NOT NULL DEFAULT 'presencial',

    estado ENUM(
        'en_espera',
        'notificado',
        'reservado',
        'cancelado',
        'vencido'
    ) NOT NULL DEFAULT 'en_espera',

    cita_id INT DEFAULT NULL,

    fecha_registro TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    fecha_actualizacion TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    solicitud_activa TINYINT
        GENERATED ALWAYS AS (
            CASE
                WHEN estado IN ('en_espera', 'notificado')
                THEN 1
                ELSE NULL
            END
        ) STORED,

    PRIMARY KEY (id),

    UNIQUE KEY uq_lista_espera_activa (
        paciente_id,
        doctor_id,
        fecha_solicitada,
        solicitud_activa
    ),

    UNIQUE KEY uq_lista_espera_cita (cita_id),

    KEY idx_lista_espera_prioridad (
        doctor_id,
        fecha_solicitada,
        estado,
        fecha_registro,
        id
    ),

    CONSTRAINT fk_espera_paciente
        FOREIGN KEY (paciente_id)
        REFERENCES pacientes(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_espera_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctores(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_espera_cita
        FOREIGN KEY (cita_id)
        REFERENCES citas(id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;