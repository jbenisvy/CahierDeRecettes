<?php


require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/controllers/RecetteController.php';

if (!isset($_GET['id'], $_GET['recette'])) {
    die('Paramètres manquants');
}

$photoId = (int) $_GET['id'];
$recetteId = (int) $_GET['recette'];

$controller = new RecetteController();
$controller->definirPhotoPrincipale($photoId, $recetteId);

header('Location: edit_recette.php?id=' . $recetteId);
exit;
