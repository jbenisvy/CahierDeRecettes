<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/models/RecetteModel.php';

if (!isset($_GET['id'], $_GET['recette'])) {
    die('Paramètres manquants');
}

$photoId   = (int) $_GET['id'];
$recetteId = (int) $_GET['recette'];

$model = new RecetteModel();

// 1️⃣ Récupérer le nom du fichier
$photo = $model->getPhotoById($photoId);
if (!$photo) {
    die("Photo introuvable");
}

// 2️⃣ Supprimer le fichier physique
$path = __DIR__ . '/uploads/recettes/' . $photo['fichier'];
if (file_exists($path)) {
    unlink($path);
}

// 3️⃣ Supprimer l’entrée en base
$model->supprimerPhoto($photoId);

// 4️⃣ Retour à l’édition
header("Location: edit_recette.php?id=" . $recetteId);
exit;
