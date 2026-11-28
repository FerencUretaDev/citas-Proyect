<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/servicios/ListaEsperaAdminService.php';
function rechazaFiltroEsperaAdmin(callable $f):bool {try{$f();}catch(DomainException $e){return true;}return false;}
$hoy='2026-11-27';$f=filtrosListaEsperaAdmin(['paciente'=>'  Prueba  ','doctor'=>'CMP-TEST01','estado'=>'en_espera','desde'=>'2026-12-01']);
[$sql,$params]=condicionesListaEsperaAdmin($f,$hoy);
$casos=[
'Administrador autorizado'=>auditoriaAdmin(['id'=>1,'rol'=>2]),
'Paciente y doctor rechazados'=>!auditoriaAdmin(['id'=>2,'rol'=>0])&&!auditoriaAdmin(['id'=>3,'rol'=>1]),
'Filtros normalizados'=>$f['paciente']==='Prueba',
'Parámetros de búsqueda'=>substr_count($sql,'?')===count($params)&&$params===['Prueba','Prueba','CMP-TEST01','CMP-TEST01','CMP-TEST01','en_espera',$hoy,'2026-12-01']&&!str_contains($sql,'CMP-TEST01'),
'Estado inválido'=>rechazaFiltroEsperaAdmin(fn()=>filtrosListaEsperaAdmin(['estado'=>'reservado OR 1=1'])),
'Arreglo rechazado'=>rechazaFiltroEsperaAdmin(fn()=>filtrosListaEsperaAdmin(['doctor'=>[1]])),
'Fecha imposible'=>rechazaFiltroEsperaAdmin(fn()=>filtrosListaEsperaAdmin(['desde'=>'2026-02-30'])),
'Rango invertido'=>rechazaFiltroEsperaAdmin(fn()=>filtrosListaEsperaAdmin(['desde'=>'2026-12-03','hasta'=>'2026-12-01'])),
'Página válida'=>paginaListaEsperaAdmin('2')===2,
'Página inválida'=>rechazaFiltroEsperaAdmin(fn()=>paginaListaEsperaAdmin(['2'])),
'Fecha pasada activa se muestra vencida'=>estadoVisibleListaEsperaAdmin(['estado'=>'en_espera','fecha_solicitada'=>'2026-11-26'],$hoy)==='vencido',
'Fecha pasada cancelada conserva estado'=>estadoVisibleListaEsperaAdmin(['estado'=>'cancelado','fecha_solicitada'=>'2026-11-26'],$hoy)==='cancelado',
'Auditoría de cancelación administrativa'=>auditoriaEvento(['id'=>1,'rol'=>2],'lista_espera','cancelar','exitoso','solicitud_espera',14)===[1,2,'lista_espera','cancelar','solicitud_espera',14,'exitoso'],
];
$_SESSION=['id'=>1,'rol'=>2,'csrf_token'=>'test'];$error='';$aviso=null;
$resultado=['total'=>2,'pagina'=>1,'paginas'=>2,'filas'=>[['id'=>14,'paciente'=>'<script>alert(1)</script>','doctor'=>'Prueba','especialidad'=>'Medicina General','fecha_solicitada'=>'2026-12-07','modalidad'=>'presencial','estado'=>'en_espera','posicion'=>1,'fecha_registro'=>'2026-11-27 19:25:35']]];
ob_start();require dirname(__DIR__).'/vistas/lista_espera_admin.php';$html=ob_get_clean();
$casos['Vista escapa nombres']=!str_contains($html,'<script>alert(1)</script>')&&str_contains($html,'&lt;script&gt;');
$casos['Formulario de cancelación POST y CSRF']=str_contains($html,'method="POST"')&&str_contains($html,'name="solicitud_id" value="14"')&&str_contains($html,'name="csrf_token" value="test"');
$casos['Paginación conserva formulario']=str_contains($html,'form="filtros-espera" name="pagina" value="2"');
$resultado['filas'][0]['estado']='cancelado';$resultado['filas'][0]['posicion']=null;
ob_start();require dirname(__DIR__).'/vistas/lista_espera_admin.php';$html=ob_get_clean();
$casos['Solicitud terminal sin botón']=!str_contains($html,'name="solicitud_id"');
foreach($casos as $n=>$ok){if(!$ok){fwrite(STDERR,"FALLO: $n\n");exit(1);}echo "OK: $n\n";}echo 'Total: '.count($casos)." pruebas superadas.\n";
