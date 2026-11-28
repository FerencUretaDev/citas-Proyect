<?php
$paginaActiva='lista_espera_admin';
$esc=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$estados=[''=>'Todos','en_espera'=>'En espera','notificado'=>'Notificado','reservado'=>'Reservado','cancelado'=>'Cancelado','vencido'=>'Vencido'];
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Lista de espera | Administración</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/lista-espera.css?v=1"><link rel="stylesheet" href="css/filtros-directorio.css?v=1"></head>
<body class="page-panel has-sidebar" data-user-role="2">
<?php require __DIR__.'/componentes/sidebar.php'; ?>
<main class="contenido pagina-lista-espera">
<header class="topbar"><div class="titulo"><h1>Lista de espera de pacientes</h1><p>Consulta las solicitudes y supervisa el orden de inscripción.</p></div></header>
<?php if($aviso): ?><p class="espera-aviso <?= $aviso['ok']?'ok':'error' ?>" role="status"><?= $esc($aviso['mensaje']) ?></p><?php endif; ?>
<?php if($error!==''): ?><p class="espera-aviso error" role="alert"><?= $esc($error) ?></p><?php endif; ?>
<section class="espera-card"><h2>Solicitudes registradas</h2><p>La posición se calcula por doctor y fecha, sin alterar la prioridad al filtrar. Cada turno libre prioriza al primer solicitante compatible; la cita solo se crea al confirmar la reserva. La disponibilidad se calcula al consultar. Las solicitudes con fecha pasada se muestran como vencidas.</p>
<form class="directorio-filtros" id="filtros-espera" action="lista_espera_admin.php" method="POST">
<input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="accion" value="buscar">
<h3>Buscar y filtrar</h3><div class="directorio-filtros-grid">
<label>Paciente<input name="paciente" maxlength="100" value="<?= $esc($f['paciente']) ?>" placeholder="DNI exacto, nombres o apellidos"></label>
<label>Doctor<input name="doctor" maxlength="100" value="<?= $esc($f['doctor']) ?>" placeholder="DNI, CMP o nombre"></label>
<label>Estado<select name="estado"><?php foreach($estados as $k=>$v): ?><option value="<?= $esc($k) ?>" <?= $f['estado']===$k?'selected':'' ?>><?= $esc($v) ?></option><?php endforeach; ?></select></label>
<label>Desde<input type="date" name="desde" value="<?= $esc($f['desde']) ?>"></label><label>Hasta<input type="date" name="hasta" value="<?= $esc($f['hasta']) ?>"></label>
</div><div class="directorio-filtros-acciones"><button type="submit" name="pagina" value="1">Buscar</button><a href="lista_espera_admin.php">Limpiar</a></div></form>
<p><?= $esc($resultado['total']) ?> resultado(s). <?= count($resultado['filas']) ?> en esta página.</p>
<div class="espera-tabla"><table><thead><tr><?php foreach(['ID','Paciente','Doctor','Especialidad','Fecha solicitada','Modalidad','Estado','Posición','Disponibilidad actual','Inscripción','Acción'] as $t): ?><th><?= $esc($t) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($resultado['filas'] as $s): ?><tr><td><?= $esc($s['id']) ?></td><td><?= $esc($s['paciente']) ?></td><td><?= $esc($s['doctor']) ?></td><td><?= $esc($s['especialidad']) ?></td><td><?= $esc(date('d/m/Y',strtotime($s['fecha_solicitada']))) ?></td><td><?= $esc(ucfirst($s['modalidad'])) ?></td><td><span class="espera-estado"><?= $esc($estados[$s['estado']]??'Desconocido') ?></span></td><td><?= $esc($s['posicion']??'—') ?></td><td><?= $esc($s['disponibilidad']??'—') ?></td><td><?= $esc($s['fecha_registro']) ?></td><td>
<?php if(in_array($s['estado'],['en_espera','notificado'],true)): ?><form method="POST" action="lista_espera_admin.php" onsubmit="return confirm('¿Cancelar esta solicitud de espera? No se cancelará ninguna cita.')"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="solicitud_id" value="<?= $esc($s['id']) ?>"><button type="submit" class="espera-boton retirar">Cancelar solicitud</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$resultado['filas']): ?><tr><td colspan="11">No hay solicitudes que coincidan con los filtros.</td></tr><?php endif; ?></tbody></table></div>
<nav class="directorio-paginacion" aria-label="Paginación"><?php if($resultado['pagina']>1): ?><button type="submit" form="filtros-espera" name="pagina" value="<?= $resultado['pagina']-1 ?>">Anterior</button><?php endif; ?><span>Página <?= $esc($resultado['pagina']) ?> de <?= $esc($resultado['paginas']) ?></span><?php if($resultado['pagina']<$resultado['paginas']): ?><button type="submit" form="filtros-espera" name="pagina" value="<?= $resultado['pagina']+1 ?>">Siguiente</button><?php endif; ?></nav>
</section></main><script src="js/javascript.js" defer></script></body></html>
