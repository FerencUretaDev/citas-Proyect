<?php if (!isset($ficha, $paginaActiva)) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $accionVisible ?> <?= $tipo ?></title><link rel="stylesheet" href="css/style.css?v=20261011-responsive"></head>
<body class="page-pacientes has-sidebar">
<?php require dirname(__DIR__) . '/sidebar.php'; ?>
<main class="pacientes-page"><section class="pacientes-form-section">
<h1><?= $accionVisible ?> <?= $tipo ?></h1>
<h2><?= htmlspecialchars($ficha['nombres'].' '.$ficha['apellidos']) ?></h2>
<?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($activo): ?>
<p>La ficha y el historial de citas se conservarán. El acceso y las nuevas reservas quedarán deshabilitados.</p>
<p>Citas futuras pendientes o confirmadas: <strong><?= $pendientes ?></strong>.</p>
<?php else: ?>
<p>Motivo de la baja: <?= htmlspecialchars($ficha['motivo_baja'] ?? '') ?>. Al reactivar se habilitarán el acceso y las nuevas reservas.</p>
<?php endif; ?>
<?php if ($pendientes): ?>
<p>Revisa estas citas antes de confirmar la baja.</p><a class="pacientes-edit" href="citas_admin?tipo=<?= $tipo ?>&amp;id=<?= $id ?>">Gestionar citas</a>
<?php else: ?>
<form method="post" class="paciente-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="accion" value="<?= $activo ? 'baja' : 'reactivar' ?>">
<?php if ($activo): ?><label>Motivo <input name="motivo" maxlength="160" required value="<?= htmlspecialchars($motivo) ?>"></label><?php endif; ?>
<div class="paciente-form-footer"><button type="submit"><?= $accionVisible ?> <?= $tipo ?></button></div>
</form>
<?php endif; ?>
<p><a class="pacientes-cancel" href="<?= $paginaActiva . ($activo ? '' : '?vista=inactivos') ?>">Volver al directorio</a></p>
</section></main><script src="js/javascript.js" defer></script></body></html>
