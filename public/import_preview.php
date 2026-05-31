<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/auth/auth_functions.php';
define('PROJECT_ROOT', dirname(__DIR__));
$options = require PROJECT_ROOT . '/config/recette_options.php';
$categories = $options['categories'] ?? [];
$modesCuisson = $options['types_cuisson'];

require_once PROJECT_ROOT . '/app/services/RecetteNormalizer.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/models/RecetteModel.php';
// Charge les constantes BASE_URL et PUBLIC_URL
require_once PROJECT_ROOT . '/app/base_url.php';


require_capability('add_recette');

ini_set('display_errors', '1');
error_reporting(E_ALL);

$json = null;
$erreur = null;
$importError = null;

if (!empty($_SESSION['import_json_error'])) {
    $importError = (string) $_SESSION['import_json_error'];
    unset($_SESSION['import_json_error']);
}

if (!empty($_SESSION['import_json_payload'])) {


   $raw = trim((string)$_SESSION['import_json_payload']);


    // 1️⃣ Retirer les fences markdown ```json ... ```
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $m)) {
        $raw = trim($m[1]);
    }

    // 2️⃣ Extraire le premier objet JSON si du texte entoure
    if (!str_starts_with($raw, '{') && preg_match('/\{.*\}/s', $raw, $m2)) {
        $raw = trim($m2[0]);
    }

    $json = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $erreur = "JSON invalide : " . json_last_error_msg();
        $json = null;
    }

// 🔁 Normalisation : si le JSON est un tableau avec une seule recette
if (is_array($json) && isset($json[0]) && is_array($json[0])) {
    $json = $json[0];
}
// ❗ Validation finale de structure
if (!is_array($json) || !isset($json['titre'])) {
    $erreur = "Structure de recette invalide";
    $json = null;
}


}

$defaults = [
  'titre' => '',
  'auteur' => $_SESSION['user']['nom'] ?? '',
  'source' => '',
  'categorie' => '',
  'tags' => [],
  'ingredients' => [],
  'etapes' => [],
  'temps_preparation' => null,
  'temps_cuisson' => null,
  'nombre_personnes' => null,
  'type_cuisson' => '',
  'difficulte' => null,
  'commentaires' => ''
];
if ($erreur) {
    $json = null;
}

if (is_array($json)) {
    $json = RecetteNormalizer::fromVision(
        $json,
        $_SESSION['user'] ?? []
    );
}

$recette = array_merge($defaults, $json ?? []);

$duplicateId = null;
try {
    $model = new RecetteModel();
    foreach ($model->getCategories() as $dbCategory) {
        $dbCategory = trim((string)$dbCategory);
        if ($dbCategory === '' || isset($categories[$dbCategory])) {
            continue;
        }
        $categories[$dbCategory] = ucfirst($dbCategory);
    }
    $duplicateId = $model->findDuplicateIdForRecette($recette);
} catch (Throwable $e) {
    $duplicateId = null;
}


// 🔐 Nettoyage session après chargement réussi


?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Prévisualisation recette</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/style.css">
</head>



<body class="bg-light page-import page-import-preview">
<div class="container my-5 import-shell">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <div class="card shadow-sm">
        <div class="card-body p-4">

          <h1 class="mb-2">Prévisualisation</h1>
          <p class="text-muted mb-4">Complète la recette avant import définitif.</p>

          <?php if ($erreur): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
          <?php endif; ?>
          <?php if ($importError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($importError) ?></div>
          <?php endif; ?>
          <?php if ($duplicateId): ?>
            <div class="alert alert-warning">
              Doublon probable détecté. Une recette similaire existe déjà :
              <a href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int) $duplicateId ?>" target="_blank">voir la recette</a>.
            </div>
          <?php endif; ?>

          <!-- Utilise PUBLIC_URL pour fonctionner correctement en sous-dossier -->
          <form method="POST" action="<?= PUBLIC_URL ?>/import_json.php">
            <!-- Titre -->
            <div class="mb-3">
              <label class="form-label">Titre</label>
              <input type="text" name="titre" class="form-control" required
                     value="<?= htmlspecialchars($recette['titre']) ?>">
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Auteur</label>
                <input type="text" name="auteur" class="form-control"
                       value="<?= htmlspecialchars($recette['auteur']) ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Source</label>
                <input type="text" name="source" class="form-control"
                       value="<?= htmlspecialchars($recette['source']) ?>">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Catégorie</label>
                <select name="categorie" class="form-select">
  <option value="">— Choisir —</option>
  <?php foreach ($categories as $value => $label): ?>
    <option value="<?= htmlspecialchars($value) ?>"
      <?= ($recette['categorie'] ?? '') === $value ? 'selected' : '' ?>>
      <?= htmlspecialchars($label) ?>
    </option>
  <?php endforeach; ?>
