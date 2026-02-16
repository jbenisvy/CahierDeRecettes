<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/auth/auth_functions.php';

// ✅ Définition des constantes AVANT usage
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('PUBLIC_ROOT', realpath(__DIR__));

// ✅ Includes
require_once PROJECT_ROOT . '/app/helpers/utils.php';
require_once PROJECT_ROOT . '/app/services/RecetteNormalizer.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/services/ChatGPTService.php';

// Définir BASE_URL et PUBLIC_URL pour les redirections
require_once PROJECT_ROOT . '/app/base_url.php';

require_capability('add_recette');

// 🧹 Nettoyage des fichiers temporaires (> 2h)
nettoyerDossierTmp(PUBLIC_ROOT . '/uploads/tmp', 2 * 3600);

// Debug (à désactiver en prod)
ini_set('display_errors', '1');
error_reporting(E_ALL);

$chat = new ChatGPTService();

// ✅ Upload temporaire
$uploadDir = PUBLIC_ROOT . '/uploads/tmp/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['image']['tmp_name'])) {
            throw new Exception("Veuillez sélectionner une image");
        }

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                throw new Exception("Impossible de créer le dossier upload");
            }
        }

        $destination = $uploadDir . uniqid('recette_', true) . '.jpg';

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            throw new Exception("Échec de l’upload de l’image");
        }

        $jsonRecette = $chat->extraireRecetteDepuisImageFichier($destination);

        $_SESSION['import_json_payload'] = $jsonRecette;

        header('Location: ' . PUBLIC_URL . '/import_preview.php');
        exit;

    } catch (Throwable $e) {
        echo "<p style='color:red'>" . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
}

http_response_code(405);
echo "Méthode non autorisée";
