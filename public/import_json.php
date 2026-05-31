<?php
declare(strict_types=1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth/auth_functions.php';
require_capability('add_recette');

// Endpoint POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Dépendances
require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/models/RecetteModel.php";
// Définir BASE_URL et PUBLIC_URL pour les redirections
require_once dirname(__DIR__) . '/app/base_url.php';

error_log("Import JSON lancé par user #" . ($_SESSION['user']['id'] ?? 'unknown'));

$jsonContent = null;
$data = null;

unset($_SESSION['import_json_error']);

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

// 2️⃣ Soumission du formulaire preview (prioritaire pour refléter les edits)
elseif (
    isset($_POST['titre']) ||
    isset($_POST['ingredients']) ||
    isset($_POST['etapes'])
) {
    $typeCuisson = trim((string)($_POST['type_cuisson'] ?? ''));
    $typeCuissonAutre = trim((string)($_POST['type_cuisson_autre'] ?? ''));
    if ($typeCuissonAutre !== '') {
        $typeCuisson = $typeCuissonAutre;
    } elseif ($typeCuisson === '__autre__') {
        $typeCuisson = '';
    }

    $ingredients = preg_split("/\r\n|\n/u", (string)($_POST['ingredients'] ?? ''));
    $etapes = preg_split("/\r\n|\n/u", (string)($_POST['etapes'] ?? ''));

    $data = [[
        'titre' => trim((string)($_POST['titre'] ?? '')),
        'auteur' => trim((string)($_POST['auteur'] ?? '')),
        'source' => trim((string)($_POST['source'] ?? '')),
        'categorie' => trim((string)($_POST['categorie'] ?? '')),
        'type_recette' => 'recette',
        'type_cuisson' => $typeCuisson,
        'temps_preparation' => ($_POST['temps_preparation'] ?? '') !== '' ? (int) $_POST['temps_preparation'] : null,
        'temps_cuisson' => ($_POST['temps_cuisson'] ?? '') !== '' ? (int) $_POST['temps_cuisson'] : null,
        'temps_repos' => null,
        'nombre_personnes' => ($_POST['nombre_personnes'] ?? '') !== '' ? (int) $_POST['nombre_personnes'] : null,
        'difficulte' => ($_POST['difficulte'] ?? '') !== '' ? (int) $_POST['difficulte'] : null,
        'ingredients' => array_values(array_filter(array_map('trim', $ingredients))),
        'etapes' => array_values(array_filter(array_map('trim', $etapes))),
        'commentaires' => trim((string)($_POST['commentaires'] ?? '')),
    ]];
}

// 3️⃣ JSON généré par le formulaire preview (fallback ancien comportement)
elseif (
    isset($_POST['json_payload']) &&
    is_string($_POST['json_payload']) &&
    trim($_POST['json_payload']) !== ''
) {
    $jsonContent = $_POST['json_payload'];
}

// 4️⃣ Rien reçu → retour propre au formulaire
else {
    header("Location: " . PUBLIC_URL . "/import_json_form.php");
    exit;
}

if ($data === null && $jsonContent === false) {
    die("Impossible de lire le JSON");
}

// Décodage JSON
if ($data === null) {
    $data = json_decode($jsonContent, true);
}

if ($data === null || !is_array($data)) {
    die("JSON invalide : " . json_last_error_msg());
}

/*
|--------------------------------------------------------------------------
| IMPORT EN BASE (LOGIQUE EXISTANTE, CONSERVÉE)
|--------------------------------------------------------------------------
*/
try {
    $model = new RecetteModel();
    $imported = 0;
    $duplicates = 0;
    $duplicateId = null;

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

        try {
            $model->ajouterRecetteDepuisJson($r);
            $imported++;
        } catch (DuplicateRecetteException $e) {
            $duplicates++;
            if ($duplicateId === null) {
                $duplicateId = $e->getExistingId();
            }
            continue;
        }
    }
} catch (Throwable $e) {
    error_log('[import_json] ' . $e->getMessage());

    if (!empty($data[0]) && is_array($data[0])) {
        $_SESSION['import_json_payload'] = json_encode($data[0], JSON_UNESCAPED_UNICODE);
    }
    $_SESSION['import_json_error'] = "L'import a échoué : " . $e->getMessage();

    header("Location: " . PUBLIC_URL . "/import_preview.php");
    exit;
}

if ($imported === 0 && $duplicates === 0) {
    header("Location: " . PUBLIC_URL . "/index.php?import=empty");
    exit;
}

// Nettoyage session Vision
unset($_SESSION['import_json_payload']);

// Redirection finale
header(
    "Location: " . PUBLIC_URL . "/index.php?import=ok&nb=" . $imported
    . "&dup=" . $duplicates
    . ($duplicateId ? "&dup_id=" . (int) $duplicateId : "")
);
exit;
