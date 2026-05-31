<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PROJECT_ROOT', dirname(__DIR__, 2));

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/controllers/ListeCoursesController.php';
require_once PROJECT_ROOT . '/public/auth/auth_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$allowedOrigin = trim((string) (getenv('SHOPPING_LIST_API_ALLOWED_ORIGIN') ?: ($_ENV['SHOPPING_LIST_API_ALLOWED_ORIGIN'] ?? '')));
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['error' => 'method_not_allowed']);
}

$auth = resolveShoppingListAuth();
if ($auth === null) {
    respond(401, ['error' => 'authentication_required']);
}

$pdo = getPDO();
$controller = new ListeCoursesController($pdo);
$items = $controller->getListeCourses($auth['user_id']);

respond(200, [
    'data' => [
        'user_id' => $auth['user_id'],
        'recipes' => groupShoppingListByRecipe($items),
        'items' => array_values(array_map(static function (array $item): array {
            return [
                'recipe' => (string) ($item['recette'] ?? ''),
                'ingredient' => (string) ($item['ingredient'] ?? ''),
            ];
        }, $items)),
        'plain_text' => buildShoppingListText($items),
    ],
    'meta' => [
        'auth_mode' => $auth['mode'],
        'total_items' => count($items),
        'total_recipes' => count(groupShoppingListByRecipe($items)),
    ],
]);

function resolveShoppingListAuth(): ?array
{
    if (!empty($_SESSION['user']['id'])) {
        sync_current_user_session();

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId > 0) {
            return [
                'mode' => 'session',
                'user_id' => $userId,
            ];
        }
    }

    $configuredToken = trim((string) (getenv('SHOPPING_LIST_API_TOKEN') ?: ($_ENV['SHOPPING_LIST_API_TOKEN'] ?? '')));
    if ($configuredToken === '') {
        return null;
    }

    $bearerToken = getBearerToken();
    if ($bearerToken === null || !hash_equals($configuredToken, $bearerToken)) {
        return null;
    }

    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    if ($userId <= 0) {
        respond(400, ['error' => 'user_id_required']);
    }

    return [
        'mode' => 'token',
        'user_id' => $userId,
    ];
}

function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($header) || $header === '') {
        return null;
    }

    if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $matches) !== 1) {
        return null;
    }

    $token = trim($matches[1]);
    return $token !== '' ? $token : null;
}

function groupShoppingListByRecipe(array $items): array
{
    $grouped = [];

    foreach ($items as $item) {
        $recipe = (string) ($item['recette'] ?? '');
        $ingredient = (string) ($item['ingredient'] ?? '');

        if ($recipe === '' || $ingredient === '') {
            continue;
        }

        if (!isset($grouped[$recipe])) {
            $grouped[$recipe] = [
                'recipe' => $recipe,
                'ingredients' => [],
            ];
        }

        $grouped[$recipe]['ingredients'][] = $ingredient;
    }

    return array_values($grouped);
}

function buildShoppingListText(array $items): string
{
    $lines = [];
    $currentRecipe = null;

    foreach ($items as $item) {
        $recipe = (string) ($item['recette'] ?? '');
        $ingredient = (string) ($item['ingredient'] ?? '');

        if ($recipe === '' || $ingredient === '') {
            continue;
        }

        if ($currentRecipe !== $recipe) {
            $currentRecipe = $recipe;
            if (!empty($lines)) {
                $lines[] = '';
            }
            $lines[] = 'Recette : ' . $recipe;
        }

        $lines[] = '- ' . $ingredient;
    }

    return implode("\n", $lines);
}

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
