<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . '/vendor/autoload.php';
require_once $root . '/config/database.php';
require_once $root . '/app/models/RecetteModel.php';

use Mpdf\Mpdf;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID recette invalide');
}

$model   = new RecetteModel();
$recette = $model->getRecetteComplete($id);

if (!$recette) {
    die('Recette introuvable');
}

$css = file_get_contents(__DIR__ . '/pdf.css');

ob_start();
require __DIR__ . '/template_recette.php';
$html = ob_get_clean();

// DEBUG FINAL — afficher le HTML BRUT


$mpdf = new Mpdf([
    'format'  => 'A4',
    'tempDir' => $root . '/tmp/mpdf',
    'margin_left'   => 12,
    'margin_right'  => 12,
    'margin_top'    => 12,
    'margin_bottom' => 12,
]);

$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

$mpdf->Output('recette-' . $id . '.pdf', 'I');
