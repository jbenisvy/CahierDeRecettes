<!-- 📋 LISTE DES RECETTES -->
<form id="form-multi-delete" method="post" action="<?= PUBLIC_URL ?>/delete_multiple.php">
<div class="table-shell desktop-only">
<table class="recettes-table recettes-table--cards">
  <thead>
   <tr>
<th data-sort="is_checked" class="col-select">Sélection</th>

  <th class="col-photo">Photo</th>

  <?php if (!empty($_SESSION['user']['id'])): ?>
    <!-- Colonne favori : utilise l'étoile pleine « ★ » en en‑tête pour plus de cohérence -->
    <th data-sort="is_favori" class="col-favori">★</th>
  <?php endif; ?>

  <th data-sort="titre" class="col-title">Titre</th>

  <th data-sort="categorie" class="col-category">Catégorie</th>
  <th data-sort="auteur" class="col-author">Auteur</th>
  <th data-sort="source" class="col-source">Source</th>
  <th data-sort="type_cuisson" class="col-cook">Cuisson</th>
  <th data-sort="type_recette" class="col-type">Type</th>
  <th class="col-actions">Actions</th>
</tr>

  </thead>
  <tbody>
<?php if (empty($recettes)): ?>
    <tr>
      <td colspan="<?= !empty($_SESSION['user']['id']) ? 10 : 9 ?>" class="muted">
  Aucune recette trouvée
</td>

    </tr>
<?php else: ?>
  <?php foreach ($recettes as $recette): ?>
    <?php
      $isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');
      $isOwner = (($_SESSION['user']['nom'] ?? '') === ($recette['auteur'] ?? ''));
      $canEditRow = can('edit_recette') && ($isAdmin || $isOwner);
      $canDeleteRow = can('delete_recette');
    ?>
    <tr>
      <td class="col-select" data-label="Sélection">
        <div class="recipe-cell-value recipe-cell-value--center">
<button
  type="button"
  class="btn-selection btn-select-recette <?= !empty($recette['is_checked']) ? 'is-selected' : '' ?>"
  data-recette-id="<?= (int)$recette['id'] ?>"
  title="Sélection"
  aria-label="Sélection"
>
  <?= !empty($recette['is_checked']) ? '✔️' : '⬜' ?>
</button>
        </div>


</td>


      <td class="col-photo" data-label="Photo">
  <?php
  $photo = null;

  if (!empty($recette['photo_principale'])) {
      if (is_array($recette['photo_principale'])) {
          $photo = $recette['photo_principale']['fichier'] ?? null;
      } else {
          $photo = $recette['photo_principale'];
      }
  }
  ?>

  <?php if ($photo): ?>
        <div class="recipe-cell-value recipe-cell-value--media">
    <img class="recette-thumb"
         src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($photo) ?>"
         loading="lazy"
         decoding="async"
         alt="">
        </div>
  <?php else: ?>
        <div class="recipe-cell-value recipe-cell-value--media">
    <span class="recette-thumb placeholder"
          aria-label="Aucune photo"></span>
        </div>
  <?php endif; ?>
</td>

<?php if (!empty($_SESSION['user']['id'])): ?>
  <td class="col-favori" data-label="Favori">
    <div class="recipe-cell-value recipe-cell-value--center">
    <button
      type="button"
      class="btn-favori <?= !empty($recette['is_favori']) ? 'is-favori' : '' ?>"
      data-recette-id="<?= (int)$recette['id'] ?>"
      title="Favori"
      aria-label="Favori"
    >
      <?= !empty($recette['is_favori']) ? '★' : '☆' ?>
    </button>
    </div>
  </td>
<?php endif; ?>

      <td class="col-title" data-label="Titre">
        <div class="recipe-cell-value recipe-cell-value--title">
        <?php if (!empty($recette['type_recette'])): ?>
          <?php if ($recette['type_recette'] === 'base'): ?>
            <span class="badge badge-base">Base</span>
          <?php elseif ($recette['type_recette'] === 'composant'): ?>
            <span class="badge badge-composant">Composant</span>
          <?php endif; ?>
        <?php endif; ?>
        <span class="recipe-title-text"><?= htmlspecialchars($recette['titre']) ?></span>
        </div>
      </td>

      <td class="col-category" data-label="Catégorie"><span class="recipe-cell-value"><?= htmlspecialchars($recette['categorie'] ?? '') ?></span></td>
      <td class="col-author" data-label="Auteur"><span class="recipe-cell-value"><?= htmlspecialchars($recette['auteur'] ?? '') ?></span></td>
      <td class="col-source" data-label="Source"><span class="recipe-cell-value"><?= htmlspecialchars($recette['source'] ?? '') ?></span></td>
      <td class="col-cook" data-label="Cuisson"><span class="recipe-cell-value"><?= htmlspecialchars($recette['type_cuisson'] ?? '') ?></span></td>
      <td class="col-type" data-label="Type"><span class="recipe-cell-value"><?= htmlspecialchars($recette['type_recette'] ?? 'recette') ?></span></td>

      <td class="actions col-actions" data-label="Actions">
        <div class="recipe-cell-value recipe-cell-value--actions">
        <a href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int)$recette['id'] ?>" title="Voir">👁️</a>
        <?php if ($canEditRow): ?>
          <a href="<?= PUBLIC_URL ?>/edit_recette.php?id=<?= (int)$recette['id'] ?>" title="Éditer">✏️</a>
        <?php endif; ?>
        <?php if ($canDeleteRow): ?>
          <a href="<?= PUBLIC_URL ?>/index.php?action=delete&id=<?= (int)$recette['id'] ?>"
             title="Supprimer"
             onclick="return confirm('Supprimer cette recette ?');">🗑️</a>
        <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
