<?php
session_start();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
if(!isset($_SESSION['id'],$_SESSION['rol'])||!in_array($_SESSION['rol'],[0,1,'0','1'],true)){http_response_code(403);exit('Acceso denegado.');}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/middlewares/SesionActiva.php';
verificarPerfilActivo($conexion);
require_once dirname(__DIR__).'/servicios/HistoriaClinicaService.php';
// Separar la identidad de la sesion del usuario de conexion a MySQL.
$rol=(int)$_SESSION['rol'];
$usuarioIdSesion=filter_var($_SESSION['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if($usuarioIdSesion===false){http_response_code(403);exit('Acceso denegado.');}
$perfil=hcPerfilActivo($conexion,$rol,$usuarioIdSesion);
if(!$perfil){http_response_code(403);exit('Acceso denegado.');}
$metodo=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($metodo,['GET','POST'],true)){http_response_code(405);exit('Método no permitido.');}
if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$raw=$metodo==='POST'?($_POST['cita_id']??null):($_GET['id']??null);
$id=$raw===null?null:filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if($id===false||($metodo==='POST'&&$id===null)){http_response_code(400);exit('Cita inválida.');}
$mensaje='';$error='';$borrador=[];
if($metodo==='POST'){
    $token=$_POST['csrf_token']??null;
    if($rol!==1||!is_string($token)||!hash_equals($_SESSION['csrf_token'],$token)){http_response_code(403);exit('Acceso denegado.');}
    if(!hcConsultar($conexion,$rol,$perfil,$id)){http_response_code(404);exit('Registro no disponible.');}
    try{hcFinalizar($conexion,$usuarioIdSesion,$perfil,$id,$_POST);header('Location: historia_clinica.php?id='.$id.'&guardado=1',true,303);exit;}
    catch(DomainException $e){$error=$e->getMessage();$borrador=$_POST;http_response_code(422);}
    catch(Throwable $e){error_log('Historia clínica: '.get_class($e).' / '.$e->getCode());$error='No se pudo finalizar la atención. Consulta nuevamente el estado antes de reintentar.';http_response_code(500);}
}
$filas=hcConsultar($conexion,$rol,$perfil,$id);
if($id!==null&&!$filas){http_response_code(404);exit('Registro no disponible.');}
$detalle=$id!==null?$filas[0]:null;
if($rol===1&&$detalle&&$detalle['historia_id']&&($_GET['guardado']??'')==='1')$mensaje='Atención registrada correctamente. Cita atendida.';
$paginaActiva='historia_clinica';
require dirname(__DIR__).'/vistas/historia_clinica.php';
