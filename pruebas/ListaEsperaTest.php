<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/servicios/ListaEsperaService.php';
$ahora=new DateTimeImmutable('2026-11-27 10:00:00',new DateTimeZone('America/Lima'));
$fecha='2026-11-28';
$d=['hora_inicio'=>'08:00:00','hora_fin'=>'10:00:00','refrigerio_inicio'=>'09:00:00','refrigerio_fin'=>'09:30:00'];
$r=['misma_especialidad'=>false,'horas_ocupadas'=>[]];
$llena=['08:00:00','08:30:00','09:30:00'];
$casos=[
 'Fecha real'=>fechaValidaListaEspera($fecha,$ahora),
 'Fecha imposible'=>!fechaValidaListaEspera('2026-02-30',$ahora),
 'Fecha pasada'=>!fechaValidaListaEspera('2026-11-26',$ahora),
 'Agenda llena'=>situacionListaEspera($d,$fecha,$llena,$r,false,$ahora)==='agenda_llena',
 'Cupo disponible'=>situacionListaEspera($d,$fecha,['08:00:00','08:30:00'],$r,false,$ahora)==='hay_cupos',
 'Ausencia'=>situacionListaEspera($d,$fecha,$llena,$r,true,$ahora)==='ausencia',
 'Jornada terminada'=>situacionListaEspera($d,'2026-11-27',$llena,$r,false,$ahora)==='sin_jornada',
 'Misma especialidad'=>situacionListaEspera($d,$fecha,$llena,array_replace($r,['misma_especialidad'=>true]),false,$ahora)==='misma_especialidad',
 'Conflicto personal total'=>situacionListaEspera($d,$fecha,$llena,array_replace($r,['horas_ocupadas'=>$llena]),false,$ahora)==='conflicto_paciente',
 'Conflicto personal no equivale a agenda llena'=>situacionListaEspera($d,$fecha,['08:00:00','08:30:00'],array_replace($r,['horas_ocupadas'=>['09:30:00']]),false,$ahora)==='hay_cupos',
 'Cruce parcial de citas'=>situacionListaEspera($d,$fecha,['08:15:00','09:30:00'],$r,false,$ahora)==='agenda_llena',
 'Refrigerio excluido'=>turnosValidosReprogramacion($d,$fecha,$ahora)===$llena,
];
foreach($casos as $nombre=>$ok){if(!$ok){fwrite(STDERR,"FALLO: $nombre\n");exit(1);}echo "OK: $nombre\n";}
echo 'Total: '.count($casos)." pruebas superadas.\n";
