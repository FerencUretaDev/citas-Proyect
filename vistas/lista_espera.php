<?php
$paginaActiva='lista_espera';
$esc=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$etiquetas=['en_espera'=>'En espera','notificado'=>'Notificado','reservado'=>'Reservado','cancelado'=>'Cancelado','vencido'=>'Vencido'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi lista de espera | Sistema de Citas Médicas</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/lista-espera.css?v=1">
</head>
<body class="page-panel has-sidebar" data-user-role="0">
<?php require __DIR__.'/componentes/sidebar.php'; ?>
<main class="contenido pagina-lista-espera">
    <header class="topbar"><div class="titulo"><h1>Mi lista de espera</h1><p>Consulta tus solicitudes y su posición en la cola.</p></div></header>
    <?php if ($aviso): ?><p class="espera-aviso <?= $aviso['ok']?'ok':'error' ?>" role="status"><?= $esc($aviso['mensaje']) ?></p><?php endif; ?>
    <section class="espera-card">
        <div class="espera-cabecera"><div><h2>Solicitudes registradas</h2><p>Inscribirte no garantiza un turno ni genera un cobro. Si aparece un horario, deberás reservarlo por el procedimiento habitual.</p></div><a class="espera-boton" href="sacar_cita.php">Buscar cita</a></div>
        <p>La disponibilidad se vuelve a consultar al abrir esta pantalla. Cada turno libre da prioridad al primer solicitante compatible, sin crear una cita ni un cobro automáticamente. La reserva aún debe confirmarse. Las alertas por correo se habilitarán en una fase posterior.</p>
        <div class="espera-tabla"><table><thead><tr><th>Doctor</th><th>Especialidad</th><th>Fecha solicitada</th><th>Modalidad</th><th>Estado</th><th>Posición</th><th>Disponibilidad actual</th><th>Acción</th></tr></thead><tbody>
        <?php foreach ($solicitudes as $s): ?>
            <tr><td><?= $esc($s['nombres'].' '.$s['apellidos']) ?></td><td><?= $esc($s['especialidad']) ?></td><td><?= $esc(date('d/m/Y',strtotime($s['fecha_solicitada']))) ?></td><td><?= $esc(ucfirst($s['modalidad'])) ?></td><td><span class="espera-estado"><?= $esc($etiquetas[$s['estado']]??'Desconocido') ?></span></td><td><?= $s['posicion']===null?'—':$esc($s['posicion']) ?></td><td><?= $esc($s['disponibilidad']??'—') ?></td><td>
            <?php if (in_array($s['estado'],['en_espera','notificado'],true)): ?>
                <form action="lista_espera.php" method="POST"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="solicitud_id" value="<?= $esc($s['id']) ?>"><button class="espera-boton retirar" type="submit">Retirarme</button></form>
            <?php else: ?>—<?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$solicitudes): ?><tr><td colspan="8">No tienes solicitudes en la lista de espera.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>
<script src="js/javascript.js" defer></script>
</body>
</html>
