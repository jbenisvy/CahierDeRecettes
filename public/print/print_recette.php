<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/base_url.php';

$recetteId = (int) ($_GET['id'] ?? 0);
if ($recetteId <= 0) {
    http_response_code(400);
    die('ID recette invalide');
}

$pdfUrl = PUBLIC_URL . '/pdf/recette_pdf.php?id=' . $recetteId;
$recipeUrl = PUBLIC_URL . '/recette.php?id=' . $recetteId;
$homeUrl = PUBLIC_URL . '/index.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Impression de la fiche recette</title>
  <?php require __DIR__ . '/../ui/pwa_head.php'; ?>
  <style>
    html,
    body {
      margin: 0;
      height: 100%;
      background: #f4efe7;
      font-family: sans-serif;
    }

    .print-shell {
      display: grid;
      grid-template-rows: auto 1fr;
      height: 100%;
    }

    .print-bar {
      display: flex;
      gap: 12px;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      background: #1f4638;
      color: #fff;
    }

    .print-bar-title {
      font-weight: 700;
      line-height: 1.25;
    }

    .print-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }

    .print-bar button,
    .print-bar a {
      border: 0;
      border-radius: 10px;
      padding: 10px 14px;
      background: #fff3e3;
      color: #2d251d;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      white-space: nowrap;
    }

    .print-bar button {
      background: #c95b45;
      color: #fff;
    }

    iframe {
      width: 100%;
      height: 100%;
      border: 0;
      background: #fff;
    }

    @media (max-width: 680px) {
      .print-bar {
        align-items: stretch;
        flex-direction: column;
      }

      .print-actions {
        justify-content: stretch;
      }

      .print-bar button,
      .print-bar a {
        flex: 1 1 auto;
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <div class="print-shell">
    <div class="print-bar">
      <div class="print-bar-title">Fiche PDF prête à imprimer</div>
      <div class="print-actions">
        <button type="button" id="print-pdf">Imprimer</button>
        <a href="<?= htmlspecialchars($recipeUrl, ENT_QUOTES, 'UTF-8') ?>">Retour fiche</a>
        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Accueil</a>
        <a href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ouvrir PDF</a>
      </div>
    </div>
    <iframe
      id="pdf-frame"
      src="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"
      title="PDF de la fiche recette"
    ></iframe>
  </div>

  <?php require __DIR__ . '/../ui/brand_signature.php'; ?>
  <script>
    (function () {
      const frame = document.getElementById('pdf-frame');
      const printBtn = document.getElementById('print-pdf');

      function printPdf() {
        try {
          frame.contentWindow.focus();
          frame.contentWindow.print();
        } catch (error) {
          window.print();
        }
      }

      printBtn.addEventListener('click', printPdf);
    })();
  </script>
</body>
</html>
