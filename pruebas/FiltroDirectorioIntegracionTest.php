<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/config/Conexion.php';
require_once dirname(__DIR__).'/repositorios/DirectorioDAO.php';
function comprobarDirectorio(bool $ok, string $mensaje): void { if (!$ok) throw new RuntimeException($mensaje); }
// Solo SELECT; no crea fichas ni modifica datos. Ejecutar en XAMPP.
foreach ([true,false] as $pacientes) {
    $tabla = $pacientes ? 'pacientes' : 'doctores';
    foreach (['0','1','todos'] as $estado) {
        $r = buscarDirectorio($conexion,$pacientes,filtrosDirectorio(['estado'=>$estado],$pacientes));
        $where = $estado==='todos' ? '' : ' WHERE activo='.(int)$estado;
        $total = (int)$conexion->query("SELECT COUNT(*) FROM $tabla$where")->fetch_row()[0];
        comprobarDirectorio($r['total']===$total && count($r['filas'])===min($total,20), 'Conteo o tamaño incorrecto');
        foreach ($r['filas'] as $fila) comprobarDirectorio($estado==='todos' || (int)$fila['activo']===(int)$estado,'Estado incorrecto');
        if ($total>20) {
            $segunda = buscarDirectorio($conexion,$pacientes,filtrosDirectorio(['estado'=>$estado,'pagina'=>'2'],$pacientes));
            comprobarDirectorio($segunda['pagina']===2 && !array_intersect(array_column($r['filas'],'id'),array_column($segunda['filas'],'id')), 'Páginas duplicadas');
        }
    }
    $r = buscarDirectorio($conexion,$pacientes,filtrosDirectorio(['estado'=>'todos','buscar'=>"zz_'%!!_sin_coincidencia_27112026"],$pacientes));
    comprobarDirectorio($r['total']===0,'Búsqueda literal inesperada');
}
foreach (['M','F'] as $sexo) {
    $r=buscarDirectorio($conexion,true,filtrosDirectorio(['estado'=>'todos','sexo'=>$sexo],true));
    foreach($r['filas'] as $fila) comprobarDirectorio($fila['sexo']===$sexo,'Sexo incorrecto');
}
foreach(especialidadesDirectorio($conexion) as $especialidad) {
    $r=buscarDirectorio($conexion,false,filtrosDirectorio(['estado'=>'todos','especialidad'=>$especialidad],false));
    comprobarDirectorio($r['total']>0,'Especialidad sin resultados');
}
// Si hay un doctor con DNI, comprueba la recuperación por el nuevo campo.
$doctorDni = $conexion->query("SELECT id, dni FROM doctores WHERE dni IS NOT NULL LIMIT 1")->fetch_assoc();
if ($doctorDni) {
    comprobarDirectorio(dniDoctorValido($doctorDni['dni']), 'DNI almacenado inválido');
    $r = buscarDirectorio($conexion, false, filtrosDirectorio(['estado'=>'todos','buscar'=>$doctorDni['dni']],false));
    comprobarDirectorio(in_array((int)$doctorDni['id'], array_map('intval',array_column($r['filas'],'id')),true),'No se encontró el doctor por DNI en la primera página');
} else { echo "Sin doctores con DNI: búsqueda real por DNI pendiente de datos de prueba.\n"; }
echo "Pruebas de integración de filtros de directorio: OK (solo lectura)\n";
