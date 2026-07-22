<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/base_url.php';
require_once __DIR__ . '/../auth/auth_functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/services/AutoRecipeImageService.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = getPDO();
@set_time_limit(0);
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');

while (ob_get_level() > 0) {
    @ob_end_flush();
}

header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');

$rows = $pdo->query("
    SELECT r.id, r.titre
    FROM recettes r
    WHERE NOT EXISTS (
        SELECT 1
        FROM photos_recettes p
        WHERE p.recette_id = r.id
    )
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$stream = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    flush();
};

$autoRecipeImageService = new AutoRecipeImageService();
$processed = 0;
$generated = 0;
$skipped = 0;
$failed = 0;
$total = count($rows);

$stream([
    'type' => 'start',
    'total' => $total,
]);

foreach ($rows as $row) {
    $recetteId = (int) ($row['id'] ?? 0);
    $titre = trim((string) ($row['titre'] ?? ''));
    if ($recetteId <= 0) {
        continue;
    }

    $processed++;
    $status = 'skipped';
    $message = 'Recette ignorée.';

    try {
        $photoId = $autoRecipeImageService->generateAndAttachAsDefault($recetteId);
        if ($photoId === null) {
            $skipped++;
            $message = 'Recette ignorée car incomplète ou déjà traitée.';
        } else {
            $generated++;
            $status = 'generated';
            $message = 'Photo générée et définie par défaut.';
        }
    } catch (Throwable $e) {
        $failed++;
        $status = 'failed';
        $message = $e->getMessage();
        error_log('[admin/generate_missing_ai_photos_progress] recette_id=' . $recetteId . ' error=' . $e->getMessage());
    }

    $stream([
        'type' => 'progress',
        'processed' => $processed,
        'total' => $total,
        'generated' => $generated,
        'skipped' => $skipped,
        'failed' => $failed,
        'status' => $status,
        'message' => $message,
        'recette_id' => $recetteId,
        'titre' => $titre,
    ]);
}

$stream([
    'type' => 'complete',
    'processed' => $processed,
    'total' => $total,
    'generated' => $generated,
    'skipped' => $skipped,
    'failed' => $failed,
]);
