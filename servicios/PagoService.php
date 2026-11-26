<?php
require_once __DIR__.'/MercadoPagoService.php';
require_once dirname(__DIR__).'/repositorios/PagoDAO.php';
require_once dirname(__DIR__).'/repositorios/AusenciaDAO.php';
function ahoraPago(): string {return (new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');}
function pagoAutorizado(mysqli $db,array $c,int $rol,int $usuario): bool {return $rol===2||($rol===0&&propietarioCitaPagoDAO($db,(int)$c['paciente_id'],$usuario));}
// Orden de bloqueo: doctor, cita, pago. La reserva original ya confirmó la transacción local.
function gestionarPago(mysqli $db,int $citaId,int $rol,int $usuario,string $accion): array {
    $c=citaPagoDAO($db,$citaId);
    if(!$c||!pagoAutorizado($db,$c,$rol,$usuario))throw new DomainException('No tienes acceso a este pago.');
    $db->begin_transaction();
    try {
        $s=$db->prepare('SELECT activo FROM doctores WHERE id=? FOR UPDATE');$s->execute([$c['doctor_id']]);$doctor=$s->get_result()->fetch_assoc();$s->close();
        $actual=citaPagoDAO($db,$citaId,true);
        if(!$actual||(int)$actual['doctor_id']!==(int)$c['doctor_id']||!pagoAutorizado($db,$actual,$rol,$usuario))throw new DomainException('La cita cambió; vuelve a consultar.');
        $p=pagoCitaDAO($db,$citaId,true);
        if(!$p||$p['ambiente']!=='test'||$p['proveedor']!=='mercadopago')throw new DomainException('No hay un pago de prueba asociado.');
        $id=(int)$p['id'];$orden=$p['id_orden_mp'];
        if($accion==='crear'){
            if($rol!==0||$actual['estado']!=='pendiente'||!in_array($p['estado'],['pendiente','rechazado','en_proceso'],true)||$actual['fecha'].' '.$actual['hora']<=ahoraPago()||!$doctor||!(int)$doctor['activo']||doctorTieneAusenciaDAO($db,(int)$actual['doctor_id'],$actual['fecha'],true))throw new DomainException('No se puede iniciar el pago para esta cita.');
            if($orden===null){
                // Reintentos acotados: nunca cambiar la clave de una solicitud incierta.
                $edad=(int)$p['edad_segundos'];
                if($edad>600)throw new DomainException('La creación de la orden requiere revisión administrativa.');
                $d=mpCrearOrden($p);
                if(!mpCoincideOrden($d,$p))throw new RuntimeException('Orden no coincide o no es de prueba.');
                $orden=$d['id'];$url=mpUrlCheckout($d['checkout_url']??null);
                guardarOrdenPagoDAO($db,$id,$orden,$url);$p['id_orden_mp']=$orden;$p['url_checkout']=$url;
            } else {$d=mpConsultarOrden($orden);}
        } else {
            if($orden===null)throw new DomainException('No hay orden verificable. No se liberará el turno si hubo una solicitud incierta.');
            $d=mpConsultarOrden($orden);
            if($accion==='cancelar'&&mpCoincideOrden($d,$p)&&mpOrdenCancelable($d)&&$actual['estado']==='pendiente'&&$p['fecha_pago']===null&&$p['estado']!=='aprobado'){
                mpCancelarOrden($orden,$p['clave_idempotencia']);$d=mpConsultarOrden($orden);
            }
        }
        if(!mpCoincideOrden($d,$p))throw new RuntimeException('La orden no coincide con la reserva.');
        $status=(string)($d['status']??'');$detalle=(string)($d['status_detail']??'');
        $estado=match($status){'created'=>'pendiente','processing','action_required'=>'en_proceso','failed'=>'rechazado','canceled'=>'cancelado','refunded'=>'reembolsado',default=>'revision'};
        $idPago=null;$fecha=null;$mensaje='Estado actualizado: '.$estado.'.';$redirect=null;
        if($status==='action_required'&&$detalle==='waiting_retry')$mensaje='El intento de pago fue rechazado. La orden sigue abierta para otro intento.';
        if($accion==='cancelar'&&$status!=='canceled')$mensaje='La orden aún no está cancelada. El turno permanece reservado hasta confirmar su cancelación.';
        if(mpPagoAcreditado($d,$p)){
            $estado='aprobado';$fecha=ahoraPago();
            foreach(($d['transactions']['payments']??[]) as $item){if(($item['status']??'')==='processed'&&($item['status_detail']??'')==='accredited'&&(is_string($item['id']??null)||is_int($item['id']??null))){$idPago=(string)$item['id'];break;}}
            if($actual['estado']==='pendiente'){
                if($actual['fecha'].' '.$actual['hora']<=ahoraPago()||!$doctor||!(int)$doctor['activo']||doctorTieneAusenciaDAO($db,(int)$actual['doctor_id'],$actual['fecha'],true)){$estado='revision';$mensaje='Pago acreditado, pero el turno requiere revisión del administrador.';}
                else {estadoCitaPagoDAO($db,$citaId,'confirmada');$mensaje='Pago acreditado. Cita confirmada correctamente.';}
            }elseif(in_array($actual['estado'],['confirmada','atendida'],true)){$mensaje='Pago acreditado. Cita verificada.';}else{$estado='revision';$mensaje='Pago acreditado en una cita cancelada. Revisión administrativa requerida.';}
        }elseif($status==='canceled'&&$actual['estado']==='pendiente'&&$p['estado']!=='aprobado'&&$p['fecha_pago']===null){estadoCitaPagoDAO($db,$citaId,'cancelada');$mensaje='Orden cancelada por Mercado Pago. Turno liberado.';}
        elseif($p['fecha_pago']!==null&&$estado!=='aprobado'){$mensaje='El estado financiero cambió. Requiere revisión administrativa.';}
        if($accion==='crear'&&$estado==='pendiente'&&$actual['estado']==='pendiente'){$redirect=mpUrlCheckout($d['checkout_url']??$p['url_checkout']);if($redirect===null)$mensaje='Orden creada, pero falta una URL válida. Consulta el pago.';}
        actualizarPagoDAO($db,$id,$estado,$status,$detalle,$idPago,$fecha);
        $db->commit();return [$mensaje,$redirect];
    } catch(Throwable $e){$db->rollback();throw $e;}
}
