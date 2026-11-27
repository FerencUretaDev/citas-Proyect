<?php
// El controlador define $filtros, $listado, $errorFiltros y $esDirectorioPaciente.
$rutaDirectorio = $esDirectorioPaciente ? 'pacientes' : 'doctores';
$escFiltro = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<form id="filtros-directorio" class="directorio-filtros" method="post" action="<?= $rutaDirectorio ?>" autocomplete="off">
    <input type="hidden" name="accion" value="buscar_directorio">
    <input type="hidden" name="csrf_token" value="<?= $escFiltro($_SESSION['csrf_token']) ?>">
    <h3>Buscar y filtrar</h3>
    <div class="directorio-filtros-grid">
        <label>Buscar
            <input name="buscar" maxlength="100" value="<?= $escFiltro($filtros['buscar']) ?>" placeholder="<?= $esDirectorioPaciente ? 'DNI, nombres o apellidos' : 'DNI, CMP, nombres o apellidos' ?>">
        </label>
        <label>Estado del perfil
            <select name="estado">
                <?php foreach (['1'=>'Activos','0'=>'Inactivos','todos'=>'Todos'] as $valor=>$etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $filtros['estado'] === (string)$valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($esDirectorioPaciente): ?>
            <label>Sexo
                <select name="sexo">
                    <option value="">Todos</option>
                    <option value="M" <?= $filtros['sexo']==='M'?'selected':'' ?>>Masculino</option>
                    <option value="F" <?= $filtros['sexo']==='F'?'selected':'' ?>>Femenino</option>
                </select>
            </label>
        <?php else: ?>
            <label>Especialidad
                <select name="especialidad">
                    <option value="">Todas</option>
                    <?php foreach ($especialidadesFiltro as $opcion): ?>
                        <option value="<?= $escFiltro($opcion) ?>" <?= $filtros['especialidad']===$opcion?'selected':'' ?>><?= $escFiltro($opcion) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
    </div>
    <div class="directorio-filtros-acciones">
        <button type="submit">Buscar</button>
        <a href="<?= $rutaDirectorio ?>">Limpiar</a>
    </div>
    <?php if ($errorFiltros !== ''): ?><p role="alert"><?= $escFiltro($errorFiltros) ?></p><?php endif; ?>
</form>
<p class="directorio-resultados" role="status"><?= (int)$listado['total'] ?> resultado(s). <?= count($listado['filas']) ?> en esta página.</p>
