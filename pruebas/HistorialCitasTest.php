<?php

require_once dirname(__DIR__) .
    "/servicios/HistorialCitasService.php";

$exitosas = 0;
$fallidas = 0;

function probarHistorial(
    string $nombre,
    callable $prueba
): void {

    global $exitosas, $fallidas;

    try {

        if ($prueba() !== true) {
            throw new RuntimeException(
                "Resultado diferente al esperado."
            );
        }

        $exitosas++;
        echo "[OK] " . $nombre . PHP_EOL;

    } catch (Throwable $e) {

        $fallidas++;
        echo "[ERROR] " . $nombre . PHP_EOL;
        echo $e->getMessage() . PHP_EOL;
    }
}

function rechazaFiltroHistorial(array $datos): bool
{
    try {
        validarFiltrosHistorial($datos);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
}

// PRUEBA 01
probarHistorial("Filtros vacios", function () {

    $resultado = validarFiltrosHistorial([]);

    return $resultado === [
        "fecha_inicio" => "",
        "fecha_fin" => "",
        "especialidad" => "",
        "estado" => ""
    ];
});

// PRUEBA 02
probarHistorial("Rango de fechas valido", function () {

    $resultado = validarFiltrosHistorial([
        "fecha_inicio" => "2026-11-01",
        "fecha_fin" => "2026-11-25"
    ]);

    return $resultado["fecha_inicio"] === "2026-11-01"
        && $resultado["fecha_fin"] === "2026-11-25";
});

// PRUEBA 03
probarHistorial("Fecha inexistente", function () {

    return rechazaFiltroHistorial([
        "fecha_inicio" => "2026-02-30"
    ]);
});

// PRUEBA 04
probarHistorial("Formato de fecha incorrecto", function () {

    return rechazaFiltroHistorial([
        "fecha_inicio" => "25/11/2026"
    ]);
});

// PRUEBA 05
probarHistorial("Rango de fechas invertido", function () {

    return rechazaFiltroHistorial([
        "fecha_inicio" => "2026-11-25",
        "fecha_fin" => "2026-11-01"
    ]);
});

// PRUEBA 06
probarHistorial("Estado atendida permitido", function () {

    $resultado = validarFiltrosHistorial([
        "estado" => "atendida"
    ]);

    return $resultado["estado"] === "atendida";
});

// PRUEBA 07
probarHistorial("Estado cancelada permitido", function () {

    $resultado = validarFiltrosHistorial([
        "estado" => "cancelada"
    ]);

    return $resultado["estado"] === "cancelada";
});

// PRUEBA 08
probarHistorial("Estado pendiente permitido", function () {

    $resultado = validarFiltrosHistorial([
        "estado" => "pendiente"
    ]);

    return $resultado["estado"] === "pendiente";
});

// PRUEBA 09
probarHistorial("Estado confirmada permitido", function () {

    $resultado = validarFiltrosHistorial([
        "estado" => "confirmada"
    ]);

    return $resultado["estado"] === "confirmada";
});

// PRUEBA 10
probarHistorial("Estado no autorizado", function () {

    return rechazaFiltroHistorial([
        "estado" => "eliminada"
    ]);
});

// PRUEBA 11
probarHistorial("Especialidad valida", function () {

    $resultado = validarFiltrosHistorial([
        "especialidad" => "Cardiologia"
    ]);

    return $resultado["especialidad"] === "Cardiologia";
});

// PRUEBA 12
probarHistorial("Especialidad demasiado extensa", function () {

    return rechazaFiltroHistorial([
        "especialidad" => str_repeat("A", 401)
    ]);
});

// PRUEBA 13
probarHistorial("Rechazar arreglos como filtros", function () {

    return rechazaFiltroHistorial([
        "estado" => ["atendida"]
    ]);
});

// PRUEBA 14
probarHistorial("Eliminar espacios innecesarios", function () {

    $resultado = validarFiltrosHistorial([
        "estado" => "  cancelada  "
    ]);

    return $resultado["estado"] === "cancelada";
});

// PRUEBA 15
probarHistorial("Rechazar estado con inyeccion SQL", function () {

    return rechazaFiltroHistorial([
        "estado" => "' OR 1=1 --"
    ]);
});

// PRUEBA 16
probarHistorial("Combinacion de filtros validos", function () {

    $resultado = validarFiltrosHistorial([
        "fecha_inicio" => "2026-11-01",
        "fecha_fin" => "2026-11-25",
        "especialidad" => "Cardiologia",
        "estado" => "atendida"
    ]);

    return $resultado === [
        "fecha_inicio" => "2026-11-01",
        "fecha_fin" => "2026-11-25",
        "especialidad" => "Cardiologia",
        "estado" => "atendida"
    ];
});

echo PHP_EOL;
echo "====================================" . PHP_EOL;
echo " PRUEBAS DEL HISTORIAL DE CITAS" . PHP_EOL;
echo "====================================" . PHP_EOL;
echo "Total: " . ($exitosas + $fallidas) . PHP_EOL;
echo "Exitosas: " . $exitosas . PHP_EOL;
echo "Fallidas: " . $fallidas . PHP_EOL;
echo "====================================" . PHP_EOL;

exit($fallidas === 0 ? 0 : 1);