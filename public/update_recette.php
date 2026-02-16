<?php


require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/controllers/RecetteController.php";
require_once __DIR__ . '/auth/auth_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();
require_capability('edit_recette');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Méthode non autorisée");
}

if (empty($_POST["id"])) {
    die("ID manquant");
}

$controller = new RecetteController();

/* Nettoyage + mapping STRICT */
$data = [
    "id" => (int) $_POST["id"],
    "titre" => trim($_POST["titre"] ?? ""),
    "auteur" => trim($_POST["auteur"] ?? ""),
    "source" => trim($_POST["source"] ?? ""),
    "categorie" => trim($_POST["categorie"] ?? ""),
    "temps_preparation" => $_POST["temps_preparation"] !== "" ? (int) $_POST["temps_preparation"] : null,
    "temps_cuisson" => $_POST["temps_cuisson"] !== "" ? (int) $_POST["temps_cuisson"] : null,
    "temps_repos" => $_POST["temps_repos"] !== "" ? (int) $_POST["temps_repos"] : null,
    "nombre_personnes" => trim($_POST["nombre_personnes"] ?? ""),
    "type_cuisson" => trim($_POST["type_cuisson"] ?? ""),
    "difficulte" => $_POST["difficulte"] !== "" ? (int) $_POST["difficulte"] : null,
    "commentaires" => trim($_POST["commentaires"] ?? ""),
];

/* Gestion du champ "autre type de cuisson" */
if (!empty($_POST["type_cuisson_autre"])) {
    $data["type_cuisson"] = trim($_POST["type_cuisson_autre"]);
}

/* Sécurité minimale */
if ($data["titre"] === "") {
    die("Le titre est obligatoire");
}

/* Mise à jour */
try {
    $ok = $controller->updateRecetteEdition($data);
    if (!$ok) {
        http_response_code(403);
        die("Accès interdit");
    }
} catch (DuplicateRecetteException $e) {
    header("Location: edit_recette.php?id=" . $data["id"] . "&dup=1");
    exit;
}

/* Retour à la recette */
header("Location: recette.php?id=" . $data["id"] . "&saved=1");
exit;

exit;
