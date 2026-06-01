<?php
declare(strict_types=1);

// ===============================
// INITIALISATION
// ===============================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root = dirname(__DIR__, 2);

require_once $root . '/vendor/autoload.php';
require_once $root . '/config/database.php';
require_once $root . '/app/models/RecetteModel.php';
require_once $root . '/app/models/SelectionModel.php';

use Mpdf\Mpdf;

// ===============================
// RÉCUPÉRATION DES IDS
// ===============================

$idsParam = trim((string)($_GET['ids'] ?? ''));
$ids = $idsParam !== ''
    ? array_filter(array_map('intval', explode(',', $idsParam)))
    : [];

if (empty($ids)) {
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId > 0) {
        $selectionModel = new SelectionModel(getPDO());
        $ids = $selectionModel->getSelectedRecetteIds($userId);
    }
}

if (empty($ids)) {
    die('Aucune recette sélectionnée');
}

// ===============================
// INITIALISATION MODÈLE + MPDF
// ===============================

$model = new RecetteModel();

$mpdf = new Mpdf([
    'format'        => 'A4',
    'margin_left'   => 12,
    'margin_right'  => 12,
    'margin_top'    => 12,
    'margin_bottom' => 12,
    'tempDir'       => $root . '/tmp/mpdf',
]);

// ===============================
// CHARGEMENT DU CSS PDF
// ===============================

$cssFile = __DIR__ . '/pdf.css';
if (is_file($cssFile)) {
    $css = file_get_contents($cssFile);
    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
}

// ===============================
// GÉNÉRATION DES RECETTES
// ===============================

$index = 0;

foreach ($ids as $id) {

    $recette = $model->getRecetteComplete($id);
    if (!$recette) {
        continue;
    }

    // Saut de page entre recettes
    if ($index > 0) {
        $mpdf->AddPage();
    }

    // Rendu HTML via le template EXISTANT
    ob_start();
    require __DIR__ . '/template_recette.php';
    $html = ob_get_clean();

    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $index++;
}

// ===============================
// SORTIE DU PDF
// ===============================

$mpdf->Output('recettes-selection.pdf', 'I');
