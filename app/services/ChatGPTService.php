<?php
declare(strict_types=1);

class ChatGPTService
{
    private string $apiKey;
    private const TIME_HINT = "Pour 'temps_preparation' et 'temps_cuisson': si ces durées sont explicitement indiquées, renseigne un entier en minutes.
Si absentes, illisibles ou ambiguës, mets null.";
    private const PEOPLE_HINT = "Pour 'nombre_personnes': si le nombre de personnes/parts est explicitement indiqué, renseigne un entier.
Si absent, illisible ou ambigu, mets null.";

    public function __construct()
    {
        $this->apiKey = $this->resolveApiKey();
    }

    private function buildCategoryHint(): string
    {
        $configFile = PROJECT_ROOT . '/config/recette_options.php';
        $options = is_file($configFile) ? (require $configFile) : [];
        $categories = array_keys($options['categories'] ?? []);
        $allowed = !empty($categories)
            ? implode(', ', $categories)
            : 'entree, plat, dessert, accompagnement, boisson, pain, snack, base';

        return "Exception autorisée: tu peux UNIQUEMENT déduire la catégorie culinaire de la recette et remplir 'categorie'.\n"
            . "Valeurs autorisées pour 'categorie' : {$allowed}.\n"
            . "Si tu hésites vraiment, mets null.";
    }

    private function resolveApiKey(): string
    {
        // Charge .env si disponible (sans fatal si absent)
        $envFile = PROJECT_ROOT . '/config/env.php';
        if (is_file($envFile)) {
            require_once $envFile;
        }

        // 1) Priorité aux variables d'environnement
        $envKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? null);
        if (is_string($envKey) && trim($envKey) !== '') {
            return trim($envKey);
        }

        $legacyEnvKey = getenv('OPENAI_KEY') ?: ($_ENV['OPENAI_KEY'] ?? null);
        if (is_string($legacyEnvKey) && trim($legacyEnvKey) !== '') {
            return trim($legacyEnvKey);
        }

        // 2) Fallback legacy: config/openai.php si présent
        $configFile = PROJECT_ROOT . '/config/openai.php';
        if (is_file($configFile)) {
            $config = require $configFile;
            $fileKey = $config['api_key'] ?? null;
            if (is_string($fileKey) && trim($fileKey) !== '') {
                return trim($fileKey);
            }
        }

