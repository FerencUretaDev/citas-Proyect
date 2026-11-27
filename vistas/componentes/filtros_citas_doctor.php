<?php
$escDoctor = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<form id="filtros-directorio" method="post" action="panel" class="directorio-filtros">
    <h3>Buscar y filtrar mis citas</h3>
    <input type="hidden" name="csrf_token" value="<?= $escDoctor($_SESSION['csrf_token']) ?>">
    <div class="directorio-filtros-grid">
        <label>Paciente<input name="paciente" maxlength="100" placeholder="DNI, nombres o apellidos" value="<?= $escDoctor($fDoctor['paciente']) ?>"></label>
        <label>Estado<select name="estado"><option value="">Todos</option><?php foreach (ETIQUETAS_ESTADO_CITA as $v=>$et): ?><option value="<?= $escDoctor($v) ?>" <?= $fDoctor['estado']===$v?'selected':'' ?>><?= $escDoctor($et) ?></option><?php endforeach; ?></select></label>
        <label>Modalidad<select name="modalidad"><option value="">Todas</option><?php foreach (FILTRO_MODALIDADES_CITAS as $v=>$et): ?><option value="<?= $escDoctor($v) ?>" <?= $fDoctor['modalidad']===$v?'selected':'' ?>><?= $escDoctor($et) ?></option><?php endforeach; ?></select></label>
        <label>Desde<input type="date" name="desde" value="<?= $escDoctor($fDoctor['desde']) ?>"></label>
        <label>Hasta<input type="date" name="hasta" value="<?= $escDoctor($fDoctor['hasta']) ?>"></label>
    </div>
    <div class="directorio-filtros-acciones"><button type="submit">Buscar</button><a href="panel">Limpiar</a></div>
</form>
