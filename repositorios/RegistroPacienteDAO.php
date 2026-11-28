<?php

function existeDniRegistroPaciente(mysqli $db, string $dni): bool
{
    $stmt = $db->prepare('SELECT id FROM pacientes WHERE dni = ? LIMIT 1');
    try {
        $stmt->bind_param('s', $dni);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    } finally {
        $stmt->close();
    }
}

function insertarFichaRegistroPaciente(mysqli $db, int $usuarioId, array $datos): int
{
    $stmt = $db->prepare('INSERT INTO pacientes (usuario_id, nombres, apellidos, dni, fecha_nacimiento, sexo, telefono, direccion, correo, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    try {
        $direccion = $datos['direccion'] === '' ? null : $datos['direccion'];
        $stmt->bind_param('issssssss', $usuarioId, $datos['nombres'], $datos['apellidos'], $datos['dni'], $datos['fecha_nacimiento'], $datos['sexo'], $datos['telefono'], $direccion, $datos['correo']);
        $stmt->execute();
        return (int)$stmt->insert_id;
    } finally {
        $stmt->close();
    }
}
