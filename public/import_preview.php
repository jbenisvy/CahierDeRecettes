<?php
declare(strict_types=1);
session_start();
define('PROJECT_ROOT', dirname(__DIR__));
$options = require PROJECT_ROOT . '/config/recette_options.php';
$categories = $options['categories'];
$modesCuisson = $options['types_cuisson'];

require_once PROJECT_ROOT . '/app/services/RecetteNormalizer.php';
// Charge les constantes BASE_URL et PUBLIC_URL
require_once PROJECT_ROOT . '/app/base_url.php';


if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Accès refusé');
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

$json = null;
$erreur = null;

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


// 🔐 Nettoyage session après chargement réussi


?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Prévisualisation recette</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>



<body class="bg-light">
<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <div class="card shadow-sm">
        <div class="card-body p-4">

          <h1 class="mb-2">Prévisualisation</h1>
          <p class="text-muted mb-4">Complète la recette avant import définitif.</p>

          <?php if ($erreur): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
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
<?php
$final = [[
    'titre' => $recette['titre'],
    'auteur' => $recette['auteur'],
    'source' => $recette['source'],
    'categorie' => $recette['categorie'],
    'type_recette' => 'recette',
    'type_cuisson' => $recette['type_cuisson'],
    'temps_preparation' => $recette['temps_preparation'],
    'temps_cuisson' => $recette['temps_cuisson'],
    'temps_repos' => null,
    'ingredients' => array_values(array_filter(array_map('trim', $recette['ingredients']))),
    'etapes' => array_values(array_filter(array_map('trim', $recette['etapes']))),
    'commentaires' => $recette['commentaires'],
]];
?>

<input type="hidden"
       name="json_payload"
       value="<?= htmlspecialchars(json_encode($final, JSON_UNESCAPED_UNICODE)) ?>">

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
  const select = document.getElementById('type_cuisson');
  const other = document.getElementById('type_cuisson_autre');
  if (!select || !other) return;

  function sync() {
    const isOther = select.value === '__autre__';
    other.style.display = isOther ? 'block' : 'none';
    if (!isOther) other.value = '';
  }

  select.addEventListener('change', sync);
  sync();
})();
</script>

</body>
</html>
