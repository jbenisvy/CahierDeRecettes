<?php
declare(strict_types=1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth/auth_functions.php';
require_capability('add_recette');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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

function import_json_debug_log(string $message): void
{
    $line = '[import_json] ' . $message;
    error_log($line);
    @error_log($line . PHP_EOL, 3, dirname(__DIR__) . '/error.log');
}

function import_json_debug_die(string $message): never
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur import</title>';
    echo '<style>body{font-family:Arial,sans-serif;margin:24px;background:#faf7f2;color:#222}';
    echo '.box{max-width:980px;margin:0 auto;background:#fff;border:1px solid #e2d8c8;border-radius:10px;padding:20px}';
    echo 'pre{white-space:pre-wrap;word-break:break-word;background:#f6f6f6;border-radius:6px;padding:12px}';
    echo '</style></head><body><div class="box"><h1>Erreur import recette</h1><pre>';
    echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '</pre></div></body></html>';
    exit;
}

function import_json_split_lines(mixed $value): array
{
    if (is_array($value)) {
        $value = implode("\n", array_map(static fn($item): string => (string) $item, $value));
    }

    if (!is_string($value)) {
        return [];
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $value);
    $parts = explode("\n", $normalized);

    return array_values(array_filter(array_map(static function ($line): string {
        return trim((string) $line);
    }, $parts), static function ($line): bool {
        return $line !== '';
    }));
}

error_log("Import JSON lancé par user #" . ($_SESSION['user']['id'] ?? 'unknown'));
import_json_debug_log('start user=' . ($_SESSION['user']['id'] ?? 'unknown') . ' method=' . ($_SERVER['REQUEST_METHOD'] ?? ''));

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        import_json_debug_log('shutdown ok');
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    import_json_debug_log(
        'fatal type=' . ($error['type'] ?? 'unknown')
        . ' file=' . ($error['file'] ?? 'unknown')
        . ' line=' . ($error['line'] ?? 'unknown')
        . ' message=' . ($error['message'] ?? 'unknown')
    );

    if (!headers_sent()) {
        import_json_debug_die(
            'Erreur fatale PHP' . "\n\n"
            . 'Type: ' . ($error['type'] ?? 'unknown') . "\n"
            . 'Fichier: ' . ($error['file'] ?? 'unknown') . "\n"
            . 'Ligne: ' . ($error['line'] ?? 'unknown') . "\n"
            . 'Message: ' . ($error['message'] ?? 'unknown')
        );
    }
});

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

// 2️⃣ JSON généré par le formulaire preview (prioritaire)
elseif (
    isset($_POST['json_payload']) &&
    is_string($_POST['json_payload']) &&
    trim($_POST['json_payload']) !== ''
) {
    $jsonContent = $_POST['json_payload'];
}

// 3️⃣ Soumission du formulaire preview (fallback sans JS)
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

    $ingredients = import_json_split_lines($_POST['ingredients'] ?? '');
    $etapes = import_json_split_lines($_POST['etapes'] ?? '');

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
        'ingredients' => $ingredients,
        'etapes' => $etapes,
        'commentaires' => trim((string)($_POST['commentaires'] ?? '')),
    ]];
}

// 4️⃣ Rien reçu → retour propre au formulaire
else {
    import_json_debug_log('redirect import_json_form: no payload');
    header("Location: " . PUBLIC_URL . "/import_json_form.php");
    exit;
}

if ($data === null && $jsonContent === false) {
    import_json_debug_die("Impossible de lire le JSON");
}

// Décodage JSON
if ($data === null) {
    $data = json_decode($jsonContent, true);
}

if ($data === null || !is_array($data)) {
    import_json_debug_log('invalid json: ' . json_last_error_msg());
    import_json_debug_die("JSON invalide : " . json_last_error_msg());
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
            $normalizedIngredients = str_replace('•', "\n", $r['ingredients']);
            $normalizedIngredients = str_replace(',', "\n", $normalizedIngredients);
            $r['ingredients'] = import_json_split_lines($normalizedIngredients);
        }

        // Normalisation étapes
        if (isset($r['etapes']) && is_string($r['etapes'])) {
            $r['etapes'] = import_json_split_lines($r['etapes']);
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
            import_json_debug_log('recipe imported title=' . substr((string) ($r['titre'] ?? ''), 0, 120));
        } catch (DuplicateRecetteException $e) {
            $duplicates++;
            if ($duplicateId === null) {
                $duplicateId = $e->getExistingId();
            }
            import_json_debug_log('duplicate title=' . substr((string) ($r['titre'] ?? ''), 0, 120) . ' existing_id=' . $e->getExistingId());
            continue;
        }
    }
} catch (Throwable $e) {
    import_json_debug_log('exception: ' . $e->getMessage());

    if (!empty($data[0]) && is_array($data[0])) {
        $_SESSION['import_json_payload'] = json_encode($data[0], JSON_UNESCAPED_UNICODE);
    }
    $detail = "L'import a échoué : " . $e->getMessage()
        . "\n\nType: " . get_class($e)
        . "\nFichier: " . $e->getFile()
        . "\nLigne: " . $e->getLine();

    if ($e->getPrevious() instanceof Throwable) {
        $detail .= "\n\nCause précédente: " . $e->getPrevious()->getMessage();
    }

    import_json_debug_die($detail);
}

if ($imported === 0 && $duplicates === 0) {
    import_json_debug_log('redirect index empty');
    header("Location: " . PUBLIC_URL . "/index.php?import=empty");
    exit;
}

// Nettoyage session Vision
unset($_SESSION['import_json_payload']);

// Redirection finale
import_json_debug_log('redirect index ok nb=' . $imported . ' dup=' . $duplicates . ' dup_id=' . ($duplicateId ?? 0));
header(
    "Location: " . PUBLIC_URL . "/index.php?import=ok&nb=" . $imported
    . "&dup=" . $duplicates
    . ($duplicateId ? "&dup_id=" . (int) $duplicateId : "")
);
exit;
