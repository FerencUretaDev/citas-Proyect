<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__).'/servicios/ListaEsperaAdminService.php';
require_once dirname(__DIR__).'/servicios/DisponibilidadListaEsperaService.php';
if (!auditoriaAdmin($_SESSION)) {
    if ($dbAud=auditoriaConexionOpcional()) auditar($dbAud,'lista_espera','acceder','denegado');
    http_response_code(403);exit('Acceso reservado al administrador.');
}
$metodo=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($metodo,['GET','POST'],true)) {header('Allow: GET, POST');http_response_code(405);exit;}
require_once dirname(__DIR__).'/config/Conexion.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
$f=filtrosListaEsperaAdmin([]);$resultado=['filas'=>[],'total'=>0,'pagina'=>1,'paginas'=>1];$error='';
$aviso=$_SESSION['aviso_lista_espera_admin']??null;unset($_SESSION['aviso_lista_espera_admin']);
try {
    $pagina=1;
    if ($metodo==='POST') {
        $accion=$_POST['accion']??null;
        if (!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['csrf_token'],$_POST['csrf_token'])||!in_array($accion,['buscar','cancelar'],true)) {
            auditar($conexion,'lista_espera','acceder','denegado');http_response_code(403);exit('Solicitud no válida.');
        }
        if ($accion==='cancelar') {
            $id=auditoriaId($_POST['solicitud_id']??null);
            $ok=$id!==null&&cancelarListaEsperaAdminDAO($conexion,$id,$hoy);
            auditar($conexion,'lista_espera','cancelar',$ok?'exitoso':'fallido',$ok?'solicitud_espera':null,$ok?$id:null);
            $_SESSION['aviso_lista_espera_admin']=['ok'=>$ok,'mensaje'=>$ok?'Solicitud cancelada. No se modificó ninguna cita.':'No se pudo cancelar la solicitud. Puede haber vencido o cambiado de estado.'];
            header('Location: lista_espera_admin.php',true,303);exit;
        }
        $f=filtrosListaEsperaAdmin($_POST);$pagina=paginaListaEsperaAdmin($_POST['pagina']??1);
    }
    $resultado=consultarListaEsperaAdminDAO($conexion,$f,$hoy,$pagina);
    $colas=[];$cacheDisponibilidad=[];$ahoraDisponibilidad=ahoraReprogramacion();
    foreach ($resultado['filas'] as &$s) {
        $s['estado']=estadoVisibleListaEsperaAdmin($s,$hoy);$s['posicion']=null;$s['disponibilidad']='—';
        if (in_array($s['estado'],['en_espera','notificado'],true)&&(int)$s['paciente_activo']===1&&(int)$s['doctor_activo']===1) {
            $clave=$s['doctor_id'].'/'.$s['fecha_solicitada'];
            if (!isset($colas[$clave])) $colas[$clave]=array_map('intval',array_column(colaListaEsperaDAO($conexion,(int)$s['doctor_id'],$s['fecha_solicitada']),'id'));
            $pos=array_search((int)$s['id'],$colas[$clave],true);if ($pos!==false) $s['posicion']=$pos+1;
            $s['disponibilidad']=disponibilidadSolicitudListaEspera($conexion,$s,$ahoraDisponibilidad,$cacheDisponibilidad);
        }
    } unset($s);
    auditar($conexion,'lista_espera','consultar');
} catch (DomainException $e) {http_response_code(400);$error=$e->getMessage();auditar($conexion,'lista_espera','consultar','fallido');}
catch (Throwable $e) {http_response_code(500);$resultado=['filas'=>[],'total'=>0,'pagina'=>1,'paginas'=>1];$error='No se pudo completar la operación. Intenta nuevamente.';error_log('Lista espera admin: '.get_class($e).' / '.$e->getCode());}
require dirname(__DIR__).'/vistas/lista_espera_admin.php';
