<!DOCTYPE html>
<html lang="es">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta charset="UTF-8">
    <title>Reporte de Citas</title>
    <!-- Librería Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    
    <link rel="stylesheet" href="css/style.css?v=20261011-responsive">
</head>

<body class="page-reporte has-sidebar">
<?php include dirname(__DIR__) . "/vistas/componentes/sidebar.php"; ?>

<div class="contenedor">

    <h1>📊 Reporte de Citas</h1>

    <p>
        Administrador:
        <strong><?php echo htmlspecialchars($_SESSION["usuario"]); ?></strong>
    </p>

    <!-- =====================================
         FILTROS
    ===================================== -->
    <div class="filtros">
        <h2>🔎 Filtrar citas</h2>
        <form method="GET">
            <div class="campo">
                <label>Fecha inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
            </div>

            <div class="campo">
                <label>Fecha fin</label>
                <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
            </div>

            <div class="campo">
                <label>DNI del paciente</label>
                <input type="text" name="dni" placeholder="Ingrese DNI" value="<?php echo htmlspecialchars($dni); ?>">
            </div>

            <div class="campo">
                <label for="filtroEstado">Estado de la cita</label>
                <select id="filtroEstado" name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach ($etiquetasEstado as $valorEstado => $textoEstado): ?>
                        <option value="<?php echo htmlspecialchars($valorEstado, ENT_QUOTES, "UTF-8"); ?>" <?php echo $estadoSeleccionado === $valorEstado ? "selected" : ""; ?>><?php echo htmlspecialchars($textoEstado, ENT_QUOTES, "UTF-8"); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

	<button type="submit">🔎 Buscar</button>

<button type="submit" name="exportar" value="excel">
    Exportar CSV para Excel
</button>

<button
    type="submit"
    formaction="exportar_excel_java"
    formmethod="GET"
    formtarget="_blank">
    📊 Exportar Excel con Java
</button>

<a href="reporte_citas" class="boton boton-limpiar">
    Limpiar
</a>            

        </form>
    </div>

    <!-- =====================================
         RESUMEN (TARJETAS)
    ===================================== -->
    <div class="tarjetas">
        <div class="tarjeta">
            <h3>Total</h3>
            <div class="numero"><?php echo $total; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Atendidas</h3>
            <div class="numero"><?php echo $estados["atendida"]; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Pendientes</h3>
            <div class="numero"><?php echo $estados["pendiente"]; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Confirmadas</h3>
            <div class="numero"><?php echo $estados["confirmada"]; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Canceladas</h3>
            <div class="numero"><?php echo $estados["cancelada"]; ?></div>
        </div>
    </div>

    <!-- =====================================
         GRÁFICOS ESTADÍSTICOS
    ===================================== -->
    <div class="graficos-grid">
        <div class="tarjeta-grafico">
            <h2>🍩 Citas por Estado</h2>
            <div class="contenedor-canvas">
                <canvas id="chartEstados"></canvas>
            </div>
        </div>

        <div class="tarjeta-grafico">
            <h2>📊 Total Citas por Especialidad</h2>
            <div class="contenedor-canvas">
                <canvas id="chartEspecialidades"></canvas>
            </div>
        </div>

        <div class="tarjeta-grafico tarjeta-grafico-full">
            <h2>📈 Evolución Mensual por Estado</h2>
            <div class="contenedor-canvas">
                <canvas id="chartMes"></canvas>
            </div>
        </div>
    </div>

    <!-- =====================================
         POR MES (TABLA)
    ===================================== -->
    <div class="seccion">
        <h2>📅 Citas por mes</h2>
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Total</th>
                    <th>Atendidas</th>
                    <th>Pendientes</th>
                    <th>Confirmadas</th>
                    <th>Canceladas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datosMes as $fila): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fila["mes"]); ?></td>
                    <td><?php echo $fila["total"]; ?></td>
                    <td class="estado-atendida"><?php echo $fila["atendidas"]; ?></td>
                    <td class="estado-pendiente"><?php echo $fila["pendientes"]; ?></td>
                    <td class="estado-confirmada"><?php echo $fila["confirmadas"]; ?></td>
                    <td class="estado-cancelada"><?php echo $fila["canceladas"]; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- =====================================
         POR ESPECIALIDAD (TABLA)
    ===================================== -->
    <div class="seccion">
        <h2>🩺 Reporte por especialidad</h2>
        <table>
            <thead>
                <tr>
                    <th>Especialidad</th>
                    <th>Total</th>
                    <th>Atendidas</th>
                    <th>Pendientes</th>
                    <th>Confirmadas</th>
                    <th>Canceladas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datosEspecialidades as $fila): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($fila["especialidad"]); ?></strong></td>
                    <td><?php echo $fila["total"]; ?></td>
                    <td><?php echo $fila["atendidas"]; ?></td>
                    <td><?php echo $fila["pendientes"]; ?></td>
                    <td><?php echo $fila["confirmadas"]; ?></td>
                    <td><?php echo $fila["canceladas"]; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <a href="citas_admin" class="boton">📅 Administrar citas</a>
    <a href="panel" class="boton">Volver al panel</a>

</div>

<!-- =====================================
     INICIALIZACIÓN DE CHART.JS
===================================== -->
<script type="application/json" id="report-chart-data"><?php
echo json_encode([
    "estados" => $estados,
    "especialidades" => $datosEspecialidades,
    "meses" => array_reverse($datosMes)
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?></script>
<script src="js/javascript.js" defer></script>

</body>

</html>
