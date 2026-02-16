<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/auth/auth_functions.php';

define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('PUBLIC_ROOT', realpath(__DIR__));

require_once PROJECT_ROOT . '/app/helpers/utils.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/services/ChatGPTService.php';
require_once PROJECT_ROOT . '/app/base_url.php';

require_capability('add_recette');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

try {
    $url = trim((string)($_POST['url'] ?? ''));
    if ($url === '') {
        throw new Exception("URL manquante");
    }

    $chat = new ChatGPTService();
    $jsonRecette = $chat->extraireRecetteDepuisUrl($url);

    $_SESSION['import_json_payload'] = $jsonRecette;
    header('Location: ' . PUBLIC_URL . '/import_preview.php');
    exit;
} catch (Throwable $e) {
    echo "<p style='color:red'>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}
