<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require dirname(__DIR__).'/config/Conexion.php';
require dirname(__DIR__).'/repositorios/RecuperacionDAO.php';

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException("FALLO: ".$mensaje);
    }

    echo "OK: ".$mensaje.PHP_EOL;
}

try {

    $conexion->query("
        CREATE TEMPORARY TABLE usuarios (
            id INT PRIMARY KEY,
            usuario VARCHAR(50) NOT NULL UNIQUE,
            rol TINYINT NOT NULL
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE pacientes (
            usuario_id INT,
            nombres VARCHAR(100),
            apellidos VARCHAR(100),
            telefono VARCHAR(20),
            activo TINYINT
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE doctores (
            usuario_id INT,
            nombres VARCHAR(100),
            apellidos VARCHAR(100),
            telefono VARCHAR(20),
            activo TINYINT
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE solicitudes_recuperacion (
            id INT PRIMARY KEY AUTO_INCREMENT,
            usuario_id INT NOT NULL,
            estado ENUM(
                'pendiente',
                'en_atencion',
                'resuelta',
                'rechazada'
            ) DEFAULT 'pendiente',
            fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_atencion DATETIME NULL,
            fecha_resolucion DATETIME NULL,
            usuario_activo INT GENERATED ALWAYS AS (
                CASE
                    WHEN estado IN ('pendiente','en_atencion')
                    THEN usuario_id
                    ELSE NULL
                END
            ) STORED,
            UNIQUE KEY uq_activa (usuario_activo)
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        INSERT INTO usuarios VALUES
        (1,'pac.prueba',0),
        (2,'doc.prueba',1),
        (3,'admin.prueba',2),
        (4,'pac.inactivo',0),
        (5,'doc.inactivo',1)
    ");

    $conexion->query("
        INSERT INTO pacientes VALUES
        (1,'Paciente','Prueba','900000000',1),
        (4,'Paciente','Inactivo','900000001',0)
    ");

    $conexion->query("
        INSERT INTO doctores VALUES
        (2,'Doctor','Prueba','900000002',1),
        (5,'Doctor','Inactivo','900000003',0)
    ");

    comprobar(
        contarRecuperacionesPendientesDAO($conexion) === 0,
        'Sin solicitudes iniciales'
    );

    comprobar(
        solicitarRecuperacionDAO($conexion,'pac.prueba'),
        'Paciente activo puede solicitar recuperacion'
    );

    comprobar(
        !solicitarRecuperacionDAO($conexion,'pac.prueba'),
        'Solicitud duplicada rechazada'
    );

    comprobar(
        solicitarRecuperacionDAO($conexion,'doc.prueba'),
        'Doctor activo puede solicitar recuperacion'
    );

    comprobar(
        !solicitarRecuperacionDAO($conexion,'admin.prueba'),
        'Administrador excluido'
    );

    comprobar(
        !solicitarRecuperacionDAO($conexion,'pac.inactivo'),
        'Paciente inactivo excluido'
    );

    comprobar(
        !solicitarRecuperacionDAO($conexion,'doc.inactivo'),
        'Doctor inactivo excluido'
    );

    comprobar(
        !solicitarRecuperacionDAO($conexion,'no.existe'),
        'Usuario inexistente rechazado'
    );

    comprobar(
        contarRecuperacionesPendientesDAO($conexion) === 2,
        'Contador de solicitudes correcto'
    );

    $filas = listarRecuperacionesDAO($conexion);

    comprobar(
        count($filas) === 2,
        'Listado administrativo correcto'
    );

    $porUsuario = array_column($filas, null, 'usuario');

    comprobar(
        ($porUsuario['pac.prueba']['telefono'] ?? null)
            === '900000000'
        &&
        ($porUsuario['doc.prueba']['telefono'] ?? null)
            === '900000002',
        'Obtiene los celulares registrados'
    );

    $conexion->query("
        UPDATE solicitudes_recuperacion
        SET estado='en_atencion'
        WHERE usuario_id=1
    ");

    comprobar(
        !solicitarRecuperacionDAO($conexion,'pac.prueba'),
        'Impide duplicados durante la atencion'
    );

    $conexion->query("
        UPDATE solicitudes_recuperacion
        SET estado='rechazada'
        WHERE usuario_id=1
    ");

    comprobar(
        solicitarRecuperacionDAO($conexion,'pac.prueba'),
        'Permite nueva solicitud tras finalizar la anterior'
    );

    echo PHP_EOL;
    echo "TOTAL: 13 PRUEBAS SUPERADAS".PHP_EOL;
    echo "SIN REGISTROS PERMANENTES".PHP_EOL;

} finally {
    $conexion->close();
}