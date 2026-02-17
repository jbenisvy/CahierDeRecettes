<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/RecetteModel.php';

class ApiController
{
    private RecetteModel $model;

    public function __construct()
    {
        $this->model = new RecetteModel();
    }

    public function listRecipes(array $queryParams, string $publicBaseUrl): array
    {
        $recherche = $this->nullableString($queryParams['q'] ?? null);
        $categorie = $this->nullableString($queryParams['categorie'] ?? null);
        $auteur = $this->nullableString($queryParams['auteur'] ?? null);
        $source = $this->nullableString($queryParams['source'] ?? null);
        $typeRecette = $this->nullableString($queryParams['type_recette'] ?? null);
        $typeCuisson = $this->nullableString($queryParams['type_cuisson'] ?? null);

        $rows = $this->model->getToutesRecettes(
            $recherche,
            $categorie,
            $auteur,
            $source,
            $typeRecette,
            $typeCuisson
        );

        $recipes = array_map(
            fn(array $row): array => $this->normalizeRecipeRow($row, $publicBaseUrl),
            $rows
        );

        return [
            'data' => $recipes,
            'meta' => [
                'total' => count($recipes),
            ],
        ];
    }

    public function getRecipeById(int $id, string $publicBaseUrl): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $complete = $this->model->getRecetteComplete($id);
        if ($complete === null) {
            return null;
        }

        $recipe = $this->normalizeRecipeRow($complete['recette'] ?? [], $publicBaseUrl);
        $recipe['ingredients'] = array_values($complete['ingredients'] ?? []);
        $recipe['steps'] = array_values($complete['etapes'] ?? []);
        $recipe['etapes'] = $recipe['steps'];
        $recipe['tags'] = array_values(
            array_map(
                fn(array $tag): string => (string) ($tag['nom'] ?? ''),
                $complete['tags'] ?? []
            )
        );

        if (!empty($complete['photos']) && is_array($complete['photos'])) {
            $recipe['photos'] = array_values(array_map(function (array $photo) use ($publicBaseUrl): array {
                $filename = (string) ($photo['fichier'] ?? '');
                return [
                    'id' => (int) ($photo['id'] ?? 0),
                    'file' => $filename,
                    'url' => $filename !== '' ? $publicBaseUrl . '/uploads/recettes/' . rawurlencode($filename) : null,
                    'is_main' => ((int) ($photo['is_principale'] ?? 0)) === 1,
                ];
            }, $complete['photos']));
        } else {
            $recipe['photos'] = [];
        }

        return $recipe;
    }

    private function normalizeRecipeRow(array $row, string $publicBaseUrl): array
    {
        $imageFilename = '';
        if (isset($row['photo_principale']) && is_array($row['photo_principale'])) {
            $imageFilename = (string) ($row['photo_principale']['fichier'] ?? '');
        } else {
            $imageFilename = (string) ($row['photo_principale'] ?? '');
        }

        $imageUrl = $imageFilename !== '' ? $publicBaseUrl . '/uploads/recettes/' . rawurlencode($imageFilename) : null;

        $title = (string) ($row['titre'] ?? '');
        $durationMinutes = $this->computeDurationMinutes($row);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => $title,
            'titre' => $title,
            'description' => $this->nullableString($row['commentaires'] ?? null),
            'auteur' => $this->nullableString($row['auteur'] ?? null),
            'source' => $this->nullableString($row['source'] ?? null),
            'categorie' => $this->nullableString($row['categorie'] ?? null),
            'type_recette' => $this->nullableString($row['type_recette'] ?? null),
            'type_cuisson' => $this->nullableString($row['type_cuisson'] ?? null),
            'duration_minutes' => $durationMinutes,
            'temps_preparation' => $this->nullableInt($row['temps_preparation'] ?? null),
            'temps_cuisson' => $this->nullableInt($row['temps_cuisson'] ?? null),
            'temps_repos' => $this->nullableInt($row['temps_repos'] ?? null),
            'nombre_personnes' => $this->nullableInt($row['nombre_personnes'] ?? null),
            'difficulte' => $this->nullableInt($row['difficulte'] ?? null),
            'image' => $imageFilename !== '' ? $imageFilename : null,
            'image_url' => $imageUrl,
            'imageUrl' => $imageUrl,
            'created_at' => $this->nullableString($row['created_at'] ?? null),
            'updated_at' => $this->nullableString($row['updated_at'] ?? null),
        ];
    }

    private function computeDurationMinutes(array $row): ?int
    {
        $prep = $this->nullableInt($row['temps_preparation'] ?? null) ?? 0;
        $cuisson = $this->nullableInt($row['temps_cuisson'] ?? null) ?? 0;
        $repos = $this->nullableInt($row['temps_repos'] ?? null) ?? 0;
        $total = $prep + $cuisson + $repos;

        return $total > 0 ? $total : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
