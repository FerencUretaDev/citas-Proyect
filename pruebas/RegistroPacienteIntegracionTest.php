<?php
// Prueba exclusivamente local. Las tablas temporales ocultan a las originales
// en esta conexión. No ejecuta INSERT, UPDATE o DELETE sobre tablas permanentes.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
if (PHP_OS_FAMILY !== 'Windows' || !in_array(getenv('DB_HOST') ?: 'localhost', ['localhost', '127.0.0.1', '::1'], true) || (getenv('DB_NAME') ?: 'sistema') !== 'sistema') {
    fwrite(STDERR, "Solo se permite XAMPP local (base sistema).\n"); exit(1);
}
require dirname(__DIR__) . '/config/Conexion.php';
require_once dirname(__DIR__) . '/servicios/RegistroPacienteService.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$total = 0;
$salida = 0;
$etapa = 'verificar base local';
function comprobarRegistroIntegracion(bool $ok, string $nombre): void {
    global $total;
    if (!$ok) throw new RuntimeException('FALLO: ' . $nombre);
    echo 'OK: ' . $nombre . PHP_EOL; $total++;
}
function cuentaFilasRegistro(mysqli $db, string $tabla): int {
    // Solo identificadores constantes internos.
    if (!in_array($tabla, ['usuarios', 'pacientes'], true)) throw new LogicException('Tabla no permitida.');
    return (int)$db->query('SELECT COUNT(*) FROM ' . $tabla)->fetch_row()[0];
}
function rechazoRegistroIntegracion(mysqli $db, array $datos): bool {
    try { registrarPacientePublico($db, $datos); return false; }
    catch (DomainException $e) { return true; }
}
try {
    if ($conexion->query('SELECT DATABASE()')->fetch_row()[0] !== 'sistema') throw new RuntimeException('Base local incorrecta.');
    // DDL explícito: evita el conflicto de alias de CREATE TEMPORARY ... LIKE
    // cuando la tabla de destino y la de origen tienen el mismo nombre.
    // Estructura de prueba basada en SHOW FULL COLUMNS / SHOW INDEX locales.
    $etapa = 'crear tabla temporal usuarios';
    $conexion->query("CREATE TEMPORARY TABLE usuarios (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        rol TINYINT NOT NULL,
        fecha_registro TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        correo_recuperacion VARCHAR(254) NULL DEFAULT NULL,
        correo_verificado_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY usuario (usuario),
        UNIQUE KEY uq_usuarios_correo_recuperacion (correo_recuperacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $etapa = 'crear tabla temporal pacientes';
    $conexion->query("CREATE TEMPORARY TABLE pacientes (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        nombres VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        dni VARCHAR(20) NOT NULL,
        fecha_nacimiento DATE NULL DEFAULT NULL,
        sexo CHAR(1) NULL DEFAULT NULL,
        telefono VARCHAR(20) NULL DEFAULT NULL,
        direccion VARCHAR(200) NULL DEFAULT NULL,
        correo VARCHAR(100) NULL DEFAULT NULL,
        fecha_registro TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        motivo_baja VARCHAR(160) NULL DEFAULT NULL,
        fecha_baja DATETIME NULL DEFAULT NULL,
        UNIQUE KEY dni (dni),
        KEY idx_pacientes_usuario (usuario_id),
        KEY idx_pacientes_dni (dni),
        UNIQUE KEY uq_prueba_correo_temporal (correo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $etapa = 'verificar aislamiento de tablas temporales';
    foreach (['usuarios', 'pacientes'] as $tabla) {
        $ddl = $conexion->query('SHOW CREATE TABLE ' . $tabla)->fetch_row()[1];
        if (stripos($ddl, 'CREATE TEMPORARY TABLE') === false || stripos($ddl, 'ENGINE=InnoDB') === false) throw new RuntimeException('No se confirmó la tabla temporal InnoDB: ' . $tabla);
    }
    comprobarRegistroIntegracion(cuentaFilasRegistro($conexion, 'usuarios') === 0 && cuentaFilasRegistro($conexion, 'pacientes') === 0, 'Tablas temporales InnoDB vacías');
    // El índice único de correo, solo en la tabla temporal, permite forzar
    // un fallo de inserción de ficha y comprobar el rollback.
    $etapa = 'ejecutar pruebas de registro';
    $datos = ['dni'=>'00000000','nombres'=>'Ana María','primer_apellido'=>'Prueba','segundo_apellido'=>'Ejemplo','fecha_nacimiento'=>'2000-01-01','sexo'=>'F','telefono'=>'900000000','correo'=>'prueba1@example.invalid','direccion'=>'','acepta_privacidad'=>'1','password'=>'Prueba!2026','confirmar_password'=>'Prueba!2026','rol'=>2,'activo'=>0,'usuario_id'=>999,'usuario'=>'admin'];
    $r = registrarPacientePublico($conexion, $datos);
    $u = $conexion->query('SELECT id, usuario, password, rol FROM usuarios')->fetch_assoc();
    $p = $conexion->query('SELECT id, usuario_id, dni, activo, nombres, correo FROM pacientes')->fetch_assoc();
    comprobarRegistroIntegracion(cuentaFilasRegistro($conexion,'usuarios') === 1 && cuentaFilasRegistro($conexion,'pacientes') === 1, 'Cuenta y ficha creadas');
    comprobarRegistroIntegracion((int)$u['id'] === $r['usuario_id'] && (int)$p['usuario_id'] === $r['usuario_id'] && (int)$p['id'] === $r['paciente_id'], 'Vínculo correcto entre cuenta y ficha');
    comprobarRegistroIntegracion((int)$u['rol'] === 0 && (int)$p['activo'] === 1, 'Rol paciente y activación impuestos por servidor');
    comprobarRegistroIntegracion($u['usuario'] === 'pac.apruebae' && $r['usuario'] === $u['usuario'], 'Usuario generado por servidor');
    comprobarRegistroIntegracion($u['password'] !== $datos['password'] && password_verify($datos['password'], $u['password']), 'Contraseña almacenada como hash válido');
    comprobarRegistroIntegracion($p['dni'] === '00000000' && $p['nombres'] === 'Ana María', 'DNI con ceros iniciales y nombres con tildes');
    comprobarRegistroIntegracion(rechazoRegistroIntegracion($conexion, $datos) && cuentaFilasRegistro($conexion,'usuarios') === 1 && cuentaFilasRegistro($conexion,'pacientes') === 1, 'DNI duplicado rechazado sin cuenta adicional');
    $conexion->query('UPDATE pacientes SET activo=0');
    comprobarRegistroIntegracion(rechazoRegistroIntegracion($conexion, $datos) && cuentaFilasRegistro($conexion,'usuarios') === 1 && cuentaFilasRegistro($conexion,'pacientes') === 1, 'DNI de ficha inactiva también rechazado');
    $conexion->query('UPDATE pacientes SET activo=1');
    $r2 = registrarPacientePublico($conexion, array_replace($datos,['dni'=>'00000001','correo'=>'prueba2@example.invalid']));
    comprobarRegistroIntegracion($r2['usuario'] === 'pac.apruebae2' && $r2['usuario_id'] !== $r['usuario_id'], 'Colisión de nombre de usuario resuelta con sufijo');
    $antesU = cuentaFilasRegistro($conexion,'usuarios'); $antesP = cuentaFilasRegistro($conexion,'pacientes');
    comprobarRegistroIntegracion(rechazoRegistroIntegracion($conexion,array_replace($datos,['dni'=>'00000002'])), 'Error forzado al insertar ficha (correo temporal único)');
    comprobarRegistroIntegracion($antesU === 2 && $antesP === 2 && cuentaFilasRegistro($conexion,'usuarios') === $antesU && cuentaFilasRegistro($conexion,'pacientes') === $antesP, 'Rollback: ninguna cuenta huérfana');
} catch (Throwable $e) {
    $salida = 1;
    fwrite(STDERR, ($e instanceof mysqli_sql_exception ? 'Error SQL: ' . $e->getCode() . ' / etapa: ' . $etapa : $e->getMessage()) . PHP_EOL);
} finally {
    // DROP TEMPORARY nunca elimina las tablas permanentes, incluso ante error.
    try { $conexion->query('DROP TEMPORARY TABLE IF EXISTS pacientes, usuarios'); }
    catch (Throwable $e) { $salida = 1; fwrite(STDERR, "No se pudo retirar explícitamente la tabla temporal; se cierra la conexión.\n"); }
    $conexion->close();
}
if ($salida === 0) echo 'Total: ' . $total . ' pruebas de integración superadas. Sin registros permanentes.' . PHP_EOL;
exit($salida);
