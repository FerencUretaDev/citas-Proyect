<?php

if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . "/config/Conexion.php";
require_once dirname(__DIR__) .
    "/servicios/HistorialCitasService.php";

$exitosas = 0;
$fallidas = 0;

function comprobarIntegracion(
    string $nombre,
    bool $resultado
): void {
    global $exitosas, $fallidas;

    if ($resultado) {
        $exitosas++;
        echo "[OK] " . $nombre . PHP_EOL;
    } else {
        $fallidas++;
        echo "[ERROR] " . $nombre . PHP_EOL;
    }
}

function idsHistorial(array $citas): array
{
    return array_map(
        "intval",
        array_column($citas, "id")
    );
}

$ahora = new DateTimeImmutable(
    "now",
    new DateTimeZone("America/Lima")
);

$fechaActual = $ahora->format("Y-m-d H:i:s");

// Obtener pacientes activos sin mostrar sus datos personales.

$pacientes = $conexion->query("
    SELECT p.id, p.usuario_id
    FROM pacientes p
    INNER JOIN usuarios u ON u.id = p.usuario_id
    WHERE p.activo = 1 AND u.rol = 0
    ORDER BY p.id
")->fetch_all(MYSQLI_ASSOC);

echo "Pacientes activos disponibles: ";
echo count($pacientes) . PHP_EOL . PHP_EOL;

$totalHistorial = 0;

foreach ($pacientes as $indice => $paciente) {

    $usuarioId = (int) $paciente["usuario_id"];
    $pacienteId = (int) $paciente["id"];
    $numero = $indice + 1;

    // Consulta independiente de referencia.

    $stmt = $conexion->prepare("
        SELECT c.id, c.fecha, c.hora,
               c.estado, d.especialidad
        FROM citas c
        INNER JOIN doctores d ON d.id = c.doctor_id
        WHERE c.paciente_id = ?
        ORDER BY c.fecha DESC, c.hora DESC, c.id DESC
    ");

    $stmt->bind_param("i", $pacienteId);
    $stmt->execute();

    $referencia = $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    // Aplicar la regla del historial independientemente.

    $esperadas = array_values(array_filter(
        $referencia,
        static function ($cita) use ($fechaActual) {

            $fechaCita = $cita["fecha"] .
                " " . $cita["hora"];

            return in_array(
                $cita["estado"],
                ["atendida", "cancelada"],
                true
            ) || $fechaCita < $fechaActual;
        }
    ));

    $historial = obtenerHistorialCitasPacienteDAO(
        $conexion,
        $usuarioId,
        $fechaActual
    );

    $totalHistorial += count($historial);

    comprobarIntegracion(
        "Paciente $numero: historial propio y orden correcto",
        idsHistorial($historial) === idsHistorial($esperadas)
    );

    // Probar los cuatro estados.

    foreach ([
        "pendiente",
        "confirmada",
        "atendida",
        "cancelada"
    ] as $estado) {

        $filtradas = obtenerHistorialCitasPacienteDAO(
            $conexion,
            $usuarioId,
            $fechaActual,
            "",
            "",
            "",
            $estado
        );

        $esperadasEstado = array_values(array_filter(
            $esperadas,
            static fn($cita) =>
                $cita["estado"] === $estado
        ));

        comprobarIntegracion(
            "Paciente $numero: filtro $estado",
            idsHistorial($filtradas) ===
            idsHistorial($esperadasEstado)
        );
    }

    // Probar el filtro por fecha si existen citas.

    if (!empty($esperadas)) {

        $fecha = $esperadas[0]["fecha"];

        $filtradas = obtenerHistorialCitasPacienteDAO(
            $conexion,
            $usuarioId,
            $fechaActual,
            $fecha,
            $fecha
        );

        $esperadasFecha = array_values(array_filter(
            $esperadas,
            static fn($cita) =>
                $cita["fecha"] === $fecha
        ));

        comprobarIntegracion(
            "Paciente $numero: filtro por fecha",
            idsHistorial($filtradas) ===
            idsHistorial($esperadasFecha)
        );
    }
}

// Usuario inexistente.

$sinUsuario = obtenerHistorialCitasPacienteDAO(
    $conexion,
    0,
    $fechaActual
);

comprobarIntegracion(
    "Usuario inexistente sin acceso a citas",
    empty($sinUsuario)
);

echo PHP_EOL;
echo "====================================" . PHP_EOL;
echo " PRUEBAS DE INTEGRACION - HISTORIAL" . PHP_EOL;
echo "====================================" . PHP_EOL;
echo "Pacientes evaluados: " . count($pacientes) . PHP_EOL;
echo "Citas evaluadas: " . $totalHistorial . PHP_EOL;
echo "Total: " . ($exitosas + $fallidas) . PHP_EOL;
echo "Exitosas: " . $exitosas . PHP_EOL;
echo "Fallidas: " . $fallidas . PHP_EOL;

if (count($pacientes) < 2 || $totalHistorial === 0) {
    echo "AVISO: Datos insuficientes para validar ";
    echo "completamente el aislamiento entre pacientes.";
    echo PHP_EOL;
}

echo "====================================" . PHP_EOL;

$conexion->close();

exit($fallidas === 0 ? 0 : 1);