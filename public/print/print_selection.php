<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/base_url.php';

$idsParam = trim((string) ($_GET['ids'] ?? ''));
$pdfUrl = PUBLIC_URL . '/pdf/pdf_selection.php?disposition=inline';
if ($idsParam !== '') {
    $pdfUrl .= '&ids=' . rawurlencode($idsParam);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Impression des recettes sélectionnées</title>
  <?php require __DIR__ . '/../ui/pwa_head.php'; ?>
  <style>
    html, body {
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
    }

    iframe {
      width: 100%;
      height: 100%;
      border: 0;
      background: #fff;
    }
  </style>
</head>
<body>
  <div class="print-shell">
    <div class="print-bar">
      <div>Le PDF de la sélection est chargé pour impression.</div>
      <div>
        <button type="button" id="print-pdf">Imprimer</button>
        <a href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ouvrir le PDF</a>
      </div>
    </div>
    <iframe
      id="pdf-frame"
      src="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"
      title="PDF des recettes sélectionnées"
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
          window.open(<?= json_encode($pdfUrl) ?>, '_blank', 'noopener');
        }
      }

      printBtn.addEventListener('click', printPdf);
      frame.addEventListener('load', function () {
        setTimeout(printPdf, 500);
      });
    })();
  </script>
</body>
</html>
