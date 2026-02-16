<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/auth/auth_functions.php';
require_once __DIR__ . '/../app/base_url.php';

require_login();

$bodyClass = 'page-tutorial';
$page = 'tutorial';
$pageTitle = 'Tutoriel';

require __DIR__ . '/ui/layout_start.php';
?>

<div class="page settings-page tutorial-page">
  <section class="settings-card card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="p-4 p-md-5" style="background: linear-gradient(135deg, rgba(31,70,56,0.95), rgba(44,92,74,0.9)); color:#fff;">
      <h1 class="page-title mb-2" style="color:#fff;">Tutoriel complet</h1>
      <p class="mb-0" style="color:rgba(255,255,255,0.9);">Guide d'utilisation de Mémoire de Saveurs: recherche, création, édition, import, photos, rôles et maintenance.</p>
    </div>
  </section>

  <section class="settings-card card border-0 shadow-sm rounded-4">
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-secondary btn-small" href="#demarrage">Démarrage</a>
      <a class="btn btn-secondary btn-small" href="#liste">Liste & filtres</a>
      <a class="btn btn-secondary btn-small" href="#fiche">Fiche recette</a>
      <a class="btn btn-secondary btn-small" href="#edition">Édition</a>
      <a class="btn btn-secondary btn-small" href="#import">Import & création</a>
      <a class="btn btn-secondary btn-small" href="#photos">Photos & IA</a>
      <a class="btn btn-secondary btn-small" href="#roles">Rôles</a>
      <a class="btn btn-secondary btn-small" href="#maintenance">Maintenance</a>
      <a class="btn btn-secondary btn-small" href="#depannage">Dépannage</a>
    </div>
  </section>

  <section id="demarrage" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>1. Démarrage</h2>
    <ul class="tutorial-list">
      <li>Connecte-toi avec ton compte.</li>
      <li>Tu arrives sur la liste des recettes avec les filtres et les actions globales.</li>
      <li>Utilise le bandeau en haut pour naviguer rapidement: liste, fiche, paramètres (admin), tutoriel, déconnexion.</li>
    </ul>
  </section>

  <section id="liste" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>2. Liste et filtres</h2>
    <ul class="tutorial-list">
      <li>Recherche texte libre par titre/contenu via le champ principal.</li>
      <li>Filtre par catégorie, auteur, source, type de recette, type de cuisson, tags.</li>
      <li>Affiche uniquement les favoris ou la sélection avec les cases dédiées.</li>
      <li>Vue liste ou galerie via les boutons d’affichage.</li>
      <li>Chaque recette propose des actions selon ton rôle: voir, éditer, supprimer.</li>
    </ul>
  </section>

  <section id="fiche" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>3. Fiche recette</h2>
    <ul class="tutorial-list">
      <li>Affiche la photo principale, les métadonnées, ingrédients, étapes et commentaires.</li>
      <li>Tu peux marquer/démarquer en favori.</li>
      <li>Tu peux générer un PDF de la recette.</li>
      <li>Le bouton Éditer est visible uniquement si ton rôle te l’autorise.</li>
    </ul>
  </section>

  <section id="edition" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>4. Édition d’une recette</h2>
    <ul class="tutorial-list">
      <li>Modifie les champs principaux: titre, source, catégorie, cuisson, difficulté, etc.</li>
      <li>Ajoute/supprime des tags.</li>
      <li>Ajoute une photo manuelle, définis la photo principale, supprime des photos secondaires.</li>
      <li>Les modifications sont enregistrées via le bouton Enregistrer du bandeau ou du formulaire.</li>
    </ul>
  </section>

  <section id="import" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>5. Import et création de recettes</h2>
    <ul class="tutorial-list">
      <li>Import JSON: importe un lot de recettes structurées.</li>
      <li>Import image: extraction automatique d’une recette à partir d’une photo.</li>
      <li>Import URL: extraction d’une recette depuis une page web.</li>
      <li>Import texte: extraction d’une recette à partir de texte brut.</li>
      <li>Prévisualisation obligatoire avant import final pour corriger les champs.</li>
      <li>Protection doublons: les doublons probables sont détectés et signalés.</li>
    </ul>
  </section>

  <section id="photos" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>6. Photos et génération IA</h2>
    <ul class="tutorial-list">
      <li>Tu peux générer une photo IA depuis l’écran d’édition, en complément de l’upload manuel.</li>
      <li>La génération utilise le titre, les ingrédients et les étapes pour proposer un plat final cohérent.</li>
      <li>Tu peux prévisualiser puis appliquer l’image en photo principale.</li>
      <li>L’image est optimisée pour le web (taille et compression).</li>
      <li>En cas de souci de droits dossier, la prévisualisation reste visible pour éviter la perte.</li>
    </ul>
  </section>

  <section id="roles" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>7. Rôles utilisateurs</h2>
    <ul class="tutorial-list">
      <li><strong>Lecteur</strong>: consultation uniquement, pas d’édition, pas de paramètres.</li>
      <li><strong>Contributeur</strong>: ajout/import de recettes et édition de ses recettes, pas de paramètres.</li>
      <li><strong>Admin</strong>: accès complet, suppression, maintenance et paramètres.</li>
      <li>Les contrôles sont appliqués côté interface et côté serveur.</li>
    </ul>
  </section>

  <section id="maintenance" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>8. Paramètres et maintenance (admin)</h2>
    <ul class="tutorial-list">
      <li>Dashboard qualité des données: recettes sans image/source/catégorie, doublons, etc.</li>
      <li>Gestion des utilisateurs et des rôles.</li>
      <li>Gestion des tags: fusion et suppression.</li>
      <li>Options recettes: catégories, types de cuisson, types de recette.</li>
      <li>Suivi OpenAI: crédit (si disponible), total recettes/photos/photos IA.</li>
    </ul>
  </section>

  <section id="depannage" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>9. Dépannage rapide</h2>
    <ul class="tutorial-list">
      <li>Si un bouton n’apparaît pas: vérifier rôle + cache navigateur (Ctrl+F5).</li>
      <li>Si une image ne s’enregistre pas: vérifier les droits de `public/uploads/recettes`.</li>
      <li>Si le crédit OpenAI est indisponible: vérifier la clé API (`.env` ou `config/openai.php`) et les permissions billing du compte.</li>
      <li>En production: tester upload manuel + génération IA + définition photo principale après chaque déploiement.</li>
    </ul>
  </section>
</div>

<?php require __DIR__ . '/ui/layout_end.php'; ?>
