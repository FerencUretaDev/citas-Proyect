<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require dirname(__DIR__).'/config/Conexion.php';
require dirname(__DIR__).'/repositorios/RecuperacionTokenDAO.php';
require dirname(__DIR__).'/repositorios/RestablecerPasswordDAO.php';
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
            usuario VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            rol TINYINT NOT NULL,
            version_sesion INT UNSIGNED DEFAULT 1
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE pacientes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            usuario_id INT,
            activo TINYINT
        ) ENGINE=InnoDB
    ");

    $conexion->query("
        CREATE TEMPORARY TABLE doctores (
            id INT PRIMARY KEY AUTO_INCREMENT,
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
            identidad_verificada_at DATETIME NULL,
            fecha_resolucion DATETIME NULL
        ) ENGINE=InnoDB
    ");

    $hashAnterior = password_hash(
        'Anterior123!',
        PASSWORD_DEFAULT
    );

    $hashNuevo = password_hash(
        'NuevaClave456!',
        PASSWORD_DEFAULT
    );

    $conexion->query("
        INSERT INTO usuarios
        (id,usuario,password,rol,version_sesion)
        VALUES
        (1,'admin.prueba','$hashAnterior',2,1),
        (10,'pac.prueba','$hashAnterior',0,1),
        (11,'doc.prueba','$hashAnterior',1,1)
    ");

    $conexion->query("
        INSERT INTO pacientes (usuario_id,activo)
        VALUES (10,1)
    ");

    $conexion->query("
        INSERT INTO doctores (usuario_id,activo)
        VALUES (11,1)
    ");

    $conexion->query("
        INSERT INTO solicitudes_recuperacion
        (id,usuario_id,administrador_id,estado)
        VALUES
        (1,10,1,'en_atencion'),
        (2,11,1,'en_atencion')
    ");

    $codigo = generarCodigoRecuperacionDAO(
        $conexion,1,1,true
    );

    comprobar(
        is_string($codigo),
        'Codigo de prueba generado'
    );

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'pac.prueba',
            str_repeat('0',32),$hashNuevo
        ),
        'Rechaza codigo incorrecto'
    );

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'no.existe',$codigo,$hashNuevo
        ),
        'Rechaza usuario inexistente'
    );

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'doc.prueba',$codigo,$hashNuevo
        ),
        'Rechaza codigo de otro usuario'
    );

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'admin.prueba',$codigo,$hashNuevo
        ),
        'Excluye cuentas administrativas'
    );

    $conexion->query("
        UPDATE tokens_cuenta
        SET fecha_expiracion =
            DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)
        WHERE usuario_id=10
    ");

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'pac.prueba',$codigo,$hashNuevo
        ),
        'Rechaza codigo vencido'
    );

    $codigo = generarCodigoRecuperacionDAO(
        $conexion,1,1,true
    );

    $conexion->query("
        UPDATE tokens_cuenta
        SET fecha_revocacion=UTC_TIMESTAMP()
        WHERE usuario_id=10
        AND fecha_revocacion IS NULL
    ");

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'pac.prueba',$codigo,$hashNuevo
        ),
        'Rechaza codigo revocado'
    );

    $codigo = generarCodigoRecuperacionDAO(
        $conexion,1,1,true
    );

    $conexion->query("
        UPDATE pacientes SET activo=0
        WHERE usuario_id=10
    ");

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'pac.prueba',$codigo,$hashNuevo
        ),
        'Rechaza paciente inactivo'
    );

    $conexion->query("
        UPDATE pacientes SET activo=1
        WHERE usuario_id=10
    ");

    comprobar(
        restablecerPasswordDAO(
            $conexion,'pac.prueba',$codigo,$hashNuevo
        ),
        'Restablecimiento exitoso'
    );

    $cuenta = $conexion->query("
        SELECT password,version_sesion
        FROM usuarios WHERE id=10
    ")->fetch_assoc();

    comprobar(
        password_verify(
            'NuevaClave456!',
            $cuenta['password']
        ),
        'Nueva contrasena almacenada correctamente'
    );

    comprobar(
        !password_verify(
            'Anterior123!',
            $cuenta['password']
        ),
        'Contrasena anterior deja de ser valida'
    );

    comprobar(
        (int)$cuenta['version_sesion'] === 2,
        'Incrementa la version de sesion'
    );

    comprobar(
        !versionSesionValida($conexion,[
            'id'=>10,
            'rol'=>0,
            'version_sesion'=>1
        ]),
        'Invalida la sesion anterior'
    );

    $resultado = $conexion->query("
        SELECT s.estado,t.fecha_uso
        FROM solicitudes_recuperacion s
        INNER JOIN tokens_cuenta t
            ON s.token_id=t.id
        WHERE s.id=1
    ")->fetch_assoc();

    comprobar(
        $resultado['estado'] === 'resuelta'
        && $resultado['fecha_uso'] !== null,
        'Resuelve solicitud y consume codigo'
    );

    comprobar(
        !restablecerPasswordDAO(
            $conexion,'pac.prueba',$codigo,$hashNuevo
        ),
        'Impide reutilizar codigo'
    );

    comprobar(
        versionSesionValida($conexion,[
            'id'=>11,
            'rol'=>1,
            'version_sesion'=>1
        ]),
        'No afecta las sesiones de otros usuarios'
    );


    // MEJORA 03 - Pruebas adicionales de seguridad.

    require_once dirname(__DIR__).'/servicios/AutenticacionService.php';

    $claveLegada = 'PruebaLegada123!';
    $hashLegado = hash('sha256', $claveLegada);

    $stmt = $conexion->prepare("
        UPDATE usuarios SET password=?
        WHERE id=10
    ");

    $stmt->bind_param('s', $hashLegado);
    $stmt->execute();
    $stmt->close();

    $login = autenticarUsuario(
        $conexion,
        'pac.prueba',
        $claveLegada
    );

    comprobar(
        is_array($login) && (int)$login['id'] === 10,
        'Autenticacion con hash antiguo'
    );

    $cuenta = $conexion->query("
        SELECT password,version_sesion
        FROM usuarios WHERE id=10
    ")->fetch_assoc();

    comprobar(
        password_verify($claveLegada,$cuenta['password'])
        && (int)$cuenta['version_sesion'] === 2,
        'Migra hash sin modificar version de sesion'
    );

    comprobar(
        !actualizarHashUsuario(
            $conexion,10,$hashNuevo,$hashLegado,2
        ),
        'Impide sobrescribir mediante hash antiguo'
    );

    comprobar(
        !actualizarHashUsuario(
            $conexion,10,$hashNuevo,$cuenta['password'],1
        ),
        'Impide actualizar con version obsoleta'
    );

    $actual = $conexion->query("
        SELECT password FROM usuarios WHERE id=10
    ")->fetch_assoc();

    comprobar(
        hash_equals($cuenta['password'],$actual['password']),
        'Conserva el hash vigente'
    );

    // Prueba de rollback con la cuenta temporal del doctor.

    $codigoDoctor = generarCodigoRecuperacionDAO(
        $conexion,2,1,true
    );

    comprobar(
        is_string($codigoDoctor),
        'Genera codigo para prueba de rollback'
    );

    // Provocar un fallo en el ultimo UPDATE del DAO.
    // Solo modifica la tabla temporal de esta conexion.
    $conexion->query("
        ALTER TABLE solicitudes_recuperacion
        DROP COLUMN fecha_resolucion
    ");

    $falloControlado = false;

    try {

        restablecerPasswordDAO(
            $conexion,
            'doc.prueba',
            $codigoDoctor,
            $hashNuevo
        );

    } catch (mysqli_sql_exception $e) {

        $falloControlado = $e->getCode() === 1054;
    }

    comprobar(
        $falloControlado,
        'Detecta fallo controlado de transaccion'
    );

    $doctor = $conexion->query("
        SELECT password,version_sesion
        FROM usuarios WHERE id=11
    ")->fetch_assoc();

    comprobar(
        hash_equals($hashAnterior,$doctor['password'])
        && (int)$doctor['version_sesion'] === 1,
        'Rollback conserva contrasena y version anterior'
    );

    $estadoRollback = $conexion->query("
        SELECT s.estado,t.fecha_uso,t.fecha_revocacion
        FROM solicitudes_recuperacion s
        INNER JOIN tokens_cuenta t ON s.token_id=t.id
        WHERE s.id=2
    ")->fetch_assoc();

    comprobar(
        $estadoRollback['estado'] === 'en_atencion'
        && $estadoRollback['fecha_uso'] === null
        && $estadoRollback['fecha_revocacion'] === null,
        'Rollback conserva solicitud y codigo sin consumir'
    );

    echo PHP_EOL;
    echo "TOTAL: $total PRUEBAS SUPERADAS".PHP_EOL;
    echo "SIN REGISTROS PERMANENTES".PHP_EOL;

} finally {
    $conexion->close();
}