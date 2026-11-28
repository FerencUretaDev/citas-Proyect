<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
if(!isset($_SESSION['id'],$_SESSION['rol'])) {
    if($dbAud=auditoriaConexionOpcional()) auditar($dbAud,'citas','acceder','denegado');
    header('Location: index.php'); exit;
}
$usuarioId=auditoriaId($_SESSION['id']);
if($usuarioId===null || auditoriaRol($_SESSION['rol'])!==0) {
    if($dbAud=auditoriaConexionOpcional()) auditar($dbAud,'citas','acceder','denegado');
    http_response_code(403); exit('Acceso reservado al paciente.');
}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/middlewares/SesionActiva.php';
require_once dirname(__DIR__).'/servicios/HistorialCitasService.php';
verificarPerfilActivo($conexion);
$rol=0; $nombreUsuario=$_SESSION['usuario']??'Paciente';
if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$metodo=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($metodo,['GET','POST'],true)) {header('Allow: GET, POST');http_response_code(405);exit('Método no permitido.');}
if($metodo==='POST' && (!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['csrf_token'],$_POST['csrf_token']))) {
    auditar($conexion,'citas','consultar','denegado'); http_response_code(403); exit('Solicitud no válida.');
}
try {
    // Mantener enlaces anteriores por fecha/estado; nombres de búsqueda solo por POST.
    $entrada=$metodo==='POST'?$_POST:array_intersect_key($_GET,array_flip(['fecha_inicio','fecha_fin','desde','hasta','especialidad','estado','modalidad','pago','pagina']));
    $filtros=filtrosHistorialAvanzado($entrada);
    $ahora=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
    $resultado=buscarHistorialPaciente($conexion,$usuarioId,$ahora,$filtros);
    $citas=$resultado['citas']; $especialidades=$resultado['especialidades']; $resumen=$resultado['resumen'];
    auditar($conexion,'citas','consultar');
} catch(DomainException|InvalidArgumentException $e) {
    auditar($conexion,'citas','consultar','fallido'); http_response_code(400); exit('Filtros no válidos. Vuelve al historial e inténtalo nuevamente.');
} catch(Throwable $e) {
    auditar($conexion,'citas','consultar','fallido'); error_log('Consulta historial paciente: '.get_class($e).' / '.$e->getCode()); http_response_code(500); exit('No se pudo consultar el historial de citas.');
}
require dirname(__DIR__).'/vistas/historial_citas.php';
