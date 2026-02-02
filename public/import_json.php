<?php
declare(strict_types=1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Accès refusé");
}

// Endpoint POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Dépendances
require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/models/RecetteModel.php";

error_log("Import JSON lancé par user #" . ($_SESSION['user']['id'] ?? 'unknown'));

$jsonContent = null;

/*
|--------------------------------------------------------------------------
| MODE UNIQUE — JSON (fichier OU payload)
|--------------------------------------------------------------------------
*/

// 1️⃣ Fichier JSON uploadé
if (
    isset($_FILES['jsonfile']) &&
    is_uploaded_file($_FILES['jsonfile']['tmp_name'])
) {
    if ($_FILES['jsonfile']['error'] !== UPLOAD_ERR_OK) {
        die("Erreur lors de l’upload du fichier JSON");
    }

    if (strtolower(pathinfo($_FILES['jsonfile']['name'], PATHINFO_EXTENSION)) !== 'json') {
        die("Le fichier doit être au format JSON");
    }

    $jsonContent = file_get_contents($_FILES['jsonfile']['tmp_name']);
}

// 2️⃣ JSON généré par le formulaire preview
elseif (
    isset($_POST['json_payload']) &&
    is_string($_POST['json_payload']) &&
    trim($_POST['json_payload']) !== ''
) {
    $jsonContent = $_POST['json_payload'];
}

// 3️⃣ Rien reçu → retour propre au formulaire
else {
    header("Location: /import_json_form.php");
    exit;
}

if ($jsonContent === false) {
    die("Impossible de lire le JSON");
}

// Décodage JSON
$data = json_decode($jsonContent, true);

if ($data === null || !is_array($data)) {
    die("JSON invalide : " . json_last_error_msg());
}

/*
|--------------------------------------------------------------------------
| IMPORT EN BASE (LOGIQUE EXISTANTE, CONSERVÉE)
|--------------------------------------------------------------------------
*/
$model = new RecetteModel();
$imported = 0;

foreach ($data as $r) {

    if (empty($r['titre'])) {
        continue;
    }

    // Normalisation ingrédients
    if (isset($r['ingredients']) && is_string($r['ingredients'])) {
        $r['ingredients'] = array_values(array_filter(array_map(
            'trim',
            preg_split("/\r\n|\n|•|,/u", $r['ingredients'])
        )));
    }

    // Normalisation étapes
    if (isset($r['etapes']) && is_string($r['etapes'])) {
        $r['etapes'] = array_values(array_filter(array_map(
            'trim',
            preg_split("/\r\n|\n/u", $r['etapes'])
        )));
    }

    if (
        empty($r['ingredients']) ||
        empty($r['etapes']) ||
        !is_array($r['ingredients']) ||
        !is_array($r['etapes'])
    ) {
        continue;
    }

    // Auteur forcé (sécurité)
    $r['auteur'] = $_SESSION['user']['nom'];

    $model->ajouterRecetteDepuisJson($r);
    $imported++;
}

if ($imported === 0) {
    header("Location: /index.php?import=empty");
    exit;
}

// Nettoyage session Vision
unset($_SESSION['import_json_payload']);

// Redirection finale
header("Location: /index.php?import=ok&nb=" . $imported);
exit;
