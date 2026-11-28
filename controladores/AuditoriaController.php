<?php
require_once dirname(__DIR__).'/middlewares/SeguridadSesion.php';
iniciarSesionSegura();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__).'/servicios/AuditoriaService.php';
if (!auditoriaAdmin($_SESSION)) {
    if ($dbAud=auditoriaConexionOpcional()) auditar($dbAud,'auditoria','acceder','denegado');
    http_response_code(403);exit('Acceso denegado.');
}
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {header('Allow: GET');http_response_code(405);exit('Método no permitido.');}
require dirname(__DIR__).'/config/Conexion.php';
$_SESSION['csrf_token']??=bin2hex(random_bytes(32));
$paginaActiva='auditoria';$rol=2;$error='';$filas=[];$resumen=['total'=>0,'exitosos'=>0,'fallidos'=>0,'denegados'=>0];$pagina=1;$limite=50;$f=auditoriaFiltros([]);
try {
    $f=auditoriaFiltros($_GET);
    $pagina=filter_var($_GET['pagina']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
    if($pagina===false)throw new DomainException('Página inválida.');
    if(($_GET['exportar']??'')==='csv'){
        $datos=auditoriaConsultarDAO($conexion,$f,5001);
        if(count($datos)>5000)throw new DomainException('El reporte supera 5000 eventos. Reduce el rango de fechas.');
        auditar($conexion,'auditoria','exportar_csv');
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="auditoria_'.date('Ymd_His').'.csv"');header('X-Content-Type-Options: nosniff');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['ID','Fecha','Usuario ID','Rol','Modulo','Accion','Entidad','Entidad ID','Resultado'],';','"','');
        foreach($datos as $r)fputcsv($out,array_map('auditoriaCsvCelda',array_values($r)),';','"','');
        fclose($out);exit;
    }
    $resumen=auditoriaResumenDAO($conexion,$f);
    $pagina=min($pagina,max(1,(int)ceil($resumen['total']/$limite)));
    $filas=auditoriaConsultarDAO($conexion,$f,$limite,($pagina-1)*$limite);
    auditar($conexion,'auditoria','consultar');
} catch(DomainException $e){http_response_code(400);$error=$e->getMessage();$pagina=1;}
catch(Throwable $e){http_response_code(500);error_log('Consulta auditoria: '.$e->getCode());$error='No se pudo consultar la auditoría.';}
require dirname(__DIR__).'/vistas/auditoria.php';
