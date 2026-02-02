-- Création de la base de données (si tu veux l'utiliser)
CREATE DATABASE IF NOT EXISTS cahier_recettes
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cahier_recettes;

-- Table principale des recettes
CREATE TABLE IF NOT EXISTS recettes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    titre VARCHAR(255) NOT NULL,
    auteur VARCHAR(255) DEFAULT NULL,
    source VARCHAR(255) DEFAULT NULL,

    categorie VARCHAR(100) DEFAULT NULL,
    tags TEXT DEFAULT NULL,

    ingredients LONGTEXT NOT NULL,
    etapes LONGTEXT NOT NULL,

    type_cuisson VARCHAR(100) DEFAULT NULL,
    temps_cuisson VARCHAR(100) DEFAULT NULL,

    difficulte TINYINT DEFAULT NULL CHECK (difficulte BETWEEN 1 AND 5),

    commentaires TEXT DEFAULT NULL,

    texte_brut LONGTEXT NOT NULL,
    texte_formatte LONGTEXT NOT NULL,

    image VARCHAR(255) DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- Index utiles pour accélérer les recherches
CREATE INDEX idx_titre ON recettes (titre);
CREATE INDEX idx_auteur ON recettes (auteur);
CREATE FULLTEXT INDEX idx_fulltext_recette ON recettes (titre, ingredients, etapes, texte_formatte);
