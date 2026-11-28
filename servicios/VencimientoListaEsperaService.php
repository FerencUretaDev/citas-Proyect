<?php
require_once dirname(__DIR__).'/repositorios/ListaEsperaDAO.php';
require_once __DIR__.'/AuditoriaService.php';

function fechaCorteListaEspera(DateTimeImmutable $ahora): string
{
    return $ahora->setTimezone(new DateTimeZone('America/Lima'))->format('Y-m-d');
}

function verificarMotorVencimientoListaEspera(mysqli $db): void
{
    $r=consultarListaEsperaDAO($db,"SELECT COUNT(*) total FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('lista_espera','auditoria') AND ENGINE='InnoDB'",'',[]);
    if((int)$r[0]['total']!==2) throw new RuntimeException('Se requieren lista_espera y auditoria transaccionales.');
}

// Operación de mantenimiento, no cambia citas ni pagos. La fecha de corte
// procede del reloj del servidor. El llamante controla la transacción.
function vencerSolicitudFechaPasada(mysqli $db, int $id, DateTimeImmutable $ahora): bool
{
    if ($id<=0) throw new InvalidArgumentException('ID inválido.');
    $hoy=fechaCorteListaEspera($ahora);
    $s=$db->prepare("UPDATE lista_espera SET estado='vencido' WHERE id=? AND solicitud_activa=1 AND fecha_solicitada<? AND cita_id IS NULL");
    try {
        $s->bind_param('is',$id,$hoy);
        $s->execute();
        $cambio=$s->affected_rows===1;
    } finally {$s->close();}
    // Auditoría en la misma transacción: si falla, el llamante revierte el lote.
    if ($cambio) auditoriaInsertarDAO($db,auditoriaEvento([],'lista_espera','vencer','exitoso','solicitud_espera',$id));
    return $cambio;
}

function contarSolicitudesFechaPasada(mysqli $db, DateTimeImmutable $ahora): int
{
    $r=consultarListaEsperaDAO($db,"SELECT COUNT(*) total FROM lista_espera WHERE solicitud_activa=1 AND fecha_solicitada<? AND cita_id IS NULL",'s',[fechaCorteListaEspera($ahora)]);
    return (int)$r[0]['total'];
}

// Lotes pequeños para evitar transacciones de larga duración. Idempotente.
// No llamar desde una transacción ya iniciada.
function vencerLoteListaEspera(mysqli $db, DateTimeImmutable $ahora, int $limite=100): int
{
    if ($limite<1 || $limite>500) throw new InvalidArgumentException('Límite inválido.');
    $db->begin_transaction();
    try {
        $filas=consultarListaEsperaDAO($db,"SELECT id FROM lista_espera WHERE solicitud_activa=1 AND fecha_solicitada<? AND cita_id IS NULL ORDER BY id LIMIT ? FOR UPDATE",'si',[fechaCorteListaEspera($ahora),$limite]);
        $total=0;
        foreach($filas as $fila) if(vencerSolicitudFechaPasada($db,(int)$fila['id'],$ahora)) $total++;
        if(!$db->commit()) throw new RuntimeException('No se confirmó el lote.');
        return $total;
    } catch(Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