        throw new Exception("Clé OpenAI manquante (OPENAI_API_KEY ou config/openai.php)");
    }

    public function extraireRecetteDepuisImageFichier(string $imagePath): string
    {
        if (!is_file($imagePath)) {
            throw new Exception("Fichier image introuvable");
        }

        $mime = mime_content_type($imagePath);
        $base64 = base64_encode(file_get_contents($imagePath));

        $payload = [
            "model" => "gpt-4.1-mini",
            "messages" => [
                [
                    "role" => "system",
                    "content" =>
                        "Tu es un assistant chargé d'extraire fidèlement le contenu d'une recette à partir d'une image.
Ne fais AUCUNE interprétation, AUCUNE amélioration, AUCUNE correction.
Exception : la catégorie est autorisée selon la règle ci-dessous.
"
                        . $this->buildCategoryHint() . "
Si une information est absente ou illisible, mets null."
                ],
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "text",
                            "text" =>
                                "Analyse cette image et retourne UNIQUEMENT un JSON valide avec les clés :
titre, auteur, source, categorie, temps_preparation, temps_cuisson, nombre_personnes, ingredients (array), etapes (array), commentaires.
" . self::TIME_HINT . "
" . self::PEOPLE_HINT
                        ],
                        [
                            "type" => "image_url",
                            "image_url" => [
                                "url" => "data:$mime;base64,$base64"
                            ]
                        ]
                    ]
                ]
            ],
            "temperature" => 0
        ];

        return $this->callOpenAI($payload);
    }

    public function extraireRecetteDepuisTexte(string $texte): string
    {
        $texte = trim($texte);
        if ($texte === '') {
            throw new Exception("Texte vide");
        }

        $payload = [
            "model" => "gpt-4.1-mini",
            "messages" => [
                [
                    "role" => "system",
                    "content" =>
                        "Tu es un assistant chargé d'extraire fidèlement le contenu d'une recette à partir d'un texte.
Ne fais AUCUNE interprétation, AUCUNE amélioration, AUCUNE correction.
Exception : la catégorie est autorisée selon la règle ci-dessous.
"
                        . $this->buildCategoryHint() . "
Si une information est absente ou illisible, mets null."
                ],
                [
                    "role" => "user",
                    "content" =>
                        "Analyse ce texte et retourne UNIQUEMENT un JSON valide avec les clés :
titre, auteur, source, categorie, temps_preparation, temps_cuisson, nombre_personnes, ingredients (array), etapes (array), commentaires.
" . self::TIME_HINT . "
" . self::PEOPLE_HINT . "
\n\nTEXTE:\n" . $texte
                ]
            ],
            "temperature" => 0
        ];

        return $this->callOpenAI($payload);
    }

    public function extraireRecetteDepuisUrl(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("URL invalide");
        }

        $content = $this->fetchUrlContent($url);
        if ($content === '') {
            throw new Exception("Impossible de lire le contenu de l'URL");
        }

        $payload = [
            "model" => "gpt-4.1-mini",
            "messages" => [
                [
                    "role" => "system",
                    "content" =>
                        "Tu es un assistant chargé d'extraire fidèlement le contenu d'une recette à partir d'une page web.
Ne fais AUCUNE interprétation, AUCUNE amélioration, AUCUNE correction.
Exception : la catégorie est autorisée selon la règle ci-dessous.
"
                        . $this->buildCategoryHint() . "
Si une information est absente ou illisible, mets null."
                ],
                [
                    "role" => "user",
                    "content" =>
                        "Analyse le contenu de cette page et retourne UNIQUEMENT un JSON valide avec les clés :
titre, auteur, source, categorie, temps_preparation, temps_cuisson, nombre_personnes, ingredients (array), etapes (array), commentaires.
" . self::TIME_HINT . "
" . self::PEOPLE_HINT . "
\n\nCONTENU:\n" . $content
                ]
            ],
            "temperature" => 0
        ];

        return $this->callOpenAI($payload);
    }

    public function genererImageRecette(array $recette): string
    {
        $titre = trim((string) ($recette['titre'] ?? ''));
        if ($titre === '') {
            throw new Exception("Titre de recette manquant pour la génération d'image");
        }

        $ingredients = $recette['ingredients'] ?? [];
        if (!is_array($ingredients)) {
            $ingredients = [];
        }
        $ingredients = array_slice(array_values(array_filter(array_map('strval', $ingredients))), 0, 14);

        $etapes = $recette['etapes'] ?? [];
        if (!is_array($etapes)) {
            $etapes = [];
        }
        $etapes = array_slice(array_values(array_filter(array_map('strval', $etapes))), 0, 4);

        $categorie = trim((string) ($recette['categorie'] ?? ''));
        $typeRecette = trim((string) ($recette['type_recette'] ?? ''));
        $typeCuisson = trim((string) ($recette['type_cuisson'] ?? ''));
        $nombrePersonnes = trim((string) ($recette['nombre_personnes'] ?? ''));

        $style = (string) (getenv('OPENAI_IMAGE_STYLE') ?: ($_ENV['OPENAI_IMAGE_STYLE'] ?? 'photo culinaire réaliste'));
        $size = (string) (getenv('OPENAI_IMAGE_SIZE') ?: ($_ENV['OPENAI_IMAGE_SIZE'] ?? '1024x1024'));
        $quality = (string) (getenv('OPENAI_IMAGE_QUALITY') ?: ($_ENV['OPENAI_IMAGE_QUALITY'] ?? 'low'));
        $model = (string) (getenv('OPENAI_IMAGE_MODEL') ?: ($_ENV['OPENAI_IMAGE_MODEL'] ?? 'gpt-image-1-mini'));

        $prompt = $this->buildFinalDishImagePrompt(
            $titre,
            $ingredients,
            $etapes,
            $style,
            $categorie,
            $typeRecette,
            $typeCuisson,
            $nombrePersonnes
        );

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'n' => 1
        ];

        $ch = curl_init("https://api.openai.com/v1/images/generations");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer {$this->apiKey}"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("Erreur CURL (images) : " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erreur API image OpenAI ($httpCode) : $response");
        }

        $data = json_decode($response, true);
        $imageData = $data['data'][0] ?? null;
        if (!is_array($imageData)) {
            throw new Exception("Réponse image invalide: image absente");
        }

        if (!empty($imageData['b64_json']) && is_string($imageData['b64_json'])) {
            $binary = base64_decode($imageData['b64_json'], true);
            if ($binary === false || $binary === '') {
                throw new Exception("Image base64 invalide dans la réponse OpenAI");
            }
            return $binary;
        }

        if (!empty($imageData['url']) && is_string($imageData['url'])) {
            $binary = $this->downloadBinary($imageData['url']);
            if ($binary !== '') {
                return $binary;
            }
        }

        throw new Exception("Réponse image OpenAI non exploitable");
    }

    private function buildFinalDishImagePrompt(
        string $titre,
        array $ingredients,
        array $etapes,
        string $style,
        string $categorie,
        string $typeRecette,
        string $typeCuisson,
        string $nombrePersonnes
    ): string {
        $titreLower = mb_strtolower($titre);

        $sceneHint = "Photographie culinaire réaliste du résultat final de la recette, prêt à être servi.";
        if (str_contains($titreLower, 'crêpe') || str_contains($titreLower, 'crepe') || str_contains($titreLower, 'pancake')) {
            $sceneHint = "Photographie culinaire réaliste de crêpes dorées déjà cuites, dressées dans une assiette, avec une garniture cohérente avec les ingrédients (ex: miel, confiture, fruits, sucre).";
        } elseif (str_contains($titreLower, 'soupe') || str_contains($titreLower, 'velout')) {
            $sceneHint = "Photographie culinaire réaliste d'une soupe/velouté servi dans un bol, avec finition adaptée aux ingrédients.";
        } elseif (str_contains($titreLower, 'salade')) {
            $sceneHint = "Photographie culinaire réaliste d'une salade finie, colorée et dressée proprement.";
        } elseif (str_contains($titreLower, 'gâteau') || str_contains($titreLower, 'gateau') || str_contains($titreLower, 'tarte')) {
            $sceneHint = "Photographie culinaire réaliste d'un dessert fini (gâteau/tarte), prêt à être servi.";
        }

        $lines = [
            "Crée une image de recette.",
            "Objectif principal: montrer UNIQUEMENT le plat final terminé, pas les étapes.",
            "Ne pas montrer d'ingrédients crus en vrac, ni plan de travail en préparation.",
            "Sans texte, sans logo, sans watermark.",
            "Style visuel: {$style}.",
            "Nom de la recette: {$titre}.",
            $sceneHint,
            "Le dressage doit être appétissant, crédible, avec lumière naturelle douce."
        ];

        if ($categorie !== '') {
            $lines[] = "Catégorie: {$categorie}.";
        }
        if ($typeRecette !== '') {
            $lines[] = "Type de recette: {$typeRecette}.";
        }
        if ($typeCuisson !== '') {
            $lines[] = "Mode de cuisson: {$typeCuisson}.";
        }
        if ($nombrePersonnes !== '') {
            $lines[] = "Portion: {$nombrePersonnes} personne(s) environ.";
        }
        if (!empty($ingredients)) {
            $lines[] = "Ingrédients de la recette (à respecter visuellement): " . implode(', ', $ingredients) . '.';
            $lines[] = "Si un topping/garniture est possible, utilise en priorité des éléments présents dans la liste d'ingrédients.";
        }
        if (!empty($etapes)) {
            $lines[] = "Contexte de préparation (pour cohérence du rendu final): " . implode(' ', $etapes);
        }

        return implode("\n", $lines);
    }

    private function fetchUrlContent(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'CahierDeRecettes/1.0'
        ]);

        $html = curl_exec($ch);
        if ($html === false) {
            curl_close($ch);
            return '';
        }
        curl_close($ch);

        // Nettoyage simple HTML -> texte
        $html = preg_replace('#<script[^>]*>.*?</script>#is', ' ', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', ' ', $html);
        $text = strip_tags($html);
        $text = preg_replace('/\\s+/u', ' ', $text);
        $text = trim($text);

        // Limite de taille pour éviter des prompts trop longs
        if (mb_strlen($text) > 12000) {
            $text = mb_substr($text, 0, 12000) . '...';
        }

        return $text;
    }

    private function downloadBinary(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'CahierDeRecettes/1.0'
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            curl_close($ch);
            return '';
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || !is_string($body)) {
            return '';
        }
        return $body;
    }

    private function callOpenAI(array $payload): string
    {
        $ch = curl_init("https://api.openai.com/v1/chat/completions");

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer {$this->apiKey}"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception("Erreur CURL : " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erreur API OpenAI ($httpCode) : $response");
        }

        $data = json_decode($response, true);

        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}
