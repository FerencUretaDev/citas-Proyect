<?php
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$roles=['0'=>'Paciente','1'=>'Doctor','2'=>'Administrador'];$catalogo=auditoriaCatalogo();$acciones=array_unique(array_merge(...array_values($catalogo)));sort($acciones);
$enlace=static fn(array $extra)=>'auditoria.php?'.http_build_query(array_merge($f,$extra));
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Auditoría del sistema</title><link rel="stylesheet" href="css/style.css?v=20261011-responsive"><link rel="stylesheet" href="css/auditoria.css?v=1"></head>
<body class="page-reporte has-sidebar"><?php require __DIR__.'/componentes/sidebar.php';?><main class="contenedor audit-main">
<header><h1>🛡 Auditoría del sistema</h1><p>Supervisión de actividades y trazabilidad de operaciones. Registros de solo lectura.</p></header>
<?php if($error):?><p class="audit-error" role="alert"><?= $e($error) ?></p><?php endif;?>
<div class="audit-metricas"><?php foreach(['total'=>'Eventos registrados','exitosos'=>'Exitosos','fallidos'=>'Fallidos','denegados'=>'Denegados'] as $k=>$t):?><div class="audit-card"><span><?= $e($t) ?></span><strong><?= $e($resumen[$k]) ?></strong></div><?php endforeach;?></div>
<section class="audit-card"><h2>Filtrar actividades</h2><form method="get" action="auditoria.php" class="audit-filtros">
<label>ID del usuario<input name="usuario_id" type="number" min="1" value="<?= $e($f['usuario_id']) ?>" placeholder="Todos"></label>
<label>Rol<select name="rol"><option value="">Todos</option><?php foreach($roles as $k=>$t):?><option value="<?= $e($k) ?>" <?= (string)$k===$f['rol']?'selected':'' ?>><?= $e($t) ?></option><?php endforeach;?></select></label>
<label>Módulo<select name="modulo"><option value="">Todos</option><?php foreach($catalogo as $k=>$a):?><option value="<?= $e($k) ?>" <?= $k===$f['modulo']?'selected':'' ?>><?= $e(ucfirst(str_replace('_',' ',$k))) ?></option><?php endforeach;?></select></label>
<label>Acción<select name="accion"><option value="">Todas</option><?php foreach($acciones as $a):?><option value="<?= $e($a) ?>" <?= $a===$f['accion']?'selected':'' ?>><?= $e(ucfirst(str_replace('_',' ',$a))) ?></option><?php endforeach;?></select></label>
<label>Resultado<select name="resultado"><option value="">Todos</option><?php foreach(['exitoso','fallido','denegado'] as $a):?><option <?= $a===$f['resultado']?'selected':'' ?>><?= $e($a) ?></option><?php endforeach;?></select></label>
<label>Desde<input type="date" name="desde" value="<?= $e($f['desde']) ?>"></label><label>Hasta<input type="date" name="hasta" value="<?= $e($f['hasta']) ?>"></label>
<div class="audit-acciones"><button class="audit-btn">Buscar</button><a class="audit-btn audit-sec" href="auditoria.php">Limpiar</a><a class="audit-btn" href="<?= $e($enlace(['exportar'=>'csv'])) ?>">Exportar CSV</a></div></form></section>
<section class="audit-card"><h2>Registro de actividades</h2><p><?= $e($resumen['total']) ?> eventos coincidentes. Hora de Perú. El registro de consulta se refleja al actualizar.</p>
<div class="audit-scroll"><table><thead><tr><?php foreach(['ID','Fecha y hora','Usuario ID','Rol','Módulo','Acción','Entidad','Resultado'] as $h):?><th><?= $e($h) ?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($filas as $r):?><tr><td><?= $e($r['id']) ?></td><td><?= $e($r['fecha_evento']) ?></td><td><?= $e($r['usuario_id']??'—') ?></td><td><?= $e($roles[$r['rol']??'']??'Sin identificar') ?></td><td><?= $e($r['modulo']) ?></td><td><?= $e(str_replace('_',' ',$r['accion'])) ?></td><td><?= $e($r['entidad']??'—') ?> <?= $r['entidad_id']!==null?'#'.$e($r['entidad_id']):'' ?></td><td><span class="audit-estado <?= $e($r['resultado']) ?>"><?= $e($r['resultado']) ?></span></td></tr><?php endforeach;?>
<?php if(!$filas):?><tr><td colspan="8">No hay eventos disponibles.</td></tr><?php endif;?></tbody></table></div>
<nav class="audit-paginacion" aria-label="Paginación"><?php if($pagina>1):?><a class="audit-btn audit-sec" href="<?= $e($enlace(['pagina'=>$pagina-1])) ?>">Anterior</a><?php endif;?><span>Página <?= $e($pagina) ?> de <?= max(1,(int)ceil($resumen['total']/$limite)) ?></span><?php if($pagina*$limite<$resumen['total']):?><a class="audit-btn" href="<?= $e($enlace(['pagina'=>$pagina+1])) ?>">Siguiente</a><?php endif;?></nav></section></main></body></html>
