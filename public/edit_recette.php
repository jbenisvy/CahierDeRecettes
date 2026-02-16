<?php


require __DIR__ . "/../config/database.php";
require __DIR__ . "/../app/controllers/RecetteController.php";
require_once __DIR__ . '/auth/auth_functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();
require_capability('edit_recette');
$options = require __DIR__ . "/../config/recette_options.php";

$controller = new RecetteController();
$dbCategories = $controller->getCategories();

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Recette introuvable");
}

$id = (int) $_GET["id"];
$recette = $controller->getRecetteComplete($id);

if (!$recette) {
    die("Recette inexistante");
}

if (
    ($_SESSION['user']['role'] ?? '') !== 'admin'
    && ($_SESSION['user']['nom'] ?? '') !== ($recette['recette']['auteur'] ?? '')
) {
    http_response_code(403);
    die("Accès interdit");
}

$r = $recette["recette"];
$tousTags = $controller->getTousLesTags();
$tagsActuels = array_map(fn($t) => (int) $t["id"], $recette["tags"] ?? []);

$bodyClass = 'page-edit';
$page = 'edit';
$recetteId = $id;

require __DIR__ . '/ui/layout_start.php';
?>


<div class="page edit-wrap recipe-sheet">

<h1 class="edit-title">Éditer la recette</h1>

<?php if (!empty($_GET["dup"])): ?>
    <div class="alert alert-error">
        Doublon détecté : une recette similaire existe déjà.
    </div>
<?php endif; ?>
<?php if (($_GET['ai_image'] ?? '') === 'ok'): ?>
    <div class="alert alert-success">
        Image générée avec succès. Vous pouvez la définir comme photo principale si besoin.
    </div>
<?php elseif (($_GET['ai_image'] ?? '') === 'preview'): ?>
    <div class="alert alert-error">
        Image générée mais non enregistrée dans la bibliothèque (droits dossier). Prévisualisation temporaire affichée ci-dessous.
    </div>
<?php elseif (($_GET['ai_image'] ?? '') === 'preview_inline'): ?>
    <div class="alert alert-error">
        Image générée mais non enregistrée dans la bibliothèque (droits dossier). Prévisualisation temporaire affichée ci-dessous.
    </div>
