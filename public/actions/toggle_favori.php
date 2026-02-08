<?php
declare(strict_types=1);


header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

if (!isset($_POST['recette_id'])) {
    echo json_encode(['error' => 'missing_recette_id']);
    exit;
}

$userId    = (int) $_SESSION['user']['id'];
$recetteId = (int) $_POST['recette_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM user_favoris WHERE user_id = ? AND recette_id = ?"
    );
    $stmt->execute([$userId, $recetteId]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $pdo->prepare(
            "DELETE FROM user_favoris WHERE user_id = ? AND recette_id = ?"
        );
        $stmt->execute([$userId, $recetteId]);

        echo json_encode(['status' => 'removed']);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO user_favoris (user_id, recette_id) VALUES (?, ?)"
        );
        $stmt->execute([$userId, $recetteId]);

        echo json_encode(['status' => 'added']);
    }
} catch (Throwable $e) {
    $msg = '[toggle_favori] ' . $e->getMessage();
    // Log standard serveur
    error_log($msg);
    // Log projet (racine) pour faciliter le debug en hébergement mutualisé
    @error_log($msg . PHP_EOL, 3, __DIR__ . '/../../error.log');
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}

exit;
