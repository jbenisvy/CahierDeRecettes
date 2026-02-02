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
require __DIR__ . '/ui/header.php';
?>


<div class="page">

<div class="fiche-recette">



<section class="fiche-header">


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
                    src="/uploads/recettes/<?= htmlspecialchars($recette["photo_principale"]["fichier"]) ?>"
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
  <?= $isFavori ? '⭐' : '☆' ?>
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
<script src="/assets/js/main.js"></script>
<script src="/assets/js/favoris.js"></script>

<?php require __DIR__ . '/ui/layout_end.php'; ?>
