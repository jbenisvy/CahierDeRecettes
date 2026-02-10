<?php
declare(strict_types=1);

class ChatGPTService
{
    private string $apiKey;

    public function __construct()
    {
        $config = require PROJECT_ROOT . '/config/openai.php';

        if (empty($config['api_key'])) {
            throw new Exception("Clé OpenAI manquante");
        }

        $this->apiKey = $config['api_key'];
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
Si une information est absente ou illisible, mets null."
                ],
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "text",
                            "text" =>
                                "Analyse cette image et retourne UNIQUEMENT un JSON valide avec les clés :
titre, auteur, source, categorie, ingredients (array), etapes (array), commentaires."
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
Si une information est absente ou illisible, mets null."
                ],
                [
                    "role" => "user",
                    "content" =>
                        "Analyse ce texte et retourne UNIQUEMENT un JSON valide avec les clés :
titre, auteur, source, categorie, ingredients (array), etapes (array), commentaires.
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
Si une information est absente ou illisible, mets null."
                ],
                [
                    "role" => "user",
                    "content" =>
                        "Analyse le contenu de cette page et retourne UNIQUEMENT un JSON valide avec les clés :
titre, auteur, source, categorie, ingredients (array), etapes (array), commentaires.
\n\nCONTENU:\n" . $content
                ]
            ],
            "temperature" => 0
        ];

        return $this->callOpenAI($payload);
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
