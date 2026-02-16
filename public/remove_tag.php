<?php
require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/models/RecetteModel.php";
require_once __DIR__ . '/auth/auth_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();
require_capability('edit_recette');

$recetteId = (int) ($_GET['recette'] ?? 0);
$tagId     = (int) ($_GET['tag'] ?? 0);

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

if ($recetteId <= 0 || $tagId <= 0) {
    header("Location: index.php");
    exit;
}

/* Suppression de l’association recette ↔ tag */
$stmt = $pdo->prepare("
    DELETE FROM recette_tags
    WHERE recette_id = :recette
      AND tag_id = :tag
");
$stmt->execute([
    ":recette" => $recetteId,
    ":tag"     => $tagId
]);

/* Retour à la fiche édition */
header("Location: edit_recette.php?id=" . $recetteId);
exit;
