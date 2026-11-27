<form id="filtros-ausencias" class="directorio-filtros" method="post" action="ausencias_doctores.php" autocomplete="off">
    <input type="hidden" name="accion" value="buscar_ausencias">
    <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_token']) ?>">
    <h3>Buscar y filtrar ausencias</h3>
    <div class="directorio-filtros-grid">
        <label>Doctor<input name="doctor" maxlength="100" placeholder="DNI, CMP, nombres o apellidos" value="<?= $escapar($filtros['doctor']) ?>"></label>
        <?php foreach (['especialidad'=>['Especialidad',array_combine($especialidadesFiltro,$especialidadesFiltro)],'estado'=>['Estado',ESTADOS_FILTRO_AUSENCIAS],'motivo'=>['Motivo',MOTIVOS_AUSENCIA]] as $k=>[$titulo,$opciones]): ?>
            <label><?= $escapar($titulo) ?><select name="<?= $escapar($k) ?>">
                <option value="">Todos</option>
                <?php foreach($opciones as $v=>$t): ?><option value="<?= $escapar($v) ?>" <?= $filtros[$k]===(string)$v?'selected':'' ?>><?= $escapar($t) ?></option><?php endforeach; ?>
            </select></label>
        <?php endforeach; ?>
        <?php foreach(['desde'=>'Desde','hasta'=>'Hasta'] as $k=>$t): ?><label><?= $t ?><input type="date" name="<?= $k ?>" value="<?= $escapar($filtros[$k]) ?>"></label><?php endforeach; ?>
    </div>
    <p>El rango incluye ausencias que coincidan total o parcialmente con esas fechas.</p>
    <div class="directorio-filtros-acciones"><button type="submit">Buscar</button><a href="ausencias_doctores.php">Limpiar</a></div>
</form>
<p class="directorio-resultados" role="status"><?= (int)$listado['total'] ?> resultado(s). <?= count($listado['filas']) ?> en esta página.</p>
