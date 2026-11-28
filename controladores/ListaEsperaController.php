<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
$usuarioId=auditoriaId($_SESSION['id']??null);
if ($usuarioId===null || auditoriaRol($_SESSION['rol']??null)!==0) {
    if ($dbAud=auditoriaConexionOpcional()) auditar($dbAud,'lista_espera','acceder','denegado');
    if ($usuarioId===null) {header('Location: index.php');exit;}
    http_response_code(403);exit('Acceso reservado al paciente.');
}
require_once dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/middlewares/SesionActiva.php';
require_once dirname(__DIR__).'/servicios/DisponibilidadListaEsperaService.php';
verificarPerfilActivo($conexion);
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$metodo=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($metodo,['GET','POST'],true)) {header('Allow: GET, POST');http_response_code(405);exit;}
try {
    $paciente=pacienteListaEsperaDAO($conexion,$usuarioId);
    if (!$paciente) {auditar($conexion,'lista_espera','acceder','denegado');http_response_code(403);exit('Perfil no disponible.');}
    $pacienteId=(int)$paciente['id'];
    if ($metodo==='POST') {
        $accion=$_POST['accion']??null;
        if (!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['csrf_token'],$_POST['csrf_token'])||!in_array($accion,['inscribir','cancelar'],true)) {
            auditar($conexion,'lista_espera','acceder','denegado');http_response_code(403);exit('Solicitud no válida.');
        }
        if ($accion==='inscribir') {
            $datos=datosInscripcionListaEspera($_POST,ahoraReprogramacion());
            $r=$datos?inscribirListaEsperaServicio($conexion,$usuarioId,...$datos):['ok'=>false,'codigo'=>'datos_invalidos'];
            auditar($conexion,'lista_espera','inscribir',$r['ok']?'exitoso':'fallido',$r['ok']?'solicitud_espera':null,$r['ok']?(int)$r['id']:null);
        } else {
            $id=auditoriaId($_POST['solicitud_id']??null);
            // El DAO condiciona la modificación a la propiedad y a que siga activa.
            $ok=$id!==null && cancelarListaEsperaDAO($conexion,$id,$pacienteId);
            $r=['ok'=>$ok,'codigo'=>$ok?'cancelada':'no_cancelada'];
            auditar($conexion,'lista_espera','cancelar',$ok?'exitoso':'denegado',$ok?'solicitud_espera':null,$ok?$id:null);
        }
        $_SESSION['aviso_lista_espera']=['ok'=>$r['ok'],'mensaje'=>mensajeListaEspera($r['codigo'])];
        header('Location: lista_espera.php',true,303);exit;
    }
    $solicitudes=solicitudesPacienteListaEsperaDAO($conexion,$pacienteId);
    $hoy=ahoraReprogramacion()->format('Y-m-d');
    $colas=[];$cacheDisponibilidad=[];$ahoraDisponibilidad=ahoraReprogramacion();
    foreach ($solicitudes as &$solicitud) {
        $solicitud['posicion']=null;
        $solicitud['paciente_id']=$pacienteId;
        $solicitud['disponibilidad']='—';
        if (in_array($solicitud['estado'],['en_espera','notificado'],true)) {
            if ($solicitud['fecha_solicitada']<$hoy) {$solicitud['estado']='vencido';continue;}
            $clave=$solicitud['doctor_id'].'/'.$solicitud['fecha_solicitada'];
            if (!isset($colas[$clave])) $colas[$clave]=array_map('intval',array_column(colaListaEsperaDAO($conexion,(int)$solicitud['doctor_id'],$solicitud['fecha_solicitada']),'id'));
            $pos=array_search((int)$solicitud['id'],$colas[$clave],true);
            if ($pos!==false) $solicitud['posicion']=$pos+1;
            $solicitud['disponibilidad']=disponibilidadSolicitudListaEspera($conexion,$solicitud,$ahoraDisponibilidad,$cacheDisponibilidad);
        }
    }
    unset($solicitud);
    $aviso=$_SESSION['aviso_lista_espera']??null;
    unset($_SESSION['aviso_lista_espera']);
    auditar($conexion,'lista_espera','consultar');
} catch (Throwable $e) {
    error_log('Lista de espera paciente: '.get_class($e).' / '.$e->getCode());
    http_response_code(500);exit('No se pudo completar la operación. Intenta nuevamente.');
}
require dirname(__DIR__).'/vistas/lista_espera.php';
