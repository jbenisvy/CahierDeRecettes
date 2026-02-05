<?php



$bodyClass = $bodyClass ?? '';
$pageTitle = $pageTitle ?? 'Mémoire de Saveurs';
$page = $page ?? '';
$view = $view ?? 'list';
$recetteId = $recetteId ?? null;
?>
<?php
// Définition de PUBLIC_URL/BASE_URL (pour liens relatifs) si non défini
// On cherche app/base_url.php à partir du dossier public/ui (deux niveaux au-dessus)
@require_once __DIR__ . '/../../app/base_url.php';
?>
<!DOCTYPE html>
<html lang="fr">
	<?php if (!empty($useBootstrap)): ?>
  <!-- Bootstrap CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">

  <!-- Bootstrap JS (optionnel mais utile pour modals, tabs, etc.) -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    defer></script>
<?php endif; ?>

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/style.css">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<?php require __DIR__ . '/header.php'; ?>