<?php elseif (($_GET['ai_image'] ?? '') === 'error'): ?>
    <div class="alert alert-error">
        Échec de génération d'image IA
        <?php if (!empty($_GET['reason'])): ?>
            : <?= htmlspecialchars((string) $_GET['reason']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (($_GET['ai_apply'] ?? '') === 'ok'): ?>
    <div class="alert alert-success">
        La prévisualisation IA a été ajoutée à la recette et définie en photo principale.
    </div>
<?php elseif (($_GET['ai_apply'] ?? '') === 'error'): ?>
    <div class="alert alert-error">
        Impossible d'appliquer la prévisualisation IA
        <?php if (!empty($_GET['reason'])): ?>
            : <?= htmlspecialchars((string) $_GET['reason']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php
$aiPreview = '';
if (!empty($_GET['ai_preview'])) {
    $candidate = basename((string) $_GET['ai_preview']);
    if (preg_match('/^ai_preview_[a-zA-Z0-9.]+\.(png|webp|jpg|jpeg)$/', $candidate)) {
        $previewFile = __DIR__ . '/uploads/tmp/' . $candidate;
        if (is_file($previewFile)) {
            $aiPreview = $candidate;
        }
    }
}
?>
<?php if ($aiPreview !== ''): ?>
    <div class="recette-photo-principale ai-preview">
        <img src="<?= PUBLIC_URL ?>/uploads/tmp/<?= htmlspecialchars($aiPreview) ?>" alt="Prévisualisation image IA">
    </div>
    <form method="post" action="save_ai_preview_photo.php" class="ai-preview-form">
        <input type="hidden" name="recette_id" value="<?= (int) $id ?>">
        <input type="hidden" name="preview_source" value="tmp">
        <input type="hidden" name="ai_preview" value="<?= htmlspecialchars($aiPreview) ?>">
        <button type="submit">✅ Utiliser cette image en photo principale</button>
    </form>
<?php endif; ?>
<?php
$aiPreviewInline = $_SESSION['ai_preview_inline'] ?? '';
if (is_string($aiPreviewInline) && $aiPreviewInline !== '' && (($_GET['ai_image'] ?? '') === 'preview_inline')): ?>
    <?php
    $inlineExt = (string) ($_SESSION['ai_preview_inline_ext'] ?? 'png');
    $inlineMime = $inlineExt === 'jpg' ? 'jpeg' : $inlineExt;
    ?>
    <div class="recette-photo-principale ai-preview">
        <img src="data:image/<?= htmlspecialchars($inlineMime) ?>;base64,<?= htmlspecialchars($aiPreviewInline) ?>" alt="Prévisualisation image IA">
    </div>
    <form method="post" action="save_ai_preview_photo.php" class="ai-preview-form">
        <input type="hidden" name="recette_id" value="<?= (int) $id ?>">
        <input type="hidden" name="preview_source" value="inline">
        <button type="submit">✅ Utiliser cette image en photo principale</button>
    </form>
<?php endif; ?>

<?php if (!empty($recette["photo_principale"])): ?>
    <div class="recette-photo-principale">
        <img src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($recette["photo_principale"]["fichier"]) ?>" alt="">
    </div>
<?php endif; ?>
<form id="form-edit" class="form-recette" method="post" action="update_recette.php">

<input type="hidden" name="id" value="<?= $id ?>">

<label>
    Titre
    <input type="text" name="titre" value="<?= htmlspecialchars($r["titre"]) ?>" required>
</label>

<label>
    Auteur
    <p class="field-readonly">
        <?= htmlspecialchars($r["auteur"] ?? "") ?>
    </p>
</label>

<label>
    Source
    <input type="text" name="source" value="<?= htmlspecialchars($r["source"] ?? "") ?>">
</label>

<label class="form-row">
    <span>Catégorie</span>

    <?php
   $categories = $options['categories'] ?? [];
   foreach ($dbCategories as $dbCategory) {
       $dbCategory = trim((string)$dbCategory);
       if ($dbCategory === '' || isset($categories[$dbCategory])) {
           continue;
       }
       $categories[$dbCategory] = ucfirst($dbCategory);
   }

    ?>

    <select name="categorie">
        <option value="">— Choisir —</option>
        <?php foreach ($categories as $value => $label): ?>
            <option value="<?= $value ?>"
                <?= ($r["categorie"] ?? "") === $value ? "selected" : "" ?>>
                <?= $label ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>
<label class="form-row">
    <span>Type de recette</span>

    <select name="type_recette">
        <?php foreach (($options['types_recette'] ?? []) as $value => $label): ?>
            <option value="<?= htmlspecialchars((string)$value) ?>"
                <?= ($r["type_recette"] ?? 'recette') === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$label) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>


<label class="form-row">
    <span>Temps de cuisson</span>
    <input type="number" min="0" name="temps_cuisson"
           value="<?= htmlspecialchars($r["temps_cuisson"] ?? "") ?>">
    <span class="unit">min</span>
</label>



<label class="form-row">
    <span>Temps de repos</span>
    <input type="number" min="0" name="temps_repos"
           value="<?= htmlspecialchars($r["temps_repos"] ?? "") ?>">
    <span class="unit">minute(s)</span>
</label>


<label>
    Nombre de personnes
    <input type="text" name="nombre_personnes" value="<?= htmlspecialchars($r["nombre_personnes"] ?? "") ?>">
</label>

<label class="form-row">
    <span>Type de cuisson</span>

    <?php
   $modesCuisson = $options['types_cuisson'];


    ?>

   <select name="type_cuisson" id="type_cuisson">
    <option value="">— Choisir —</option>

    <?php foreach ($modesCuisson as $value => $label): ?>
        <option value="<?= $value ?>"
            <?= ($r["type_cuisson"] ?? "") === $value ? "selected" : "" ?>>
            <?= $label ?>
        </option>
    <?php endforeach; ?>

    <option value="__autre__">Autre…</option>
</select>


    <input type="text"
       name="type_cuisson_autre"
       id="type_cuisson_autre"
       placeholder="Préciser le type de cuisson"
       style="display:none;">

</label>


<label>
    Difficulté (1 à 5)
    <input type="number" min="1" max="5" name="difficulte" value="<?= htmlspecialchars($r["difficulte"] ?? "") ?>">
</label>

<label>
    Commentaires
    <textarea name="commentaires"><?= htmlspecialchars($r["commentaires"] ?? "") ?></textarea>
</label>

<button type="submit">💾 Enregistrer</button>

</form>
<hr>



<h2>👀 Contenu de la recette</h2>
<hr>

<div class="section-card">
<h2>🏷 Tags</h2>

<div class="tags-edit">
    <?php if (!empty($recette["tags"])): ?>
        <?php foreach ($recette["tags"] as $tag): ?>
            <span class="tag">
                <?= htmlspecialchars($tag["nom"]) ?>
                <a href="remove_tag.php?recette=<?= $r["id"] ?>&tag=<?= $tag["id"] ?>">✖</a>
            </span>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="muted">Aucun tag</p>
    <?php endif; ?>
</div>

</div>
<form method="post" action="add_tag.php" class="tag-form">
    <input type="hidden" name="recette_id" value="<?= $r["id"] ?>">
    <label for="tag-select">Choisir un tag existant</label>
    <input type="text" id="tag-filter-input" placeholder="Filtrer les tags…">
    <select id="tag-select" name="tag">
        <option value="">— Choisir un tag existant —</option>
        <?php foreach ($tousTags as $tag): ?>
            <?php if (!in_array((int) $tag["id"], $tagsActuels, true)): ?>
                <option value="<?= htmlspecialchars($tag["nom"]) ?>">
                    <?= htmlspecialchars($tag["nom"]) ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <div id="tag-select-error" class="muted" style="color:#b00020; display:none;">
        Veuillez choisir un tag dans la liste.
    </div>
    <button type="submit">➕ Ajouter</button>
</form>
<form method="post" action="add_tag.php" class="tag-form">
    <input type="hidden" name="recette_id" value="<?= $r["id"] ?>">
    <input type="text" id="tag-input" name="tag" placeholder="Ajouter un nouveau tag">
    <button id="add-tag-btn" type="submit">➕</button>
</form>

</div>
<div class="section-card">
<h3>🧺 Ingrédients</h3>
<ul>
    <?php foreach ($recette["ingredients"] as $ing): ?>
        <li><?= htmlspecialchars($ing) ?></li>
    <?php endforeach; ?>
</ul>

<h3>👩‍🍳 Étapes</h3>
<ol>
    <?php foreach ($recette["etapes"] as $etape): ?>
        <li><?= htmlspecialchars($etape) ?></li>
    <?php endforeach; ?>
</ol>
</div>
<div class="section-card">
<h2>📸 Photos de la recette</h2>

<?php
$photoPrincipale = $recette["photo_principale"] ?? null;
$autresPhotos = [];

if (!empty($recette["photos"])) {
    foreach ($recette["photos"] as $p) {
        if (!empty($p["is_principale"])) {
            $photoPrincipale = $p;
        } else {
            $autresPhotos[] = $p;
        }
    }
}
?>

<?php if ($photoPrincipale): ?>
    <div class="photo-principale">
        <img src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($photoPrincipale["fichier"]) ?>" alt="">
    </div>
<?php endif; ?>

<div class="photos-secondaires">
    <?php foreach ($autresPhotos as $photo): ?>
        <div class="photo-vignette">
            <img src="<?= PUBLIC_URL ?>/uploads/recettes/<?= htmlspecialchars($photo["fichier"]) ?>" alt="">

            <div class="photo-actions">
    <a href="set_photo_principale.php?id=<?= $photo["id"] ?>&recette=<?= $id ?>"
       title="Définir comme photo principale">⭐</a>

    <a href="delete_photo.php?id=<?= $photo["id"] ?>&recette=<?= $id ?>"
       title="Supprimer la photo"
       onclick="return confirm('Supprimer cette photo ?')">🗑</a>
</div>

        </div>
    <?php endforeach; ?>
    
</div>

<h3>➕ Ajouter une photo</h3>

<form method="post"
      action="upload_photo.php"
      enctype="multipart/form-data"
      id="upload-photo-form"
      class="photo-upload-form">

    <input type="hidden" name="recette_id" value="<?= $id ?>">

    <div id="photo-upload-desktop-wrap" class="desktop-only">
        <label for="photo-upload-desktop">Choisir une photo (ordinateur)</label>
        <input type="file" id="photo-upload-desktop" name="photo_desktop" accept="image/*">
    </div>

    <div id="photo-upload-mobile-wrap" class="mobile-only">
        <div class="photo-upload-mobile-actions">
            <button type="button" id="photo-open-gallery">📁 Pellicule / fichiers</button>
            <button type="button" id="photo-open-camera">📷 Prendre une photo</button>
        </div>

        <input type="file" id="photo-upload-gallery" name="photo_gallery" accept="image/*" class="photo-upload-hidden-input">
        <input type="file" id="photo-upload-camera" name="photo_camera" accept="image/*" capture="environment" class="photo-upload-hidden-input">
        <p class="muted" id="photo-mobile-status">Aucune photo sélectionnée.</p>
    </div>

    <button type="submit">📤 Ajouter la photo</button>
</form>

<h3>🤖 Générer une photo (optionnel)</h3>
<p class="muted">Ce bouton ajoute une nouvelle image IA sans modifier votre système actuel d'upload.</p>
<form method="post" action="generate_photo_ai.php" id="ai-image-form">
    <input type="hidden" name="recette_id" value="<?= $id ?>">
    <button type="submit" id="ai-image-btn">🤖 Générer une image avec l'IA</button>
</form>

</div>
</div>
<script src="<?= PUBLIC_URL ?>/assets/js/main.js"></script>
<script src="<?= PUBLIC_URL ?>/assets/js/edit-recette.js"></script>
<?php require __DIR__ . '/ui/layout_end.php'; ?>
