<?php
// Reglas de negocio para las nuevas citas del paciente (turnos de 30 minutos).
require_once __DIR__ . "/../repositorios/CitaDAO.php";
require_once __DIR__ . "/EspecialidadService.php";

function resumenRestriccionesReserva(array $citas, string $especialidad, array $opcionesPorClave): array
{
    $mismaEspecialidad = false;
    $horasOcupadas = [];
    $clave = claveEspecialidad($especialidad);
    foreach ($citas as $cita) {
        $claveCita = claveEspecialidad($cita['especialidad']);
        // Usar las mismas equivalencias del catálogo que el formulario.
        $canonica = $opcionesPorClave[$claveCita] ?? $cita['especialidad'];
        if (claveEspecialidad($canonica) === $clave || $claveCita === $clave) {
            $mismaEspecialidad = true;
        }
        $horasOcupadas[] = $cita['hora'];
    }
    return ['misma_especialidad' => $mismaEspecialidad, 'horas_ocupadas' => array_values(array_unique($horasOcupadas))];
}

function segundosReserva(string $hora): int
{
    [$h, $m, $s] = array_map('intval', explode(':', $hora));
    return $h * 3600 + $m * 60 + $s;
}

function seCruzanTurnosReserva(string $hora, string $otraHora): bool
{
    $a = segundosReserva($hora);
    $b = segundosReserva($otraHora);
    return $a < $b + 1800 && $b < $a + 1800;
}

function conflictoReserva(array $restricciones, string $hora): ?string
{
    if ($restricciones['misma_especialidad']) {
        return 'Ya tienes una cita de esta especialidad en la fecha seleccionada. Elige otra especialidad u otro día.';
    }
    foreach ($restricciones['horas_ocupadas'] as $ocupada) {
        if (seCruzanTurnosReserva($hora, $ocupada)) {
            return 'Ya tienes otra cita que coincide con este horario. Selecciona un turno diferente.';
        }
    }
    return null;
}
