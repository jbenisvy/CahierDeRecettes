<!-- 📋 LISTE DES RECETTES -->
<form id="form-multi-delete" method="post" action="<?= PUBLIC_URL ?>/delete_multiple.php">
<div class="table-shell">
<table class="recettes-table recettes-table--cards">
  <thead>
   <tr>
<th data-sort="is_checked" class="col-select">Sélection</th>

  <th class="col-photo">Photo</th>

  <?php if (!empty($_SESSION['user']['id'])): ?>
    <!-- Colonne favori : utilise l'étoile pleine « ★ » en en‑tête pour plus de cohérence -->
    <th data-sort="is_favori" class="col-favori">★</th>
  <?php endif; ?>

  <th data-sort="titre">Titre</th>

  <th data-sort="categorie">Catégorie</th>
  <th data-sort="auteur">Auteur</th>
  <th data-sort="source">Source</th>
  <th data-sort="type_cuisson">Cuisson</th>
  <th data-sort="type_recette">Type</th>
  <th class="col-actions">Actions</th>
  <td class="col-select" data-sort-value="<?= !empty($recette['is_checked']) ? '1' : '0' ?>">

</tr>

  </thead>
  <tbody>
<?php if (empty($recettes)): ?>
    <tr>
      <td colspan="<?= !empty($_SESSION['user']['id']) ? 9 : 8 ?>" class="muted">
  Aucune recette trouvée
</td>

    </tr>
<?php else: ?>
  <?php foreach ($recettes as $recette): ?>
    <tr>
      <td class="col-select" data-label="Sélection">
<button
  type="button"
  class="btn-selection btn-select-recette <?= !empty($recette['is_checked']) ? 'is-selected' : '' ?>"
  data-recette-id="<?= (int)$recette['id'] ?>"
  title="Sélection"
  aria-label="Sélection"
>
  <?= !empty($recette['is_checked']) ? '✔️' : '⬜' ?>
</button>


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
    <img class="recette-thumb"
         src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($photo) ?>"
         alt="">
  <?php else: ?>
    <span class="recette-thumb placeholder"
          aria-label="Aucune photo"></span>
  <?php endif; ?>
</td>

<?php if (!empty($_SESSION['user']['id'])): ?>
  <td class="col-favori" data-label="Favori">
    <button
      type="button"
      class="btn-favori <?= !empty($recette['is_favori']) ? 'is-favori' : '' ?>"
      data-recette-id="<?= (int)$recette['id'] ?>"
      title="Favori"
      aria-label="Favori"
    >
      <?= !empty($recette['is_favori']) ? '★' : '☆' ?>
    </button>
  </td>
<?php endif; ?>

      <td data-label="Titre">
        <?php if (!empty($recette['type_recette'])): ?>
          <?php if ($recette['type_recette'] === 'base'): ?>
            <span class="badge badge-base">Base</span>
          <?php elseif ($recette['type_recette'] === 'composant'): ?>
            <span class="badge badge-composant">Composant</span>
          <?php endif; ?>
        <?php endif; ?>
        <?= htmlspecialchars($recette['titre']) ?>
      </td>

      <td data-label="Catégorie"><?= htmlspecialchars($recette['categorie'] ?? '') ?></td>
      <td data-label="Auteur"><?= htmlspecialchars($recette['auteur'] ?? '') ?></td>
      <td data-label="Source"><?= htmlspecialchars($recette['source'] ?? '') ?></td>
      <td data-label="Cuisson"><?= htmlspecialchars($recette['type_cuisson'] ?? '') ?></td>
      <td data-label="Type"><?= htmlspecialchars($recette['type_recette'] ?? 'recette') ?></td>

      <td class="actions col-actions" data-label="Actions">
        <a href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int)$recette['id'] ?>" title="Voir">👁️</a>
        <a href="<?= PUBLIC_URL ?>/edit_recette.php?id=<?= (int)$recette['id'] ?>" title="Éditer">✏️</a>
        <a href="<?= PUBLIC_URL ?>/index.php?action=delete&id=<?= (int)$recette['id'] ?>"
           title="Supprimer"
           onclick="return confirm('Supprimer cette recette ?');">🗑️</a>
      </td>
    </tr>
  <?php endforeach; ?>
<?php endif; ?>
  </tbody>
</table>
</div>

</form>
