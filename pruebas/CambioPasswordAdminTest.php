<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require dirname(__DIR__).'/config/Conexion.php';
require dirname(__DIR__).'/repositorios/DirectorioDAO.php';

$total = 0;

function comprobar(bool $ok, string $mensaje): void
{
    global $total;

    if (!$ok) {
        throw new RuntimeException('FALLO: '.$mensaje);
    }

    $total++;
    echo 'OK: '.$mensaje.PHP_EOL;
}

try {

    $conexion->query("
        CREATE TEMPORARY TABLE usuarios (
            id INT PRIMARY KEY,
            password VARCHAR(255) NOT NULL,
            rol TINYINT NOT NULL,
            version_sesion INT UNSIGNED DEFAULT 1
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE pacientes (
            id INT PRIMARY KEY,
            usuario_id INT,
            activo TINYINT
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE doctores (
            id INT PRIMARY KEY,
            usuario_id INT,
            activo TINYINT
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE tokens_cuenta (
            id INT PRIMARY KEY,
            usuario_id INT,
            finalidad VARCHAR(40),
            fecha_uso DATETIME NULL,
            fecha_revocacion DATETIME NULL
        ) ENGINE=InnoDB
    ");

    $anterior = password_hash(
        'Anterior123!', PASSWORD_DEFAULT
    );

    $nuevo = password_hash(
        'NuevaPrueba456!', PASSWORD_DEFAULT
    );

    $conexion->query("
        INSERT INTO usuarios VALUES
        (10,'$anterior',0,1),
        (11,'$anterior',1,1),
        (12,'$anterior',0,1)
    ");

    $conexion->query("
        INSERT INTO pacientes VALUES
        (100,10,1),(102,12,0)
    ");

    $conexion->query("
        INSERT INTO doctores VALUES (101,11,1)
    ");

    $conexion->query("
        INSERT INTO tokens_cuenta VALUES
        (1,10,'recuperar_asistencia',NULL,NULL),
        (2,10,'recuperar_password',NULL,NULL),
        (3,10,'verificar_correo',NULL,NULL),
        (4,12,'recuperar_asistencia',NULL,NULL),
        (5,10,'recuperar_asistencia',UTC_TIMESTAMP(),NULL)
    ");

    $conexion->begin_transaction();

    actualizarPasswordFichaDirectorio(
        $conexion,100,0,$nuevo
    );

    $conexion->commit();

    $cuenta = $conexion->query("
        SELECT * FROM usuarios WHERE id=10
    ")->fetch_assoc();

    comprobar(
        password_verify('NuevaPrueba456!',$cuenta['password']),
        'Actualiza contrasena del paciente'
    );

    comprobar(
        (int)$cuenta['version_sesion'] === 2,
        'Invalida sesiones anteriores una sola vez'
    );

    $revocados = $conexion->query("
        SELECT COUNT(*) AS total
        FROM tokens_cuenta
        WHERE id IN (1,2)
        AND fecha_revocacion IS NOT NULL
    ")->fetch_assoc();

    comprobar(
        (int)$revocados['total'] === 2,
        'Revoca todos los codigos pendientes'
    );

    $conservados = $conexion->query("
        SELECT COUNT(*) AS total
        FROM tokens_cuenta
        WHERE id IN (3,4,5)
        AND fecha_revocacion IS NULL
    ")->fetch_assoc();

    comprobar(
        (int)$conservados['total'] === 3,
        'Conserva tokens ajenos o ya utilizados'
    );

    $conexion->begin_transaction();

    actualizarPasswordFichaDirectorio(
        $conexion,101,1,$nuevo
    );

    $conexion->commit();

    $doctor = $conexion->query("
        SELECT * FROM usuarios WHERE id=11
    ")->fetch_assoc();

    comprobar(
        password_verify('NuevaPrueba456!',$doctor['password'])
        && (int)$doctor['version_sesion'] === 2,
        'Actualiza doctor aunque no tenga tokens'
    );

    $rechazado = false;

    $conexion->begin_transaction();

    try {
        actualizarPasswordFichaDirectorio(
            $conexion,100,1,$nuevo
        );
    } catch (DomainException $e) {
        $rechazado = true;
    } finally {
        $conexion->rollback();
    }

    comprobar($rechazado,'Rechaza ficha con rol incorrecto');

    $rechazado = false;

    $conexion->begin_transaction();

    try {
        actualizarPasswordFichaDirectorio(
            $conexion,102,0,$nuevo
        );
    } catch (DomainException $e) {
        $rechazado = true;
    } finally {
        $conexion->rollback();
    }

    comprobar($rechazado,'Rechaza ficha inactiva');

    $conexion->query("
        INSERT INTO tokens_cuenta VALUES
        (6,10,'recuperar_asistencia',NULL,NULL)
    ");

    $conexion->begin_transaction();

    actualizarPasswordFichaDirectorio(
        $conexion,100,0,$anterior
    );

    $conexion->rollback();

    $cuenta = $conexion->query("
        SELECT * FROM usuarios WHERE id=10
    ")->fetch_assoc();

    comprobar(
        hash_equals($nuevo,$cuenta['password'])
        && (int)$cuenta['version_sesion'] === 2,
        'Rollback conserva contrasena y version'
    );

    $token = $conexion->query("
        SELECT fecha_revocacion
        FROM tokens_cuenta WHERE id=6
    ")->fetch_assoc();

    comprobar(
        $token['fecha_revocacion'] === null,
        'Rollback conserva codigo pendiente'
    );

    echo PHP_EOL;
    echo "TOTAL: $total PRUEBAS SUPERADAS".PHP_EOL;
    echo "SIN REGISTROS PERMANENTES".PHP_EOL;

} finally {
    $conexion->close();
}