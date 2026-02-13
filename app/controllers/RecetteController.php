<?php

require_once __DIR__ . '/../models/RecetteModel.php';
require_once __DIR__ . '/../models/FavoriModel.php';
require_once __DIR__ . '/../models/SelectionModel.php';


class RecetteController
{
    private RecetteModel $model;
    private ?FavoriModel $favoriModel = null;
    private ?SelectionModel $selectionModel = null;

    public function __construct()
{
    $this->model = new RecetteModel();

    if (method_exists($this->model, 'getDb')) {
        $db = $this->model->getDb();

        $this->favoriModel    = new FavoriModel($db);
        $this->selectionModel = new SelectionModel($db);
    }
}


    public function getCategories(): array
    {
        return $this->model->getCategories();
    }

   public function supprimerRecette(int $id): void
{
    $this->model->supprimerRecette($id);
}


    /**
     * Retourne toutes les recettes en fonction des filtres et marque
     * celles qui sont en favoris pour l'utilisateur connecté. Si $favoris est
     * à true, seule la liste des favoris est renvoyée.
     *
     * @param string|null $recherche Terme de recherche à appliquer.
     * @param string|null $categorie Catégorie à filtrer.
     * @param string|null $auteur    Auteur à filtrer.
     * @param string|null $source    Source à filtrer.
     * @param string|null $typeRecette Type de recette (recette, base, composant).
     * @param string|null $typeCuisson Type de cuisson à filtrer.
     * @param array $tagIds          Identifiants de tags (filtre AND).
     * @param bool|null $favoris     Si true, ne renvoie que les favoris de l'utilisateur.
     * @param int|null $userId       Identifiant de l'utilisateur courant.
     *
     * @return array Liste de recettes avec une clé supplémentaire "is_favori".
     */
  public function getToutesRecettes(
    ?string $recherche = null,
    ?string $categorie = null,
    ?string $auteur = null,
    ?string $source = null,
    ?string $typeRecette = null,
    ?string $typeCuisson = null,
    array $tagIds = [],
    bool $favorisOnly = false,
    bool $selectionOnly = false,
    ?int $userId = null,
    ?string $dashboardFilter = null
): array {

    // 🔹 MODE SÉLECTION (miroir des favoris)
    // 🔒 Normalisation des filtres vides (CRITIQUE pour PDO)
$recherche    = ($recherche === '')    ? null : $recherche;
$categorie    = ($categorie === '')    ? null : $categorie;
$auteur       = ($auteur === '')       ? null : $auteur;
$source       = ($source === '')       ? null : $source;
$typeRecette  = ($typeRecette === '')  ? null : $typeRecette;
$typeCuisson  = ($typeCuisson === '')  ? null : $typeCuisson;
$dashboardFilter = ($dashboardFilter === '') ? null : $dashboardFilter;

    if ($selectionOnly && $userId !== null && $this->selectionModel !== null) {
        return $this->selectionModel->getRecettesSelectionnees(
            $userId,
            $recherche,
            $categorie,
            $auteur,
            $source,
            $typeRecette,
            $typeCuisson,
            $tagIds,
            $dashboardFilter
        );
    }

    // 🔹 MODE NORMAL / FAVORIS
    return $this->model->getToutesRecettes(
    $recherche,
    $categorie,
    $auteur,
    $source,
    $typeRecette,
    $typeCuisson,
    $tagIds,
    $favorisOnly,
     false,      // 🔒 IMPORTANT : jamais true ici
    $userId,
    $dashboardFilter
);

}




    /**
     * Retourne les identifiants des recettes favorites d'un utilisateur.
     *
     * @param int $userId
     * @return array
     */
    public function getIdsFavorisParUser(int $userId): array
    {
        if ($this->favoriModel === null) {
            return [];
        }
        return $this->favoriModel->getIdsFavorisParUser($userId);
    }

    public function getTypesCuisson(): array
    {
        return $this->model->getTypesCuisson();
    }

 
public function updateRecetteEdition(array $data): bool
{
    if (!isset($_SESSION)) {
        session_start();
    }

    if (!isset($_SESSION['user'])) {
        return false;
    }

    $recette = $this->model->getRecetteComplete((int)$data['id']);

    if (!$recette) {
        return false;
    }

    // Seul l'auteur ou un admin peut modifier
    if (
        $_SESSION['user']['nom'] !== $recette['auteur']
        && $_SESSION['user']['role'] !== 'admin'
    ) {
        return false;
    }

    unset($data['auteur']); // 🔒 verrouillage définitif

    return $this->model->updateRecetteEdition($data);
}


    public function getRecetteComplete(int $id): ?array
    {
        return $this->model->getRecetteComplete($id);
    }

 public function getAuteurs(): array
{
    return $this->model->getAuteurs();
}


    public function getTousLesTags(): array
    {
        return $this->model->getTousLesTags();
    }

    public function getSources(): array
    {
        return $this->model->getSources();
    }

    public function definirPhotoPrincipale(int $photoId, int $recetteId): bool
    {
        return $this->model->definirPhotoPrincipale($photoId, $recetteId);
    }
}
