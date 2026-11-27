<?php
require_once __DIR__.'/CitaService.php';
require_once __DIR__.'/FiltroDirectorioService.php';
require_once __DIR__.'/AuditoriaService.php';

const FILTRO_PAGOS_CITAS = [
    'sin_pago'=>'Sin pago asociado', 'pendiente'=>'Pendiente', 'en_proceso'=>'En proceso',
    'aprobado'=>'Aprobado', 'rechazado'=>'Rechazado', 'cancelado'=>'Cancelado',
    'expirado'=>'Expirado', 'reembolsado'=>'Reembolsado', 'revision'=>'En revisión'
];
const FILTRO_MODALIDADES_CITAS = ['presencial'=>'Presencial','virtual'=>'Virtual'];

function filtrosCitasAdmin(array $entrada): array {
    $f=[];
    foreach (['paciente','doctor','especialidad','estado','modalidad','pago','desde','hasta'] as $k) {
        $v=$entrada[$k]??'';
        if (!is_string($v) || !preg_match('//u',$v) || strlen($v)>400 || preg_match('/[\x00-\x1F\x7F]/',$v)) {
            throw new DomainException('Filtro de citas no válido.');
        }
        $f[$k]=trim($v);
        if (preg_match('/\A.{0,100}\z/usD',$f[$k])!==1) throw new DomainException('Filtro demasiado largo.');
    }
    foreach (['estado'=>ETIQUETAS_ESTADO_CITA,'modalidad'=>FILTRO_MODALIDADES_CITAS,'pago'=>FILTRO_PAGOS_CITAS] as $k=>$opciones) {
        if ($f[$k]!=='' && !array_key_exists($f[$k],$opciones)) throw new DomainException('Opción de filtro no válida.');
    }
    $f['desde']=auditoriaFecha($f['desde']);
    $f['hasta']=auditoriaFecha($f['hasta']);
    if ($f['desde']!=='' && $f['hasta']!=='' && $f['desde']>$f['hasta']) throw new DomainException('El rango de fechas no es válido.');
    $v=$entrada['pagina']??'1';
    if (!is_string($v) && !is_int($v)) throw new DomainException('Página no válida.');
    $f['pagina']=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
    if ($f['pagina']===false) throw new DomainException('Página no válida.');
    return $f;
}

// Conserva el contexto de gestión de citas futuras de una ficha, incluso al limpiar filtros.
function fichaFiltroCitasAdmin(array $q): array {
    if (!array_key_exists('tipo',$q) && !array_key_exists('id',$q)) return ['',0];
    $tipo=$q['tipo']??null;
    $id=auditoriaId($q['id']??null);
    if (!in_array($tipo,['paciente','doctor'],true) || $id===null) throw new DomainException('Ficha no válida.');
    return [$tipo,$id];
}
