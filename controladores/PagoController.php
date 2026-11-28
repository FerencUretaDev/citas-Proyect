<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
date_default_timezone_set('America/Lima');
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/middlewares/SesionActiva.php';
verificarPerfilActivo($conexion);
require_once dirname(__DIR__).'/servicios/PagoService.php';
$rol=(int)($_SESSION['rol']??-1);$usuario=(int)($_SESSION['id']??0);
if(!$usuario||!in_array($rol,[0,2],true)){auditar($conexion,'pagos','acceder','denegado');http_response_code(403);exit('Acceso denegado.');}
header('Cache-Control: no-store');
$metodo=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($metodo,['GET','POST'],true)){http_response_code(405);exit('Método no permitido.');}
$citaId=filter_var($metodo==='POST'?($_POST['cita_id']??null):($_GET['id']??null),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$citaId){http_response_code(400);exit('Cita no válida.');}
$cita=citaPagoDAO($conexion,$citaId);
if(!$cita||!pagoAutorizado($conexion,$cita,$rol,$usuario)){auditar($conexion,'pagos','acceder','denegado','cita',$citaId);http_response_code(403);exit('Acceso denegado.');}
$pago=pagoCitaDAO($conexion,$citaId);
if(!$pago||$pago['ambiente']!=='test'){http_response_code(404);exit('No hay pago de prueba asociado.');}
if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$mensaje=$_SESSION['mensaje_pago']??'';unset($_SESSION['mensaje_pago']);
if($metodo==='POST'){
    $token=$_POST['csrf_token']??null;$accion=$_POST['accion']??null;
    if(!is_string($token)||!hash_equals($_SESSION['csrf_token'],$token)||!in_array($accion,['crear','verificar','cancelar'],true)){auditar($conexion,'pagos','acceder','denegado','cita',$citaId);http_response_code(403);exit('Solicitud no válida.');}
    try {[$mensaje,$url]=gestionarPago($conexion,$citaId,$rol,$usuario,$accion);if($url){header('Location: '.$url,true,303);exit;}}
    catch(DomainException $e){auditar($conexion,'pagos',match($accion){'crear'=>'crear_orden','cancelar'=>'cancelar',default=>'verificar'},'fallido','cita',$citaId);$mensaje=$e->getMessage();}
    catch(Throwable $e){auditar($conexion,'pagos',match($accion){'crear'=>'crear_orden','cancelar'=>'cancelar',default=>'verificar'},'fallido','cita',$citaId);error_log('Pago cita: '.get_class($e).' / '.$e->getCode());$mensaje='No se pudo completar la consulta. No repitas una reserva ni realices otro pago. Verifica esta misma operación o solicita revisión.';}
    $_SESSION['mensaje_pago']=$mensaje;header('Location: pago_cita.php?id='.$citaId,true,303);exit;
}
$paginaActiva=$rol===2?'citas':'citas';
require dirname(__DIR__).'/vistas/pago_cita.php';
