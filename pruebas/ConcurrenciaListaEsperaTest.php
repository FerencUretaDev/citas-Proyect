<?php
// Prueba de contención con dos conexiones reales. Solo XAMPP local.
// No confirma ninguna transacción ni llama a Mercado Pago.
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Windows') {
    http_response_code(403); exit(1);
}
if (!in_array(getenv('DB_HOST') ?: 'localhost', ['localhost','127.0.0.1','::1'], true)
    || (getenv('DB_NAME') ?: 'sistema') !== 'sistema') {
    fwrite(STDERR, "Entorno no local.\n"); exit(1);
}
require_once dirname(__DIR__).'/servicios/ListaEsperaService.php';
require_once dirname(__DIR__).'/servicios/PrioridadListaEsperaService.php';
function conexionConcurrenciaEspera(): mysqli {
    require dirname(__DIR__).'/config/Conexion.php';
    return $conexion;
}
function comprobarConcurrenciaEspera(bool $ok, string $mensaje): void {
    if (!$ok) throw new RuntimeException($mensaje);
    echo "OK: $mensaje\n";
}
$fallo = false; $a = null; $b = null; $txA = false; $txB = false; $ids = []; $cita = 0;
try {
    $a = conexionConcurrenciaEspera();
    $b = conexionConcurrenciaEspera();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    foreach ([$a,$b] as $db) {
        if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== 'sistema') {
            throw new RuntimeException('Base inesperada.');
        }
    }
    comprobarConcurrenciaEspera($a->thread_id !== $b->thread_id &&
        $a->query('SELECT @@hostname')->fetch_row()[0] === $b->query('SELECT @@hostname')->fetch_row()[0],
        'Dos conexiones independientes al mismo servidor');
    $motores = $a->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('citas','pacientes','doctores','lista_espera','ausencias_doctores') AND ENGINE='InnoDB'")->fetch_row()[0];
    comprobarConcurrenciaEspera((int)$motores === 5,'Tablas transaccionales InnoDB');
    $b->query('SET SESSION innodb_lock_wait_timeout=2');
    $fecha = ahoraReprogramacion()->modify('+180 days')->format('Y-m-d');
    $hora = '08:00:00';
    $a->begin_transaction(); $txA = true;
    $d = bloquearDoctorReserva($a,3);
    if (!$d || $d['hora_inicio'] !== '08:00:00' || $d['hora_fin'] !== '08:30:00'
        || $d['refrigerio_inicio'] !== null || $d['refrigerio_fin'] !== null
        || !consultarListaEsperaDAO($a,"SELECT id FROM doctores WHERE id=3 AND cmp='CMP-TEST01'",'',[])) {
        throw new RuntimeException('Doctor ficticio fuera de la configuración de prueba.');
    }
    comprobarConcurrenciaEspera(bloquearPacienteReserva($a,2) && bloquearPacienteReserva($a,3),'Conexión A bloquea doctor y pacientes de prueba');
    if (doctorTieneAusenciaDAO($a,3,$fecha,true) || consultarListaEsperaDAO($a,'SELECT id FROM citas WHERE fecha=? AND (doctor_id=3 OR paciente_id IN (2,3)) FOR UPDATE','s',[$fecha])
        || consultarListaEsperaDAO($a,'SELECT id FROM lista_espera WHERE doctor_id=3 AND fecha_solicitada=? FOR UPDATE','s',[$fecha])) {
        throw new RuntimeException('Fecha auxiliar ocupada; no se crean datos de prueba.');
    }
    $ids[] = insertarListaEsperaDAO($a,2,3,$fecha,'presencial');
    $ids[] = insertarListaEsperaDAO($a,3,3,$fecha,'presencial');
    [, $mapa] = cargarCatalogoEspecialidades($a);
    $esp = $mapa[claveEspecialidad($d['especialidad'])] ?? $d['especialidad'];
    $cola = candidatosPrioridadEspera($a,3,$fecha,$esp,$mapa,true,2);
    comprobarConcurrenciaEspera((int)(primerCompatiblePrioridadEspera($cola,$hora)['id'] ?? 0) === $ids[0]
        && !permiteTurnoPrioridadEspera($cola,3,$hora),'Prioridad FIFO dentro de la transacción A');
    comprobarConcurrenciaEspera(!existeCruceReserva($a,3,2,$fecha,$hora),'Turno auxiliar inicialmente libre');
    [$ok,,$cita] = insertarReserva($a,2,3,$fecha,$hora,'presencial','PRUEBA_MEJORA11_CONCURRENCIA');
    comprobarConcurrenciaEspera($ok && $cita>0,'Reserva temporal en A, sin pago ni commit');
    vincularReservaPrioridadEspera($a,$ids[0],2,3,$fecha,$cita);
    comprobarConcurrenciaEspera(existeCruceReserva($a,3,3,$fecha,$hora),'Cruce de segunda reserva detectado en A');
    $duplicado = false;
    try { insertarReserva($a,3,3,$fecha,$hora,'presencial','PRUEBA_MEJORA11_DUPLICADO'); }
    catch (mysqli_sql_exception $e) { if ((int)$e->getCode() !== 1062) throw $e; $duplicado=true; }
    comprobarConcurrenciaEspera($duplicado,'Índice único rechaza segunda cita activa en el turno');
    $b->begin_transaction(); $txB=true;
    $bloqueada = false;
    try { bloquearDoctorReserva($b,3); }
    catch (mysqli_sql_exception $e) { if ((int)$e->getCode() !== 1205) throw $e; $bloqueada=true; }
    comprobarConcurrenciaEspera($bloqueada,'Conexión B no puede adquirir bloqueo del doctor mientras A está abierta (1205)');
    $b->rollback(); $txB=false;
    $a->rollback(); $txA=false;
    $b->begin_transaction(); $txB=true;
    comprobarConcurrenciaEspera((bool)bloquearDoctorReserva($b,3),'Conexión B adquiere bloqueo después del rollback de A');
    comprobarConcurrenciaEspera(!existeCruceReserva($b,3,3,$fecha,$hora),'Turno libre tras rollback');
    foreach ($ids as $id) {
        if (consultarListaEsperaDAO($b,'SELECT id FROM lista_espera WHERE id=?','i',[$id])) throw new RuntimeException('Solicitud temporal persistente.');
    }
    comprobarConcurrenciaEspera(!consultarListaEsperaDAO($b,'SELECT id FROM citas WHERE id=?','i',[$cita]),'Rollback elimina cita y solicitudes temporales');
    $b->rollback(); $txB=false;
    echo "Integración concurrencia: OK (sin registros nuevos persistentes).\n";
} catch (Throwable $e) {
    fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL); $fallo = true;
} finally {
    if ($txB && $b) $b->rollback();
    if ($txA && $a) $a->rollback();
    if ($b) $b->close();
    if ($a) $a->close();
}

if ($fallo) exit(1);
