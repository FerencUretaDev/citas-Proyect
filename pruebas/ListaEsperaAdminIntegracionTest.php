<?php
// Solo XAMPP local. Utiliza las dos solicitudes de la prueba controlada y revierte cualquier cambio.
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Windows'){http_response_code(403);exit(1);}
if(!in_array(getenv('DB_HOST')?:'localhost',['localhost','127.0.0.1','::1'],true)||(getenv('DB_NAME')?:'sistema')!=='sistema'){fwrite(STDERR,"Entorno no local.\n");exit(1);}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/servicios/ListaEsperaAdminService.php';
function pruebaEsperaAdmin(bool $ok,string $nombre):void{if(!$ok)throw new RuntimeException($nombre);echo "OK: $nombre\n";}
$tx=false;
try {
    if($conexion->query('SELECT DATABASE()')->fetch_row()[0]!=='sistema')throw new RuntimeException('Base inesperada.');
    $hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
    $fecha='2026-12-07';if($hoy>$fecha)throw new RuntimeException('Fecha de prueba vencida.');
    $f=filtrosListaEsperaAdmin(['doctor'=>'CMP-TEST01','desde'=>$fecha,'hasta'=>$fecha]);
    $conexion->begin_transaction();$tx=true;
    $a=consultarListaEsperaAdminDAO($conexion,$f,$hoy,1,1);$b=consultarListaEsperaAdminDAO($conexion,$f,$hoy,2,1);
    pruebaEsperaAdmin($a['total']===2&&$a['paginas']===2&&count($a['filas'])===1&&count($b['filas'])===1,'Dos solicitudes y paginación real de una fila');
    pruebaEsperaAdmin((int)$a['filas'][0]['id']===13&&(int)$b['filas'][0]['id']===14,'Orden de las solicitudes 13 y 14');
    pruebaEsperaAdmin(consultarListaEsperaAdminDAO($conexion,array_replace($f,['estado'=>'cancelado']),$hoy,1)['total']===1,'Filtro cancelado');
    pruebaEsperaAdmin(consultarListaEsperaAdminDAO($conexion,array_replace($f,['estado'=>'en_espera']),$hoy,1)['total']===1,'Filtro en espera');
    pruebaEsperaAdmin(consultarListaEsperaAdminDAO($conexion,array_replace($f,['paciente'=>'ZZZZZ']),$hoy,1)['total']===0,'Búsqueda sin coincidencias');
    pruebaEsperaAdmin(!cancelarListaEsperaAdminDAO($conexion,13,$hoy),'Solicitud terminal no se modifica');
    pruebaEsperaAdmin(cancelarListaEsperaAdminDAO($conexion,14,$hoy),'Cancelación administrativa temporal');
    pruebaEsperaAdmin(consultarListaEsperaAdminDAO($conexion,array_replace($f,['estado'=>'en_espera']),$hoy,1)['total']===0,'Solicitud retirada de cola');
    $conexion->rollback();$tx=false;
    pruebaEsperaAdmin(consultarListaEsperaAdminDAO($conexion,array_replace($f,['estado'=>'en_espera']),$hoy,1)['total']===1,'Rollback: solicitud 14 sigue activa');
    echo "Integración administrativa: OK (sin cambios persistentes).\n";
} catch(Throwable $e){if($tx)$conexion->rollback();fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL);exit(1);}
