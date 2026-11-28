<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/servicios/ListaEsperaService.php';
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
$ahora=new DateTimeImmutable('2026-11-27 10:00:00',new DateTimeZone('America/Lima'));
$b=['doctor_id'=>'1','fecha'=>'2026-11-28','modalidad'=>'presencial'];
$casos=[
 'Datos válidos'=>datosInscripcionListaEspera($b,$ahora)===[1,'2026-11-28','presencial'],
 'No acepta ID como arreglo'=>datosInscripcionListaEspera(array_replace($b,['doctor_id'=>[1]]),$ahora)===null,
 'No acepta ID negativo'=>datosInscripcionListaEspera(array_replace($b,['doctor_id'=>'-1']),$ahora)===null,
 'No acepta ID fuera de rango'=>datosInscripcionListaEspera(array_replace($b,['doctor_id'=>'2147483648']),$ahora)===null,
 'No acepta modalidad inválida'=>datosInscripcionListaEspera(array_replace($b,['modalidad'=>'otra']),$ahora)===null,
 'No acepta fecha pasada'=>datosInscripcionListaEspera(array_replace($b,['fecha'=>'2026-11-26']),$ahora)===null,
 'No acepta fecha imposible'=>datosInscripcionListaEspera(array_replace($b,['fecha'=>'2026-11-31']),$ahora)===null,
 'Auditoría inscripción'=>auditoriaEvento(['id'=>3,'rol'=>0],'lista_espera','inscribir','exitoso','solicitud_espera',5)===[3,0,'lista_espera','inscribir','solicitud_espera',5,'exitoso'],
 'Auditoría cancelación ajena'=>auditoriaEvento(['id'=>3,'rol'=>0],'lista_espera','cancelar','denegado')===[3,0,'lista_espera','cancelar',null,null,'denegado'],
];
$_SESSION=['id'=>3,'rol'=>0,'csrf_token'=>'test'];$aviso=null;
$solicitudes=[['id'=>1,'nombres'=>'<script>alert(1)</script>','apellidos'=>'Prueba','especialidad'=>'Cardiología','fecha_solicitada'=>'2026-11-28','modalidad'=>'presencial','estado'=>'en_espera','posicion'=>1]];
ob_start();require dirname(__DIR__).'/vistas/lista_espera.php';$html=ob_get_clean();
$casos['Vista escapa nombres']=!str_contains($html,'<script>alert(1)</script>')&&str_contains($html,'&lt;script&gt;');
$casos['Formulario de retiro POST con CSRF']=str_contains($html,'name="csrf_token" value="test"')&&str_contains($html,'name="solicitud_id" value="1"')&&str_contains($html,'method="POST"');
$solicitudes[0]['estado']='cancelado';$solicitudes[0]['posicion']=null;
ob_start();require dirname(__DIR__).'/vistas/lista_espera.php';$html=ob_get_clean();
$casos['Solicitud terminal sin botón de retiro']=!str_contains($html,'name="solicitud_id"');
foreach($casos as $nombre=>$ok){if(!$ok){fwrite(STDERR,"FALLO: $nombre\n");exit(1);}echo "OK: $nombre\n";}
echo 'Total: '.count($casos)." pruebas superadas.\n";
