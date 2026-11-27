<?php
// Acceso a datos de cuentas y directorios administrativos.

require_once dirname(__DIR__).'/servicios/FiltroDirectorioService.php';

function condicionesDirectorio(array $f, bool $pacientes): array
{
    $cond = ['1=1']; $tipos = ''; $params = [];
    if ($f['estado'] !== 'todos') {
        $cond[] = 'f.activo = ?'; $tipos .= 'i'; $params[] = (int)$f['estado'];
    }
    if ($f['buscar'] !== '') {
        $cond[] = "(CONCAT_WS(' ', f.nombres, f.apellidos) LIKE ? ESCAPE '!' OR CONCAT_WS(' ', f.apellidos, f.nombres) LIKE ? ESCAPE '!' OR f.dni LIKE ? ESCAPE '!'
            " . ($pacientes ? '' : " OR f.cmp LIKE ? ESCAPE '!'") . ")";
        $patron = patronDirectorio($f['buscar']);
        $tipos .= 'sss'; array_push($params, $patron, $patron, $patron);
        if (!$pacientes) { $tipos .= 's'; $params[] = $patron; }
    }
    if ($pacientes && $f['sexo'] !== '') {
        $cond[] = 'f.sexo = ?'; $tipos .= 's'; $params[] = $f['sexo'];
    }
    if (!$pacientes && $f['especialidad'] !== '') {
        $cond[] = 'f.especialidad = ?'; $tipos .= 's'; $params[] = $f['especialidad'];
    }
    return [implode(' AND ', $cond), $tipos, $params];
}

function consultarDirectorio(mysqli $db, string $sql, string $tipos, array $params): array
{
    $stmt = $db->prepare($sql);
    try {
        if ($tipos !== '') $stmt->bind_param($tipos, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally { $stmt->close(); }
}

function buscarDirectorio(mysqli $db, bool $pacientes, array $f): array
{
    $tabla = $pacientes ? 'pacientes' : 'doctores';
    $columnas = $pacientes
        ? 'f.id, f.nombres, f.apellidos, f.dni, f.sexo, f.telefono, f.correo, f.fecha_registro, f.activo, u.usuario'
        : 'f.id, f.nombres, f.apellidos, f.dni, f.cmp, f.especialidad, f.telefono, f.hora_inicio, f.hora_fin, f.activo, u.usuario';
    [$where, $tipos, $params] = condicionesDirectorio($f, $pacientes);
    $total = (int)consultarDirectorio($db, "SELECT COUNT(*) AS total FROM $tabla f WHERE $where", $tipos, $params)[0]['total'];
    $p = paginaDirectorio($total, $f['pagina']);
    $filas = consultarDirectorio($db, "SELECT $columnas FROM $tabla f LEFT JOIN usuarios u ON u.id=f.usuario_id WHERE $where ORDER BY f.apellidos, f.nombres, f.id LIMIT ? OFFSET ?", $tipos.'ii', [...$params, $p['limite'], $p['offset']]);
    return ['filas'=>$filas, 'total'=>$total] + $p;
}

function especialidadesDirectorio(mysqli $db): array
{
    return array_column(consultarDirectorio($db, "SELECT DISTINCT especialidad FROM doctores WHERE especialidad IS NOT NULL AND especialidad <> '' ORDER BY especialidad", '', []), 'especialidad');
}

function usuarioFichaPaciente(mysqli $conexion, int $id): ?string
{
    $stmt = $conexion->prepare("SELECT u.usuario FROM pacientes p
        LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ? AND p.activo = 1");
    try {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()["usuario"] ?? null;
    } finally {
        $stmt->close();
    }
}

function usuarioFichaDoctor(mysqli $conexion, int $id): ?string
{
    $stmt = $conexion->prepare("SELECT u.usuario FROM doctores d
        LEFT JOIN usuarios u ON u.id = d.usuario_id WHERE d.id = ? AND d.activo = 1");
    try {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()["usuario"] ?? null;
    } finally {
        $stmt->close();
    }
}


// El rol determina la tabla; nunca se acepta un nombre de tabla del cliente.
function tablaFichaDirectorio(int $rol): string
{
    return match ($rol) {
        0 => 'pacientes',
        1 => 'doctores',
        default => throw new LogicException("Rol de ficha no permitido.")
    };
}

// El llamador debe tener una transacción abierta. Evita editar fichas inactivas
// o inexistentes aunque el identificador enviado por el cliente sea manipulado.
function bloquearFichaDirectorio(mysqli $conexion, int $fichaId, int $rol): void
{
    $tabla = tablaFichaDirectorio($rol);
    $stmt = $conexion->prepare("SELECT id FROM $tabla WHERE id = ? AND activo = 1 FOR UPDATE");
    try {
        $stmt->bind_param("i", $fichaId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new DomainException("La ficha activa ya no está disponible.");
        }
    } finally {
        $stmt->close();
    }
}

// Actualiza únicamente la cuenta vinculada a la ficha y al rol esperado.
// Recibe un hash, nunca la contraseña en texto plano. Requiere transacción.
function actualizarPasswordFichaDirectorio(mysqli $conexion, int $fichaId, int $rol, string $hash): void
{
    $tabla = tablaFichaDirectorio($rol);
    $stmt = $conexion->prepare("SELECT u.id FROM $tabla f
        INNER JOIN usuarios u ON u.id = f.usuario_id
        WHERE f.id = ? AND f.activo = 1 AND u.rol = ? FOR UPDATE");
    try {
        $stmt->bind_param("ii", $fichaId, $rol);
        $stmt->execute();
        $cuenta = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$cuenta) {
        throw new DomainException("No existe una cuenta vinculada con el rol correcto. No se guardaron los cambios.");
    }
    $usuarioId = (int)$cuenta["id"];
    $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ? AND rol = ?");
    try {
        $stmt->bind_param("sii", $hash, $usuarioId, $rol);
        $stmt->execute();
    } finally {
        $stmt->close();
    }
}
