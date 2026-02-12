<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../app/services/ChatGPTService.php";
$recetteOptions = require __DIR__ . "/../config/recette_options.php";
$categories = $recetteOptions['categories'] ?? [];

$chat = new ChatGPTService();
$recetteFormattee = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titre = $_POST["titre"] ?? "";
    $auteur = $_POST["auteur"] ?? "";
    $categorie = $_POST["categorie"] ?? "";
    $texteBrut = $_POST["texte_brut"] ?? "";

    if (!empty($texteBrut)) {
        // Appel API
        $recetteFormattee = $chat->uniformiserRecette($texteBrut);
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une recette (API ChatGPT)</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<h1>Ajouter une nouvelle recette</h1>

<form method="POST" action="">
    <label>Titre :</label><br>
    <input type="text" name="titre" required><br><br>

    <label>Auteur :</label><br>
    <input type="text" name="auteur"><br><br>

    <label>Catégorie :</label><br>
    <select name="categorie">
        <?php foreach ($categories as $value => $label): ?>
            <option value="<?= htmlspecialchars((string)$value) ?>">
                <?= htmlspecialchars((string)$label) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Texte brut de la recette :</label><br>
    <textarea name="texte_brut" rows="10" cols="80" required></textarea><br><br>

    <button type="submit">Uniformiser avec ChatGPT</button>
</form>

<?php if ($recetteFormattee): ?>
    <hr>
    <h2>Résultat formaté :</h2>

    <pre style="background:#f5f5f5;padding:15px;border:1px solid #ccc;">
<?= htmlspecialchars($recetteFormattee) ?>
    </pre>

    <!-- À venir : bouton "Enregistrer dans la base" -->
<?php endif; ?>

</body>
</html>
