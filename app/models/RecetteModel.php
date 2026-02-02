<?php

class RecetteModel
{
  private $pdo;


    public function __construct()
{
    $this->pdo = getPDO();
}
    public function getDb(): PDO
    {
        return $this->pdo;
    }

public function getRecetteComplete(int $id): ?array
{
    $recette = $this->getRecetteById($id);

    if (!$recette) {
        return null;
    }

    // Ingrédients
    $ingredients = $this->getIngredientsByRecette($id);

    // Étapes
    $etapes = $this->getEtapesByRecette($id);

    // Tags
    $stmtTags = $this->pdo->prepare("
        SELECT t.id, t.nom
        FROM tags t
        INNER JOIN recette_tags rt ON rt.tag_id = t.id
        WHERE rt.recette_id = :id
        ORDER BY t.nom
    ");
    $stmtTags->execute([":id" => $id]);
    $tags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);

    // Photos
    $stmtPhotos = $this->pdo->prepare("
        SELECT id, recette_id, fichier, is_principale, date_ajout
        FROM photos_recettes
        WHERE recette_id = :id
        ORDER BY is_principale DESC, date_ajout DESC
    ");
    $stmtPhotos->execute([":id" => $id]);
    $photos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

    // Photo principale
    $photoPrincipale = null;
    foreach ($photos as $photo) {
        if ((int)$photo["is_principale"] === 1) {
            $photoPrincipale = $photo;
            break;
        }
    }

    return [
        "recette"          => $recette,
        "ingredients"      => $ingredients,
        "etapes"           => $etapes,
        "tags"             => $tags,
        "photos"           => $photos,
        "photo_principale" => $photoPrincipale
    ];
}
public function chercherDoublonsPotentiels(array $recette): array
{
    if (empty($recette['titre'])) {
        return [];
    }

    $titre = mb_strtolower(trim($recette['titre']));

    // normalisation simple
    $titre = preg_replace('/[^a-z0-9 ]/u', '', $titre);

    $stmt = $this->pdo->prepare("
        SELECT id, titre, auteur
        FROM recettes
        WHERE LOWER(titre) LIKE ?
        ORDER BY id DESC
        LIMIT 5
    ");

    $stmt->execute(['%' . $titre . '%']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getTousLesTags(): array
{
    $stmt = $this->pdo->query("
        SELECT id, nom
        FROM tags
        ORDER BY nom
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function assurerPhotoPrincipale(int $recetteId): void
{
    $stmt = $this->pdo->prepare("
        SELECT id FROM photos_recettes
        WHERE recette_id = :id
    ");
    $stmt->execute([':id' => $recetteId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($photos) === 1) {
        $this->pdo->prepare("
            UPDATE photos_recettes
            SET is_principale = 1
            WHERE id = :photo_id
        ")->execute([
            ':photo_id' => $photos[0]['id']
        ]);
    }
}

public function supprimerRecette(int $id): void
{
    $this->pdo->beginTransaction();

    try {
        // 🔗 Tables de liaison
        $this->pdo->prepare("DELETE FROM recette_tags WHERE recette_id = ?")
                  ->execute([$id]);

        $this->pdo->prepare("DELETE FROM user_favoris WHERE recette_id = ?")
                  ->execute([$id]);

        $this->pdo->prepare("DELETE FROM user_recette_selection WHERE recette_id = ?")
                  ->execute([$id]);

        // Tables enfants
        $this->pdo->prepare("DELETE FROM ingredients WHERE recette_id = ?")
                  ->execute([$id]);

        $this->pdo->prepare("DELETE FROM etapes WHERE recette_id = ?")
                  ->execute([$id]);

        $this->pdo->prepare("DELETE FROM photos_recettes WHERE recette_id = ?")
                  ->execute([$id]);

        // Table parent (EN DERNIER)
        $this->pdo->prepare("DELETE FROM recettes WHERE id = ?")
                  ->execute([$id]);

        $this->pdo->commit();

    } catch (Throwable $e) {
        $this->pdo->rollBack();
        throw $e; // laisse remonter si besoin
    }
}



public function ajouterRecetteDepuisJson(array $r): int
{
    $pdo = $this->pdo;

    $pdo->beginTransaction();

    try {
        // 1. Insertion recette principale
      $stmt = $pdo->prepare("
    INSERT INTO recettes
    (
        titre,
        auteur,
        source,
        categorie,
        type_recette,
        type_cuisson,
        temps_preparation,
        temps_cuisson,
        temps_repos,
        commentaires
    )
    VALUES
    (
        :titre,
        :auteur,
        :source,
        :categorie,
        :type_recette,
        :type_cuisson,
        :temps_preparation,
        :temps_cuisson,
        :temps_repos,
        :commentaires
    )
");

$stmt->execute([
    ":titre"             => $r["titre"],
    ":auteur"            => $r["auteur"] ?? null,
    ":source"            => $r["source"] ?? null,
    ":categorie"         => $r["categorie"] ?? "autre",
    ":type_recette"      => $r["type_recette"] ?? "recette",
    ":type_cuisson" 	 => $r["type_cuisson"] ?? null,
    ":temps_preparation" => isset($r["temps_preparation"]) ? (int)$r["temps_preparation"] : null,
    ":temps_cuisson"     => isset($r["temps_cuisson"]) ? (int)$r["temps_cuisson"] : null,
    ":temps_repos"       => isset($r["temps_repos"]) ? (int)$r["temps_repos"] : null,
    ":commentaires"      => $r["commentaires"] ?? null,
]);

$recetteId = (int) $pdo->lastInsertId();

if ($recetteId <= 0) {
    throw new Exception("Échec insertion recette (ID invalide)");
}

        // 2. Ingrédients
        $stmtIng = $pdo->prepare("
            INSERT INTO ingredients (recette_id, ordre, texte)
            VALUES (:recette_id, :ordre, :texte)
        ");

        foreach ($r["ingredients"] as $i => $texte) {
            $stmtIng->execute([
                ":recette_id" => $recetteId,
                ":ordre" => $i + 1,
                ":texte" => $texte
            ]);
        }

        // 3. Étapes
        $stmtEtape = $pdo->prepare("
            INSERT INTO etapes (recette_id, ordre, texte)
            VALUES (:recette_id, :ordre, :texte)
        ");

        foreach ($r["etapes"] as $i => $texte) {
            $stmtEtape->execute([
                ":recette_id" => $recetteId,
                ":ordre" => $i + 1,
                ":texte" => $texte
            ]);
        }

        $pdo->commit();
        return $recetteId;

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

public function getRecetteById(int $id): ?array
{
    $stmt = $this->pdo->prepare("
        SELECT * FROM recettes WHERE id = :id
    ");
    $stmt->execute([":id" => $id]);
    $recette = $stmt->fetch();

    return $recette ?: null;
}

public function getIngredientsByRecette(int $recetteId): array
{
    $stmt = $this->pdo->prepare("
        SELECT texte 
        FROM ingredients 
        WHERE recette_id = :id 
        ORDER BY ordre
    ");
    $stmt->execute([":id" => $recetteId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

public function getEtapesByRecette(int $recetteId): array
{
    $stmt = $this->pdo->prepare("
        SELECT texte 
        FROM etapes 
        WHERE recette_id = :id 
        ORDER BY ordre
    ");
    $stmt->execute([":id" => $recetteId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


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
    ?int $userId = null
): array {

    $params = [];
    $conditions = [];

    $sql = "
        SELECT
            r.*,
            p.fichier AS photo_principale
    ";

    if ($userId !== null) {
        $sql .= ",
           EXISTS (
    SELECT 1
    FROM user_favoris uf
    WHERE uf.recette_id = r.id
      AND uf.user_id = :user_id_favori_select
) AS is_favori,

EXISTS (
    SELECT 1
    FROM user_recette_selection urs
    WHERE urs.recette_id = r.id
      AND urs.user_id = :user_id_selection_select
) AS is_checked

        ";

       $params[':user_id_favori_select'] = $userId;
		$params[':user_id_selection_select'] = $userId;


    } else {
        $sql .= ",
            0 AS is_favori,
            0 AS is_checked
        ";
    }

    $sql .= "
        FROM recettes r
        LEFT JOIN photos_recettes p
            ON p.recette_id = r.id
           AND p.is_principale = 1
    ";

    // ⭐ Favoris uniquement
    if ($favorisOnly && $userId !== null) {
    $conditions[] = "EXISTS (
        SELECT 1
        FROM user_favoris uf
        WHERE uf.recette_id = r.id
          AND uf.user_id = :user_id_favori_filter
    )";

    $params[':user_id_favori_filter'] = $userId;
}


  

    // 🔍 Recherche
    if ($recherche !== null && $recherche !== '') {
        $conditions[] = "(r.titre LIKE :rech_titre OR r.auteur LIKE :rech_auteur OR r.source LIKE :rech_source)";

$params[':rech_titre']  = '%' . $recherche . '%';
$params[':rech_auteur'] = '%' . $recherche . '%';
$params[':rech_source'] = '%' . $recherche . '%';

    }

    if ($categorie) {
        $conditions[] = "r.categorie = :categorie";
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

    // 🏷️ Tags (ET logique)
    if (!empty($tagIds)) {
        $tagIds = array_map('intval', $tagIds);
        $placeholders = [];

        foreach ($tagIds as $i => $tagId) {
            $ph = ":tag_$i";
            $placeholders[] = $ph;
            $params[$ph] = $tagId;
        }

        $conditions[] = "
            r.id IN (
                SELECT rt.recette_id
                FROM recette_tags rt
                WHERE rt.tag_id IN (" . implode(',', $placeholders) . ")
                GROUP BY rt.recette_id
                HAVING COUNT(DISTINCT rt.tag_id) = " . count($tagIds) . "
            )
        ";
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    if ($userId !== null) {
        $sql .= " ORDER BY is_favori DESC, is_checked DESC, r.created_at DESC";
    } else {
        $sql .= " ORDER BY r.created_at DESC";
    }

    $stmt = $this->pdo->prepare($sql);
 


    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}




       /* ============================
       PHOTOS DES RECETTES
       ============================ */

    public function getPhotosByRecette(int $recetteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, recette_id, fichier, date_ajout
            FROM photos_recettes
            WHERE recette_id = :id
            ORDER BY date_ajout DESC
        ");
        $stmt->execute([":id" => $recetteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
	public function getCategories(): array
{
    $sql = "
        SELECT DISTINCT categorie
        FROM recettes
        WHERE categorie IS NOT NULL
          AND categorie <> ''
        ORDER BY categorie
    ";

    $stmt = $this->pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

    public function getPhotoById(int $photoId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, recette_id, fichier
            FROM photos_recettes
            WHERE id = :id
        ");
        $stmt->execute([":id" => $photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $photo ?: null;
    }

    public function ajouterPhoto(int $recetteId, string $fichier): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO photos_recettes (recette_id, fichier)
            VALUES (:recette_id, :fichier)
        ");

        return $stmt->execute([
            ":recette_id" => $recetteId,
            ":fichier" => $fichier
        ]);
    }

    public function supprimerPhoto(int $photoId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM photos_recettes WHERE id = :id
        ");
        return $stmt->execute([":id" => $photoId]);
    }

    /* ============================
       ÉDITION LIMITÉE DE LA RECETTE
       ============================ */
       
  public function definirPhotoPrincipale(int $photoId, int $recetteId): bool
{
    // 1. On remet toutes les photos à 0
    $stmt = $this->pdo->prepare("
        UPDATE photos_recettes
        SET is_principale = 0
        WHERE recette_id = :recette_id
    ");
    $stmt->execute([":recette_id" => $recetteId]);

    // 2. On définit la nouvelle principale
    $stmt = $this->pdo->prepare("
        UPDATE photos_recettes
        SET is_principale = 1
        WHERE id = :id
    ");
    $stmt->execute([":id" => $photoId]);

    return true; // ⭐ AJOUT CRITIQUE
}


public function getTypesCuisson(): array

{
    $stmt = $this->pdo->query("
        SELECT DISTINCT type_cuisson
        FROM recettes
        WHERE type_cuisson IS NOT NULL
          AND type_cuisson <> ''
        ORDER BY type_cuisson
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

public function getAuteurs(): array
{
    $stmt = $this->pdo->query("
        SELECT DISTINCT auteur
        FROM recettes
        WHERE auteur IS NOT NULL AND auteur <> ''
        ORDER BY auteur
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
 public function getSources(): array
{
    $stmt = $this->pdo->query("
        SELECT DISTINCT source
        FROM recettes
        WHERE source IS NOT NULL AND source <> ''
        ORDER BY source
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
  

   public function updateRecetteEdition(array $data): bool
{
    $this->pdo->beginTransaction();

    try {
        // 1️⃣ Mise à jour de la recette
        $sql = "
            UPDATE recettes SET
                titre = :titre,
                auteur = :auteur,
                source = :source,
                categorie = :categorie,
                temps_preparation = :temps_preparation,
                temps_cuisson = :temps_cuisson,
                temps_repos = :temps_repos,
                nombre_personnes = :nombre_personnes,
                type_cuisson = :type_cuisson,
                difficulte = :difficulte,
                commentaires = :commentaires
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":id" => (int) ($data["id"] ?? 0),

            ":titre" => $data["titre"] ?? "",
            ":auteur" => $data["auteur"] ?? null,
            ":source" => $data["source"] ?? null,
            ":categorie" => $data["categorie"] ?? "autre",

            ":temps_preparation" => $data["temps_preparation"] ?? null,
            ":temps_cuisson" => $data["temps_cuisson"] ?? null,
            ":temps_repos" => $data["temps_repos"] ?? null,

            ":nombre_personnes" => $data["nombre_personnes"] ?? null,
            ":type_cuisson" => $data["type_cuisson"] ?? null,
            ":difficulte" => $data["difficulte"] ?? null,

            ":commentaires" => $data["commentaires"] ?? null,
        ]);

       // 2️⃣ Gestion des tags
if (!empty($data['tags']) && is_array($data['tags'])) {

    // Supprimer les anciens liens
    $del = $this->pdo->prepare("
        DELETE FROM recette_tags WHERE recette_id = :id
    ");
    $del->execute([":id" => (int)$data["id"]]);

    // Insérer les nouveaux
    $ins = $this->pdo->prepare("
        INSERT INTO recette_tags (recette_id, tag_id)
        VALUES (:recette_id, :tag_id)
    ");

    foreach ($data['tags'] as $tagId) {
        $ins->execute([
            ":recette_id" => (int)$data["id"],
            ":tag_id" => (int)$tagId
        ]);
    }
}


        $this->pdo->commit();
        return true;

    } catch (Throwable $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}
}
