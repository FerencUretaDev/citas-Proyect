<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/servicios/PrioridadListaEsperaService.php';
function candidatoPruebaPrioridad(int $id,int $pid,bool $elegible=true,bool $misma=false,array $horas=[]):array{return ['id'=>$id,'paciente_id'=>$pid,'elegible'=>$elegible,'restricciones'=>['misma_especialidad'=>$misma,'horas_ocupadas'=>$horas]];}
$a=candidatoPruebaPrioridad(13,2);$b=candidatoPruebaPrioridad(14,3);$hora='08:00:00';
$casos=[
 'Cola vacía permite reserva normal'=>permiteTurnoPrioridadEspera([],1,$hora),
 'Primero FIFO permitido'=>permiteTurnoPrioridadEspera([$a,$b],2,$hora),
 'Segundo FIFO no puede adelantarse'=>!permiteTurnoPrioridadEspera([$a,$b],3,$hora),
 'Paciente sin inscripción no puede adelantarse'=>!permiteTurnoPrioridadEspera([$a,$b],1,$hora),
 'Al retirar primero avanza segundo'=>permiteTurnoPrioridadEspera([$b],3,$hora),
 'Perfil inactivo no bloquea'=>permiteTurnoPrioridadEspera([candidatoPruebaPrioridad(13,2,false),$b],3,$hora),
 'Misma especialidad excluye candidato'=>permiteTurnoPrioridadEspera([candidatoPruebaPrioridad(13,2,true,true),$b],3,$hora),
 'Cruce parcial excluye candidato'=>permiteTurnoPrioridadEspera([candidatoPruebaPrioridad(13,2,true,false,['07:45:00']),$b],3,$hora),
 'Turno contiguo conserva prioridad'=>permiteTurnoPrioridadEspera([candidatoPruebaPrioridad(13,2,true,false,['07:30:00']),$b],2,$hora),
 'Sin candidatos compatibles queda libre'=>permiteTurnoPrioridadEspera([candidatoPruebaPrioridad(13,2,true,true)],1,$hora),
 'Auditoría vinculación'=>auditoriaEvento(['id'=>6,'rol'=>0],'lista_espera','reservar','exitoso','solicitud_espera',14)===[6,0,'lista_espera','reservar','solicitud_espera',14,'exitoso'],
 'Auditoría desvinculación'=>auditoriaEvento(['id'=>1,'rol'=>2],'lista_espera','desvincular','exitoso','solicitud_espera',14)===[1,2,'lista_espera','desvincular','solicitud_espera',14,'exitoso'],
];
foreach($casos as $n=>$ok){if(!$ok){fwrite(STDERR,"FALLO: $n\n");exit(1);}echo "OK: $n\n";}echo 'Total: '.count($casos)." pruebas superadas.\n";
