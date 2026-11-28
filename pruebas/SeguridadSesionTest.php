<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require dirname(__DIR__).'/config/Conexion.php';
require dirname(__DIR__).'/middlewares/SeguridadSesion.php';

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
            rol TINYINT NOT NULL,
            version_sesion INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        INSERT INTO usuarios VALUES
        (1,2,1),
        (2,0,1),
        (3,1,1)
    ");

    $paciente = [
        'id'=>2,
        'rol'=>0,
        'version_sesion'=>1
    ];

    $doctor = [
        'id'=>3,
        'rol'=>1,
        'version_sesion'=>1
    ];

    $admin = [
        'id'=>1,
        'rol'=>2,
        'version_sesion'=>1
    ];

    comprobar(
        versionSesionValida($conexion,$paciente),
        'Sesion de paciente valida'
    );

    comprobar(
        versionSesionValida($conexion,$doctor),
        'Sesion de doctor valida'
    );

    comprobar(
        versionSesionValida($conexion,$admin),
        'Sesion administrativa valida'
    );

    comprobar(
        !versionSesionValida($conexion,[
            'id'=>2,
            'rol'=>0
        ]),
        'Rechaza sesion sin version'
    );

    comprobar(
        !versionSesionValida($conexion,[
            'id'=>2,
            'rol'=>2,
            'version_sesion'=>1
        ]),
        'Rechaza rol incorrecto'
    );

    comprobar(
        !versionSesionValida($conexion,[
            'id'=>999,
            'rol'=>0,
            'version_sesion'=>1
        ]),
        'Rechaza usuario inexistente'
    );

    comprobar(
        !versionSesionValida($conexion,[
            'id'=>[],
            'rol'=>0,
            'version_sesion'=>1
        ]),
        'Rechaza identificador invalido'
    );

    $conexion->query("
        UPDATE usuarios
        SET version_sesion=version_sesion+1
        WHERE id=2
    ");

    comprobar(
        !versionSesionValida($conexion,$paciente),
        'Invalida sesion anterior del paciente'
    );

    comprobar(
        versionSesionValida($conexion,$doctor),
        'No afecta la sesion del doctor'
    );

    comprobar(
        versionSesionValida($conexion,$admin),
        'No afecta la sesion administrativa'
    );

    $paciente['version_sesion'] = 2;

    comprobar(
        versionSesionValida($conexion,$paciente),
        'Permite nuevo inicio de sesion'
    );

    echo PHP_EOL;
    echo "TOTAL: $total PRUEBAS SUPERADAS".PHP_EOL;
    echo "SIN REGISTROS PERMANENTES".PHP_EOL;

} finally {

    $conexion->close();
}