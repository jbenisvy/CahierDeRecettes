<?php
require __DIR__ . "/../config/database.php";

$recetteId = (int) ($_POST["recette_id"] ?? 0);
$tagNom = trim($_POST["tag"] ?? '');

if ($recetteId <= 0 || $tagNom === '') {
    header("Location: edit_recette.php?id=" . $recetteId);
    exit;
}


$recetteId = (int) $_POST["recette_id"];
$tagNom = trim($_POST["tag"]);

if ($tagNom === "") {
    header("Location: edit_recette.php?id=" . $recetteId);
    exit;
}

/* 1️⃣ Vérifier si le tag existe déjà */
$stmt = $pdo->prepare("SELECT id FROM tags WHERE nom = :nom");
$stmt->execute([":nom" => $tagNom]);
$tag = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tag) {
    $tagId = $tag["id"];
} else {
    /* 2️⃣ Créer le tag */
    $stmt = $pdo->prepare("INSERT INTO tags (nom) VALUES (:nom)");
    $stmt->execute([":nom" => $tagNom]);
    $tagId = (int) $pdo->lastInsertId();
}

/* 3️⃣ Associer le tag à la recette */
$stmt = $pdo->prepare("
    INSERT IGNORE INTO recette_tags (recette_id, tag_id)
    VALUES (:recette, :tag)
");
$stmt->execute([
    ":recette" => $recetteId,
    ":tag"     => $tagId
]);

/* 4️⃣ Retour à l’édition */
header("Location: edit_recette.php?id=" . $recetteId);
exit;
