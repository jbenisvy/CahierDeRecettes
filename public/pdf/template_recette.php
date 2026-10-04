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
$tags        = array_values(array_filter(array_map(
    static fn($tag): string => trim((string)($tag['nom'] ?? '')),
    is_array($recette['tags'] ?? null) ? $recette['tags'] : []
)));

$root = realpath(__DIR__ . '/../../');

if (!function_exists('pdf_public_url')) {
    function pdf_public_url(string $path): string
    {
        $publicUrl = defined('PUBLIC_URL') ? (string) PUBLIC_URL : '';

        if (preg_match('~^https?://~i', $publicUrl) === 1) {
            return rtrim($publicUrl, '/') . '/' . ltrim($path, '/');
        }

        $forwardedProto = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if ($forwardedProto !== '') {
            $scheme = trim(explode(',', $forwardedProto)[0]) ?: 'http';
        } else {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $scheme = $isHttps ? 'https' : 'http';
        }

        $forwardedHost = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '');
        $host = $forwardedHost !== ''
            ? trim(explode(',', $forwardedHost)[0])
            : (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));

        if ($host === '') {
            return rtrim($publicUrl, '/') . '/' . ltrim($path, '/');
        }

        return $scheme . '://' . $host . rtrim($publicUrl, '/') . '/' . ltrim($path, '/');
    }
}

$recipeId = (int)($r['id'] ?? ($id ?? 0));
$recipeUrl = $recipeId > 0 ? pdf_public_url('recette.php?id=' . $recipeId) : '';
$homeUrl = pdf_public_url('index.php');

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

    <div class="pdf-nav">
        <?php if ($recipeUrl !== ''): ?>
            <a href="<?= htmlspecialchars($recipeUrl, ENT_QUOTES, 'UTF-8') ?>">Retour à la fiche recette</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Retour à l'accueil</a>
    </div>

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
        <?= !empty($tags) ? htmlspecialchars(implode(', ', $tags), ENT_QUOTES, 'UTF-8') : 'Non renseigné' ?>
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


    <?php if (!empty($tags)): ?>
        <div class="tags">
            <strong>Tags :</strong>
            <?= htmlspecialchars(implode(', ', $tags), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($r['commentaires'])): ?>
        <div class="commentaires">
            <strong>Commentaires :</strong><br>
            <?= nl2br(htmlspecialchars($r['commentaires'])) ?>
        </div>
    <?php endif; ?>

</div>
