<?php
declare(strict_types=1);

class RecetteNormalizer
{
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
            'categorie' => $json['categorie'] ?? '',
            'tags' => [],

            'ingredients' =>
                is_array($json['ingredients'] ?? null)
                    ? array_values(array_filter(array_map('trim', $json['ingredients'])))
                    : [],

            'etapes' =>
                is_array($json['etapes'] ?? null)
                    ? array_values(array_filter(array_map('trim', $json['etapes'])))
                    : [],

            // Champs non fournis par Vision (par design)
            'temps_preparation' => null,
            'temps_cuisson' => null,
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
