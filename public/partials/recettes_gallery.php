<div class="gallery-grid">

<?php foreach ($recettes as $recette): ?>
 <article class="gallery-card">

 <?php if (!empty($_SESSION['user']['id'])): ?>

  <!-- 🛒 Sélection (liste de courses) -->
  <button
    type="button"
    class="btn-select-recette vignette-select <?= !empty($recette['is_checked']) ? 'is-selected' : '' ?>"
    data-recette-id="<?= (int)$recette['id'] ?>"
    title="Sélectionner pour la liste de courses"
    aria-label="Sélection"
  >
    <?= !empty($recette['is_checked']) ? '✔️' : '⬜' ?>
  </button>

  <!-- ★ Favori -->
  <button
    type="button"
    class="btn-favori vignette-favori <?= !empty($recette['is_favori']) ? 'is-favori' : '' ?>"
    data-recette-id="<?= (int)$recette['id'] ?>"
    title="Favori"
    aria-label="Favori"
  >
    <?= !empty($recette['is_favori']) ? '★' : '☆' ?>
  </button>

<?php endif; ?>


  <a href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int)$recette['id'] ?>">


      <!-- IMAGE / PLACEHOLDER -->
<div class="gallery-thumb">
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
    <img
      src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($photo) ?>"
      loading="lazy"
      decoding="async"
      alt=""
    >
<?php else: ?>
    <div class="gallery-placeholder">📷</div>
<?php endif; ?>
</div>



      <!-- TEXTE -->
      <div class="gallery-overlay">
        <h3 class="gallery-title">
          <?= htmlspecialchars($recette['titre']) ?>
        </h3>
        <div class="gallery-meta">
          <?= htmlspecialchars($recette['categorie'] ?? '') ?>
        </div>
      </div>

    </a>
  </article>
<?php endforeach; ?>

</div>
