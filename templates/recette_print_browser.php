
<?php

/**
 * Template impression NAVIGATEUR (multi-recettes)
 * - Ne charge PAS la DB, ne fait PAS de session, ne lit PAS $_GET
 * - Attend une variable $recette (array) fournie par print_selection.php
 * - Reprend le rendu "fiche recette" de recette.php
 */

// Sécurisation
$r = is_array($recette['recette'] ?? null) ? $recette['recette'] : [];

// Étapes en 2 colonnes comme sur recette.php
$etapes = is_array($recette['etapes'] ?? null) ? $recette['etapes'] : [];
$SEUIL_ETAPES = 6;
$prep_gauche = array_slice($etapes, 0, $SEUIL_ETAPES);
$prep_droite = array_slice($etapes, $SEUIL_ETAPES);

// Helpers d'affichage (identiques à recette.php, sans dépendances)
if (!function_exists('v')) {
    function v(?string $value, string $fallback = "Non renseigné"): string {
        $value = trim((string)$value);
        return $value !== "" ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $fallback;
    }
}
if (!function_exists('stars')) {
    function stars($n): string {
        $n = (int)$n;
        if ($n < 1) return "Non renseigné";
        $n = max(1, min(5, $n));
        return str_repeat("★", $n) . str_repeat("☆", 5 - $n);
    }
}

// Auteur (comme recette.php)
$auteur = trim((string)($r['auteur'] ?? ''));

// Photo (NAVIGATEUR = URL web, pas chemin disque)
$photoUrl = null;
if (!empty($recette['photo_principale']['fichier'])) {
    $photoUrl = '/uploads/recettes/' . $recette['photo_principale']['fichier'];
}

// Ingrédients
$ingredients = is_array($recette['ingredients'] ?? null) ? $recette['ingredients'] : [];

// Tags (structure = tableau d’objets ['nom' => ...])
$tags = is_array($recette['tags'] ?? null) ? $recette['tags'] : [];
?>

<div class="fiche-recette">

    <section class="fiche-header">

        <!-- COLONNE GAUCHE -->
        <div class="fiche-header__left">

            <div class="fiche-title">
                <div>
                    <div class="fiche-subtitle">Fiche Recette</div>
                    <h1><?= htmlspecialchars((string)($r["titre"] ?? "Recette"), ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
            </div>

            <div class="fiche-photo-zone">
                <?php if (!empty($photoUrl)): ?>
                    <img
                        src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
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

                <div class="meta-ligne"><strong>Catégorie :</strong> <?= v($r["categorie"] ?? null) ?></div>

                <div class="meta-ligne">
                    <strong>Auteur :</strong>
                    <?= $auteur !== '' ? htmlspecialchars($auteur, ENT_QUOTES, 'UTF-8') : '—' ?>
                </div>

                <div class="meta-ligne"><strong>Source :</strong> <?= v($r["source"] ?? null) ?></div>
                <div class="meta-ligne"><strong>Qualité :</strong> <?= v($r["qualite_source"] ?? null) ?></div>

                <div class="meta-separator"></div>

                <div class="meta-ligne"><strong>Préparation :</strong> <?= v(isset($r["temps_preparation"]) ? (string)$r["temps_preparation"] : null, "—") ?> min</div>
                <div class="meta-ligne"><strong>Cuisson :</strong> <?= v(isset($r["temps_cuisson"]) ? (string)$r["temps_cuisson"] : null, "—") ?> min</div>
                <div class="meta-ligne"><strong>Repos :</strong> <?= v(isset($r["temps_repos"]) ? (string)$r["temps_repos"] : null, "—") ?> min</div>
                <div class="meta-ligne"><strong>Personnes :</strong> <?= v(isset($r["nombre_personnes"]) ? (string)$r["nombre_personnes"] : null) ?></div>
                <div class="meta-ligne"><strong>Type cuisson :</strong> <?= v($r["type_cuisson"] ?? null) ?></div>
                <div class="meta-ligne"><strong>Difficulté :</strong> <?= stars($r["difficulte"] ?? null) ?></div>

                <div class="meta-separator"></div>

                <div class="meta-ligne">
                    <strong>Tags :</strong>
                    <?php if (!empty($tags)): ?>
                        <?php foreach ($tags as $t): ?>
                            <?php if (!empty($t["nom"])): ?>
                                <span class="tag"><?= htmlspecialchars((string)$t["nom"], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="muted">Non renseigné</span>
                    <?php endif; ?>
                </div>

                <div class="meta-separator"></div>

                <div class="meta-ligne commentaires">
                    <strong>Commentaires :</strong><br>
                    <?= nl2br(v($r["commentaires"] ?? null)) ?>
                </div>

            </div>

        </div>

    </section>

    <section class="fiche-bas">

        <!-- COLONNE GAUCHE -->
        <div class="col-gauche">

            <h2>Ingrédients</h2>
            <?php if (!empty($ingredients)): ?>
                <ul>
                    <?php foreach ($ingredients as $ing): ?>
                        <li><?= htmlspecialchars((string)$ing, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">Non renseigné</p>
            <?php endif; ?>

            <?php if (!empty($prep_gauche)): ?>
                <h2>Préparation</h2>
                <ol>
                    <?php foreach ($prep_gauche as $etape): ?>
                        <li><?= htmlspecialchars((string)$etape, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

        </div>

        <!-- COLONNE DROITE -->
        <div class="col-droite">

            <?php if (!empty($prep_droite)): ?>
                <h2>Préparation (suite)</h2>
                <ol start="<?= (int)($SEUIL_ETAPES + 1) ?>">
                    <?php foreach ($prep_droite as $etape): ?>
                        <li><?= htmlspecialchars((string)$etape, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

        </div>

    </section>

</div>
