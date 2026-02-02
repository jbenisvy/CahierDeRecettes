<?php
$page = $page ?? 'unknown';
$recetteId = $recetteId ?? null;
$view = $view ?? ($_GET['view'] ?? 'list');
?>


<header class="app-topbar">
  <div class="app-topbar__left">
    <a href="/index.php" class="app-logo-link" aria-label="Accueil">
      <img src="/assets/img/logo-memoire-saveur-fond-sombre.png" alt="Mémoire de Saveurs" class="app-logo">
    </a>
  </div>

 <nav class="app-topbar__right" aria-label="Menu">

  <?php if ($page === 'liste'): ?>

    <a class="btn btn-ghost" href="/index.php">Reset filtres</a>
    <a class="btn btn-primary btn-liste-courses"
   href="/liste_courses.php"
   title="Générer la liste de courses à partir des recettes cochées">
   🛒 Liste de courses
</a>

<a class="btn btn-secondary btn-print-selection" href="#">
    🖨️ Imprimer sélection
</a>

<a class="btn btn-secondary btn-pdf-selection" href="#">
    📄 PDF sélection
</a>
<button
    type="button"
    class="btn btn-danger btn-delete-selection"
    id="btn-delete-selection"
    disabled
>
    🗑️ Supprimer sélection
</button>

    <?php if (
    isset($_SESSION['user']) &&
    $_SESSION['user']['role'] === 'admin'
): ?>
 <a class="btn btn-primary"
   href="/import_json_form.php">
   ➕ Import Recette
</a>

<?php endif; ?>


    <div class="view-switch" aria-label="Changer de vue">
      <a class="view-btn <?= $view === 'list' ? 'active' : '' ?>"
         href="/index.php?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>">📄</a>
      <a class="view-btn <?= $view === 'gallery' ? 'active' : '' ?>"
         href="/index.php?<?= http_build_query(array_merge($_GET, ['view' => 'gallery'])) ?>">🖼️</a>
    </div>

  <?php elseif ($page === 'recette'): ?>

    <a class="btn btn-ghost" href="/index.php">← Liste</a>
    <?php if ($recetteId): ?>
      <a class="btn btn-ghost" href="/edit_recette.php?id=<?= (int)$recetteId ?>">Éditer</a>
      <a class="btn btn-ghost" href="/pdf/recette_pdf.php?id=<?= (int)$recetteId ?>">PDF</a>
    <?php endif; ?>
    <button class="btn btn-ghost" onclick="window.print()">Imprimer</button>


  <?php elseif ($page === 'edit'): ?>

    <a class="btn btn-ghost" href="/index.php">← Liste</a>
    <?php if ($recetteId): ?>
      <a class="btn btn-ghost" href="/recette.php?id=<?= (int)$recetteId ?>">← Fiche</a>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit" form="form-edit">Enregistrer</button>

  <?php elseif ($page === 'admin-users'): ?>

    <a class="btn btn-ghost" href="/index.php">← Recettes</a>
    <a class="btn btn-primary" href="/admin/users.php">👥 Utilisateurs</a>

  <?php endif; ?>

  <?php if (isset($_SESSION['user'])): ?>
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

  <?php if (
    isset($_SESSION['user'], $_SESSION['user']['role']) &&
    $_SESSION['user']['role'] === 'admin'
): ?>


  <a href="/admin/users.php" class="btn btn-ghost btn-small">👥 Utilisateurs</a>
<?php endif; ?>


      <a href="/auth/logout.php" class="btn btn-ghost btn-small">Déconnexion</a>
    </div>
  <?php endif; ?>

</nav>

</header>


