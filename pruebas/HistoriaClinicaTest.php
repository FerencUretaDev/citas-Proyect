<?php
require_once dirname(__DIR__).'/servicios/HistoriaClinicaService.php';
function comprobarHC(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$ahora=new DateTimeImmutable('2026-11-26 15:00:00',new DateTimeZone('America/Lima'));
$c=['estado'=>'confirmada','fecha'=>'2026-11-26','hora'=>'14:00:00','pago_estado'=>'aprobado','historia_id'=>null];
comprobarHC(hcPuedeRegistrar($c,$ahora),'Cita válida');
foreach([['estado'=>'pendiente'],['estado'=>'cancelada'],['estado'=>'atendida'],['pago_estado'=>'pendiente'],['pago_estado'=>'reembolsado'],['historia_id'=>1],['fecha'=>'2026-11-27'],['hora'=>'16:00:00']] as $cambio)comprobarHC(!hcPuedeRegistrar(array_replace($c,$cambio),$ahora),'Cita inválida');
comprobarHC(hcPuedeRegistrar(array_replace($c,['pago_estado'=>null]),$ahora),'Cita antigua sin pago');
$v=hcValidar(['evaluacion'=>'Evaluación ficticia','diagnostico'=>'Diagnóstico ficticio'],$ahora);
comprobarHC($v['antecedentes']===null&&$v['proximo_control']===null,'Opcionales');
foreach([['evaluacion'=>''],['diagnostico'=>[]],['proximo_control'=>'2026-02-30'],['proximo_control'=>'2026-11-25'],['tratamiento'=>str_repeat('a',3001)]] as $cambio){$fallo=false;try{hcValidar(array_replace(['evaluacion'=>'A','diagnostico'=>'B'],$cambio),$ahora);}catch(DomainException $e){$fallo=true;}comprobarHC($fallo,'Validación inválida');}
echo "Pruebas aisladas de historia clínica: OK\n";