</select>

              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Type de cuisson</label>
                <select name="type_cuisson" class="form-select" id="type_cuisson">
  <option value="">— Choisir —</option>
  <?php foreach ($modesCuisson as $value => $label): ?>
    <option value="<?= htmlspecialchars($value) ?>"
      <?= ($recette['type_cuisson'] ?? '') === $value ? 'selected' : '' ?>>
      <?= htmlspecialchars($label) ?>
    </option>
  <?php endforeach; ?>
  <option value="__autre__">Autre…</option>
</select>

<input type="text"
   name="type_cuisson_autre"
   id="type_cuisson_autre"
   placeholder="Préciser le type de cuisson"
   style="display:none;">

              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Temps préparation (min)</label>
                <input type="number" name="temps_preparation" class="form-control"
                       value="<?= htmlspecialchars((string)$recette['temps_preparation']) ?>">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Temps cuisson (min)</label>
                <input type="number" name="temps_cuisson" class="form-control"
                       value="<?= htmlspecialchars((string)$recette['temps_cuisson']) ?>">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Difficulté (1–5)</label>
                <input type="number" name="difficulte" min="1" max="5" class="form-control"
                       value="<?= htmlspecialchars((string)$recette['difficulte']) ?>">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Nombre de personnes</label>
                <input type="number" name="nombre_personnes" min="1" class="form-control"
                       value="<?= htmlspecialchars((string)$recette['nombre_personnes']) ?>">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Ingrédients (1 par ligne)</label>
              <textarea name="ingredients" class="form-control" rows="6"><?= htmlspecialchars(implode("\n", (array)$recette['ingredients'])) ?></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">Étapes (1 par ligne)</label>
              <textarea name="etapes" class="form-control" rows="8"><?= htmlspecialchars(implode("\n", (array)$recette['etapes'])) ?></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">Commentaires</label>
              <textarea name="commentaires" class="form-control" rows="3"><?= htmlspecialchars((string)$recette['commentaires']) ?></textarea>
            </div>
            <input type="hidden" name="json_payload" id="json_payload" value="">
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-primary" type="submit">✅ Importer</button>
              <a href="<?= PUBLIC_URL ?>/import_json_form.php" class="btn btn-outline-secondary">↩ Retour</a>
            </div>

          </form>

        </div>
      </div>

    </div>
  </div>
</div>
<script>
(function() {
  const form = document.querySelector('form[action$="import_json.php"]');
  const select = document.getElementById('type_cuisson');
  const other = document.getElementById('type_cuisson_autre');
  if (!form || !select || !other) return;

  const jsonPayloadField = document.getElementById('json_payload');
  const ingredientsField = form.querySelector('textarea[name="ingredients"]');
  const etapesField = form.querySelector('textarea[name="etapes"]');
  const commentairesField = form.querySelector('textarea[name="commentaires"]');

  function splitLines(value) {
    return String(value || '')
      .replace(/\r\n/g, '\n')
      .replace(/\r/g, '\n')
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line !== '');
  }

  function sync() {
    const isOther = select.value === '__autre__';
    other.style.display = isOther ? 'block' : 'none';
    if (!isOther) other.value = '';
  }

  select.addEventListener('change', sync);
  sync();

  form.addEventListener('submit', function () {
    if (!jsonPayloadField) return;

    const typeCuisson = other.value.trim() !== ''
      ? other.value.trim()
      : (select.value === '__autre__' ? '' : select.value);

    const payload = [[
      titre: (form.querySelector('input[name="titre"]')?.value || '').trim(),
      auteur: (form.querySelector('input[name="auteur"]')?.value || '').trim(),
      source: (form.querySelector('input[name="source"]')?.value || '').trim(),
      categorie: (form.querySelector('select[name="categorie"]')?.value || '').trim(),
      type_recette: 'recette',
      type_cuisson: typeCuisson,
      temps_preparation: (form.querySelector('input[name="temps_preparation"]')?.value || '').trim(),
      temps_cuisson: (form.querySelector('input[name="temps_cuisson"]')?.value || '').trim(),
      temps_repos: null,
      nombre_personnes: (form.querySelector('input[name="nombre_personnes"]')?.value || '').trim(),
      difficulte: (form.querySelector('input[name="difficulte"]')?.value || '').trim(),
      ingredients: splitLines(ingredientsField?.value || ''),
      etapes: splitLines(etapesField?.value || ''),
      commentaires: (commentairesField?.value || '').trim()
    ]];

    jsonPayloadField.value = JSON.stringify(payload);

    if (ingredientsField) {
      ingredientsField.dataset.originalName = ingredientsField.name;
      ingredientsField.removeAttribute('name');
    }
    if (etapesField) {
      etapesField.dataset.originalName = etapesField.name;
      etapesField.removeAttribute('name');
    }
    if (commentairesField) {
      commentairesField.dataset.originalName = commentairesField.name;
      commentairesField.removeAttribute('name');
    }
  });
})();
</script>

</body>
</html>
