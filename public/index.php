<?php
declare(strict_types=1);

/* =========================
   BOOTSTRAP GLOBAL
========================= */

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Constantes globales
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('PUBLIC_ROOT', realpath(__DIR__));

// Configuration environnement
$config = require PROJECT_ROOT . '/config/config.php';

if ($config['env'] === 'prod') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

/* =========================
   INCLUDES
========================= */

require PROJECT_ROOT . '/config/database.php';
require PROJECT_ROOT . '/app/controllers/RecetteController.php';
require PROJECT_ROOT . '/public/auth/auth.php';

// Définition des constantes BASE_URL et PUBLIC_URL pour gérer les liens en sous-dossier
require_once PROJECT_ROOT . '/app/base_url.php';


// Instanciation du contrôleur
$controller = new RecetteController();


/* =========================
   Messages
========================= */
$action  = $_GET['action'] ?? 'list';
$message = $_GET["message"] ?? null;
$message_import = null;

if (isset($_GET["import"])) {
    $message_import = $_GET["import"] === "ok"
        ? "Importation réussie !"
        : "Échec de l'importation (fichier invalide ou erreur serveur)";
}

/* =========================
   Filtres
========================= */
$recherche   = $_GET["q"] ?? null;
$categorie   = $_GET["categorie"] ?? null;
$auteur      = $_GET["auteur"] ?? null;
$source      = $_GET["source"] ?? null;
$typesCuisson = $controller->getTypesCuisson();
$typeRecette = $_GET["type_recette"] ?? null;
$typeCuisson = $_GET["type_cuisson"] ?? null;
$tagsSelectionnes = $_GET['tags'] ?? [];
if (!is_array($tagsSelectionnes)) {
    $tagsSelectionnes = [];
}

// Filtre favoris : si la case est cochée, on ne veut que les recettes en favoris.
// On récupère également l'id utilisateur pour marquer les favoris et filtrer.
$favorisFilter = false;
$userId = null;

if (!empty($_SESSION['user']['id'])) {
    $userId = (int) $_SESSION['user']['id'];
    // le paramètre peut être "favoris" ou "favoris=1"
    if (isset($_GET['favoris']) && $_GET['favoris'] === '1') {
        $favorisFilter = true;
    }
}
// ===============================
// Filtre "sélection" (OBLIGATOIRE)
// ===============================
$selectionFilter = false;

if (!empty($_SESSION['user']['id'])) {
    if (isset($_GET['selection']) && $_GET['selection'] === '1') {
        $selectionFilter = true;
    }
}



/* =========================
   Listes pour filtres
========================= */
$categories = $controller->getCategories();
$auteurs    = $controller->getAuteurs();
$sources    = $controller->getSources();
$tags       = $controller->getTousLesTags();
$typesCuisson = $controller->getTypesCuisson();

$view = $_GET['view'] ?? 'list'; // list | gallery

/* =========================
   Actions
========================= */
switch ($action) {

    case 'delete':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $controller->supprimerRecette($id);
        }
        header("Location: index.php?message=" . urlencode("Recette supprimée"));
        exit;

   default:
    try {
    $recettes = $controller->getToutesRecettes(
    $recherche,
    $categorie,
    $auteur,
    $source,
    $typeRecette,
    $typeCuisson,
    $tagsSelectionnes,
    $favorisFilter,     // bool
    $selectionFilter,   // bool
    $userId             // int|null
);

    } catch (Throwable $e) {
        die("ERREUR: " . $e->getMessage());
    }
}


$bodyClass = 'page-liste';
$page = 'liste';

require __DIR__ . '/ui/layout_start.php';
require __DIR__ . '/ui/header.php';
?>



<div class="page">

<?php if ($message_import): ?>
    <div class="alert <?= $_GET["import"] === "ok" ? "alert-success" : "alert-error" ?>">
        <?= htmlspecialchars($message_import) ?>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if (($_GET['import'] ?? '') === 'empty'): ?>
    <div class="alert alert-error">
        ⚠️ Aucune recette valide n’a été importée
    </div>
<?php endif; ?>

<!-- 🔍 FORMULAIRE DE RECHERCHE -->
<form method="GET" class="search-form">

    <input
        type="text"
        name="q"
        placeholder="Rechercher une recette…"
        value="<?= htmlspecialchars($recherche ?? '') ?>"
    >

    <select name="categorie">
        <option value="">Toutes les catégories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>"
                <?= ($categorie === $cat) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
            </option>
        <?php endforeach; ?>
    </select>

  <?php
$tagsSelectionnes = $_GET['tags'] ?? [];
if (!is_array($tagsSelectionnes)) {
    $tagsSelectionnes = [];
}
?>

<fieldset class="filter-tags">
    <legend>Tags</legend>

    <?php foreach ($tags as $tag): ?>
        <label class="tag-filter">
            <input type="checkbox"
       name="tags[]"
       value="<?= (int)$tag['id'] ?>"
       <?= in_array((int)$tag['id'], $tagsSelectionnes) ? 'checked' : '' ?>>

            <?= htmlspecialchars($tag['nom']) ?>
        </label>
    <?php endforeach; ?>
</fieldset>

    
	<select name="type_cuisson">
    <option value="">Tous les types de cuisson</option>

    <?php foreach ($typesCuisson as $tc): ?>
        <option value="<?= htmlspecialchars($tc) ?>"
            <?= ($typeCuisson === $tc) ? 'selected' : '' ?>>
            <?= htmlspecialchars(ucfirst($tc)) ?>
        </option>
    <?php endforeach; ?>
</select>

    <select name="type_recette">
        <option value="">Tous les types</option>
        <option value="recette" <?= $typeRecette === 'recette' ? 'selected' : '' ?>>Recettes</option>
        <option value="base" <?= $typeRecette === 'base' ? 'selected' : '' ?>>Bases</option>
        <option value="composant" <?= $typeRecette === 'composant' ? 'selected' : '' ?>>Composants</option>
    </select>

    <select name="auteur">
        <option value="">Tous les auteurs</option>
        <?php foreach ($auteurs as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>"
                <?= ($auteur === $a) ? 'selected' : '' ?>>
                <?= htmlspecialchars($a) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="source">
        <option value="">Toutes les sources</option>
        <?php foreach ($sources as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"
                <?= ($source === $s) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if (!empty($_SESSION['user']['id'])): ?>
        <label class="favoris-filter">
            <input
    type="checkbox"
    name="favoris"
    value="1"
    <?= !empty($_GET['favoris']) ? 'checked' : '' ?>
  >
  Mes favoris
        </label>
    <?php endif; ?>
    
	<?php if (!empty($_SESSION['user']['id'])): ?>
    <label class="selection-filter">
        <input
            type="checkbox"
            name="selection"
            value="1"
            <?= !empty($_GET['selection']) ? 'checked' : '' ?>
        >
        Ma sélection
    </label>
<?php endif; ?>


    <button type="submit">🔍 Rechercher</button>

</form>
<?php if ($view === 'gallery'): ?>
    <?php include __DIR__ . '/partials/recettes_gallery.php'; ?>
<?php else: ?>
    <?php include __DIR__ . '/partials/recettes_list.php'; ?>
<?php endif; ?>

</div>
<script src="<?= PUBLIC_URL ?>/assets/js/main.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/liste.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/favoris.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/selection.js"></script>

<?php require __DIR__ . '/ui/layout_end.php'; ?>
