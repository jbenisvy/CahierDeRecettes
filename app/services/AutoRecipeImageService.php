<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/RecetteModel.php';
require_once __DIR__ . '/ChatGPTService.php';
require_once __DIR__ . '/../helpers/image_optimizer.php';

class AutoRecipeImageService
{
    private const MIN_INGREDIENTS = 2;
    private const MIN_TITLE_LENGTH = 6;
    /** @var ?RecetteModel */
    private $recetteModel;
    /** @var ?ChatGPTService */
    private $chatGPTService;

    public function __construct(?RecetteModel $recetteModel = null, ?ChatGPTService $chatGPTService = null)
    {
        $this->recetteModel = $recetteModel;
        $this->chatGPTService = $chatGPTService;
    }

    public function generateAndAttachAsDefault(int $recetteId): ?int
    {
        $model = $this->recetteModel ?? new RecetteModel();
        $chat = $this->chatGPTService ?? new ChatGPTService();

        $complete = $model->getRecetteComplete($recetteId);
        if ($complete === null || empty($complete['recette'])) {
            throw new RuntimeException('Recette introuvable pour génération automatique.');
        }

        if (!empty($complete['photo_principale'])) {
            return null;
        }

        if (!$this->shouldGenerateImage($complete)) {
            return null;
        }

        $recette = $complete['recette'];
        $imageBinary = $chat->genererImageRecette([
            'titre' => $recette['titre'] ?? '',
            'categorie' => $recette['categorie'] ?? '',
            'type_recette' => $recette['type_recette'] ?? '',
            'type_cuisson' => $recette['type_cuisson'] ?? '',
            'nombre_personnes' => $recette['nombre_personnes'] ?? '',
            'ingredients' => $complete['ingredients'] ?? [],
            'etapes' => $complete['etapes'] ?? [],
        ]);

        $optimized = optimizeImageBinaryForWeb($imageBinary);
        $filename = $this->storeImageBinary($optimized['binary'], $optimized['extension']);

        if (!$model->ajouterPhoto($recetteId, $filename)) {
            throw new RuntimeException('Impossible d’enregistrer la photo générée en base.');
        }

        $photos = $model->getPhotosByRecette($recetteId);
        $photoId = null;
        foreach ($photos as $photo) {
            if (($photo['fichier'] ?? '') === $filename) {
                $photoId = (int) ($photo['id'] ?? 0);
                break;
            }
        }

        if ($photoId === null || $photoId <= 0) {
            throw new RuntimeException('Photo générée enregistrée, mais identifiant introuvable.');
        }

        $model->definirPhotoPrincipale($photoId, $recetteId);

        return $photoId;
    }

    /**
     * @param array{recette:array<string,mixed>,ingredients?:array<int,string>,etapes?:array<int,string>,photo_principale?:mixed} $complete
     */
    private function shouldGenerateImage(array $complete): bool
    {
        $recette = $complete['recette'] ?? [];
        $titre = trim((string) ($recette['titre'] ?? ''));
        if (mb_strlen($titre) < self::MIN_TITLE_LENGTH) {
            return false;
        }

        $ingredients = $complete['ingredients'] ?? [];
        if (!is_array($ingredients)) {
            return false;
        }

        $normalizedIngredients = array_values(array_filter(array_map(static function ($ingredient): string {
            return trim((string) $ingredient);
        }, $ingredients), static function (string $ingredient): bool {
            return $ingredient !== '';
        }));

        if (count($normalizedIngredients) < self::MIN_INGREDIENTS) {
            return false;
        }

        $categorie = trim((string) ($recette['categorie'] ?? ''));
        $typeRecette = trim((string) ($recette['type_recette'] ?? ''));
        $typeCuisson = trim((string) ($recette['type_cuisson'] ?? ''));
        $etapes = $complete['etapes'] ?? [];
        $hasEtapes = is_array($etapes) && count(array_filter(array_map(static fn ($etape): string => trim((string) $etape), $etapes))) > 0;

        return $categorie !== '' || $typeRecette !== '' || $typeCuisson !== '' || $hasEtapes;
    }

    private function storeImageBinary(string $imageBinary, string $extension): string
    {
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/recettes/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Impossible de créer le dossier uploads/recettes.');
        }

        $filename = uniqid('recette_ai_', true) . '.' . $extension;
        $destination = $uploadDir . $filename;
        $written = @file_put_contents($destination, $imageBinary);

        if ($written === false || $written <= 0) {
            $error = error_get_last();
            throw new RuntimeException('Échec écriture image générée: ' . ($error['message'] ?? 'erreur inconnue'));
        }

        @chmod($destination, 0644);

        return $filename;
    }
}
