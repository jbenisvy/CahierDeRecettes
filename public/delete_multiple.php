<?php


require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/controllers/RecetteController.php";
require_once __DIR__ . '/auth/auth_functions.php';
// Charge BASE_URL/PUBLIC_URL pour redirections
require_once dirname(__DIR__) . '/app/base_url.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();
require_capability('delete_recette');

$controller = new RecetteController();

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];

$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

if (empty($ids)) {
    header("Location: " . PUBLIC_URL . "/index.php?message=" . urlencode("Aucune recette sélectionnée"));
    exit;
}

foreach ($ids as $id) {
    $controller->supprimerRecette($id);
}

header("Location: " . PUBLIC_URL . "/index.php?message=" . urlencode(count($ids) . " recette(s) supprimée(s)"));
exit;
