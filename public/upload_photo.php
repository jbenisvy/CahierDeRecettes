<?php
require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/models/RecetteModel.php";

$model = new RecetteModel();

if (!isset($_POST["recette_id"], $_FILES["photo"])) {
    die("Données manquantes");
}

$recetteId = (int) $_POST["recette_id"];
$file = $_FILES["photo"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    die("Erreur upload fichier");
}

// Vérification image
if (getimagesize($file["tmp_name"]) === false) {
    die("Le fichier n'est pas une image valide");
}

// Dossier cible
$uploadDir = __DIR__ . "/uploads/recettes/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Génération nom fiable
$originalName = $file["name"];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, ["jpg", "jpeg", "png", "webp"])) {
    die("Extension de fichier non autorisée");
}

$filename = uniqid("recette_", true) . "." . $extension;
$destination = $uploadDir . $filename;

// Déplacement réel
if (!copy($file["tmp_name"], $destination)) {
    die("Impossible de copier le fichier uploadé vers : " . $destination);
}
unlink($file["tmp_name"]);


// Enregistrement DB
$model->ajouterPhoto($recetteId, $filename);

// Retour édition
header("Location: edit_recette.php?id=" . $recetteId);
exit;
