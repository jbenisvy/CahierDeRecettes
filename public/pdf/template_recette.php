<?php

// ===============================
// DONNÉES
// ===============================

$r = is_array($recette['recette'] ?? null) ? $recette['recette'] : [];

$titre     = (string)($r['titre'] ?? 'Recette');
$auteur    = (string)($r['auteur'] ?? '');
$categorie = (string)($r['categorie'] ?? '');

$ingredients = is_array($recette['ingredients'] ?? null) ? $recette['ingredients'] : [];
$etapes      = is_array($recette['etapes'] ?? null) ? $recette['etapes'] : [];

$root = realpath(__DIR__ . '/../../');

// Photo principale
$photo = null;
if (
    isset($recette['photo_principale']['fichier'])
) {
    $candidate = $root . '/public/uploads/recettes/' . $recette['photo_principale']['fichier'];
    if (is_file($candidate)) {
        $photo = $candidate;
    }
}
?>

<div class="pdf-wrap">

    <div class="header">
        <div class="kicker">FICHE RECETTE</div>
        <h1 class="titre-recette"><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if ($photo): ?>
    <div class="photo-wrapper">
        <img class="photo" src="<?= $photo ?>" alt="">
    </div>
<?php endif; ?>


        <div class="meta">
            <?php if ($categorie): ?>
                <div><strong>Catégorie :</strong> <?= htmlspecialchars($categorie) ?></div>
            <?php endif; ?>
            <?php if ($auteur): ?>
                <div><strong>Auteur :</strong> <?= htmlspecialchars($auteur) ?></div>
            <?php endif; ?>
        </div>

        <div class="infos-recette">
    <div><strong>Personnes :</strong> <?= $r['nombre_personnes'] ?? 'Non renseigné' ?></div>
    <div><strong>Préparation :</strong> <?= $r['temps_preparation'] ?? '—' ?> min</div>
    <div><strong>Cuisson :</strong> <?= $r['temps_cuisson'] ?? '—' ?> min</div>
    <div><strong>Repos :</strong> <?= $r['temps_repos'] ?? '—' ?> min</div>
    <div><strong>Type de cuisson :</strong> <?= $r['type_cuisson'] ?: 'Non renseigné' ?></div>
    <div><strong>Difficulté :</strong> <?= $r['difficulte'] !== null ? $r['difficulte'].'/5' : 'Non renseigné' ?></div>
    <div><strong>Tags :</strong>
        <?= !empty($recette['tags']) ? implode(', ', $recette['tags']) : 'Non renseigné' ?>
    </div>
</div>


   <table class="table-recette">
    <tr>
        <th>Ingrédients</th>
        <th>Préparation</th>
    </tr>
    <tr>
        <td class="cell-ingredients">
            <ul>
                <?php foreach ($ingredients as $ing): ?>
                    <li><?= htmlspecialchars((string)$ing, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </td>
        <td class="cell-preparation">
            <ol>
                <?php foreach ($etapes as $step): ?>
                    <li><?= htmlspecialchars((string)$step, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ol>
        </td>
    </tr>
</table>


    <?php if (!empty($recette['tags'])): ?>
        <div class="tags">
            <strong>Tags :</strong>
            <?= implode(', ', array_map('htmlspecialchars', $recette['tags'])) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($r['commentaires'])): ?>
        <div class="commentaires">
            <strong>Commentaires :</strong><br>
            <?= nl2br(htmlspecialchars($r['commentaires'])) ?>
        </div>
    <?php endif; ?>

</div>
