<?php


session_start();

require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/controllers/RecetteController.php";
$pdo = getPDO();


$controller = new RecetteController();

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Recette introuvable");
}

$id = (int) $_GET["id"];
$recette = $controller->getRecetteComplete($id);

if (!$recette) {
    die("Recette inexistante");
}

/* ===============================
   FAVORI : LECTURE ÉTAT INITIAL
   =============================== */

$isFavori = false;

if (!empty($_SESSION['user']['id'])) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_favoris
        WHERE user_id = :user_id
          AND recette_id = :recette_id
        LIMIT 1
    ");
    $stmt->execute([
        'user_id' => (int) $_SESSION['user']['id'],
        'recette_id' => $id
    ]);

    $isFavori = (bool) $stmt->fetchColumn();
}

$recette['is_favori'] = $isFavori;


// ===============================
// Préparation intelligente (solution 2)
// ===============================

$etapes = $recette["etapes"] ?? [];

$SEUIL_ETAPES = 6; // nombre d'étapes max en colonne gauche

$prep_gauche = array_slice($etapes, 0, $SEUIL_ETAPES);
$prep_droite = array_slice($etapes, $SEUIL_ETAPES);

if (!$recette) {
    die("Recette inexistante");
}


// 🔹 Photo principale (déjà calculée côté Model)


// 🔹 Helpers d'affichage (évite les pages moches quand vide)
function v(?string $value, string $fallback = "Non renseigné"): string {
    $value = trim((string)$value);
    return $value !== "" ? htmlspecialchars($value) : $fallback;
}

function stars($n): string {
    $n = (int)$n;
    if ($n < 1) return "Non renseigné";
    $n = max(1, min(5, $n));
    return str_repeat("★", $n) . str_repeat("☆", 5 - $n);
}

$page='recette';
$recetteId=$id;
?>
<?php
$bodyClass = 'page-recette';
$page = 'recette';
$recetteId = $id;

require __DIR__ . '/ui/layout_start.php';
?>


<div class="page">

<div class="fiche-recette recipe-sheet">



<section class="fiche-header recipe-hero">


    <!-- COLONNE GAUCHE -->
    <div class="fiche-header__left">

        <div class="fiche-title">
            <div>
                <div class="fiche-subtitle">Fiche Recette</div>
                <h1><?= htmlspecialchars($recette["recette"]["titre"]) ?></h1>
            </div>
        </div>

        <div class="fiche-photo-zone">
            <?php if (!empty($recette["photo_principale"])): ?>
                <img
                    src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($recette["photo_principale"]["fichier"]) ?>"
                    alt="Photo de la recette"
                >
            <?php else: ?>
                <div class="photo-placeholder">Aucune photo disponible</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- COLONNE DROITE -->
    <div class="fiche-header__right fiche-infos">



  <div class="fiche-meta">
<div class="fiche-actions">
  <button
  class="btn-favori <?= $isFavori ? 'is-favori' : '' ?>"
  data-recette-id="<?= (int) $id ?>"
  aria-label="Ajouter aux favoris"
  title="Favori"
>
  <?= $isFavori ? '★' : '☆' ?>
</button>


    <div class="meta-separator"></div>
</div>
<?php
$r = $recette['recette'];
$auteur = trim((string)($r['auteur'] ?? ''));
?>

<div class="meta-ligne"><strong>Catégorie :</strong> <?= v($r["categorie"]) ?></div>

<div class="meta-ligne">
  <strong>Auteur :</strong>
  <?= $auteur !== '' ? htmlspecialchars($auteur) : '—' ?>
</div>

<div class="meta-ligne"><strong>Source :</strong> <?= v($r["source"]) ?></div>
<div class="meta-ligne"><strong>Qualité :</strong> <?= v($r["qualite_source"]) ?></div>


  <div class="meta-separator"></div>

  <div class="meta-ligne"><strong>Préparation :</strong> <?= v($r["temps_preparation"]) ?> min</div>
<div class="meta-ligne"><strong>Cuisson :</strong> <?= v($r["temps_cuisson"]) ?> min</div>
<div class="meta-ligne"><strong>Repos :</strong> <?= v($r["temps_repos"]) ?> min</div>
<div class="meta-ligne"><strong>Personnes :</strong> <?= v($r["nombre_personnes"]) ?></div>
<div class="meta-ligne"><strong>Type cuisson :</strong> <?= v($r["type_cuisson"]) ?></div>
<div class="meta-ligne"><strong>Difficulté :</strong> <?= stars($r["difficulte"]) ?></div>


  <div class="meta-separator"></div>

  <div class="meta-ligne">
    <strong>Tags :</strong>
    <?php if (!empty($recette["tags"])): ?>
      <?php foreach ($recette["tags"] as $t): ?>
        <span class="tag"><?= htmlspecialchars($t["nom"]) ?></span>
      <?php endforeach; ?>
    <?php else: ?>
      <span class="muted">Non renseigné</span>
    <?php endif; ?>
  </div>

  <div class="meta-separator"></div>

  <div class="meta-ligne commentaires">
    <strong>Commentaires :</strong><br>
    <?= nl2br(v($recette["recette"]["commentaires"])) ?>
  </div>

