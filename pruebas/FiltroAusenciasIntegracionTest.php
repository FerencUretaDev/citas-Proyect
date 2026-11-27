<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/repositorios/FiltroAusenciasDAO.php';
function comprobarIntegracionAusencias(bool $ok,string $msg):void {if(!$ok)throw new RuntimeException($msg);}
$hoy=ahoraAusencia()->format('Y-m-d');
$base=listarAusenciasDAO($conexion);
$ids=static fn(array $filas):array=>array_map('intval',array_column($filas,'id'));
$probar=static function(array $f,array $esperado)use($conexion,$hoy,$ids):void{
    $r=buscarAusenciasFiltradas($conexion,filtrosAusencias($f),$hoy);
    comprobarIntegracionAusencias($r['total']===count($esperado)&&$ids($r['filas'])===$ids(array_slice($esperado,0,20)),'Conteo o coincidencias incorrectos');
};
$probar([],$base);
$esperado=['vigente'=>0,'programada'=>0,'finalizada'=>0,'cancelada'=>0,'citas_afectadas'=>0];
foreach($base as $a){$esperado[estadoAusencia($a,$hoy)]++;$esperado['citas_afectadas']+=(int)$a['citas_afectadas'];}
comprobarIntegracionAusencias(resumenAusenciasFiltradas($conexion,$hoy)===$esperado,'Indicadores generales incorrectos');
foreach(ESTADOS_FILTRO_AUSENCIAS as $k=>$v)$probar(['estado'=>$k],array_values(array_filter($base,fn($a)=>estadoAusencia($a,$hoy)===$k)));
foreach(MOTIVOS_AUSENCIA as $k=>$v)$probar(['motivo'=>$k],array_values(array_filter($base,fn($a)=>$a['motivo']===$k)));
$probar(['doctor'=>'zz_%!sin_coincidencia_27112026'],[]);
if($base){
    $a=$base[0]; $detalle=detalleAusenciaFiltrada($conexion,(int)$a['id']);
    comprobarIntegracionAusencias($detalle!==null&&(int)$detalle['id']===(int)$a['id']&&(int)$detalle['citas_afectadas']===(int)$a['citas_afectadas'],'Detalle incorrecto');
    $fecha=$a['fecha_fin'];$esp=$a['especialidad'];
    $probar(['desde'=>$fecha,'hasta'=>$fecha,'especialidad'=>$esp],array_values(array_filter($base,fn($b)=>$b['fecha_inicio']<=$fecha&&$b['fecha_fin']>=$fecha&&$b['especialidad']===$esp)));
    $doctor=consultarDirectorio($conexion,'SELECT dni,cmp,nombres,apellidos FROM doctores WHERE id=?','i',[(int)$a['doctor_id']])[0];
    $r=buscarAusenciasFiltradas($conexion,filtrosAusencias(['doctor'=>$doctor['cmp']?:$doctor['nombres'],'desde'=>$fecha,'hasta'=>$fecha,'estado'=>estadoAusencia($a,$hoy)]),$hoy);
    comprobarIntegracionAusencias($r['total']>0,'Búsqueda de doctor sin resultado esperado');
} else echo "Sin ausencias: combinación y detalle con datos reales pendientes.\n";
if(count($base)>1){
    $a=buscarAusenciasFiltradas($conexion,filtrosAusencias([]),$hoy,1);
    $b=buscarAusenciasFiltradas($conexion,filtrosAusencias(['pagina'=>'2']),$hoy,1);
    comprobarIntegracionAusencias($a['pagina']===1&&$b['pagina']===2&&$ids($a['filas'])===$ids(array_slice($base,0,1))&&$ids($b['filas'])===$ids(array_slice($base,1,1)),'Paginación incorrecta');
} else echo "Paginación real pendiente: menos de dos ausencias.\n";
echo "Pruebas de integración de filtros de ausencias: OK (solo lectura)\n";
