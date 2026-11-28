<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require_once dirname(__DIR__).'/servicios/DisponibilidadListaEsperaService.php';
$ahora=new DateTimeImmutable('2026-11-27 10:00:00',new DateTimeZone('America/Lima'));
$f='2026-12-07';$d=['hora_inicio'=>'08:00:00','hora_fin'=>'09:00:00','refrigerio_inicio'=>null,'refrigerio_fin'=>null];
$r=['misma_especialidad'=>false,'horas_ocupadas'=>[]];
$casos=[
 'Agenda llena'=>turnosLibresListaEspera($d,$f,['08:00:00','08:30:00'],false,$ahora)===[],
 'Turno liberado'=>turnosLibresListaEspera($d,$f,['08:30:00'],false,$ahora)===['08:00:00'],
 'Agenda vacía'=>turnosLibresListaEspera($d,$f,[],false,$ahora)===['08:00:00','08:30:00'],
 'Cruce parcial'=>turnosLibresListaEspera($d,$f,['08:15:00'],false,$ahora)===[],
 'Ausencia'=>turnosLibresListaEspera($d,$f,[],true,$ahora)===[],
 'Fecha pasada'=>turnosLibresListaEspera($d,'2026-11-26',[],false,$ahora)===[],
 'Fecha imposible'=>turnosLibresListaEspera($d,'2026-02-30',[],false,$ahora)===[],
 'Jornada concluida'=>turnosLibresListaEspera($d,'2026-11-27',[],false,$ahora)===[],
 'Refrigerio'=>turnosLibresListaEspera(array_replace($d,['refrigerio_inicio'=>'08:00:00','refrigerio_fin'=>'08:30:00']),$f,[],false,$ahora)===['08:30:00'],
 'Paciente sin conflicto'=>turnosCompatiblesListaEspera(['08:00:00'],$r)===['08:00:00'],
 'Paciente con cruce'=>turnosCompatiblesListaEspera(['08:00:00'],array_replace($r,['horas_ocupadas'=>['08:15:00']]))===[],
 'Paciente con misma especialidad'=>turnosCompatiblesListaEspera(['08:00:00'],array_replace($r,['misma_especialidad'=>true]))===[],
];
foreach($casos as $n=>$ok){if(!$ok){fwrite(STDERR,"FALLO: $n\n");exit(1);}echo "OK: $n\n";}echo 'Total: '.count($casos)." pruebas superadas.\n";