</div>

    </div>

</section>





<?php if (!empty($_GET["saved"])): ?>
    <div class="flash-success">
        ✅ Les modifications ont été enregistrées avec succès.
    </div>
<?php endif; ?>


<section class="fiche-bas">

    <!-- COLONNE GAUCHE -->
    <div class="col-gauche">

        <h2>Ingrédients</h2>
        <ul>
            <?php foreach ($recette["ingredients"] as $ing): ?>
                <li><?= htmlspecialchars($ing) ?></li>
            <?php endforeach; ?>
        </ul>

        <?php if (!empty($prep_gauche)): ?>
            <h2>Préparation</h2>
            <ol>
                <?php foreach ($prep_gauche as $etape): ?>
                    <li><?= htmlspecialchars($etape) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

    </div>

    <!-- COLONNE DROITE -->
    <div class="col-droite">

        <?php if (!empty($prep_droite)): ?>
            <h2>Préparation (suite)</h2>
            <ol start="<?= $SEUIL_ETAPES + 1 ?>">
                <?php foreach ($prep_droite as $etape): ?>
                    <li><?= htmlspecialchars($etape) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

    </div>

</section>



</div> <!-- fiche-recette -->
</div>

<div
  class="modal-convertisseur"
  id="convertisseur-modal"
  role="dialog"
  aria-modal="true"
  aria-hidden="true"
  aria-label="Convertisseur culinaire"
>
  <div class="modal-convertisseur__backdrop" data-modal-close></div>
  <div class="modal-convertisseur__panel" role="document">
    <div class="modal-convertisseur__header">
      <h2>Convertisseur culinaire</h2>
      <div class="modal-convertisseur__actions">
        <button
          type="button"
          class="modal-convertisseur__minimize"
          data-modal-minimize
          aria-label="Réduire"
        >
          Réduire
        </button>
        <button
          type="button"
          class="modal-convertisseur__close"
          data-modal-close
          aria-label="Fermer"
        >
          ×
        </button>
      </div>
    </div>
    <div class="modal-convertisseur__body">
      <iframe
        title="Convertisseur culinaire"
        src="https://www.convertisseur.sanstracasdigital.fr"
        loading="lazy"
        referrerpolicy="no-referrer"
      ></iframe>
      <div class="modal-convertisseur__fallback">
        Si le convertisseur ne s'affiche pas, vous pouvez l'ouvrir dans un nouvel onglet.
        <a
          href="https://www.convertisseur.sanstracasdigital.fr"
          target="_blank"
          rel="noopener noreferrer"
        >Ouvrir le convertisseur</a>
      </div>
    </div>
  </div>
</div>

<script src="<?= PUBLIC_URL ?>/assets/js/main.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/favoris.js"></script>
<script>
  (function () {
    const openBtn = document.querySelector('[data-open-convertisseur]');
    const modal = document.getElementById('convertisseur-modal');
    if (!openBtn || !modal) return;

    const closeEls = modal.querySelectorAll('[data-modal-close]');
    const minimizeBtn = modal.querySelector('[data-modal-minimize]');

    const openModal = () => {
      modal.classList.add('is-open');
      modal.classList.remove('is-min');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
      if (minimizeBtn) {
        minimizeBtn.textContent = 'Réduire';
        minimizeBtn.setAttribute('aria-label', 'Réduire');
      }
    };
    const closeModal = () => {
      modal.classList.remove('is-open');
      modal.classList.remove('is-min');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
    };
    const toggleMinimize = () => {
      const isMin = modal.classList.toggle('is-min');
      if (isMin) {
        document.body.classList.remove('modal-open');
        if (minimizeBtn) {
          minimizeBtn.textContent = 'Agrandir';
          minimizeBtn.setAttribute('aria-label', 'Agrandir');
        }
      } else {
        document.body.classList.add('modal-open');
        if (minimizeBtn) {
          minimizeBtn.textContent = 'Réduire';
          minimizeBtn.setAttribute('aria-label', 'Réduire');
        }
      }
    };

    const isMobile = () => {
      return window.matchMedia('(max-width: 720px)').matches;
    };

    openBtn.addEventListener('click', () => {
      if (isMobile()) {
        window.open('https://www.convertisseur.sanstracasdigital.fr', '_blank', 'noopener');
        return;
      }
      if (!modal.classList.contains('is-open')) {
        openModal();
        return;
      }
      if (modal.classList.contains('is-min')) {
        toggleMinimize();
      }
    });
    closeEls.forEach((el) => el.addEventListener('click', closeModal));
    if (minimizeBtn) minimizeBtn.addEventListener('click', toggleMinimize);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  })();
</script>

<?php require __DIR__ . '/ui/layout_end.php'; ?>
