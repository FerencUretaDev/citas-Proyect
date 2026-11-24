<?php

require_once dirname(__DIR__) . "/repositorios/CitaDAO.php";

const ETIQUETAS_ESTADO_CITA = [
    "pendiente" => "Pendiente",
    "confirmada" => "Confirmada",
    "atendida" => "Atendida",
    "cancelada" => "Cancelada"
];

const TRANSICIONES_ESTADO_CITA = [
    "pendiente" => ["confirmada", "cancelada"],
    "confirmada" => ["atendida", "cancelada"],
    "atendida" => [],
    "cancelada" => []
];

// Cancelación de citas por el paciente
function cancelarCitaPaciente(
    mysqli $conexion,
    int $citaId,
    int $usuarioId
): bool {

    if ($citaId <= 0 || $usuarioId <= 0) {
        return false;
    }

    $ahora = new DateTimeImmutable(
        "now",
        new DateTimeZone("America/Lima")
    );

    return cancelarCitaPacienteDAO(
        $conexion,
        $citaId,
        $usuarioId,
        $ahora->format("Y-m-d H:i:s")
    );
}

// Verificar si el paciente puede cancelar su cita
function puedeCancelarCitaPaciente(array $cita): bool
{
    if (!in_array(
        $cita["estado"],
        ["pendiente", "confirmada"],
        true
    )) {
        return false;
    }

    $zona = new DateTimeZone("America/Lima");

    $fechaCita = new DateTimeImmutable(
        $cita["fecha"] . " " . $cita["hora"],
        $zona
    );

    return $fechaCita > new DateTimeImmutable(
        "now",
        $zona
    );
}