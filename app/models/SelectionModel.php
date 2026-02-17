<?php

/**
 * Modèle gérant la sélection des recettes par utilisateur.
 * Table : user_recette_selection (user_id, recette_id)
 */
class SelectionModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function isSelected(int $userId, int $recetteId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM user_recette_selection
            WHERE user_id = :u AND recette_id = :r
            LIMIT 1
        ");
        $stmt->execute([
            ':u' => $userId,
            ':r' => $recetteId
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Récupère les recettes sélectionnées par l'utilisateur
     * avec filtres IDENTIQUES à getToutesRecettes
     * (version HY093-safe)
     */
    public function getRecettesSelectionnees(
        int $userId,
        ?string $recherche = null,
        ?string $categorie = null,
        ?string $auteur = null,
        ?string $source = null,
        ?string $typeRecette = null,
        ?string $typeCuisson = null,
        array $tagIds = [],
        ?string $dashboardFilter = null
    ): array {

        $sql = "
            SELECT
                r.*,
                p.fichier AS photo_principale,
                1 AS is_checked,
                EXISTS (
                    SELECT 1
                    FROM user_favoris uf
                    WHERE uf.recette_id = r.id
                      AND uf.user_id = :user_favori
                ) AS is_favori
            FROM recettes r
            INNER JOIN user_recette_selection urs
                ON urs.recette_id = r.id
               AND urs.user_id   = :user_selection
            LEFT JOIN photos_recettes p
                ON p.recette_id = r.id
               AND p.is_principale = 1
        ";

        $conditions = [];
        $params = [
            ':user_favori'    => $userId,
            ':user_selection' => $userId
        ];

        // 🔍 Recherche texte
        if ($recherche !== null && $recherche !== '') {
            $conditions[] = "
                (r.titre COLLATE utf8mb4_unicode_ci LIKE :rech_titre
                 OR r.auteur COLLATE utf8mb4_unicode_ci LIKE :rech_auteur
                 OR r.source COLLATE utf8mb4_unicode_ci LIKE :rech_source)
            ";
            $params[':rech_titre']  = "%$recherche%";
            $params[':rech_auteur'] = "%$recherche%";
            $params[':rech_source'] = "%$recherche%";
        }

        if ($categorie) {
            $conditions[] = "r.categorie COLLATE utf8mb4_unicode_ci = :categorie";
            $params[':categorie'] = $categorie;
        }

        if ($auteur) {
            $conditions[] = "r.auteur = :auteur";
            $params[':auteur'] = $auteur;
        }

        if ($source) {
            $conditions[] = "r.source = :source";
            $params[':source'] = $source;
        }

        if ($typeRecette) {
            $conditions[] = "r.type_recette = :type_recette";
            $params[':type_recette'] = $typeRecette;
        }

        if ($typeCuisson) {
            $conditions[] = "r.type_cuisson = :type_cuisson";
            $params[':type_cuisson'] = $typeCuisson;
        }

        if ($dashboardFilter) {
            switch ($dashboardFilter) {
                case 'sans_image':
                    $conditions[] = 'NOT EXISTS (SELECT 1 FROM photos_recettes p2 WHERE p2.recette_id = r.id)';
                    break;
                case 'photos_ia':
                    $conditions[] = "EXISTS (
                        SELECT 1
                        FROM photos_recettes p2
                        WHERE p2.recette_id = r.id
                          AND p2.fichier LIKE 'recette_ai_%'
                    )";
                    break;
                case 'sans_categorie':
                    $conditions[] = '(r.categorie IS NULL OR TRIM(r.categorie) = "")';
                    break;
                case 'sans_source':
                    $conditions[] = '(r.source IS NULL OR TRIM(r.source) = "")';
                    break;
                case 'sans_type_cuisson':
                    $conditions[] = '(r.type_cuisson IS NULL OR TRIM(r.type_cuisson) = "")';
                    break;
                case 'incompletes':
                    $conditions[] = '(
                        NOT EXISTS (SELECT 1 FROM ingredients i WHERE i.recette_id = r.id)
                        OR NOT EXISTS (SELECT 1 FROM etapes e WHERE e.recette_id = r.id)
                    )';
                    break;
                case 'dedup_hash_vides':
                    $conditions[] = '(r.dedup_hash IS NULL OR TRIM(r.dedup_hash) = "")';
                    break;
                case 'doublons':
                    $conditions[] = '(
                        r.dedup_hash IS NOT NULL
                        AND TRIM(r.dedup_hash) <> ""
                        AND r.dedup_hash IN (
                            SELECT d.dedup_hash
                            FROM recettes d
                            WHERE d.dedup_hash IS NOT NULL
                              AND TRIM(d.dedup_hash) <> ""
                            GROUP BY d.dedup_hash
                            HAVING COUNT(*) > 1
                        )
                    )';
                    break;
            }
        }

        // 🏷️ Tags (ET logique)
        if (!empty($tagIds)) {
            $tagIds = array_map('intval', $tagIds);
            $ph = [];

            foreach ($tagIds as $i => $tagId) {
                $key = ":tag_$i";
                $ph[] = $key;
                $params[$key] = $tagId;
            }

            $conditions[] = "
                r.id IN (
                    SELECT rt.recette_id
                    FROM recette_tags rt
                    WHERE rt.tag_id IN (" . implode(',', $ph) . ")
                    GROUP BY rt.recette_id
                    HAVING COUNT(DISTINCT rt.tag_id) = " . count($tagIds) . "
                )
            ";
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Toggle sélection
     */
    public function toggle(int $userId, int $recetteId): bool
    {
        if ($this->isSelected($userId, $recetteId)) {

            $stmt = $this->pdo->prepare("
                DELETE FROM user_recette_selection
                WHERE user_id = :u AND recette_id = :r
            ");
            $stmt->execute([
                ':u' => $userId,
                ':r' => $recetteId
            ]);

            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO user_recette_selection (user_id, recette_id)
            VALUES (:u, :r)
        ");
        $stmt->execute([
            ':u' => $userId,
            ':r' => $recetteId
        ]);

        return true;
    }
}
