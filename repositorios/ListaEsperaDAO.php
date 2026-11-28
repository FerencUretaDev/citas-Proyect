<?php
// Acceso a datos; el servicio controla permisos y transacciones.
function consultarListaEsperaDAO(mysqli $db, string $sql, string $tipos, array $params): array
{
    $s = $db->prepare($sql);
    try {
        if ($tipos !== '') $s->bind_param($tipos, ...$params);
        $s->execute();
        return $s->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally { $s->close(); }
}

function pacienteListaEsperaDAO(mysqli $db, int $usuarioId, bool $bloquear = false): ?array
{
    $sql = 'SELECT id FROM pacientes WHERE usuario_id=? AND activo=1 LIMIT 1';
    return consultarListaEsperaDAO($db, $sql.($bloquear?' FOR UPDATE':''), 'i', [$usuarioId])[0] ?? null;
}

function horasDoctorListaEsperaDAO(mysqli $db, int $doctorId, string $fecha, bool $bloquear = false): array
{
    $sql = "SELECT hora FROM citas WHERE doctor_id=? AND fecha=? AND estado<>'cancelada'";
    return array_column(consultarListaEsperaDAO($db, $sql.($bloquear?' FOR UPDATE':''), 'is', [$doctorId,$fecha]), 'hora');
}

function solicitudActivaListaEsperaDAO(mysqli $db, int $pacienteId, int $doctorId, string $fecha): ?array
{
    return consultarListaEsperaDAO($db, 'SELECT id, estado FROM lista_espera WHERE paciente_id=? AND doctor_id=? AND fecha_solicitada=? AND solicitud_activa=1', 'iis', [$pacienteId,$doctorId,$fecha])[0] ?? null;
}

function insertarListaEsperaDAO(mysqli $db, int $pacienteId, int $doctorId, string $fecha, string $modalidad): int
{
    $s = $db->prepare('INSERT INTO lista_espera (paciente_id,doctor_id,fecha_solicitada,modalidad) VALUES (?,?,?,?)');
    try {
        $s->bind_param('iiss',$pacienteId,$doctorId,$fecha,$modalidad);
        $s->execute();
        return (int)$db->insert_id;
    } finally { $s->close(); }
}

function solicitudesPacienteListaEsperaDAO(mysqli $db, int $pacienteId): array
{
    return consultarListaEsperaDAO($db, "SELECT l.id,l.doctor_id,l.fecha_solicitada,l.modalidad,l.estado,l.cita_id,l.fecha_registro,d.nombres,d.apellidos,d.especialidad FROM lista_espera l JOIN doctores d ON d.id=l.doctor_id WHERE l.paciente_id=? ORDER BY l.fecha_registro DESC,l.id DESC", 'i', [$pacienteId]);
}

// Orden FIFO de solicitudes activas. No equivale a un turno reservado.
function colaListaEsperaDAO(mysqli $db, int $doctorId, string $fecha): array
{
    return consultarListaEsperaDAO($db, "SELECT l.id,l.paciente_id,l.modalidad,l.estado,l.fecha_registro FROM lista_espera l JOIN pacientes p ON p.id=l.paciente_id AND p.activo=1 JOIN doctores d ON d.id=l.doctor_id AND d.activo=1 WHERE l.doctor_id=? AND l.fecha_solicitada=? AND l.solicitud_activa=1 ORDER BY l.fecha_registro,l.id", 'is', [$doctorId,$fecha]);
}

function cancelarListaEsperaDAO(mysqli $db, int $id, int $pacienteId): bool
{
    $hoy = (new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
    $s = $db->prepare("UPDATE lista_espera SET estado='cancelado' WHERE id=? AND paciente_id=? AND solicitud_activa=1 AND fecha_solicitada>=?");
    try {
        $s->bind_param('iis',$id,$pacienteId,$hoy);
        $s->execute();
        return $s->affected_rows === 1;
    } finally { $s->close(); }
}
