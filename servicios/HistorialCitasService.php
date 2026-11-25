<?php

require_once dirname(__DIR__) . "/repositorios/CitaDAO.php";

// Validar los filtros del historial.
function validarFiltrosHistorial(array $datos): array
{
    $filtros = [
        "fecha_inicio" => "",
        "fecha_fin" => "",
        "especialidad" => "",
        "estado" => ""
    ];

    foreach ($filtros as $campo => $valor) {

        $entrada = $datos[$campo] ?? "";

        if (!is_string($entrada)) {
            throw new InvalidArgumentException(
                "Los filtros ingresados no son validos."
            );
        }

        $filtros[$campo] = trim($entrada);
    }

    // Validar fechas.
    foreach (["fecha_inicio", "fecha_fin"] as $campo) {

        $fecha = $filtros[$campo];

        if ($fecha !== "") {

            $objeto = DateTimeImmutable::createFromFormat(
                "!Y-m-d",
                $fecha
            );

            if (!$objeto || $objeto->format("Y-m-d") !== $fecha) {
                throw new InvalidArgumentException(
                    "La fecha ingresada no es valida."
                );
            }
        }
    }

    // Validar rango de fechas.
    if (
        $filtros["fecha_inicio"] !== "" &&
        $filtros["fecha_fin"] !== "" &&
        $filtros["fecha_inicio"] > $filtros["fecha_fin"]
    ) {
        throw new InvalidArgumentException(
            "La fecha inicial no puede superar la fecha final."
        );
    }

    // Validar estado de la cita.
    $estadosPermitidos = [
        "",
        "pendiente",
        "confirmada",
        "atendida",
        "cancelada"
    ];

    if (!in_array(
        $filtros["estado"],
        $estadosPermitidos,
        true
    )) {
        throw new InvalidArgumentException(
            "El estado seleccionado no es valido."
        );
    }

    // Limitar longitud de la especialidad.
    if (strlen($filtros["especialidad"]) > 400) {
        throw new InvalidArgumentException(
            "La especialidad ingresada no es valida."
        );
    }

    return $filtros;
}

// Obtener el historial del paciente autenticado.
function consultarHistorialPaciente(
    mysqli $conexion,
    int $usuarioId,
    array $datos
): array {

    $filtros = validarFiltrosHistorial($datos);

    $ahora = new DateTimeImmutable(
        "now",
        new DateTimeZone("America/Lima")
    );

    $fechaActual = $ahora->format("Y-m-d H:i:s");

    $historialCompleto = obtenerHistorialCitasPacienteDAO(
        $conexion,
        $usuarioId,
        $fechaActual
    );

    // Obtener especialidades disponibles en el historial.
    $especialidades = array_values(
        array_unique(
            array_column(
                $historialCompleto,
                "especialidad"
            )
        )
    );

    sort($especialidades, SORT_NATURAL | SORT_FLAG_CASE);

    // Consultar resultados filtrados cuando corresponda.
    $tieneFiltros = false;

    foreach ($filtros as $valor) {
        if ($valor !== "") {
            $tieneFiltros = true;
            break;
        }
    }

    $citas = $tieneFiltros
        ? obtenerHistorialCitasPacienteDAO(
            $conexion,
            $usuarioId,
            $fechaActual,
            $filtros["fecha_inicio"],
            $filtros["fecha_fin"],
            $filtros["especialidad"],
            $filtros["estado"]
        )
        : $historialCompleto;

    // Generar resumen del historial filtrado.
    $resumen = [
        "total" => count($citas),
        "atendida" => 0,
        "pendiente" => 0,
        "confirmada" => 0,
        "cancelada" => 0
    ];

    foreach ($citas as $cita) {

        $estado = $cita["estado"];

        if (isset($resumen[$estado])) {
            $resumen[$estado]++;
        }
    }

    return [
        "citas" => $citas,
        "filtros" => $filtros,
        "especialidades" => $especialidades,
        "resumen" => $resumen
    ];
}