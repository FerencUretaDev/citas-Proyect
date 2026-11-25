<?php

require_once dirname(__DIR__) . '/servicios/ReprogramacionService.php';

$total = 0;
function comprobarReprogramacion(string $nombre, bool $condicion): void
{
    global $total;
    if (!$condicion) {
        fwrite(STDERR, "ERROR: $nombre\n");
        exit(1);
    }
    $total++;
    echo "OK: $nombre\n";
}

$zona = new DateTimeZone('America/Lima');
$ahora = new DateTimeImmutable('2026-11-24 12:30:00', $zona);
$cita = ['id' => 1, 'doctor_id' => 2, 'paciente_usuario_id' => 10, 'paciente_activo' => 1,
    'fecha' => '2026-11-25', 'hora' => '13:00:00', 'estado' => 'pendiente'];
comprobarReprogramacion('Paciente propietario', autorizarReprogramacion($cita, 0, 10));
comprobarReprogramacion('Paciente ajeno rechazado', !autorizarReprogramacion($cita, 0, 11));
comprobarReprogramacion('Administrador autorizado', autorizarReprogramacion($cita, 2, 11));
comprobarReprogramacion('Rol doctor rechazado', !autorizarReprogramacion($cita, 1, 10));
comprobarReprogramacion('Perfil inactivo rechazado', !autorizarReprogramacion(array_replace($cita, ['paciente_activo' => 0]), 0, 10));
comprobarReprogramacion('Cita futura pendiente', puedeReprogramarCitaPaciente($cita, $ahora));
comprobarReprogramacion('Cita futura confirmada', puedeReprogramarCitaPaciente(array_replace($cita, ['estado' => 'confirmada']), $ahora));
comprobarReprogramacion('Citas terminales rechazadas', !puedeReprogramarCitaPaciente(array_replace($cita, ['estado' => 'cancelada']), $ahora) && !puedeReprogramarCitaPaciente(array_replace($cita, ['estado' => 'atendida']), $ahora));
comprobarReprogramacion('Cita pasada rechazada', !puedeReprogramarCitaPaciente(array_replace($cita, ['fecha' => '2026-11-23']), $ahora));
comprobarReprogramacion('Fecha imposible y hora invalida', fechaHoraReprogramacion('2026-02-30', '13:00:00') === null && fechaHoraReprogramacion('2026-11-25', '25:00:00') === null);
comprobarReprogramacion('Turno original detectado', esMismoTurnoReprogramacion($cita, 2, '2026-11-25', '13:00:00') && !esMismoTurnoReprogramacion($cita, 2, '2026-11-25', '13:30:00'));
$doctor = ['hora_inicio' => '12:00:00', 'hora_fin' => '15:00:00', 'refrigerio_inicio' => '13:00:00', 'refrigerio_fin' => '14:00:00'];
comprobarReprogramacion('Turnos pasados, refrigerio y cierre excluidos', turnosValidosReprogramacion($doctor, '2026-11-24', $ahora) === ['14:00:00', '14:30:00']);
$mapa = ['medicina general' => 'Medicina General', 'medecina general' => 'Medicina General'];
comprobarReprogramacion('Alias de especialidad reconocido', mismaEspecialidadReprogramacion('Medecina General', 'Medicina General', $mapa) && !mismaEspecialidadReprogramacion('Medicina General', 'Cardiologia', $mapa));
echo "Total: $total pruebas de reprogramacion superadas.\n";
