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
require PROJECT_ROOT . '/public/auth/auth_guard.php';
require PROJECT_ROOT . '/public/auth/auth_functions.php';
$recetteOptions = require PROJECT_ROOT . '/config/recette_options.php';
$categoryOptions = $recetteOptions['categories'] ?? [];
$typesRecetteOptions = $recetteOptions['types_recette'] ?? [];


// Définition des constantes BASE_URL et PUBLIC_URL pour gérer les liens en sous-dossier
// L'authentification se charge déjà de définir BASE_URL et PUBLIC_URL
// via public/auth/auth.php qui inclut app/base_url.php.
// On ne réinclut pas base_url ici pour éviter les doublons.
// require_once PROJECT_ROOT . '/app/base_url.php';


// Instanciation du contrôleur
$controller = new RecetteController();


/* =========================
   Messages
========================= */
$action  = $_GET['action'] ?? 'list';
$message = $_GET["message"] ?? null;
$message_import = null;

if (isset($_GET["import"])) {
    if ($_GET["import"] === "ok") {
        $nb = isset($_GET["nb"]) ? (int) $_GET["nb"] : 0;
        $dup = isset($_GET["dup"]) ? (int) $_GET["dup"] : 0;
        $dupId = isset($_GET["dup_id"]) ? (int) $_GET["dup_id"] : 0;

        if ($nb === 0 && $dup > 0) {
            $message_import = "Importation terminée : " . $dup . " doublon(s) ignoré(s).";
        } else {
            $parts = [];
            if ($nb > 0) {
                $parts[] = $nb . " recette(s) importée(s)";
            }
            if ($dup > 0) {
                $parts[] = $dup . " doublon(s) ignoré(s)";
            }
            $message_import = $parts
                ? "Importation : " . implode(" · ", $parts)
                : "Importation réussie !";
        }
    } else {
        $message_import = "Échec de l'importation (fichier invalide ou erreur serveur)";
    }
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
$dashboardFilter = $_GET['dashboard_filter'] ?? null;
$dashboardFilterLabels = [
    'sans_image' => 'Sans image',
    'photos_ia' => 'Photos IA',
    'sans_categorie' => 'Sans catégorie',
    'sans_source' => 'Sans source',
    'sans_type_cuisson' => 'Sans type cuisson',
    'incompletes' => 'Incomplètes',
    'dedup_hash_vides' => 'dedup_hash vides',
    'doublons' => 'Groupes doublons',
];
if (!is_string($dashboardFilter) || !isset($dashboardFilterLabels[$dashboardFilter])) {
    $dashboardFilter = null;
}
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
        require_capability('delete_recette');
        // Suppression d'une recette puis redirection vers la liste avec un message.
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $controller->supprimerRecette($id);
        }
        redirect('index.php?message=' . urlencode('Recette supprimée'));
        exit;
case 'login':

    // Si déjà connecté, on ne montre pas le login :
    if (!empty($_SESSION['user'])) {
        // Redirection vers l'accueil (index.php) via la fonction centralisée
        redirect('index.php');
    }

    require __DIR__ . '/auth/login.php';
    exit;
case 'logout':

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    // Redirige vers la page de login
    redirect('index.php?action=login');
    exit;
case 'register':
  require __DIR__ . '/auth/register.php';
  exit;

case 'forgot_password':
  require __DIR__ . '/auth/forgot_password.php';
  exit;

case 'reset_password':
  require __DIR__ . '/auth/reset_password.php';
  exit;

case 'request_login_link':
  require __DIR__ . '/auth/request_login_link.php';
  exit;

case 'login_link':
  require __DIR__ . '/auth/login_link.php';
  exit;

   default:
    // Toutes les pages hors login/register/forgot/reset nécessitent une connexion.
    // On force la connexion ici pour sécuriser l'accès au listing.
    require_login();
    // Mémorise la dernière liste consultée (filtres + vue + recherche)
    if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
        $_SESSION['last_list_url'] = $_SERVER['REQUEST_URI'];
    }
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
            $userId,            // int|null
            $dashboardFilter
        );
    } catch (Throwable $e) {
        die("ERREUR: " . $e->getMessage());
    }
}


$bodyClass = 'page-liste';
$useBootstrap = (($_GET["import"] ?? "") === "ok" && !empty($_GET["dup"]));
$page = 'liste';

require __DIR__ . '/ui/layout_start.php';
?>



<div class="page">

<?php if ($message_import): ?>
    <div class="alert <?= $_GET["import"] === "ok" ? "alert-success" : "alert-error" ?>">
        <?= htmlspecialchars($message_import) ?>
    </div>
<?php endif; ?>

