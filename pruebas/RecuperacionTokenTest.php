<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require dirname(__DIR__).'/config/Conexion.php';
require dirname(__DIR__).'/repositorios/RecuperacionDAO.php';
require dirname(__DIR__).'/repositorios/RecuperacionTokenDAO.php';

$total = 0;

function comprobar(bool $condicion, string $mensaje): void
{
    global $total;

    if (!$condicion) {
        throw new RuntimeException('FALLO: '.$mensaje);
    }

    $total++;

    echo 'OK: '.$mensaje.PHP_EOL;
}

try {

    $conexion->query("
        CREATE TEMPORARY TABLE usuarios (
            id INT PRIMARY KEY,
            rol TINYINT NOT NULL
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE pacientes (
            usuario_id INT,
            activo TINYINT
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE doctores (
            usuario_id INT,
            activo TINYINT
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE tokens_cuenta (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            usuario_id INT NOT NULL,
            finalidad VARCHAR(40) NOT NULL,
            correo_destino VARCHAR(254) NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            fecha_creacion DATETIME NOT NULL,
            fecha_expiracion DATETIME NOT NULL,
            fecha_uso DATETIME NULL,
            fecha_revocacion DATETIME NULL
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE solicitudes_recuperacion (
            id INT PRIMARY KEY,
            usuario_id INT NOT NULL,
            administrador_id INT NULL,
            token_id BIGINT UNSIGNED NULL UNIQUE,
            estado VARCHAR(20) NOT NULL,
            fecha_atencion DATETIME NULL,
            identidad_verificada_at DATETIME NULL,
            fecha_resolucion DATETIME NULL
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        INSERT INTO usuarios VALUES
        (1,2),(2,2),(10,0),(11,0),(12,1),(13,0)
    ");

    $conexion->query("
        INSERT INTO pacientes VALUES
        (10,1),(11,0),(13,1)
    ");

    $conexion->query("
        INSERT INTO doctores VALUES (12,1)
    ");

    $conexion->query("
        INSERT INTO solicitudes_recuperacion
        (id,usuario_id,administrador_id,estado)
        VALUES
        (1,10,1,'en_atencion'),
        (2,11,1,'en_atencion'),
        (3,12,NULL,'pendiente'),
        (4,13,10,'en_atencion')
    ");

    comprobar(
        generarCodigoRecuperacionDAO(
            $conexion,1,1,false
        ) === null,
        'Requiere confirmacion de identidad'
    );

    comprobar(
        generarCodigoRecuperacionDAO(
            $conexion,1,2,true
        ) === null,
        'Rechaza administrador no asignado'
    );

    comprobar(
        generarCodigoRecuperacionDAO(
            $conexion,2,1,true
        ) === null,
        'Rechaza cuenta inactiva'
    );

    comprobar(
        generarCodigoRecuperacionDAO(
            $conexion,3,1,true
        ) === null,
        'Rechaza solicitud pendiente'
    );

    comprobar(
        generarCodigoRecuperacionDAO(
            $conexion,4,10,true
        ) === null,
        'Rechaza actor sin rol administrativo'
    );

    $codigo = generarCodigoRecuperacionDAO(
        $conexion,1,1,true
    );

    comprobar(
        is_string($codigo) &&
        preg_match('/\A[a-f0-9]{32}\z/', $codigo) === 1,
        'Genera codigo aleatorio de 128 bits'
    );

    $token = $conexion->query("
        SELECT *,
        TIMESTAMPDIFF(
            SECOND,fecha_creacion,fecha_expiracion
        ) AS vigencia
        FROM tokens_cuenta
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();

    comprobar(
        hash_equals(
            hash('sha256',$codigo),
            $token['token_hash']
        ),
        'Almacena el hash SHA-256'
    );

    comprobar(
        (int)$token['vigencia'] === 1800,
        'Vigencia configurada en 30 minutos'
    );

    $solicitud = $conexion->query("
        SELECT * FROM solicitudes_recuperacion
        WHERE id=1
    ")->fetch_assoc();

    comprobar(
        $solicitud['token_id'] === $token['id'] &&
        $solicitud['identidad_verificada_at'] !== null,
        'Vincula token y confirmacion de identidad'
    );

    $nuevo = generarCodigoRecuperacionDAO(
        $conexion,1,1,true
    );

    comprobar(
        is_string($nuevo) && $nuevo !== $codigo,
        'Genera un codigo sustituto diferente'
    );

    $revocados = $conexion->query("
        SELECT COUNT(*) AS total
        FROM tokens_cuenta
        WHERE fecha_revocacion IS NOT NULL
    ")->fetch_assoc();

    comprobar(
        (int)$revocados['total'] === 1,
        'Revoca el codigo anterior'
    );

    comprobar(
        gestionarRecuperacionDAO(
            $conexion,1,1,'rechazar'
        ),
        'Permite rechazar solicitud en atencion'
    );

    $revocados = $conexion->query("
        SELECT COUNT(*) AS total
        FROM tokens_cuenta
        WHERE fecha_revocacion IS NOT NULL
    ")->fetch_assoc();

    comprobar(
        (int)$revocados['total'] === 2,
        'Rechazar solicitud revoca su codigo'
    );

    comprobar(
        generarCodigoRecuperacionDAO(
            $conexion,1,1,true
        ) === null,
        'Impide generar codigo para solicitud rechazada'
    );

    echo PHP_EOL;
    echo "TOTAL: $total PRUEBAS SUPERADAS".PHP_EOL;
    echo "SIN REGISTROS PERMANENTES".PHP_EOL;

} finally {
    $conexion->close();
}