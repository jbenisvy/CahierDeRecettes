<?php

class ListeCoursesController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère la liste des ingrédients
     * pour les recettes sélectionnées par l'utilisateur
     */
    public function getListeCourses(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                r.titre   AS recette,
                i.texte  AS ingredient
            FROM user_recette_selection urs
            JOIN recettes r   ON r.id = urs.recette_id
            JOIN ingredients i ON i.recette_id = r.id
            WHERE urs.user_id = :user_id
            ORDER BY r.titre, i.ordre
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