<?php if (($_GET["import"] ?? "") === "ok" && !empty($_GET["dup"])): ?>
    <div class="modal fade" id="dup-modal" tabindex="-1" aria-labelledby="dup-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dup-modal-label">Doublon détecté</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <?= htmlspecialchars((int) $_GET["dup"]) ?> recette(s) ignorée(s) pendant l'import.
                </div>
                <div class="modal-footer">
                    <?php if (!empty($_GET["dup_id"])): ?>
                        <a class="btn btn-outline-primary" href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int) $_GET["dup_id"] ?>" target="_blank">
                            Voir la recette en doublon
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const modalEl = document.getElementById('dup-modal');
            if (!modalEl) return;

            let tries = 0;
            function tryShow() {
                if (window.bootstrap) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                    return;
                }
                if (tries < 50) {
                    tries++;
                    setTimeout(tryShow, 50);
                }
            }

            if (document.readyState === 'complete') {
                tryShow();
            } else {
                window.addEventListener('load', tryShow);
            }
        })();
    </script>
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
<section class="filters-panel">
  <div class="filters-head">
    <div>
      <h1 class="page-title">Carnet de recettes</h1>
      <p class="page-subtitle">Filtre, explore et compose tes menus en un clin d’œil.</p>
    </div>
  </div>

  <form method="GET" class="search-form filters-grid">

    <input
        type="text"
        name="q"
        class="filter-query"
        placeholder="Rechercher une recette…"
        value="<?= htmlspecialchars($recherche ?? '') ?>"
    >

    <select name="categorie" class="filter-categories">
        <option value="">Toutes les catégories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>"
                <?= ($categorie === $cat) ? 'selected' : '' ?>>
                <?= htmlspecialchars($categoryOptions[$cat] ?? ucfirst((string)$cat)) ?>
            </option>
        <?php endforeach; ?>
    </select>

  <?php
$tagsSelectionnes = $_GET['tags'] ?? [];
if (!is_array($tagsSelectionnes)) {
    $tagsSelectionnes = [];
}
?>

    <select name="type_recette" class="filter-type">
        <option value="">Tous les types</option>
        <?php foreach ($typesRecetteOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars((string)$value) ?>"
                <?= ($typeRecette === $value) ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="auteur" class="filter-author">
        <option value="">Tous les auteurs</option>
        <?php foreach ($auteurs as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>"
                <?= ($auteur === $a) ? 'selected' : '' ?>>
                <?= htmlspecialchars($a) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="source" class="filter-source">
        <option value="">Toutes les sources</option>
        <?php foreach ($sources as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"
                <?= ($source === $s) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s) ?>
            </option>
        <?php endforeach; ?>
    </select>
    
	<select name="type_cuisson" class="filter-cook">
    <option value="">Tous les types de cuisson</option>

    <?php foreach ($typesCuisson as $tc): ?>
        <option value="<?= htmlspecialchars($tc) ?>"
            <?= ($typeCuisson === $tc) ? 'selected' : '' ?>>
            <?= htmlspecialchars(ucfirst($tc)) ?>
        </option>
    <?php endforeach; ?>
</select>

    <select name="dashboard_filter" class="filter-dashboard">
        <option value="">Tous les états dashboard</option>
        <?php foreach ($dashboardFilterLabels as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>"
                <?= ($dashboardFilter === $key) ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if (!empty($_SESSION['user']['id'])): ?>
        <div class="filter-flags">
            <label class="favoris-filter">
                <input
                    type="checkbox"
                    name="favoris"
                    value="1"
                    <?= !empty($_GET['favoris']) ? 'checked' : '' ?>
                >
                Mes favoris
            </label>
            <label class="selection-filter">
                <input
                    type="checkbox"
                    name="selection"
                    value="1"
                    <?= !empty($_GET['selection']) ? 'checked' : '' ?>
                >
                Ma sélection
            </label>
        </div>
    <?php endif; ?>

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

    <button type="submit" class="btn btn-primary filter-submit">🔍 Rechercher</button>

</form>
<?php if ($dashboardFilter !== null): ?>
  <p class="muted" style="margin-top:10px;">
    Filtre dashboard actif: <strong><?= htmlspecialchars($dashboardFilterLabels[$dashboardFilter]) ?></strong>
    · <a href="<?= PUBLIC_URL ?>/index.php">retirer</a>
  </p>
<?php endif; ?>
</section>

<section class="results-panel">
<?php if ($view === 'gallery'): ?>
    <?php include __DIR__ . '/partials/recettes_gallery.php'; ?>
<?php else: ?>
    <?php include __DIR__ . '/partials/recettes_list.php'; ?>
<?php endif; ?>
</section>

</div>
<script src="<?= PUBLIC_URL ?>/assets/js/main.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/liste.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/favoris.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/selection.js"></script>

<?php require __DIR__ . '/ui/layout_end.php'; ?>
