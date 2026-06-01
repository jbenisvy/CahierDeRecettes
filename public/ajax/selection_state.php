<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/models/SelectionModel.php';

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    echo json_encode([
        'count' => 0,
        'ids' => [],
    ]);
    exit;
}

$pdo = getPDO();
$model = new SelectionModel($pdo);
$ids = $model->getSelectedRecetteIds($userId);

echo json_encode([
    'count' => count($ids),
    'ids' => $ids,
]);
exit;
