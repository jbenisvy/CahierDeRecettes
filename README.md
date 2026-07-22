# CahierDeRecettes

Mémoire de Saveurs est une application PHP de gestion de recettes avec import assisté par IA, gestion des photos et administration des données.

## Architecture

- `public/` contient les points d’entrée HTTP, les vues principales et les actions d’administration.
- `app/models/` regroupe l’accès aux données recettes, photos, favoris, sélections et tags.
- `app/controllers/` porte les actions métier exposées aux pages et à l’API.
- `app/services/` concentre les services techniques, notamment OpenAI, SSO, normalisation et génération automatique des photos.
- `config/` contient la configuration applicative et les accès base/API.

## Flux d’import de recette

Les recettes peuvent être créées depuis plusieurs entrées :

- `public/import_json_form.php` pour choisir le mode d’import.
- `public/import_recette_image.php` pour l’extraction depuis une photo.
- `public/import_recette_url.php` pour l’extraction depuis une URL.
- `public/import_recette_texte.php` pour l’extraction depuis un texte libre.
- `public/import_preview.php` pour la validation manuelle avant import final.
- `public/import_json.php` pour l’enregistrement final en base.

Le flux d’import applique une détection de doublons probables avant insertion.

## Génération IA des photos

La génération d’image repose sur `app/services/ChatGPTService.php`.

- Le service extrait aussi des recettes depuis image, texte et URL.
- Pour les images, le prompt tient compte du titre, des ingrédients, des étapes, de la catégorie, du type de recette et du type de cuisson.
- Une table de correspondance métier renforce certains cas culinaires pour respecter la vraie forme du plat, par exemple `poké`, `cake`, `boisson chaude`, `sushi`, `quiche`, `gratin` ou `couscous`.

## Génération automatique après ajout

`app/services/AutoRecipeImageService.php` est chargé de :

- relire la recette complète après création,
- vérifier qu’aucune photo principale n’existe déjà,
- appliquer des garde-fous de complétude,
- générer l’image IA,
- optimiser le fichier pour le web,
- enregistrer la photo,
- la définir comme photo principale.

Ce service est branché sur `public/import_json.php`, ce qui permet aux imports JSON, image, texte et URL de bénéficier automatiquement de la génération de photo après l’ajout final.

## Traitement admin des recettes sans photo

Depuis `public/admin/settings.php`, un bouton permet de traiter uniquement les recettes sans photo.

- Les recettes qui ont déjà une image ne sont jamais modifiées.
- Le lot utilise `app/services/AutoRecipeImageService.php`.
- Le suivi temps réel passe par `public/admin/generate_missing_ai_photos_progress.php`.
- L’interface affiche un avancement `traitées / total`, la recette en cours et le bilan `générées / ignorées / échecs`.

## Points d’attention

- Les appels OpenAI nécessitent une clé configurée via environnement ou `config/openai.php`.
- Les images sont stockées dans `public/uploads/recettes`.
- En cas d’échec de génération automatique après import, la recette reste créée et l’ajout n’est pas annulé.
