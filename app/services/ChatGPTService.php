<?php
declare(strict_types=1);

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 2));
}

if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', PROJECT_ROOT . '/public');
}

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
        $dishContext = $this->buildDishContext($titre, $categorie, $typeRecette, $typeCuisson, $ingredients);
        $dishLabel = $dishContext['dishLabel'];
        $dishFormatHint = $dishContext['formatHint'];
        $dishShapeHint = $dishContext['shapeHint'];
        $dishNegatives = $dishContext['negativeHints'];
        $servingHint = $dishContext['servingHint'];
        $ingredientVisualHint = $dishContext['ingredientVisualHint'];

        $lines = [
            "Crée une image de recette.",
            "Objectif principal: montrer UNIQUEMENT le plat final terminé, pas les étapes.",
            "Ne pas montrer d'ingrédients crus en vrac, ni plan de travail en préparation.",
            "Sans texte, sans logo, sans watermark.",
            "Style visuel: {$style}.",
            "Nom de la recette: {$titre}.",
            "Le plat représenté doit correspondre à la vraie nature culinaire de la recette.",
            "Format visuel attendu: {$dishLabel}.",
            $dishFormatHint,
            $dishShapeHint,
            $servingHint,
            $ingredientVisualHint,
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
        if ($dishNegatives !== '') {
            $lines[] = $dishNegatives;
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $ingredients
     * @return array{
     *   dishLabel:string,
     *   formatHint:string,
     *   shapeHint:string,
     *   servingHint:string,
     *   ingredientVisualHint:string,
     *   negativeHints:string
     * }
     */
    private function buildDishContext(
        string $titre,
        string $categorie,
        string $typeRecette,
        string $typeCuisson,
        array $ingredients
    ): array {
        $context = $this->normalizePromptText(implode(' ', [$titre, $categorie, $typeRecette, $typeCuisson]));
        $ingredientsText = $this->normalizePromptText(implode(' ', $ingredients));

        $default = [
            'dishLabel' => 'plat final réaliste et cohérent',
            'formatHint' => "Photographie culinaire réaliste du résultat final de la recette, prêt à être servi.",
            'shapeHint' => "La forme du mets, son contenant et son dressage doivent correspondre exactement au type de plat annoncé par le titre.",
            'servingHint' => "Montrer une portion servie de façon naturelle et crédible.",
            'ingredientVisualHint' => "Les éléments visibles doivent refléter les ingrédients dominants et la structure réelle du plat.",
            'negativeHints' => "Éviter une présentation générique qui changerait la nature du plat."
        ];

        $profiles = [
            [
                'keywords' => ['poke', 'poké', 'poke bowl', 'pokebowl'],
                'dishLabel' => 'poke bowl',
                'formatHint' => "Montrer explicitement un poke bowl servi dans un bol, avec des ingrédients disposés en sections visibles et nettes.",
                'shapeHint' => "On doit immédiatement reconnaître un poke: bol, base visible, garnitures séparées, composition fraîche et structurée.",
                'servingHint' => "Vue trois-quarts ou vue du dessus d'un bol complet, prêt à déguster.",
                'ingredientVisualHint' => "Faire apparaître la base, les protéines, les légumes et les toppings réellement cohérents avec les ingrédients.",
                'negativeHints' => "Ne pas générer une simple salade plate, une assiette chaude ou un plat mijoté."
            ],
            [
                'keywords' => ['sushi', 'maki', 'california', 'nigiri', 'temaki'],
                'dishLabel' => 'sushi ou maki',
                'formatHint' => "Montrer clairement des sushi, maki, nigiri ou california rolls dressés proprement.",
                'shapeHint' => "Les pièces doivent être immédiatement reconnaissables avec leur forme japonaise typique.",
                'servingHint' => "Présenter sur assiette, plateau ou planche, en composition nette et soignée.",
                'ingredientVisualHint' => "Les garnitures visibles doivent correspondre aux ingrédients principaux comme poisson, riz, avocat, concombre, algues.",
                'negativeHints' => "Ne pas générer un poke bowl, une salade ou un plat chaud."
            ],
            [
                'keywords' => ['couscous', 'semoule'],
                'dishLabel' => 'couscous',
                'formatHint' => "Montrer un vrai couscous servi chaud, avec semoule, légumes et garniture cohérente.",
                'shapeHint' => "La semoule doit être visible et distincte des légumes, viandes ou pois chiches.",
                'servingHint' => "Présenter dans un grand plat ou une assiette généreuse de couscous.",
                'ingredientVisualHint' => "Les éléments visibles doivent respecter les ingrédients principaux et l'esprit d'un vrai couscous.",
                'negativeHints' => "Ne pas générer un riz sauté, un ragoût seul ou une salade."
            ],
            [
                'keywords' => ['cake', 'quatre quart', 'banana bread', 'pain d epices', 'pain d\'epices'],
                'dishLabel' => 'cake ou pain cuit',
                'formatHint' => "Montrer un vrai cake cuit, avec sa forme allongée de moule à cake ou une tranche clairement identifiable.",
                'shapeHint' => "La silhouette doit être celle d'un cake: volume épais, mie visible si coupé, croûte cuite cohérente.",
                'servingHint' => "Présenter soit le cake entier, soit une ou deux tranches servies proprement.",
                'ingredientVisualHint' => "La texture et la couleur doivent être compatibles avec le type de cake et ses ingrédients dominants.",
                'negativeHints' => "Ne pas générer un gâteau rond, un muffin, un biscuit ou une tarte."
            ],
            [
                'keywords' => ['brioche', 'babka', 'pain au lait'],
                'dishLabel' => 'brioche',
                'formatHint' => "Montrer une vraie brioche cuite, moelleuse, dorée, avec une forme de brioche clairement identifiable.",
                'shapeHint' => "La mie et la croûte doivent évoquer une brioche et non un cake dense.",
                'servingHint' => "Présenter entière, tressée ou en tranches selon la recette.",
                'ingredientVisualHint' => "La texture visible doit être légère, filante ou moelleuse selon le type de brioche.",
                'negativeHints' => "Ne pas générer un cake, une baguette ou un simple pain de mie."
            ],
            [
                'keywords' => ['madeleine', 'madeleines', 'financier', 'financiers'],
                'dishLabel' => 'petits gâteaux individuels',
                'formatHint' => "Montrer de vrais petits gâteaux individuels cuits, reconnaissables en nombre, par exemple madeleines ou financiers.",
                'shapeHint' => "La forme doit être fidèle: coquille pour madeleines, petit rectangle ou lingot pour financiers.",
                'servingHint' => "Présenter plusieurs pièces ensemble sur assiette ou plateau.",
                'ingredientVisualHint' => "La texture et la couleur doivent être cohérentes avec une pâtisserie sèche et moelleuse.",
                'negativeHints' => "Ne pas générer un gros gâteau unique, un cookie ou une brioche."
            ],
            [
                'keywords' => ['boisson chaude', 'chocolat chaud', 'cafe', 'café', 'latte', 'cappuccino', 'the ', 'thé', 'infusion'],
                'dishLabel' => 'boisson chaude',
                'formatHint' => "Montrer une vraie boisson chaude servie dans une tasse, un mug ou un verre adapté, avec vapeur légère si pertinente.",
                'shapeHint' => "Le contenant doit être immédiatement identifiable comme celui d'une boisson chaude.",
                'servingHint' => "Cadrage rapproché sur la tasse ou le mug, boisson prête à boire.",
                'ingredientVisualHint' => "La couleur, la mousse ou les garnitures doivent correspondre à la boisson attendue.",
                'negativeHints' => "Ne pas générer un dessert solide, une soupe ou une boisson froide."
            ],
            [
                'keywords' => ['smoothie', 'milkshake', 'jus', 'cocktail', 'limonade'],
                'dishLabel' => 'boisson froide',
                'formatHint' => "Montrer une vraie boisson froide dans un verre adapté, avec texture cohérente.",
                'shapeHint' => "Le rendu doit être clairement celui d'une boisson et non d'un dessert à l'assiette.",
                'servingHint' => "Servir dans un verre ou une bouteille appropriée.",
                'ingredientVisualHint' => "Respecter la couleur et la consistance attendues de la boisson.",
                'negativeHints' => "Ne pas générer de soupe, de gâteau, ni de plat chaud."
            ],
            [
                'keywords' => ['soupe', 'veloute', 'velouté', 'ramen', 'bouillon'],
                'dishLabel' => 'soupe ou bouillon',
                'formatHint' => "Montrer clairement une soupe, un velouté ou un bouillon servi dans un bol ou une assiette creuse.",
                'shapeHint' => "Le plat doit rester liquide ou semi-liquide, avec surface visible et garniture adaptée.",
                'servingHint' => "Présenter un contenant profond adapté à un plat à la cuillère.",
                'ingredientVisualHint' => "La texture doit être cohérente: lisse pour un velouté, plus structurée pour une soupe garnie.",
                'negativeHints' => "Ne pas transformer le plat en purée sèche, salade ou plat solide."
            ],
            [
                'keywords' => ['puree', 'purée', 'ecrase', 'écrasé', 'mousseline'],
                'dishLabel' => 'purée ou écrasé',
                'formatHint' => "Montrer clairement une purée, un écrasé ou une mousseline servi en accompagnement ou en plat, avec texture souple et onctueuse.",
                'shapeHint' => "La texture doit rester dense, crémeuse ou écrasée, jamais liquide comme une soupe.",
                'servingHint' => "Présenter en quenelle, en dôme ou en portion dans une assiette adaptée.",
                'ingredientVisualHint' => "La couleur et la texture doivent correspondre à l'ingrédient principal de la purée.",
                'negativeHints' => "Ne pas générer une soupe, un gratin ou une salade."
            ],
            [
                'keywords' => ['salade', 'taboule', 'taboulé', 'coleslaw', 'carpaccio'],
                'dishLabel' => 'salade ou plat froid dressé',
                'formatHint' => "Montrer une salade finale, fraîche, structurée et bien dressée.",
                'shapeHint' => "La composition doit évoquer un plat froid ou tempéré, léger, avec éléments distincts.",
                'servingHint' => "Présenter dans une assiette ou un bol large adapté.",
                'ingredientVisualHint' => "Mettre en avant les légumes, herbes, graines, protéines froides ou condiments réellement cohérents.",
                'negativeHints' => "Ne pas générer une soupe, un ragoût ou un gâteau."
            ],
            [
                'keywords' => ['gateau', 'gâteau', 'tarte', 'entremet', 'cheesecake', 'brownie', 'fondant'],
                'dishLabel' => 'dessert pâtissier',
                'formatHint' => "Montrer un vrai dessert fini, pâtissier, prêt à être servi.",
                'shapeHint' => "Respecter la géométrie réelle du dessert: gâteau, part de tarte, brownie, fondant, etc.",
                'servingHint' => "Présenter entier ou en part, selon ce qui rend le dessert immédiatement reconnaissable.",
                'ingredientVisualHint' => "Les finitions visibles doivent correspondre aux ingrédients majeurs du dessert.",
                'negativeHints' => "Ne pas générer une assiette salée, une boisson ou un plat principal."
            ],
            [
                'keywords' => ['quiche', 'tourte', 'feuillete', 'feuilleté', 'chausson sale', 'chausson salé'],
                'dishLabel' => 'quiche, tourte ou feuilleté salé',
                'formatHint' => "Montrer une vraie quiche, tourte ou préparation feuilletée salée cuite, avec pâte bien visible.",
                'shapeHint' => "La forme doit évoquer une tarte salée, une part de quiche, une tourte ou un feuilleté identifiable.",
                'servingHint' => "Présenter entière ou en part selon ce qui rend le plat le plus reconnaissable.",
                'ingredientVisualHint' => "La garniture visible doit correspondre aux ingrédients salés de la recette.",
                'negativeHints' => "Ne pas générer un cake, une pizza ou un simple gratin."
            ],
            [
                'keywords' => ['gratin', 'parmentier', 'lasagne', 'lasagnes', 'moussaka'],
                'dishLabel' => 'gratin ou plat en couches',
                'formatHint' => "Montrer un vrai gratin ou plat gratiné, chaud, doré au four, avec surface gratinée visible.",
                'shapeHint' => "La structure doit évoquer un plat en couches ou un gratin servi en portion.",
                'servingHint' => "Présenter dans un plat à gratin ou une part servie en assiette.",
                'ingredientVisualHint' => "Les couches, la croûte dorée et la texture doivent correspondre aux ingrédients principaux.",
                'negativeHints' => "Ne pas générer une soupe, une salade ou un ragoût liquide."
            ],
            [
                'keywords' => ['pizza', 'flammekueche', 'tarte flambee', 'tarte flambée'],
                'dishLabel' => 'pizza ou tarte fine salée',
                'formatHint' => "Montrer clairement une pizza ou une tarte fine cuite, vue entière ou en part.",
                'shapeHint' => "La pâte, la garniture et la forme doivent être immédiatement reconnaissables.",
                'servingHint' => "Présenter sur une planche, une assiette ou avec une part visible.",
                'ingredientVisualHint' => "Faire ressortir les garnitures réellement compatibles avec les ingrédients.",
                'negativeHints' => "Ne pas générer une casserole, un bowl ou une soupe."
            ],
            [
                'keywords' => ['burger', 'sandwich', 'wrap', 'tacos', 'panini'],
                'dishLabel' => 'sandwich ou plat à main',
                'formatHint' => "Montrer un vrai sandwich, burger, wrap ou tacos, bien assemblé et identifiable.",
                'shapeHint' => "La structure du pain, de la tortilla ou de l'enveloppe doit être visible.",
                'servingHint' => "Présenter entier ou coupé pour rendre la garniture lisible.",
                'ingredientVisualHint' => "La garniture visible doit correspondre aux ingrédients principaux.",
                'negativeHints' => "Ne pas générer une assiette classique ou un bowl."
            ],
            [
                'keywords' => ['croque', 'toast', 'tartine', 'bruschetta'],
                'dishLabel' => 'tartine, toast ou croque',
                'formatHint' => "Montrer une vraie tartine, un toast ou un croque servi chaud ou tiède selon la recette.",
                'shapeHint' => "Le pain ou la tranche doit rester visible et identifiable comme base du plat.",
                'servingHint' => "Présenter une ou plusieurs pièces sur assiette ou planche.",
                'ingredientVisualHint' => "Les garnitures visibles doivent être fidèles aux ingrédients de la recette.",
                'negativeHints' => "Ne pas générer un burger, une pizza ou un plat en sauce."
            ],
            [
                'keywords' => ['risotto', 'pates', 'pâtes', 'spaghetti', 'linguine', 'penne', 'vermicelles', 'nouilles'],
                'dishLabel' => 'plat de pâtes ou céréales chaudes',
                'formatHint' => "Montrer clairement un plat de pâtes, nouilles, riz crémeux ou céréales servies chaudes.",
                'shapeHint' => "La texture et la masse du plat doivent être cohérentes avec des pâtes, du riz ou des vermicelles cuits.",
                'servingHint' => "Présenter en assiette creuse ou bol selon le plat.",
                'ingredientVisualHint' => "Les garnitures et sauces visibles doivent refléter les ingrédients dominants.",
                'negativeHints' => "Ne pas générer une soupe liquide, un cake ou une salade froide générique."
            ],
            [
                'keywords' => ['riz saute', 'riz sauté', 'fried rice'],
                'dishLabel' => 'riz sauté',
                'formatHint' => "Montrer clairement un riz sauté servi chaud, avec grains visibles et garnitures mélangées.",
                'shapeHint' => "La texture doit rester celle d'un riz sauté sec et structuré, pas celle d'un risotto ni d'une purée.",
                'servingHint' => "Présenter en bol ou assiette selon le style du plat.",
                'ingredientVisualHint' => "Les ajouts visibles doivent être cohérents avec les ingrédients de la recette.",
                'negativeHints' => "Ne pas générer un couscous, une soupe ou un plat en sauce lourd."
            ],
            [
                'keywords' => ['curry', 'tajine', 'ragout', 'ragoût', 'mijote', 'mijoté', 'chili'],
                'dishLabel' => 'plat mijoté ou en sauce',
                'formatHint' => "Montrer un vrai plat mijoté ou en sauce, généreux, servi chaud.",
                'shapeHint' => "La sauce doit être visible et la consistance cohérente avec un plat mijoté.",
                'servingHint' => "Présenter en assiette creuse, bol ou plat de service adapté.",
                'ingredientVisualHint' => "Les morceaux principaux doivent évoquer les ingrédients réellement utilisés.",
                'negativeHints' => "Ne pas générer une salade, un sandwich ou un dessert."
            ],
            [
                'keywords' => ['brochette', 'kebab', 'yakitori'],
                'dishLabel' => 'brochettes',
                'formatHint' => "Montrer clairement des brochettes cuites et dressées, avec les morceaux visibles sur pics ou bâtonnets.",
                'shapeHint' => "La forme en brochette doit être immédiatement identifiable.",
                'servingHint' => "Présenter plusieurs brochettes ou une brochette principale en assiette.",
                'ingredientVisualHint' => "Les morceaux visibles doivent correspondre aux ingrédients principaux marinés ou grillés.",
                'negativeHints' => "Ne pas générer un ragoût, un burger ou une salade générique."
            ],
            [
                'keywords' => ['omelette', 'oeufs brouilles', 'oeufs brouillés', 'frittata'],
                'dishLabel' => 'plat à base d œufs',
                'formatHint' => "Montrer clairement une omelette, une frittata ou des œufs brouillés selon la recette.",
                'shapeHint' => "La texture et la forme doivent respecter le type exact de préparation aux œufs.",
                'servingHint' => "Présenter en assiette simple et lisible, prête à être consommée.",
                'ingredientVisualHint' => "Les garnitures visibles doivent être cohérentes avec les ingrédients associés aux œufs.",
                'negativeHints' => "Ne pas générer une quiche, un cake ou un gratin."
            ],
        ];

        foreach ($profiles as $profile) {
            foreach ($profile['keywords'] as $keyword) {
                $keywordNormalized = $this->normalizePromptText($keyword);
                if ($keywordNormalized !== '' && (str_contains($context, $keywordNormalized) || str_contains($ingredientsText, $keywordNormalized))) {
                    return $profile;
                }
            }
        }

        return $default;
    }

    private function normalizePromptText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ÿ' => 'y',
            'œ' => 'oe',
            'æ' => 'ae',
        ]);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
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
