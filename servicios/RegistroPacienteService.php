<?php
require_once __DIR__ . '/UsuarioService.php';
require_once dirname(__DIR__) . '/repositorios/RegistroPacienteDAO.php';

function textoRegistroPaciente(array $entrada, string $campo, int $max, bool $obligatorio = true): string
{
    $valor = $entrada[$campo] ?? '';
    if (!is_string($valor)) throw new DomainException('Formato de datos inválido.');
    $valor = trim($valor);
    if (($obligatorio && $valor === '') || !preg_match('/\A[^\p{C}]*\z/uD', $valor) || preg_match('/\A.{0,' . $max . '}\z/usD', $valor) !== 1) {
        throw new DomainException('Revisa los campos obligatorios y su longitud.');
    }
    return $valor;
}

function validarRegistroPaciente(array $entrada, ?DateTimeImmutable $ahora = null): array
{
    $dni = $entrada['dni'] ?? null;
    if (!is_string($dni) || !preg_match('/\A[0-9]{8}\z/D', $dni)) throw new DomainException('El DNI debe tener 8 dígitos.');
    $nombres = textoRegistroPaciente($entrada, 'nombres', 100);
    $primero = textoRegistroPaciente($entrada, 'primer_apellido', 100);
    $segundo = textoRegistroPaciente($entrada, 'segundo_apellido', 100, false);
    $apellidos = trim($primero . ' ' . $segundo);
    if (!preg_match('/\A.{1,100}\z/usD', $apellidos)) throw new DomainException('Los apellidos admiten hasta 100 caracteres.');
    foreach ([$nombres, $primero, $segundo] as $nombre) {
        if ($nombre !== '' && !preg_match("/\\A[\\p{L}\\p{M}][\\p{L}\\p{M} .’'-]*\\z/uD", $nombre)) throw new DomainException('Revisa los nombres y apellidos.');
    }
    $base = baseUsuario($nombres, $primero, $segundo, 0);
    if ($base === '') throw new DomainException('No se pudo generar el usuario. Revisa tus nombres y apellidos.');
    $zona = new DateTimeZone('America/Lima');
    $hoy = ($ahora ?? new DateTimeImmutable('now', $zona))->setTimezone($zona)->format('Y-m-d');
    $fecha = $entrada['fecha_nacimiento'] ?? null;
    if (!is_string($fecha) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $fecha)) throw new DomainException('Fecha de nacimiento inválida.');
    $f = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, $zona);
    if (!$f || $f->format('Y-m-d') !== $fecha || $fecha < '1000-01-01' || $fecha > $hoy) throw new DomainException('Fecha de nacimiento inválida.');
    $sexo = $entrada['sexo'] ?? null;
    if (!in_array($sexo, ['M', 'F'], true)) throw new DomainException('Selecciona un sexo válido.');
    $telefono = textoRegistroPaciente($entrada, 'telefono', 9);
    // Contacto obligatorio; no acredita posesión del número.
    if (!preg_match('/\A9[0-9]{8}\z/D', $telefono)) throw new DomainException('Ingresa un celular peruano de 9 dígitos que comience con 9.');
    // Correo de contacto declarado. No habilita recuperación hasta verificarlo.
    $correo = textoRegistroPaciente($entrada, 'correo', 100);
    if (strlen($correo) > 100 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) throw new DomainException('Ingresa un correo válido.');
    $direccion = textoRegistroPaciente($entrada, 'direccion', 200, false);
    if (($entrada['acepta_privacidad'] ?? null) !== '1') throw new DomainException('Debes aceptar el aviso de privacidad del proyecto.');
    $error = validarPasswordDirectorio($entrada['password'] ?? null, $entrada['confirmar_password'] ?? null, false);
    if ($error !== null) throw new DomainException($error);
    // Nunca aceptar usuario_id, rol, activo ni usuario suministrados por el cliente.
    return ['dni'=>$dni, 'nombres'=>$nombres, 'apellidos'=>$apellidos, 'fecha_nacimiento'=>$fecha, 'sexo'=>$sexo, 'telefono'=>$telefono, 'correo'=>$correo, 'direccion'=>$direccion, 'base'=>$base];
}

// Llamar desde un controlador que aplique CSRF y límites persistentes de solicitudes.
// No vincula fichas existentes. UNIQUE(dni) protege también ante solicitudes simultáneas.
function registrarPacientePublico(mysqli $db, array $entrada): array
{
    $datos = validarRegistroPaciente($entrada);
    $hash = password_hash($entrada['password'], PASSWORD_DEFAULT);
    $db->begin_transaction();
    try {
        if (existeDniRegistroPaciente($db, $datos['dni'])) throw new DomainException('No se pudo completar el registro. Si ya tienes una cuenta, inicia sesión o contacta a la clínica.');
        [$usuarioId, $usuario] = crearCuentaConUsuario($db, $datos['base'], $hash, 0);
        $pacienteId = insertarFichaRegistroPaciente($db, (int)$usuarioId, $datos);
        $db->commit();
        return ['usuario_id'=>(int)$usuarioId, 'paciente_id'=>$pacienteId, 'usuario'=>$usuario];
    } catch (Throwable $error) {
        $db->rollback();
        if ($error instanceof mysqli_sql_exception && $error->getCode() === 1062) {
            throw new DomainException('No se pudo completar el registro. Si ya tienes una cuenta, inicia sesión o contacta a la clínica.');
        }
        throw $error;
    }
}
