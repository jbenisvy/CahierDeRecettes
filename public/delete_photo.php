<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/models/RecetteModel.php';
require_once __DIR__ . '/auth/auth_functions.php';
// Définir BASE_URL et PUBLIC_URL pour gérer les redirections
require_once dirname(__DIR__) . '/app/base_url.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();
require_capability('edit_recette');

if (!isset($_GET['id'], $_GET['recette'])) {
    die('Paramètres manquants');
}

$photoId   = (int) $_GET['id'];
$recetteId = (int) $_GET['recette'];

$model = new RecetteModel();
$recette = $model->getRecetteById($recetteId);
if (!$recette) {
    die("Recette introuvable");
}
if (
    ($_SESSION['user']['role'] ?? '') !== 'admin'
    && ($_SESSION['user']['nom'] ?? '') !== ($recette['auteur'] ?? '')
) {
    http_response_code(403);
    die("Accès interdit");
}

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
header("Location: " . PUBLIC_URL . "/edit_recette.php?id=" . $recetteId);
exit;
