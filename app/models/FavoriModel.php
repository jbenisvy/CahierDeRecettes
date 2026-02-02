<?php

/**
 * Modèle gérant les favoris des utilisateurs.
 *
 * Cette classe fournit des méthodes pour récupérer les recettes mises en
 * favoris par un utilisateur ainsi que les identifiants des favoris. Elle
 * s'appuie sur la table `user_favoris` qui relie un utilisateur à une recette.
 */
class FavoriModel
{
    /**
     * Connexion PDO à la base de données.
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Constructeur.
     *
     * @param PDO $pdo Connexion à utiliser pour toutes les requêtes.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère la liste des identifiants de recettes mises en favoris
     * par l'utilisateur donné.
     *
     * @param int $userId Identifiant de l'utilisateur.
     * @return array Liste des identifiants de recettes.
     */
    public function getIdsFavorisParUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT recette_id
            FROM user_favoris
            WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Récupère la liste des recettes favorites d'un utilisateur en
     * appliquant les mêmes filtres que getToutesRecettes.
     *
     * @param int      $userId      Identifiant de l'utilisateur.
     * @param string|null $recherche  Terme de recherche.
     * @param string|null $categorie  Catégorie à filtrer.
     * @param string|null $auteur     Auteur à filtrer.
     * @param string|null $source     Source à filtrer.
     * @param string|null $typeRecette Type de recette (recette, base, composant).
     * @param string|null $typeCuisson Type de cuisson à filtrer.
     * @param array    $tagIds     Tableau d'identifiants de tags pour un filtrage AND.
     *
     * @return array Tableau de recettes sous la même forme que getToutesRecettes.
     */
    public function getRecettesFavoris(
        int $userId,
        ?string $recherche = null,
        ?string $categorie = null,
        ?string $auteur = null,
        ?string $source = null,
        ?string $typeRecette = null,
        ?string $typeCuisson = null,
        array $tagIds = []
    ): array {
        $sql = "
            SELECT
                r.*,
                p.fichier AS photo_principale
            FROM recettes r
            INNER JOIN user_favoris uf
                ON uf.recette_id = r.id
               AND uf.user_id   = :user_id
            LEFT JOIN photos_recettes p
                ON p.recette_id = r.id
               AND p.is_principale = 1
        ";

        $conditions = [];
        $params = [
            ':user_id' => $userId
        ];

        if ($recherche !== null && $recherche !== '') {
            $conditions[] = "(r.titre LIKE :rech_titre OR r.auteur LIKE :rech_auteur OR r.source LIKE :rech_source)";
            $params[':rech_titre']  = '%'.$recherche.'%';
            $params[':rech_auteur'] = '%'.$recherche.'%';
            $params[':rech_source'] = '%'.$recherche.'%';
        }

        if ($categorie !== null && $categorie !== '') {
            $conditions[] = "categorie = :categorie";
            $params[':categorie'] = $categorie;
        }

        if ($auteur !== null && $auteur !== '') {
            $conditions[] = "auteur = :auteur";
            $params[':auteur'] = $auteur;
        }

        if ($source !== null && $source !== '') {
            $conditions[] = "source = :source";
            $params[':source'] = $source;
        }

        if ($typeRecette) {
            $conditions[] = "r.type_recette = :type_recette";
            $params[':type_recette'] = $typeRecette;
        }

        if ($typeCuisson !== null && $typeCuisson !== '') {
            $conditions[] = "r.type_cuisson = :type_cuisson";
            $params[':type_cuisson'] = $typeCuisson;
        }

        if (!empty($tagIds)) {
            $tagIds = array_map('intval', $tagIds);
            $tagParams = [];
            foreach ($tagIds as $i => $tagId) {
                $key = ":tag_$i";
                $tagParams[] = $key;
                $params[$key] = $tagId;
            }
            $conditions[] = "
                r.id IN (
                    SELECT rt.recette_id
                    FROM recette_tags rt
                    WHERE rt.tag_id IN (" . implode(',', $tagParams) . ")
                    GROUP BY rt.recette_id
                    HAVING COUNT(DISTINCT rt.tag_id) = " . count($tagIds) . "
                )
            ";
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}