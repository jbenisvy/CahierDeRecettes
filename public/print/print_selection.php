<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/base_url.php';

$idsParam = trim((string) ($_GET['ids'] ?? ''));
if ($idsParam === '') {
    die('Aucune recette sélectionnée');
}

header('Location: ' . PUBLIC_URL . '/pdf/pdf_selection.php?ids=' . rawurlencode($idsParam), true, 302);
exit;
