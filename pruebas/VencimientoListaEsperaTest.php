<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require_once dirname(__DIR__).'/servicios/VencimientoListaEsperaService.php';
require_once dirname(__DIR__).'/servicios/ListaEsperaAdminService.php';
$antes=new DateTimeImmutable('2026-12-08 04:59:59',new DateTimeZone('UTC'));
$despues=$antes->modify('+1 second');
$activa=['estado'=>'en_espera','fecha_solicitada'=>'2026-12-07'];
$casos=[
 'Corte antes de medianoche Lima'=>fechaCorteListaEspera($antes)==='2026-12-07',
 'Corte al cambiar de día Lima'=>fechaCorteListaEspera($despues)==='2026-12-08',
 'Fecha actual no vence'=>estadoVisibleListaEsperaAdmin($activa,fechaCorteListaEspera($antes))==='en_espera',
 'Fecha pasada se muestra vencida'=>estadoVisibleListaEsperaAdmin($activa,fechaCorteListaEspera($despues))==='vencido',
 'Historial reservado se conserva'=>estadoVisibleListaEsperaAdmin(array_replace($activa,['estado'=>'reservado']),fechaCorteListaEspera($despues))==='reservado',
 'Historial cancelado se conserva'=>estadoVisibleListaEsperaAdmin(array_replace($activa,['estado'=>'cancelado']),fechaCorteListaEspera($despues))==='cancelado',
 'Auditoría del sistema sin identidad ficticia'=>auditoriaEvento([],'lista_espera','vencer','exitoso','solicitud_espera',14)===[null,null,'lista_espera','vencer','solicitud_espera',14,'exitoso'],
 'Filtro de auditoría permitido'=>auditoriaFiltros(['modulo'=>'lista_espera','accion'=>'vencer'])['accion']==='vencer',
];
foreach($casos as $nombre=>$ok){if(!$ok){fwrite(STDERR,"FALLO: $nombre\n");exit(1);}echo "OK: $nombre\n";}
echo 'Total: '.count($casos)." pruebas superadas.\n";
