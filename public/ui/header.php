<?php
if (defined('HEADER_RENDERED')) {
    return;
}
define('HEADER_RENDERED', true);
require_once __DIR__ . '/../auth/auth_functions.php';

$page = $page ?? 'unknown';
$recetteId = $recetteId ?? null;
$view = $view ?? ($_GET['view'] ?? 'list');
$lastListUrl = $_SESSION['last_list_url'] ?? (PUBLIC_URL . '/index.php');
if (!is_string($lastListUrl) || trim($lastListUrl) === '') {
    $lastListUrl = PUBLIC_URL . '/index.php';
}
?>


<header class="app-topbar">
  <div class="app-topbar__left">
    <a href="<?= PUBLIC_URL ?>/index.php" class="app-logo-link" aria-label="Accueil">
      <img src="<?= PUBLIC_URL ?>/assets/img/logo-memoire-saveur-fond-sombre.png" alt="Mémoire de Saveurs" class="app-logo">
      <span class="app-logo-text">
        <span class="app-logo-title">Mémoire</span>
        <span class="app-logo-sub">de Saveurs</span>
      </span>
    </a>
  </div>

  <button
    type="button"
    class="app-topbar__toggle"
    aria-label="Ouvrir le menu"
    aria-controls="app-topbar-menu"
    aria-expanded="false"
    data-menu-toggle
  >
    ☰
  </button>

<nav class="app-topbar__right" id="app-topbar-menu" aria-label="Menu">
  <?php
    $canAdd = isset($_SESSION['user']) && can('add_recette');
    $canEdit = isset($_SESSION['user']) && can('edit_recette');
    $canDelete = isset($_SESSION['user']) && can('delete_recette');
    $isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
  ?>

  <?php if ($page === 'liste'): ?>

    <a class="btn btn-ghost" href="<?= PUBLIC_URL ?>/index.php">Reset filtres</a>
    <a class="btn btn-primary btn-liste-courses"
   href="<?= PUBLIC_URL ?>/liste_courses.php"
   title="Générer la liste de courses à partir des recettes cochées">
   🛒 Liste de courses
</a>

<a class="btn btn-secondary btn-print-selection" href="#">
    🖨️ Imprimer sélection
</a>

<a class="btn btn-secondary btn-pdf-selection" href="#">
    📄 PDF sélection
</a>
<?php if ($canDelete): ?>
  <button
      type="button"
      class="btn btn-danger btn-delete-selection"
      id="btn-delete-selection"
      disabled
  >
      🗑️ Supprimer sélection
  </button>
<?php endif; ?>

    <?php if ($canAdd): ?>
 <a class="btn btn-primary"
   href="<?= PUBLIC_URL ?>/import_json_form.php">
   ➕ Import Recette
</a>
<?php endif; ?>


    <div class="view-switch" aria-label="Changer de vue">
      <a class="view-btn <?= $view === 'list' ? 'active' : '' ?>"
         href="<?= PUBLIC_URL ?>/index.php?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>">📄</a>
      <a class="view-btn <?= $view === 'gallery' ? 'active' : '' ?>"
         href="<?= PUBLIC_URL ?>/index.php?<?= http_build_query(array_merge($_GET, ['view' => 'gallery'])) ?>">🖼️</a>
    </div>

  <?php elseif ($page === 'recette'): ?>

    <a class="btn btn-ghost" href="<?= htmlspecialchars($lastListUrl) ?>">← Liste</a>
    <?php if ($recetteId && $canEdit): ?>
      <a class="btn btn-ghost" href="<?= PUBLIC_URL ?>/edit_recette.php?id=<?= (int)$recetteId ?>">Éditer</a>
    <?php endif; ?>
    <?php if ($recetteId): ?>
      <a class="btn btn-ghost" href="<?= PUBLIC_URL ?>/pdf/recette_pdf.php?id=<?= (int)$recetteId ?>">PDF</a>
    <?php endif; ?>
    <button
      type="button"
      class="btn btn-ghost"
      data-open-convertisseur
    >
      Convertisseur
    </button>
    <?php if ($recetteId): ?>
      <a
        class="btn btn-ghost"
        href="<?= PUBLIC_URL ?>/pdf/recette_pdf.php?id=<?= (int)$recetteId ?>"
        target="_blank"
        rel="noopener"
      >Imprimer</a>
    <?php endif; ?>


  <?php elseif ($page === 'edit'): ?>

    <a class="btn btn-ghost" href="<?= htmlspecialchars($lastListUrl) ?>">← Liste</a>
    <?php if ($recetteId): ?>
      <a class="btn btn-ghost" href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int)$recetteId ?>">← Fiche</a>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit" form="form-edit">Enregistrer</button>

  <?php elseif ($page === 'admin-users' || $page === 'admin-settings'): ?>

    <a class="btn btn-ghost" href="<?= htmlspecialchars($lastListUrl) ?>">← Recettes</a>
    <a class="btn btn-primary" href="<?= PUBLIC_URL ?>/admin/settings.php">⚙️ Paramètres</a>

  <?php elseif ($page === 'tutorial'): ?>

    <a class="btn btn-ghost" href="<?= htmlspecialchars($lastListUrl) ?>">← Recettes</a>
    <a class="btn btn-primary" href="<?= PUBLIC_URL ?>/tutorial.php">Tutoriel</a>

  <?php endif; ?>

  <?php if (isset($_SESSION['user'])): ?>
    <a
      href="https://sanstracasdigital.fr/FreshRSS/p/i/"
      class="btn btn-ghost btn-small"
      target="_blank"
      rel="noopener noreferrer"
    >
      📰 Info
    </a>
    <a href="<?= PUBLIC_URL ?>/tutorial.php" class="btn btn-ghost btn-small">📘 Tutoriel</a>

    <div class="user-info">
      <span class="user-name">
        👤 <?= htmlspecialchars($_SESSION['user']['nom']) ?>
       <?php if (
    isset($_SESSION['user'], $_SESSION['user']['role']) &&
    $_SESSION['user']['role'] === 'admin'
): ?>

          <span class="badge-admin">admin</span>
        <?php endif; ?>
      </span>

  <?php if ($isAdmin): ?>


  <a href="<?= PUBLIC_URL ?>/admin/settings.php" class="btn btn-ghost btn-small">⚙️ Paramètres</a>
<?php endif; ?>


      <a href="<?= BASE_URL ?>/?action=logout" class="btn btn-ghost btn-small">Déconnexion</a>

    </div>
  <?php endif; ?>

</nav>

</header>
