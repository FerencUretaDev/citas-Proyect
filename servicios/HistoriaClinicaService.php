<?php
require_once dirname(__DIR__).'/repositorios/HistoriaClinicaDAO.php';
require_once dirname(__DIR__).'/repositorios/PagoDAO.php';
function hcAhora(): DateTimeImmutable {return new DateTimeImmutable('now',new DateTimeZone('America/Lima'));}
function hcPuedeRegistrar(array $c,DateTimeImmutable $ahora): bool {
    return $c['estado']==='confirmada' && empty($c['historia_id']) && (($c['pago_estado']??null)===null || $c['pago_estado']==='aprobado') && $c['fecha'].' '.$c['hora'] <= $ahora->format('Y-m-d H:i:s');
}
function hcValidar(array $datos,DateTimeImmutable $ahora): array {
    $v=[];
    foreach(['antecedentes','alergias','evaluacion','diagnostico','tratamiento','recomendaciones'] as $campo){
        $texto=$datos[$campo]??'';
        if(!is_string($texto)||!preg_match('//u',$texto)||strlen($texto)>12000)throw new DomainException('Texto clínico inválido o demasiado extenso (máximo 3000 caracteres).');
        $texto=trim($texto);
        if(preg_match_all('/./us',$texto)>3000)throw new DomainException('Máximo 3000 caracteres por campo.');
        if(in_array($campo,['evaluacion','diagnostico'],true)&&$texto==='')throw new DomainException('La evaluación y el diagnóstico son obligatorios.');
        $v[$campo]=$texto===''?null:$texto;
    }
    $fecha=$datos['proximo_control']??'';
    if(!is_string($fecha))throw new DomainException('Fecha de control inválida.');
    if($fecha!==''){
        $f=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha,$ahora->getTimezone());
        if(!$f||$f->format('Y-m-d')!==$fecha||$fecha<$ahora->format('Y-m-d')||$fecha>'9999-12-31')throw new DomainException('El próximo control debe ser una fecha válida, de hoy o posterior.');
    }
    $v['proximo_control']=$fecha===''?null:$fecha;return $v;
}
function hcFinalizar(mysqli $db,int $usuario,int $doctor,int $cita,array $datos): void {
    $ahora=hcAhora();$v=hcValidar($datos,$ahora);
    $db->begin_transaction();
    try{
        if(!hcBloquearDoctor($db,$doctor,$usuario))throw new DomainException('Acceso denegado.');
        $c=hcBloquearCita($db,$cita,$doctor);
        if(!$c)throw new DomainException('Cita no disponible.');
        $p=pagoCitaDAO($db,$cita,true);$c['pago_estado']=$p['estado']??null;
        $c['historia_id']=hcExiste($db,$cita)?1:null;
        if(!hcPuedeRegistrar($c,$ahora))throw new DomainException('La cita debe estar confirmada, con pago aprobado si corresponde, y haber llegado a su horario. No se pueden sobrescribir atenciones finalizadas.');
        hcInsertar($db,$cita,$v,$ahora->format('Y-m-d H:i:s'));
        $db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
