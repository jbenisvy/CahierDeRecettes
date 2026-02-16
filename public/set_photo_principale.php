<?php


require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/controllers/RecetteController.php';
require_once __DIR__ . '/auth/auth_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();
require_capability('edit_recette');

if (!isset($_GET['id'], $_GET['recette'])) {
    die('Paramètres manquants');
}

$photoId = (int) $_GET['id'];
$recetteId = (int) $_GET['recette'];

$controller = new RecetteController();
$recette = $controller->getRecetteComplete($recetteId);
if (!$recette || empty($recette['recette'])) {
    die("Recette introuvable");
}
if (
    ($_SESSION['user']['role'] ?? '') !== 'admin'
    && ($_SESSION['user']['nom'] ?? '') !== ($recette['recette']['auteur'] ?? '')
) {
    http_response_code(403);
    die("Accès interdit");
}

$controller->definirPhotoPrincipale($photoId, $recetteId);

header('Location: edit_recette.php?id=' . $recetteId);
exit;
