<?php
require __DIR__ . "/../config/database.php";

$recetteId = (int) ($_GET['recette'] ?? 0);
$tagId     = (int) ($_GET['tag'] ?? 0);

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
