<nav class="directorio-paginacion" aria-label="Paginación de resultados">
    <?php if ($listado['pagina'] > 1): ?>
        <button form="filtros-directorio" type="submit" name="pagina" value="<?= $listado['pagina']-1 ?>">Anterior</button>
    <?php endif; ?>
    <span>Página <?= (int)$listado['pagina'] ?> de <?= (int)$listado['paginas'] ?></span>
    <?php if ($listado['pagina'] < $listado['paginas']): ?>
        <button form="filtros-directorio" type="submit" name="pagina" value="<?= $listado['pagina']+1 ?>">Siguiente</button>
    <?php endif; ?>
</nav>
