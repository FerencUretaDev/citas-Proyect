<?php
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$soles = static fn($v) => 'S/ '.number_format((float)$v,2,'.',',');
$etiquetasPago=['pendiente'=>'Pendiente','en_proceso'=>'En proceso','aprobado'=>'Aprobado','rechazado'=>'Rechazado','cancelado'=>'Cancelado','expirado'=>'Expirado','reembolsado'=>'Reembolsado','revision'=>'Revisión'];
$conteos=array_fill_keys(array_keys($etiquetasPago),0); $montos=$conteos;
foreach($analiticaPago['resumen'] as $r){$conteos[$r['estado']]=(int)$r['cantidad'];$montos[$r['estado']]=$r['monto'];}
$f=$filtrosPago;
?>
<section id="analitica-pagos" class="dashboard-finanzas" aria-labelledby="titulo-finanzas">
  <header class="dashboard-fin-header"><div><h2 id="titulo-finanzas">Analítica de pagos</h2><p>Importes brutos de pagos actualmente aprobados, sin descontar comisiones. No equivale a saldo disponible.</p></div><span class="dashboard-env"><?= $f['ambiente']==='test'?'AMBIENTE DE PRUEBA · SIN DINERO REAL':'PRODUCCIÓN' ?></span></header>
  <div class="filtros"><h2>Filtrar pagos</h2><form method="GET" action="reporte_citas#analitica-pagos">
    <?php foreach(['fecha_inicio'=>$fecha_inicio,'fecha_fin'=>$fecha_fin,'dni'=>$dni,'estado'=>$estadoSeleccionado] as $k=>$v): ?><input type="hidden" name="<?= $esc($k) ?>" value="<?= $esc($v) ?>"><?php endforeach; ?>
    <div class="campo"><label for="ambiente_pago">Ambiente</label><select name="ambiente_pago" id="ambiente_pago"><option value="test" <?= $f['ambiente']==='test'?'selected':'' ?>>Prueba</option><option value="production" <?= $f['ambiente']==='production'?'selected':'' ?>>Producción</option></select></div>
    <div class="campo"><label for="pago_desde">Desde</label><input type="date" id="pago_desde" name="pago_desde" value="<?= $esc($f['desde']) ?>"></div>
    <div class="campo"><label for="pago_hasta">Hasta</label><input type="date" id="pago_hasta" name="pago_hasta" value="<?= $esc($f['hasta']) ?>"></div>
    <div class="campo"><label for="modalidad_pago">Modalidad</label><select id="modalidad_pago" name="modalidad_pago"><option value="">Todas</option><?php foreach(['presencial'=>'Presencial','virtual'=>'Virtual'] as $k=>$v): ?><option value="<?= $k ?>" <?= $f['modalidad']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div class="campo"><label for="estado_pago">Estado (tabla)</label><select id="estado_pago" name="estado_pago"><option value="">Todos</option><?php foreach($etiquetasPago as $k=>$v): ?><option value="<?= $k ?>" <?= $f['estado']===$k?'selected':'' ?>><?= $esc($v) ?></option><?php endforeach; ?></select></div>
    <button type="submit">Aplicar filtros</button><button type="submit" name="exportar" value="pagos_csv">Exportar pagos CSV</button>
  </form><p class="dashboard-note">Periodo según fecha de acreditación si existe; en caso contrario, fecha de registro. El filtro de estado solo afecta el detalle y su CSV.</p></div>
  <div class="dashboard-kpis">
    <?php foreach(['Aprobados en el periodo'=>$soles($montos['aprobado']),'Aprobados hoy'=>$soles($analiticaPago['periodos']['hoy']),'Aprobados este mes'=>$soles($analiticaPago['periodos']['mes']),'Aprobados este año'=>$soles($analiticaPago['periodos']['anio']),'Pagos aprobados'=>$conteos['aprobado'],'Pendientes / en proceso'=>$conteos['pendiente']+$conteos['en_proceso'],'Rechazados'=>$conteos['rechazado'],'Reembolsados'=>$soles($montos['reembolsado'])] as $label=>$valor): ?>
      <article class="dashboard-kpi"><span><?= $esc($label) ?></span><strong><?= $esc($valor) ?></strong></article>
    <?php endforeach; ?>
  </div>
  <div class="graficos-grid">
    <div class="tarjeta-grafico"><h2>Pagos por estado</h2><div class="contenedor-canvas"><canvas id="dashboardEstadosPago" aria-label="Gráfico de estados de pago" role="img"></canvas></div><p class="dashboard-note"><?= array_sum($conteos) ?> transacciones en el periodo.</p></div>
    <div class="tarjeta-grafico"><h2>Importes aprobados por modalidad</h2><div class="contenedor-canvas"><canvas id="dashboardModalidadPago" role="img" aria-label="Importes por modalidad"></canvas></div></div>
    <div class="tarjeta-grafico tarjeta-grafico-full"><h2>Evolución mensual de importes aprobados</h2><div class="contenedor-canvas"><canvas id="dashboardMesPago" role="img" aria-label="Evolución mensual"></canvas></div></div>
  </div>
  <div class="seccion"><h2>Transacciones recientes</h2><p class="dashboard-note">Hasta 50 registros. El CSV incluye todos los resultados del filtro.</p><div class="dashboard-table"><table><thead><tr><?php foreach(['Cita','Paciente','Doctor','Modalidad','Monto','Estado','Registro','Acreditación','Operación'] as $h): ?><th><?= $h ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach($analiticaPago['filas'] as $r): ?><tr><td>#<?= (int)$r['cita_id'] ?></td><td><?= $esc($r['paciente']) ?></td><td><?= $esc($r['doctor']) ?></td><td><?= $esc(ucfirst($r['modalidad'])) ?></td><td><?= $esc($soles($r['monto'])) ?></td><td><?= $esc($etiquetasPago[$r['estado']] ?? $r['estado']) ?></td><td><?= $esc($r['fecha_registro']) ?></td><td><?= $esc($r['fecha_pago'] ?? '—') ?></td><td><?= $esc($r['id_pago_mp'] ?? '—') ?></td></tr><?php endforeach; ?>
  <?php if (!$analiticaPago['filas']): ?><tr><td colspan="9">No hay pagos registrados para estos filtros.</td></tr><?php endif; ?>
  </tbody></table></div></div>
  <script type="application/json" id="dashboard-pago-data"><?= json_encode(['estados'=>$conteos,'etiquetas'=>$etiquetasPago,'modalidades'=>$analiticaPago['modalidades'],'meses'=>$analiticaPago['meses']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
</section>
