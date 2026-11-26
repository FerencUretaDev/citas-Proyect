<?php
require_once __DIR__ . '/ReservaService.php';
require_once dirname(__DIR__) . '/repositorios/TarifaDAO.php';
require_once dirname(__DIR__) . '/repositorios/PagoDAO.php';

require_once dirname(__DIR__) .
    '/repositorios/AusenciaDAO.php';

// La transacción y el orden de bloqueo se conservan del flujo original.
function registrarCita(mysqli $conexion, int $paciente_id, int $doctor_id, string $especialidadSeleccionada, string $fecha, string $hora, string $modalidad, string $motivo, array $opcionesPorClave, string $precioMostrado = ""): array
{
    $mensaje = '';
    $tipo_mensaje = '';
        try {
        $conexion->begin_transaction();

        /* =============================================
           OBTENER HORARIO DEL DOCTOR
        ============================================= */

        $doctor = bloquearDoctorReserva($conexion, $doctor_id);
        if (!$doctor || ($opcionesPorClave[claveEspecialidad($doctor["especialidad"])] ?? null) !== $especialidadSeleccionada) {
            $conexion->rollback();

            $mensaje = "El doctor no corresponde a la especialidad seleccionada.";
            $tipo_mensaje = "error";

        } else {


            $pacienteBloqueado = bloquearPacienteReserva($conexion, $paciente_id);

            if (!$pacienteBloqueado) {
                $conexion->rollback();
                return ["No se encontró el perfil del paciente.", "error"];
            }
	  
	    // MEJORA 05: verificar ausencia dentro de la transaccion.
	if (doctorTieneAusenciaDAO(
    	$conexion,
    	$doctor_id,
    	$fecha,
    	true
	)) {

    	$conexion->rollback();

    	return [
        "El doctor no se encuentra disponible en la fecha seleccionada.",
        "error"
    ];
}
	    

            /* =========================================
               VALIDAR FECHA
            ========================================= */

            if ($fecha < date("Y-m-d")) {

                $conexion->rollback();

                $mensaje = "No puede seleccionar una fecha anterior a hoy.";
                $tipo_mensaje = "error";

            } else {

                /* =====================================
                   VALIDAR HORARIO
                ===================================== */

                $inicio = new DateTime($doctor["hora_inicio"]);
                $fin = new DateTime($doctor["hora_fin"]);

                $horaSeleccionada = new DateTime($hora);
                $fechaHoraSeleccionada = DateTime::createFromFormat("!Y-m-d H:i:s", $fecha . " " . $hora);

                /* Una cita dura 30 minutos */
                $finCita = clone $horaSeleccionada;
                $finCita->modify("+30 minutes");

                $segundosDesdeInicio = $horaSeleccionada->getTimestamp() - $inicio->getTimestamp();
                $horarioValido = true;

                if (
                    $horaSeleccionada < $inicio ||
                    $finCita > $fin ||
                    $segundosDesdeInicio % (30 * 60) !== 0
                ) {
                    $horarioValido = false;
                }

                /* =====================================
                   VALIDAR REFRIGERIO
                ===================================== */

                if (
                    $horarioValido &&
                    !empty($doctor["refrigerio_inicio"]) &&
                    !empty($doctor["refrigerio_fin"])
                ) {

                    $inicioRefrigerio = new DateTime($doctor["refrigerio_inicio"]);
                    $finRefrigerio = new DateTime($doctor["refrigerio_fin"]);

                    if (
                        $horaSeleccionada < $finRefrigerio &&
                        $finCita > $inicioRefrigerio
                    ) {
                        $horarioValido = false;
                    }
                }

                /* =====================================
                   SI ES HOY, NO PERMITIR HORAS PASADAS
                ===================================== */

                if (
                    $horarioValido &&
                    $fecha === date("Y-m-d") &&
                    $fechaHoraSeleccionada <= new DateTime()
                ) {
                    $horarioValido = false;
                }

                if (!$horarioValido) {

                    $conexion->rollback();

                    $mensaje = "El horario seleccionado no está disponible para este doctor.";
                    $tipo_mensaje = "error";

                } else {

                    /* =================================
                       VERIFICAR CITA EXISTENTE (DOCTOR O PACIENTE)
                    ================================= */

                    // La ficha del paciente ya está bloqueada dentro de la transacción.
                    $citasDia = citasPacienteDelDia($conexion, (int)$paciente_id, $fecha, true);
                    $restricciones = resumenRestriccionesReserva($citasDia, $especialidadSeleccionada, $opcionesPorClave);
                    $conflictoPaciente = conflictoReserva($restricciones, $hora);

                    $citaExistente = existeCruceReserva($conexion, $doctor_id, $paciente_id, $fecha, $hora);

                    if ($conflictoPaciente !== null || $citaExistente) {

                        $conexion->rollback();

                        $mensaje = $conflictoPaciente ?? "El horario ya está reservado para este profesional. Selecciona otro turno.";
                        $tipo_mensaje = "error";

                    } else {

                        /* =============================
                           INSERTAR CITA
                        ============================= */

                        // La tarifa se obtiene del servidor; no se confía en el valor del formulario.
                        $precioVigente = obtenerTarifaCitaDAO($conexion, $modalidad);
                        if ($precioVigente === null) {
                            $conexion->rollback();
                            return ["La modalidad seleccionada no tiene una tarifa disponible.", "error"];
                        }
                        if ($precioMostrado !== $precioVigente) {
                            $conexion->rollback();
                            return ["La tarifa cambió o no coincide con el precio vigente. Revisa el nuevo importe antes de confirmar.", "error"];
                        }

                        [$guardada, $codigoError, $citaId] = insertarReserva($conexion, $paciente_id, $doctor_id, $fecha, $hora, $modalidad, $motivo);
                        if ($guardada) {

                            $citaId = (int)$citaId;
                            if ($citaId <= 0) throw new RuntimeException('Identificador de cita no disponible.');
                            nuevoPagoCitaDAO($conexion, $citaId, $precioVigente);
                            if (!$conexion->commit()) throw new RuntimeException('No se pudo confirmar la reserva.');
                            return ["Reserva pendiente de pago.", "success", $citaId];

                        } else {

                            $conexion->rollback();

                            if ($codigoError == 1062) {
                                $mensaje = "⚠️ El horario acaba de ser ocupado por otro paciente. Seleccione otro horario.";
                            } else {
                                $mensaje = "Error al registrar la cita.";
                            }

                            $tipo_mensaje = "error";
                        }
                    }
                }
            }
        }
        } catch (Throwable $error) {
            $conexion->rollback();
            error_log('Error al registrar cita. Código MySQL: ' . $error->getCode());
            $mensaje = in_array($error->getCode(), [1062, 1205, 1213], true)
                ? 'La disponibilidad cambió o hay otra reserva en proceso. Vuelve a consultar los horarios.'
                : 'No se pudo registrar la cita. Inténtalo de nuevo.';
            $tipo_mensaje = 'error';
        }
    return [$mensaje, $tipo_mensaje];
}