<?php endif; ?>
  </tbody>
</table>
</div>

<div class="recettes-mobile-list mobile-only">
<?php if (empty($recettes)): ?>
  <div class="recette-mobile-card">
    <p class="muted">Aucune recette trouvée</p>
  </div>
<?php else: ?>
  <?php foreach ($recettes as $recette): ?>
    <?php
      $isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');
      $isOwner = (($_SESSION['user']['nom'] ?? '') === ($recette['auteur'] ?? ''));
      $canEditRow = can('edit_recette') && ($isAdmin || $isOwner);
      $canDeleteRow = can('delete_recette');

      $photo = null;
      if (!empty($recette['photo_principale'])) {
          if (is_array($recette['photo_principale'])) {
              $photo = $recette['photo_principale']['fichier'] ?? null;
          } else {
              $photo = $recette['photo_principale'];
          }
      }
    ?>
    <article class="recette-mobile-card">
      <div class="recette-mobile-hero">
        <div class="recette-mobile-photo">
          <?php if ($photo): ?>
            <img
              class="recette-thumb"
              src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($photo) ?>"
              loading="lazy"
              decoding="async"
              alt=""
            >
          <?php else: ?>
            <span class="recette-thumb placeholder" aria-label="Aucune photo"></span>
          <?php endif; ?>
        </div>

        <div class="recette-mobile-main">
          <div class="recette-mobile-main-top">
            <div class="recette-mobile-badges">
              <?php if (!empty($recette['type_recette'])): ?>
                <?php if ($recette['type_recette'] === 'base'): ?>
                  <span class="badge badge-base">Base</span>
                <?php elseif ($recette['type_recette'] === 'composant'): ?>
                  <span class="badge badge-composant">Composant</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="recette-mobile-tools">
              <button
                type="button"
                class="btn-selection btn-select-recette <?= !empty($recette['is_checked']) ? 'is-selected' : '' ?>"
                data-recette-id="<?= (int)$recette['id'] ?>"
                title="Sélection"
                aria-label="Sélection"
              >
                <?= !empty($recette['is_checked']) ? '✔️' : '⬜' ?>
              </button>
              <?php if (!empty($_SESSION['user']['id'])): ?>
                <button
                  type="button"
                  class="btn-favori <?= !empty($recette['is_favori']) ? 'is-favori' : '' ?>"
                  data-recette-id="<?= (int)$recette['id'] ?>"
                  title="Favori"
                  aria-label="Favori"
                >
                  <?= !empty($recette['is_favori']) ? '★' : '☆' ?>
                </button>
              <?php endif; ?>
            </div>
          </div>

          <div class="recette-mobile-title-block">
            <a class="recette-mobile-title-link" href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int)$recette['id'] ?>">
              <?= htmlspecialchars($recette['titre']) ?>
            </a>
          </div>

          <div class="recette-mobile-actions">
            <a href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int)$recette['id'] ?>" title="Voir">👁️</a>
            <?php if ($canEditRow): ?>
              <a href="<?= PUBLIC_URL ?>/edit_recette.php?id=<?= (int)$recette['id'] ?>" title="Éditer">✏️</a>
            <?php endif; ?>
            <?php if ($canDeleteRow): ?>
              <a href="<?= PUBLIC_URL ?>/index.php?action=delete&id=<?= (int)$recette['id'] ?>"
                 title="Supprimer"
                 onclick="return confirm('Supprimer cette recette ?');">🗑️</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="recette-mobile-meta-grid">
        <div class="recette-mobile-meta-line">
          <span class="recette-mobile-label">Catégorie :</span>
          <div class="recette-mobile-value recette-mobile-value--inline"><?= htmlspecialchars($recette['categorie'] ?? '') ?: '—' ?></div>
        </div>

        <div class="recette-mobile-meta-line">
          <span class="recette-mobile-label">Auteur :</span>
          <div class="recette-mobile-value recette-mobile-value--inline"><?= htmlspecialchars($recette['auteur'] ?? '') ?: '—' ?></div>
        </div>

        <div class="recette-mobile-meta-line recette-mobile-meta-line--full">
          <span class="recette-mobile-label">Source :</span>
          <div class="recette-mobile-value recette-mobile-value--inline"><?= htmlspecialchars($recette['source'] ?? '') ?: '—' ?></div>
        </div>

        <div class="recette-mobile-meta-line">
          <span class="recette-mobile-label">Cuisson :</span>
          <div class="recette-mobile-value recette-mobile-value--inline"><?= htmlspecialchars($recette['type_cuisson'] ?? '') ?: '—' ?></div>
        </div>

        <div class="recette-mobile-meta-line">
          <span class="recette-mobile-label">Type :</span>
          <div class="recette-mobile-value recette-mobile-value--inline"><?= htmlspecialchars($recette['type_recette'] ?? 'recette') ?></div>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
</div>

</form>
