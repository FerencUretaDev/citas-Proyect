<?php
// Tarifas activas de consulta. Los importes DECIMAL se conservan como texto.
function listarTarifasCitasDAO(mysqli $conexion): array
{
    $resultado = $conexion->query("SELECT modalidad, precio FROM tarifas_citas
        WHERE activo = 1 AND moneda = 'PEN' AND precio > 0 ORDER BY modalidad");
    $tarifas = [];
    try {
        while ($fila = $resultado->fetch_assoc()) {
            $tarifas[$fila['modalidad']] = (string) $fila['precio'];
        }
    } finally {
        $resultado->free();
    }
    return $tarifas;
}

// Llamar dentro de la transacción de reserva para mantener estable la tarifa.
function obtenerTarifaCitaDAO(mysqli $conexion, string $modalidad): ?string
{
    $stmt = $conexion->prepare("SELECT precio FROM tarifas_citas
        WHERE modalidad = ? AND activo = 1 AND moneda = 'PEN' AND precio > 0
        FOR UPDATE");
    try {
        $stmt->bind_param('s', $modalidad);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        return $fila ? (string) $fila['precio'] : null;
    } finally {
        $stmt->close();
    }
}
