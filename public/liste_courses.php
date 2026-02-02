<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/controllers/ListeCoursesController.php';

$userId = (int)($_SESSION['user']['id'] ?? 0);

$controller = new ListeCoursesController($pdo);
$liste = $controller->getListeCourses($userId);

// ===============================
// Préparation du texte brut ChatGPT
// ===============================
$texteBrut = "";
$recetteCourante = null;

foreach ($liste as $item) {
    if ($recetteCourante !== $item['recette']) {
        $recetteCourante = $item['recette'];
        $texteBrut .= "\nRecette : " . $recetteCourante . "\n";
    }
    $texteBrut .= "- " . $item['ingredient'] . "\n";
}

$promptChatGPT = <<<TXT
Tu es un assistant culinaire.

Voici une liste d’ingrédients bruts issus de plusieurs recettes.
Je veux que tu :
- regroupes les ingrédients similaires
- proposes une liste de courses claire
- respectes strictement le texte original
- ne modifies pas les quantités existantes

Ingrédients :
$texteBrut
TXT;

// Variables attendues par le layout
$page = 'liste_courses';
$pageTitle = 'Liste de courses';
$bodyClass = 'page-liste-courses';

// layout + header
require __DIR__ . '/ui/layout_start.php';
require __DIR__ . '/ui/header.php';
?>

<div class="page">


  <div class="page-header-actions">
    <div class="page-header-left">
      <h1>🛒 Liste de courses</h1>
      <div class="page-subtitle">Ingrédients issus des recettes sélectionnées</div>
    </div>

    <div class="page-header-right">
      <button type="button" id="btn-copy-chatgpt" class="btn btn-primary">
        🧠 Copier le prompt ChatGPT
      </button>
    </div>
  </div>

  <div class="bloc">
    <?php
    $currentRecette = null;

    foreach ($liste as $item):
        if ($currentRecette !== $item['recette']):
            if ($currentRecette !== null) echo '</ul>';
            $currentRecette = $item['recette'];
            echo '<h2>' . htmlspecialchars($currentRecette) . '</h2><ul>';
        endif;
    ?>
      <li>
        <label>
          <input type="checkbox">
          <?= htmlspecialchars($item['ingredient']) ?>
        </label>
      </li>
    <?php endforeach;

    if ($currentRecette !== null) {
        echo '</ul>';
    }
    ?>
  </div>

  <div class="bloc">
    <h2>🧠 Préparer pour ChatGPT</h2>

    <p class="muted">
      Clique sur le bouton en haut pour copier un prompt prêt à coller dans ChatGPT,
      avec les ingrédients bruts issus de tes recettes sélectionnées.
    </p>

    <textarea id="prompt-chatgpt" rows="18"><?= htmlspecialchars($promptChatGPT) ?></textarea>
  </div>

</div>

<?php


require __DIR__ . '/ui/layout_end.php';
