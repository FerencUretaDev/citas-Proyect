-- Sistema de citas medicas - Mejora 03
-- Aplicar UNA sola vez, despues de realizar un respaldo privado.
-- MariaDB: las operaciones DDL realizan commits implicitos.
USE sistema;

ALTER TABLE usuarios
    ADD COLUMN correo_recuperacion VARCHAR(254) NULL,
    ADD COLUMN correo_verificado_at DATETIME NULL,
    ADD UNIQUE KEY uq_usuarios_correo_recuperacion (correo_recuperacion);

CREATE TABLE tokens_cuenta (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    finalidad ENUM('verificar_correo', 'recuperar_password') NOT NULL,
    correo_destino VARCHAR(254) NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    fecha_creacion DATETIME NOT NULL,
    fecha_expiracion DATETIME NOT NULL,
    fecha_uso DATETIME NULL,
    fecha_revocacion DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tokens_cuenta_hash (token_hash),
    KEY idx_tokens_cuenta_usuario (usuario_id, finalidad, fecha_creacion),
    KEY idx_tokens_cuenta_expiracion (fecha_expiracion),
    CONSTRAINT fk_tokens_cuenta_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
