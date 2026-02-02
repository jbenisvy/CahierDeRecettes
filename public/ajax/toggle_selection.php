<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/models/SelectionModel.php';

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['error' => 'not_authenticated']);

    exit;
}

$recetteId = (int)($_POST['recette_id'] ?? 0);
if ($recetteId <= 0) {
   echo json_encode(['error' => 'missing_recette_id']);


    exit;
}

$pdo = getPDO();
$model = new SelectionModel($pdo);

try {
    $selected = $model->toggle($userId, $recetteId);

if ($selected) {
    echo json_encode(['status' => 'added']);
} else {
    echo json_encode(['status' => 'removed']);
}

} catch (Throwable $e) {
    echo json_encode(['error' => 'db_error']);

}
exit;
