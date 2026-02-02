<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/models/RecetteModel.php';

// ===============================
// Récupération des IDs
// ===============================
$idsParam = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $idsParam)));

if (empty($ids)) {
    die('Aucune recette sélectionnée');
}

$model = new RecetteModel();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Impression des recettes sélectionnées</title>

<link rel="stylesheet" href="/assets/css/style.css">

<style>
.page-break { page-break-after: always; }
@page { margin: 12mm; }
</style>
</head>

<body>

<?php
$nb = count($ids);
$i = 0;

foreach ($ids as $id):
    $recette = $model->getRecetteComplete($id);
    if (!$recette) continue;

    $i++;
?>
   <div class="print-recette">
    <?php require __DIR__ . '/../../templates/recette_print_browser.php'; ?>
</div>


    <?php if ($i < $nb): ?>
        <div class="page-break"></div>
    <?php endif; ?>

<?php endforeach; ?>

<script>
window.print();
</script>

</body>
</html>
