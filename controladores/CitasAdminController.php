<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
require_once dirname(__DIR__).'/repositorios/FiltroCitasAdminDAO.php';
if (!auditoriaAdmin($_SESSION)) {
    if ($dbAud=auditoriaConexionOpcional()) auditar($dbAud,'citas','acceder','denegado');
    header('Location: index.php'); exit;
}
require dirname(__DIR__).'/config/Conexion.php';
$rol=2; $paginaActiva='citas';
$_SESSION['csrf_token']??=bin2hex(random_bytes(32));
$etiquetasEstado=ETIQUETAS_ESTADO_CITA;
$transiciones=TRANSICIONES_ESTADO_CITA;
$metodo=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($metodo,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit('Método no permitido.'); }
if ($metodo==='POST' && (!is_string($_POST['csrf_token']??null) || !hash_equals($_SESSION['csrf_token'],$_POST['csrf_token']))) {
    auditar($conexion,'citas','consultar','denegado'); http_response_code(403); exit('Solicitud no válida.');
}
try {
    [$tipoFicha,$idFicha]=fichaFiltroCitasAdmin($_GET);
    $filtrarFicha=$idFicha>0;
    // GET mantiene los enlaces existentes por estado/ficha; textos personales solo por POST.
    $entrada=$metodo==='POST'?$_POST:array_intersect_key($_GET,array_flip(['estado','especialidad','modalidad','pago','desde','hasta','pagina']));
    $f=filtrosCitasAdmin($entrada);
    $especialidades=especialidadesDirectorio($conexion);
    if ($f['especialidad']!=='' && !in_array($f['especialidad'],$especialidades,true)) throw new DomainException('Especialidad no válida.');
    $ahora=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
    $listado=buscarCitasAdmin($conexion,$f,$tipoFicha,$idFicha,$ahora);
    $estadoSeleccionado=$f['estado']; $totalVisible=$listado['total'];
    $urlFiltros='citas_admin'.($filtrarFicha?'?'.http_build_query(['tipo'=>$tipoFicha,'id'=>$idFicha]):'');
    auditar($conexion,'citas','consultar');
} catch (DomainException $e) {
    auditar($conexion,'citas','consultar','fallido'); http_response_code(400); exit('Filtros no válidos. Vuelve al listado de citas e inténtalo nuevamente.');
} catch (Throwable $e) {
    auditar($conexion,'citas','consultar','fallido'); error_log('Consulta citas administrativas: '.get_class($e).' / '.$e->getCode()); http_response_code(500); exit('No se pudo consultar las citas.');
}
require dirname(__DIR__).'/vistas/citas_admin.php';
