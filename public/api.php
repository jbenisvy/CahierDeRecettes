<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('PUBLIC_ROOT', realpath(__DIR__));

require_once PROJECT_ROOT . '/app/base_url.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/controllers/ApiController.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['error' => 'method_not_allowed']);
}

$controller = new ApiController();
$path = normalizePath($_GET['path'] ?? null);
$publicBaseUrl = getPublicBaseUrl();

try {
    if ($path === '' || $path === 'recipes') {
        $payload = $controller->listRecipes($_GET, $publicBaseUrl);
        respond(200, $payload);
    }

    if (preg_match('/^recipes\/(\d+)$/', $path, $matches) === 1) {
        $recipeId = (int) $matches[1];
        $recipe = $controller->getRecipeById($recipeId, $publicBaseUrl);

        if ($recipe === null) {
            respond(404, ['error' => 'recipe_not_found']);
        }

        respond(200, ['data' => $recipe]);
    }

    respond(404, ['error' => 'not_found']);
} catch (Throwable $e) {
    error_log('[api] ' . $e->getMessage());
    @error_log('[api] ' . $e->getMessage() . PHP_EOL, 3, PROJECT_ROOT . '/error.log');
    respond(500, ['error' => 'server_error']);
}

function normalizePath(?string $path): string
{
    if ($path === null) {
        return '';
    }

    $value = trim($path);
    $value = preg_replace('#/+#', '/', $value) ?? '';
    return trim($value, '/');
}

function getPublicBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $prefix = defined('PUBLIC_URL') ? (string) PUBLIC_URL : '';
    return $scheme . '://' . $host . $prefix;
}

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
