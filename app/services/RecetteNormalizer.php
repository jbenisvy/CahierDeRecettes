<?php
declare(strict_types=1);

class RecetteNormalizer
{
    private static ?array $categoryIndex = null;

    private static function canonicalCategoryKey(string $value): string
    {
        $key = mb_strtolower(trim($value));
        $key = strtr($key, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        return $key;
    }

    private static function getCategoryIndex(): array
    {
        if (self::$categoryIndex !== null) {
            return self::$categoryIndex;
        }

        $options = require __DIR__ . '/../../config/recette_options.php';
        $categories = array_keys($options['categories'] ?? []);
        $index = [];

        foreach ($categories as $category) {
            $canonical = self::canonicalCategoryKey((string)$category);
            if ($canonical === '') {
                continue;
            }
            $index[$canonical] = $category;
            if (!str_ends_with($canonical, 's')) {
                $index[$canonical . 's'] = $category;
            }
        }

        // Alias historiques courants
        if (isset($index['dessert'])) {
            $index['gateau'] = $index['dessert'];
            $index['gateaux'] = $index['dessert'];
        }

        self::$categoryIndex = $index;
        return self::$categoryIndex;
    }

    private static function normalizeCategory(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $key = self::canonicalCategoryKey($value);
        $index = self::getCategoryIndex();
        return $index[$key] ?? '';
    }

    private static function normalizeMinutes(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_float($value)) {
            $minutes = (int) round($value);
            return $minutes >= 0 ? $minutes : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/\d+/', $raw, $m) === 1) {
            $minutes = (int) $m[0];
            return $minutes >= 0 ? $minutes : null;
        }

        return null;
    }

    private static function normalizePeopleCount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_float($value)) {
            $count = (int) round($value);
            return $count > 0 ? $count : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/\d+/', $raw, $m) === 1) {
            $count = (int) $m[0];
            return $count > 0 ? $count : null;
        }

        return null;
    }

    /**
     * Normalise une recette issue de ChatGPT Vision
     * pour l'adapter au format applicatif interne.
     */
    public static function fromVision(array $json, array $user = []): array
    {
        return [
            'titre' => (string)($json['titre'] ?? ''),
            'auteur' => $json['auteur'] ?? ($user['nom'] ?? ''),
            'source' => $json['source'] ?? '',
            'categorie' => self::normalizeCategory($json['categorie'] ?? ''),
            'tags' => [],

            'ingredients' =>
                is_array($json['ingredients'] ?? null)
                    ? array_values(array_filter(array_map('trim', $json['ingredients'])))
                    : [],

            'etapes' =>
                is_array($json['etapes'] ?? null)
                    ? array_values(array_filter(array_map('trim', $json['etapes'])))
                    : [],

            'temps_preparation' => self::normalizeMinutes($json['temps_preparation'] ?? null),
            'temps_cuisson' => self::normalizeMinutes($json['temps_cuisson'] ?? null),
            'nombre_personnes' => self::normalizePeopleCount($json['nombre_personnes'] ?? null),
            'type_cuisson' => '',
            'difficulte' => null,

            // Vision renvoie parfois un tableau
            'commentaires' =>
                is_array($json['commentaires'] ?? null)
                    ? implode("\n", $json['commentaires'])
                    : (string)($json['commentaires'] ?? '')
        ];
    }
}
